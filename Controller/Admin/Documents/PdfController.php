<?php
/*
 *  Copyright 2024.  Baks.dev <admin@baks.dev>
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

namespace BaksDev\Materials\Sign\Controller\Admin\Documents;

use BaksDev\Barcode\Writer\BarcodeFormat;
use BaksDev\Barcode\Writer\BarcodeType;
use BaksDev\Barcode\Writer\BarcodeWrite;
use BaksDev\Core\Controller\AbstractController;
use BaksDev\Core\Type\UidType\ParamConverter;
use BaksDev\Files\Resources\Twig\ImagePathExtension;
use BaksDev\Materials\Catalog\Type\Offers\ConstId\MaterialOfferConst;
use BaksDev\Materials\Catalog\Type\Offers\Variation\ConstId\MaterialVariationConst;
use BaksDev\Materials\Catalog\Type\Offers\Variation\Modification\ConstId\MaterialModificationConst;
use BaksDev\Materials\Sign\Repository\MaterialSignByOrder\MaterialSignByOrderInterface;
use BaksDev\Materials\Sign\Repository\MaterialSignByOrder\MaterialSignByOrderResult;
use BaksDev\Materials\Sign\Repository\MaterialSignByPart\MaterialSignByPartInterface;
use BaksDev\Materials\Sign\Repository\MaterialSignByPart\MaterialSignByPartResult;
use BaksDev\Materials\Sign\Type\Id\MaterialSignUid;
use BaksDev\Orders\Order\Type\Id\OrderUid;
use BaksDev\Products\Product\Type\Material\MaterialUid;
use Doctrine\ORM\Mapping\Table;
use Generator;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\ResponseHeaderBag;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\Process\Process;
use Symfony\Component\Routing\Attribute\Route;

#[AsController]
final class PdfController extends AbstractController
{
    private string $projectDir;

    private ImagePathExtension $ImagePathExtension;
    private string $article;

    #[Route('/admin/material/sign/document/pdf/orders/{article}/{order}/{material}/{offer}/{variation}/{modification}', name: 'document.pdf.orders', methods: ['GET'])]
    public function orders(
        MaterialSignByOrderInterface $materialSignByOrder,
        ImagePathExtension $ImagePathExtension,
        BarcodeWrite $barcodeWrite,
        string $article,
        #[Autowire('%kernel.project_dir%')] string $projectDir,
        #[ParamConverter(OrderUid::class)] OrderUid $order,
        #[ParamConverter(MaterialUid::class)] MaterialUid $material,
        #[ParamConverter(MaterialOfferConst::class)] ?MaterialOfferConst $offer = null,
        #[ParamConverter(MaterialVariationConst::class)] ?MaterialVariationConst $variation = null,
        #[ParamConverter(MaterialModificationConst::class)] ?MaterialModificationConst $modification = null,
    ): Response
    {

        $this->article = $article;
        $this->projectDir = $projectDir;
        $this->ImagePathExtension = $ImagePathExtension;

        $codes = $materialSignByOrder
            ->forOrder($order)
            ->material($material)
            ->offer($offer)
            ->variation($variation)
            ->modification($modification)
            ->findAll();


        /**
         * Создаем путь для создания PDF файла
         */

        $paths[] = $projectDir;
        $paths[] = 'public';
        $paths[] = 'upload';
        $paths[] = 'barcode';

        $paths[] = (string) $order;
        $paths[] = (string) $material;

        !$offer ?: $paths[] = (string) $offer;
        !$variation ?: $paths[] = (string) $variation;
        !$modification ?: $paths[] = (string) $modification;


        return $this->BinaryFileResponse($paths, $codes, $barcodeWrite);

    }


    private function BinaryFileResponse(array $paths, Generator $codes, BarcodeWrite $barcodeWrite): BinaryFileResponse
    {
        $filesystem = new Filesystem();

        $uploadDir = implode(DIRECTORY_SEPARATOR, $paths);

        $uploadFile = $uploadDir.DIRECTORY_SEPARATOR.'output.pdf';

        /**
         * Если файл имеется - отдаем
         */

        if($filesystem->exists($uploadFile))
        {
            $filesystem->remove($uploadFile);
        }

        /**
         * Создаем директорию при отсутствии
         */

        if($filesystem->exists($uploadDir) === false)
        {
            $filesystem->mkdir($uploadDir);
        }

        /**
         * Формируем запрос на генерацию PDF с массивом изображений
         */

        $Process[] = 'convert';

        /** Присваиваем директорию public для локальных файлов */
        $projectDir = implode(DIRECTORY_SEPARATOR, [
            $this->projectDir,
            'public',
            '',
        ]);

        /** @var MaterialSignByPartResult|MaterialSignByOrderResult $MaterialSignResult */
        foreach($codes as $MaterialSignResult)
        {
            $barcodeWrite
                ->text($MaterialSignResult->getBigCode())
                ->type(BarcodeType::DataMatrix)
                ->format(BarcodeFormat::PNG)
                ->generate(filename: (string) $MaterialSignResult->getCodeEvent());

            $path = $barcodeWrite->getPath();

            $Process[] = $path.$MaterialSignResult->getCodeEvent().'.png';
        }

        $Process[] = $uploadFile;

        $processCrop = new Process($Process);
        $processCrop->mustRun();

        return new BinaryFileResponse($uploadFile, Response::HTTP_OK)
            ->setContentDisposition(
                ResponseHeaderBag::DISPOSITION_ATTACHMENT,
                $this->article.'.pdf',
            );

    }

    #[Route('/admin/material/sign/document/pdf/parts/{article}/{part}', name: 'document.pdf.parts', methods: ['GET'])]
    public function parts(
        #[Autowire('%kernel.project_dir%')] $projectDir,
        #[ParamConverter(MaterialSignUid::class)] $part,
        string $article,
        MaterialSignByPartInterface $materialSignByPart,
        ImagePathExtension $ImagePathExtension,
        BarcodeWrite $barcodeWrite,
    ): Response
    {
        $this->projectDir = $projectDir;
        $this->ImagePathExtension = $ImagePathExtension;
        $this->article = $article;

        $codes = $materialSignByPart
            ->forPart($part)
            ->withStatusDone()
            ->findAll();

        if(false === $codes || false === $codes->valid())
        {
            return new Response(status: Response::HTTP_NOT_FOUND);
        }

        /**
         * Создаем путь для создания PDF файла
         */

        $paths[] = $projectDir;
        $paths[] = 'public';
        $paths[] = 'upload';
        $paths[] = 'barcode';

        $paths[] = (string) $part;

        return $this->BinaryFileResponse($paths, $codes, $barcodeWrite);
    }

}
