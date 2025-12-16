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

namespace BaksDev\Materials\Sign\Messenger\MaterialSignPdf\MaterialSignScaner;

use BaksDev\Barcode\Reader\BarcodeRead;
use BaksDev\Core\Messenger\MessageDispatchInterface;
use BaksDev\Files\Resources\Messenger\Request\Images\CDNUploadImageMessage;
use BaksDev\Materials\Catalog\Repository\ExistMaterialBarcode\ExistMaterialBarcodeInterface;
use BaksDev\Materials\Catalog\Type\Barcode\MaterialBarcode;
use BaksDev\Materials\Sign\Entity\Code\MaterialSignCode;
use BaksDev\Materials\Sign\Entity\MaterialSign;
use BaksDev\Materials\Sign\Type\Status\MaterialSignStatus\MaterialSignStatusError;
use BaksDev\Materials\Sign\UseCase\Admin\New\MaterialSignDTO;
use BaksDev\Materials\Sign\UseCase\Admin\New\MaterialSignHandler;
use Doctrine\ORM\Mapping\Table;
use Exception;
use Imagick;
use Psr\Log\LoggerInterface;
use ReflectionAttribute;
use ReflectionClass;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\DependencyInjection\Attribute\Target;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler(priority: 0)]
final readonly class MaterialSignScannerDispatcher
{
    public function __construct(
        #[Autowire('%kernel.project_dir%')] private string $upload,
        #[Target('materialsSignLogger')] private LoggerInterface $logger,
        private MaterialSignHandler $materialSignHandler,
        private Filesystem $filesystem,
        private BarcodeRead $barcodeRead,
        private MessageDispatchInterface $messageDispatch,
        private ExistMaterialBarcodeInterface $ExistMaterialBarcodeRepository,
    ) {}

    public function __invoke(MaterialSignScannerMessage $message): void
    {

        /** Файла больше не существует */
        if(false === $this->filesystem->exists($message->getRealPath()))
        {
            return;
        }

        /** Директория загрузки файла с кодом */

        $ref = new ReflectionClass(MaterialSignCode::class);
        /** @var ReflectionAttribute $current */
        $current = current($ref->getAttributes(Table::class));

        if(!isset($current->getArguments()['name']))
        {
            $this->logger->critical(
                sprintf('Невозможно определить название таблицы из класса сущности %s ', MaterialSignCode::class),
                [self::class.':'.__LINE__],
            );
        }

        /**
         * Создаем полный путь для сохранения файла с кодом относительно директории сущности
         */

        $pathCode = null;
        $pathCode[] = $this->upload;
        $pathCode[] = 'public';
        $pathCode[] = 'upload';
        $pathCode[] = $current->getArguments()['name'];
        $pathCode[] = '';

        $dirCode = implode(DIRECTORY_SEPARATOR, $pathCode);

        /** Если директория загрузки не найдена - создаем с правами 0700 */
        $this->filesystem->exists($dirCode) ?: $this->filesystem->mkdir($dirCode);

        $pdfPath = $message->getRealPath();

        if(false === $this->filesystem->exists($pdfPath))
        {
            return;
        }


        /**
         * Открываем PDF для подсчета страниц на случай если их несколько
         */

        Imagick::setResourceLimit(Imagick::RESOURCETYPE_TIME, 3600);
        Imagick::setResourceLimit(Imagick::RESOURCETYPE_MEMORY, (1024 * 1024 * 256));


        $Imagick = new Imagick();

        $Imagick->setResolution(500, 500);
        $Imagick->readImage($pdfPath);
        $pages = $Imagick->getNumberImages(); // количество страниц в файле

        for($number = 0; $number < $pages; $number++)
        {
            $fileTemp = $dirCode.uniqid('', true).'.png';

            /** Преобразуем PDF страницу в PNG и сохраняем временно для расчета дайджеста md5 */
            $Imagick->setIteratorIndex($number);
            $Imagick->setImageFormat('png');


            /**
             * В некоторых случаях может вызывать ошибку,
             * в таком случае сохраняем без рамки и пробуем отсканировать как есть
             */
            try
            {
                $Imagick->borderImage('white', 5, 5);
            }
            catch(Exception $e)
            {
                $this->logger->critical('products-sign: Ошибка при добавлении рамки к изображению. Пробуем отсканировать как есть.', [$e->getMessage()]);
            }

            $Imagick->writeImage($fileTemp);
            $Imagick->clear();

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


            /**
             * Необходимо убедиться, что баркод соответствует выбранному сырью, однако только при условии, что ранее
             * данному сырью был присвоен баркод
             */
            if(false === $message->isNew())
            {
                /** Пытаемся проверить соответствие продукта и баркода в базе */
                $result = $this->ExistMaterialBarcodeRepository
                    ->forBarcode(new MaterialBarcode($code))
                    ->forMaterial($message->getMaterial())
                    ->forOffer($message->getOffer())
                    ->forVariation($message->getVariation())
                    ->forModification($message->getModification())
                    ->exist();


                /** Если баркод не соответствует торговому предложению - не сохраняем такой честный знак */
                if(false === $result)
                {
                    $this->logger->warning(
                        sprintf('Баркод %s не соответствует выбранному продукту', $code),
                        [self::class.':'.__LINE__],
                    );


                    /** Удаляем после обработки файл PDF */
                    $this->filesystem->remove($pdfPath);

                    return;
                }
            }


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

            $MaterialSignCodeDTO
                ->setCode($code)
                ->setName($scanDirName)
                ->setExt('png');

            $MaterialSignInvariableDTO = $MaterialSignDTO->getInvariable();

            $MaterialSignInvariableDTO
                ->setPart($message->getPart())
                ->setUsr($message->getUsr())
                ->setProfile($message->getProfile())
                ->setSeller($message->isNotShare() ? $message->getProfile() : null)
                ->setNumber($message->getNumber())
                ->setMaterial($message->getMaterial())
                ->setOffer($message->getOffer())
                ->setVariation($message->getVariation())
                ->setModification($message->getModification());

            $handle = $this->materialSignHandler->handle($MaterialSignDTO);

            if(false === ($handle instanceof MaterialSign))
            {
                if($handle === false)
                {
                    $this->logger->warning(sprintf('Дубликат честного знака %s: ', $code));

                    /** Удаляем после обработки файл PDF */
                    $this->filesystem->remove($pdfPath);

                    continue;
                }

                $this->logger->critical(
                    sprintf('materials-sign: Ошибка %s при сканировании: ', $handle),
                    [self::class.':'.__LINE__],
                );
            }
            else
            {
                $this->logger->info(
                    sprintf('%s: %s', $handle->getId(), $code),
                    [self::class.':'.__LINE__],
                );

                /** Создаем комманду для отправки файла CDN */
                $this->messageDispatch->dispatch(
                    new CDNUploadImageMessage($handle->getId(), MaterialSignCode::class, $md5),
                    transport: 'files-res-low',
                );

                /** Удаляем после обработки файл PDF */
                $this->filesystem->remove($pdfPath);
            }
        }
    }
}
