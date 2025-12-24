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

namespace BaksDev\Materials\Sign\UseCase\Admin\Pdf;

use BaksDev\Core\Messenger\MessageDispatchInterface;
use BaksDev\Core\Validator\ValidatorCollectionInterface;
use BaksDev\Materials\Sign\Entity\MaterialSign;
use BaksDev\Materials\Sign\Messenger\MaterialSignLink\MaterialSignLinkMessage;
use BaksDev\Materials\Sign\Messenger\MaterialSignPdf\MaterialSignPdfMessage;
use BaksDev\Materials\Sign\UseCase\Admin\Pdf\MaterialSignFile\MaterialSignFileDTO;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\DependencyInjection\Attribute\Target;
use Symfony\Component\Filesystem\Exception\IOExceptionInterface;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\HttpFoundation\File\UploadedFile;

final readonly class MaterialSignPdfHandler
{
    public function __construct(
        #[Autowire('%kernel.project_dir%')] private string $upload,
        #[Target('materialsSignLogger')] private LoggerInterface $logger,
        private MessageDispatchInterface $messageDispatch,
        private ValidatorCollectionInterface $validatorCollection,
    ) {}


    /** @see MaterialSign */
    public function handle(MaterialSignPdfDTO $command): string|bool
    {
        $upload[] = $this->upload;
        $upload[] = 'public';
        $upload[] = 'upload';
        $upload[] = 'barcode';
        $upload[] = 'materials-sign';

        $upload[] = (string) $command->getUsr();

        if($command->getProfile())
        {
            $upload[] = (string) $command->getProfile();
        }

        $upload[] = (string) $command->getMaterial();

        if($command->getOffer())
        {
            $upload[] = (string) $command->getOffer();
        }

        if($command->getVariation())
        {
            $upload[] = (string) $command->getVariation();
        }

        if($command->getModification())
        {
            $upload[] = (string) $command->getModification();
        }


        $upload[] = '';

        // Директория загрузки файла
        $uploadDir = implode(DIRECTORY_SEPARATOR, $upload);


        $Filesystem = new Filesystem();

        /** Если директория загрузки не найдена - создаем  */
        if(!$Filesystem->exists($uploadDir))
        {
            try
            {
                $Filesystem->mkdir($uploadDir);
            }
            catch(IOExceptionInterface $exception)
            {
                $this->logger->critical(
                    'Ошибка при создании директории. Попробуйте применить комманду ',
                    [
                        'chown -R unit:unit '.$uploadDir
                    ]
                );

                return 'Ошибка при создании директории.';
            }
        }


        $filename = [];

        /**
         * @var MaterialSignFileDTO $files
         * @var UploadedFile $file
         */
        foreach($command->getFiles() as $files)
        {
            foreach($files->pdf as $file)
            {
                $name = uniqid('original_', true).'.pdf';
                $filename[] = $name;

                $file->move($uploadDir, $name);

                /** Валидация файла  */
                $this->validatorCollection->add($file);
            }
        }

        if(false === empty($command->getLinks()))
        {
            /** Получаем (если есть) введенные текстом ссылки на скачивание PDF */
            $text = $command->getLinks();


            /** Разделение текста на строки */
            $lines = preg_split('/\r\n|\r|\n/', $text);

            foreach ($lines as $line)
            {
                /** Если строка является ссылкой - пытаемся скачать  */
                if(str_starts_with($line, 'https:'))
                {
                    $this->messageDispatch->dispatch(
                        new MaterialSignLinkMessage(
                            $line,
                            $uploadDir,
                            $command->getUsr(),
                            $command->getProfile(),
                            $command->getMaterial(),
                            $command->getOffer(),
                            $command->getVariation(),
                            $command->getModification(),
                            $command->isPurchase(),
                            $command->isNotShare(),
                            $command->getNumber(),
                            $command->isNew()
                        ),
                        transport: 'materials-sign'
                    );
                }
            }
        }

        if(empty($filename))
        {
            /**
             * Добавляем в коллекцию dto, т.к. пустая коллекция валидатора (в случае, если файлы не загружались
             * пользователем, а были добавлены через ссылки) не позволит отправить сообщение на сканирование честных
             * знаков
             */
            $this->validatorCollection->add($command);
        }

        /** Валидация всех объектов */
        if($this->validatorCollection->isInvalid())
        {
            foreach($filename as $item)
            {
                $Filesystem->remove($uploadDir.'/'.$item);
            }

            return $this->validatorCollection->getErrorUniqid();
        }


        /** @var MaterialSignPdfMessage $message */

        /* Отправляем сообщение в шину для обработки файлов */
        $this->messageDispatch->dispatch(
            message: new MaterialSignPdfMessage(
                $command->getUsr(),
                $command->getProfile(),
                $command->getMaterial(),
                $command->getOffer(),
                $command->getVariation(),
                $command->getModification(),
                $command->isPurchase(),
                $command->isNotShare(),
                $command->getNumber(),
                $command->isNew(),
            ),
            transport: 'materials-sign'
        );

        return true;
    }
}
