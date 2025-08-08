<?php
/*
 *  Copyright 2025.  Baks.dev <admin@baks.dev>
 *  
 *  Permission is hereby granted, free of charge, to any person obtaining a copy
 *  of this software and associated documentation files (the "Software"), to deal
 *  in the Software without restriction, including without limitation the rights
 *  to use, copy, modify, merge, publish, distribute, sublicense, and/or sell
 *  copies of the Software, and to permit persons to whom the Software is furnished
 *  to do so, subject to the following conditions:
 *  
 *  The above copyright notice and this permission notice shall be included in all
 *  copies or substantial portions of the Software.
 *  
 *  THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR
 *  IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY,
 *  FITNESS FOR A PARTICULAR PURPOSE AND NON INFRINGEMENT. IN NO EVENT SHALL THE
 *  AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER
 *  LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING FROM,
 *  OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN
 *  THE SOFTWARE.
 */

declare(strict_types=1);

namespace BaksDev\Materials\Sign\Messenger\MaterialSignPdf;

use BaksDev\Barcode\Reader\BarcodeRead;
use BaksDev\Core\Messenger\MessageDispatchInterface;
use BaksDev\Files\Resources\Messenger\Request\Images\CDNUploadImageMessage;
use BaksDev\Materials\Sign\Entity\Code\MaterialSignCode;
use BaksDev\Materials\Sign\Entity\MaterialSign;
use BaksDev\Materials\Sign\Type\Id\MaterialSignUid;
use BaksDev\Materials\Sign\Type\Status\MaterialSignStatus\MaterialSignStatusError;
use BaksDev\Materials\Sign\UseCase\Admin\New\MaterialSignDTO;
use BaksDev\Materials\Sign\UseCase\Admin\New\MaterialSignHandler;
use BaksDev\Materials\Stocks\UseCase\Admin\Purchase\Materials\MaterialStockDTO;
use BaksDev\Materials\Stocks\UseCase\Admin\Purchase\PurchaseMaterialStockDTO;
use BaksDev\Materials\Stocks\UseCase\Admin\Purchase\PurchaseMaterialStockHandler;
use BaksDev\Products\Sign\Type\Status\ProductSignStatus\ProductSignStatusError;
use BaksDev\Users\Profile\UserProfile\Repository\UserByUserProfile\UserByUserProfileInterface;
use DateTimeImmutable;
use DirectoryIterator;
use Doctrine\ORM\Mapping\Table;
use Imagick;
use ImagickPixel;
use Psr\Log\LoggerInterface;
use ReflectionAttribute;
use ReflectionClass;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\DependencyInjection\Attribute\Target;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler(priority: 0)]
final readonly class MaterialSignPdfHandler
{
    public function __construct(
        #[Autowire('%kernel.project_dir%')] private string $upload,
        #[Target('materialsSignLogger')] private LoggerInterface $logger,
        private MaterialSignHandler $materialSignHandler,
        private PurchaseMaterialStockHandler $purchaseMaterialStockHandler,
        private Filesystem $filesystem,
        private BarcodeRead $barcodeRead,
        private MessageDispatchInterface $messageDispatch,
        private UserByUserProfileInterface $UserByUserProfileInterface,

    ) {}

    public function __invoke(MaterialSignPdfMessage $message): void
    {
        // public/upload/materials-sign/

        $upload[] = $this->upload;
        $upload[] = 'public';
        $upload[] = 'upload';
        $upload[] = 'barcode';
        $upload[] = 'materials-sign';

        $upload[] = (string) $message->getUsr();

        if($message->getProfile())
        {
            $upload[] = (string) $message->getProfile();
        }

        $upload[] = (string) $message->getMaterial();

        if($message->getOffer())
        {
            $upload[] = (string) $message->getOffer();
        }

        if($message->getVariation())
        {
            $upload[] = (string) $message->getVariation();
        }

        if($message->getModification())
        {
            $upload[] = (string) $message->getModification();
        }

        $upload[] = '';

        // Директория загрузки файла PDF
        $uploadDir = implode(DIRECTORY_SEPARATOR, $upload);


        //        /**
        //         * Сохраняем все листы PDF в отдельные файлы на случай, если есть непреобразованные
        //         */
        //
        //        /** @var DirectoryIterator $SignPdfFile */
        //        foreach(new DirectoryIterator($uploadDir) as $SignPdfFile)
        //        {
        //            if($SignPdfFile->getExtension() !== 'pdf')
        //            {
        //                continue;
        //            }
        //
        //            /** Пропускаем файлы, которые уже разбиты на страницы */
        //            if(str_starts_with($SignPdfFile->getFilename(), 'page') === true)
        //            {
        //                continue;
        //            }
        //
        //            $process = new Process(['pdftk', $SignPdfFile->getRealPath(), 'burst', 'output', $SignPdfFile->getPath().DIRECTORY_SEPARATOR.uniqid('page_', true).'.%d.pdf']);
        //            $process->mustRun();
        //
        //            /** Удаляем после обработки основной файл PDF */
        //            $this->filesystem->remove($SignPdfFile->getRealPath());
        //        }




        /** Обрабатываем страницы */

        foreach(new DirectoryIterator($uploadDir) as $SignFile)
        {
            if($SignFile->getExtension() !== 'pdf')
            {
                continue;
            }

            if(str_starts_with($SignFile->getFilename(), 'crop') === false)
            {
                continue;
            }

            if(false === $SignFile->getRealPath() || false === file_exists($SignFile->getRealPath()))
            {
                continue;
            }

            /** Генерируем идентификатор группы для отмены */
            $part = new MaterialSignUid()->stringToUuid($SignFile->getPath().(new DateTimeImmutable('now')->format('Ymd')));

            $counter = 0;
            $errors = 0;

            /** Создаем предварительно закупку для заполнения сырья */
            if($message->isPurchase() && $message->getProfile())
            {
                // Получаем идентификатор пользователя по профилю

                $User = $this->UserByUserProfileInterface
                    ->forProfile($message->getProfile())
                    ->find();

                if($User)
                {
                    $PurchaseNumber = number_format(microtime(true) * 100, 0, '.', '.');

                    $PurchaseMaterialStockDTO = new PurchaseMaterialStockDTO();
                    $PurchaseMaterialInvariableDTO = $PurchaseMaterialStockDTO->getInvariable();

                    $PurchaseMaterialInvariableDTO
                        ->setUsr($User->getId())
                        ->setNumber($PurchaseNumber);
                }
            }

            /** Директория загрузки файла с кодом */

            $ref = new ReflectionClass(MaterialSignCode::class);
            /** @var ReflectionAttribute $current */
            $current = current($ref->getAttributes(Table::class));

            if(!isset($current->getArguments()['name']))
            {
                $this->logger->critical(
                    sprintf('Невозможно определить название таблицы из класса сущности %s ', MaterialSignCode::class),
                    [self::class.':'.__LINE__]
                );
            }

            /** Создаем полный путь для сохранения файла с кодом относительно директории сущности */
            $pathCode = null;
            $pathCode[] = $this->upload;
            $pathCode[] = 'public';
            $pathCode[] = 'upload';
            $pathCode[] = $current->getArguments()['name'];
            $pathCode[] = '';

            $dirCode = implode(DIRECTORY_SEPARATOR, $pathCode);

            /** Если директория загрузки не найдена - создаем с правами 0700 */
            $this->filesystem->exists($dirCode) ?: $this->filesystem->mkdir($dirCode);


            /**
             * Открываем PDF для подсчета страниц на случай если их несколько
             */
            $pdfPath = $SignFile->getRealPath();
            $Imagick = new Imagick();
            $Imagick->setResolution(500, 500);
            $Imagick->readImage($pdfPath);
            $pages = $Imagick->getNumberImages(); // количество страниц в файле

            /** Удаляем после обработки файл PDF */
            $this->filesystem->remove($pdfPath);


            for($number = 0; $number < $pages; $number++)
            {
                $fileTemp = $dirCode.uniqid('', true).'.png';

                /** Преобразуем PDF страницу в PNG и сохраняем временно для расчета дайджеста md5 */
                $Imagick->setIteratorIndex($number);
                $Imagick->setImageFormat('png');
                $Imagick->borderImage(new ImagickPixel("white"), 5, 5);
                $Imagick->writeImage($fileTemp);


                /** Рассчитываем дайджест файла для перемещения */

                $md5 = md5_file($fileTemp);
                $dirMove = $dirCode.$md5.DIRECTORY_SEPARATOR;
                $fileMove = $dirMove.'image.png';


                /** Если директория для перемещения не найдена - создаем  */
                $this->filesystem->exists($dirMove) ?: $this->filesystem->mkdir($dirMove);

                /**
                 * Перемещаем в указанную директорию если файла не существует
                 * Если в перемещаемой директории файл существует - удаляем временный файл
                 */
                $this->filesystem->exists($fileMove) === true
                    ? $this->filesystem->remove($fileTemp)
                    : $this->filesystem->rename($fileTemp, $fileMove);


                /** Сканируем честный знак */
                $decode = $this->barcodeRead->decode($fileMove);
                $code = $decode->getText();


                /**
                 * Создаем для сохранения честный знак
                 * в случае ошибки сканирования - присваивается статус с ошибкой
                 */
                $MaterialSignDTO = new MaterialSignDTO();

                if($decode->isError() || str_starts_with($code, '(00)'))
                {
                    $code = uniqid('error_', true);
                    $MaterialSignDTO->setStatus(MaterialSignStatusError::class);
                }

                $decode->isError() ? ++$errors : ++$counter;


                /**
                 * Переименовываем директорию по коду честного знака (для уникальности)
                 */

                $scanDirName = md5($code);
                $renameDir = $dirCode.$scanDirName.DIRECTORY_SEPARATOR;

                if($this->filesystem->exists($renameDir) === true)
                {
                    // Удаляем директорию если уже имеется
                    $this->filesystem->remove($dirMove);
                }
                else
                {
                    // переименовываем директорию если не существует
                    $this->filesystem->rename($dirMove, $renameDir);
                }


                /** Присваиваем результат сканера */

                $MaterialSignCodeDTO = $MaterialSignDTO->getCode();
                $MaterialSignCodeDTO->setCode($code);
                $MaterialSignCodeDTO->setName($scanDirName);
                $MaterialSignCodeDTO->setExt('png');

                $MaterialSignInvariableDTO = $MaterialSignDTO->getInvariable();
                $MaterialSignInvariableDTO->setPart($part);
                $MaterialSignInvariableDTO->setUsr($message->getUsr());

                $MaterialSignInvariableDTO->setProfile($message->getProfile());
                $MaterialSignInvariableDTO->setSeller($message->isNotShare() ? $message->getProfile() : null);

                $MaterialSignInvariableDTO->setMaterial($message->getMaterial());
                $MaterialSignInvariableDTO->setOffer($message->getOffer());
                $MaterialSignInvariableDTO->setVariation($message->getVariation());
                $MaterialSignInvariableDTO->setModification($message->getModification());
                $MaterialSignInvariableDTO->setNumber($message->getNumber());

                $handle = $this->materialSignHandler->handle($MaterialSignDTO);

                if(!$handle instanceof MaterialSign)
                {
                    if($handle === false)
                    {
                        $this->logger->warning(sprintf('Дубликат честного знака %s: ', $code));
                        continue;
                    }

                    $this->logger->critical(sprintf('materials-sign: Ошибка %s при сканировании: ', $handle));
                }
                else
                {
                    $this->logger->info(
                        sprintf('%s: %s', $handle->getId(), $code),
                        [self::class.':'.__LINE__]
                    );

                    /** Создаем комманду для отправки файла CDN */
                    $this->messageDispatch->dispatch(
                        new CDNUploadImageMessage($handle->getId(), MaterialSignCode::class, $md5),
                        transport: 'files-res-low'
                    );
                }

                /** Создаем закупку */
                if(isset($PurchaseMaterialStockDTO) && $message->isPurchase() && $message->getProfile())
                {
                    /** Ищем в массиве такое сырье */
                    $getPurchaseMaterial = $PurchaseMaterialStockDTO->getMaterial()
                        ->filter(function(MaterialStockDTO $element) use ($message) {
                            return
                                $message->getMaterial()->equals($element->getMaterial()) &&
                                (
                                    ($message->getOffer() === null && $element->getOffer() === null) ||
                                    $message->getOffer()->equals($element->getOffer())
                                ) &&

                                (
                                    ($message->getVariation() === null && $element->getVariation() === null) ||
                                    $message->getVariation()->equals($element->getVariation())
                                ) &&

                                (
                                    ($message->getModification() === null && $element->getModification() === null) ||
                                    $message->getModification()->equals($element->getModification())
                                );

                        });

                    $MaterialStockDTO = $getPurchaseMaterial->current();

                    /* если сырья еще нет - добавляем */
                    if(!$MaterialStockDTO)
                    {
                        $MaterialStockDTO = new MaterialStockDTO();
                        $MaterialStockDTO->setMaterial($message->getMaterial());
                        $MaterialStockDTO->setOffer($message->getOffer());
                        $MaterialStockDTO->setVariation($message->getVariation());
                        $MaterialStockDTO->setModification($message->getModification());
                        $MaterialStockDTO->setTotal(0);

                        $PurchaseMaterialStockDTO->addMaterial($MaterialStockDTO);
                    }

                    $MaterialStockTotal = $MaterialStockDTO->getTotal() + 1;
                    $MaterialStockDTO->setTotal($MaterialStockTotal);
                }
            }

            /** Сохраняем закупку */
            if($message->isPurchase() && $message->getProfile() && (isset($PurchaseMaterialStockDTO) && false === $PurchaseMaterialStockDTO->getMaterial()->isEmpty()))
            {
                $this->purchaseMaterialStockHandler->handle($PurchaseMaterialStockDTO);
            }

            $Imagick->clear();
            $Imagick->destroy();
        }
    }
}
