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
use Psr\Log\LoggerInterface;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\DependencyInjection\Attribute\Target;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\Process\Process;

#[AsMessageHandler(priority: 10)]
final readonly class MaterialSignCrop
{
    public function __construct(
        #[Target('materialsSignLogger')] private LoggerInterface $logger,
        #[Autowire('%kernel.project_dir%')] private string $upload,
        private Filesystem $filesystem,
    ) {}

    /**
     * Метод обрезает пустую область в файлах и сохраняет
     */
    public function __invoke(MaterialSignPdfMessage $message): void
    {
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

        /**
         * Рекурсивно сохраняем все листы PDF в отдельные файлы
         */

        $uploadDir = implode(DIRECTORY_SEPARATOR, $upload);

        $directory = new RecursiveDirectoryIterator($uploadDir);
        $iterator = new RecursiveIteratorIterator($directory);

        foreach($iterator as $info)
        {
            if($info->isFile() === false)
            {
                continue;
            }

            if($info->getExtension() !== 'pdf')
            {
                continue;
            }

            /** Пропускаем файлы, которые НЕ разбиты на страницы  */
            if(str_starts_with($info->getFilename(), 'page') === false)
            {
                continue;
            }

            if(false === $info->getRealPath() || false === file_exists($info->getRealPath()))
            {
                continue;
            }

            /**
             * Проверяем размер файла (пропускаем пустые страницы переименовав в error.txt)
             */
            if($info->getSize() < 100)
            {
                $this->filesystem->rename($info->getRealPath(), $info->getRealPath().'.error.txt');

                $this->logger->critical(
                    'Ошибка при удалении неразмеченной пустой области в файле PDF',
                    [$info->getRealPath(), self::class.':'.__LINE__],
                );

                continue;
            }


            $cropFilename = $info->getPath().DIRECTORY_SEPARATOR.uniqid('crop_', true).'.pdf';

            /**
             * Обрезаем пустую область
             */

            $processCrop = new Process(['sudo', 'pdfcrop', '--margins', '1', $info->getRealPath(), $cropFilename]);
            $processCrop->mustRun();

            /** Удаляем после обработки основной файл PDF */
            $this->filesystem->remove($info->getRealPath());
        }
    }
}
