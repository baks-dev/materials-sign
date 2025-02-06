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

use BaksDev\Core\Controller\AbstractController;
use BaksDev\Core\Listeners\Event\Security\RoleSecurity;
use BaksDev\Core\Type\UidType\ParamConverter;
use BaksDev\Files\Resources\Twig\ImagePathExtension;
use BaksDev\Materials\Catalog\Entity\Material;
use BaksDev\Materials\Catalog\Type\Offers\ConstId\MaterialOfferConst;
use BaksDev\Materials\Catalog\Type\Offers\Variation\ConstId\MaterialVariationConst;
use BaksDev\Materials\Catalog\Type\Offers\Variation\Modification\ConstId\MaterialModificationConst;
use BaksDev\Materials\Sign\Entity\Code\MaterialSignCode;
use BaksDev\Materials\Sign\Repository\MaterialSignByOrder\MaterialSignByOrderInterface;
use BaksDev\Materials\Sign\Repository\MaterialSignByPart\MaterialSignByPartInterface;
use BaksDev\Materials\Sign\Type\Id\MaterialSignUid;
use BaksDev\Orders\Order\Type\Id\OrderUid;
use BaksDev\Products\Product\Type\Material\MaterialUid;
use BaksDev\Products\Sign\Type\Id\ProductSignUid;
use Doctrine\ORM\Mapping\Table;
use ReflectionAttribute;
use ReflectionClass;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\ResponseHeaderBag;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\Process\Process;
use Symfony\Component\Routing\Attribute\Route;

#[AsController]
#[RoleSecurity(['ROLE_ORDERS', 'ROLE_MATERIAL_SIGN'])]
final class PdfController extends AbstractController
{
    private string $projectDir;

    private ImagePathExtension $ImagePathExtension;

    #[Route('/admin/material/sign/document/pdf/orders/{order}/{material}/{offer}/{variation}/{modification}', name: 'document.pdf.orders', methods: ['GET'])]
    public function orders(
        MaterialSignByOrderInterface $materialSignByOrder,
        ImagePathExtension $ImagePathExtension,
        #[Autowire('%kernel.project_dir%')] string $projectDir,
        #[ParamConverter(OrderUid::class)] OrderUid $order,
        #[ParamConverter(MaterialUid::class)] MaterialUid $material,
        #[ParamConverter(MaterialOfferConst::class)] ?MaterialOfferConst $offer = null,
        #[ParamConverter(MaterialVariationConst::class)] ?MaterialVariationConst $variation = null,
        #[ParamConverter(MaterialModificationConst::class)] ?MaterialModificationConst $modification = null,
    ): Response
    {

        $this->projectDir = $projectDir;
        $this->ImagePathExtension = $ImagePathExtension;

        $codes = $materialSignByOrder
            ->forOrder($order)
            ->material($material)
            ->offer($offer)
            ->variation($variation)
            ->modification($modification)
            ->findAll();

        return $this->BinaryFileResponse((string) $order, $codes);

    }

    #[Route('/admin/material/sign/document/pdf/parts/{part}', name: 'document.pdf.parts', methods: ['GET'])]
    public function parts(
        #[Autowire('%kernel.project_dir%')] $projectDir,
        #[ParamConverter(MaterialSignUid::class)] $part,
        MaterialSignByPartInterface $materialSignByPart,
        ImagePathExtension $ImagePathExtension,
    ): Response
    {
        $this->projectDir = $projectDir;
        $this->ImagePathExtension = $ImagePathExtension;

        $codes = $materialSignByPart
            ->forPart($part)
            ->withStatusDone()
            ->findAll();

        return $this->BinaryFileResponse((string) $part, $codes);
    }


    private function BinaryFileResponse(string $identifier, array $codes): BinaryFileResponse
    {
        $filesystem = new Filesystem();

        /**
         * Создаем путь для создания PDF файла
         */

        $ref = new ReflectionClass(MaterialSignCode::class);
        /** @var ReflectionAttribute $current */
        $current = current($ref->getAttributes(Table::class));
        $dirName = $current->getArguments()['name'] ?? 'barcode';

        $uploadDir = implode(DIRECTORY_SEPARATOR, [
            $this->projectDir,
            'public',
            'upload',
            $dirName,
            $identifier
        ]);

        $uploadFile = $uploadDir.DIRECTORY_SEPARATOR.'output.pdf';

        /**
         * Если файл имеется - отдаем
         */

        if($filesystem->exists($uploadFile))
        {
            return new BinaryFileResponse($uploadFile, Response::HTTP_OK)
                ->setContentDisposition(
                    ResponseHeaderBag::DISPOSITION_ATTACHMENT,
                    'sign.pdf'
                );
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

        $projectDir = implode(DIRECTORY_SEPARATOR, [
            $this->projectDir,
            'public',
            ''
        ]);

        foreach($codes as $code)
        {
            $Process[] = ($code['code_cdn'] === false ? $projectDir : '').$this->ImagePathExtension->imagePath($code['code_image'], $code['code_ext'], $code['code_cdn']);
        }

        $Process[] = $uploadFile;

        $processCrop = new Process($Process);
        $processCrop->mustRun();

        return new BinaryFileResponse($uploadFile, Response::HTTP_OK)
            ->setContentDisposition(
                ResponseHeaderBag::DISPOSITION_ATTACHMENT,
                'sign.pdf'
            );

    }

}
