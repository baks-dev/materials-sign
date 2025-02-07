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
use BaksDev\Materials\Catalog\Type\Offers\ConstId\MaterialOfferConst;
use BaksDev\Materials\Catalog\Type\Offers\Variation\ConstId\MaterialVariationConst;
use BaksDev\Materials\Catalog\Type\Offers\Variation\Modification\ConstId\MaterialModificationConst;
use BaksDev\Materials\Sign\Repository\MaterialSignByOrder\MaterialSignByOrderInterface;
use BaksDev\Materials\Sign\Repository\MaterialSignByPart\MaterialSignByPartInterface;
use BaksDev\Materials\Sign\Type\Id\MaterialSignUid;
use BaksDev\Orders\Order\Type\Id\OrderUid;
use BaksDev\Products\Product\Type\Material\MaterialUid;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\Routing\Attribute\Route;

#[AsController]
#[RoleSecurity(['ROLE_ORDERS', 'ROLE_MATERIAL_SIGN'])]
final class TxtController extends AbstractController
{
    #[Route('/admin/material/sign/document/txt/orders/{order}/{material}/{offer}/{variation}/{modification}', name: 'admin.txt.orders', methods: ['GET'])]
    public function orders(
        MaterialSignByOrderInterface $materialSignByOrder,
        #[ParamConverter(OrderUid::class)] $order,
        #[ParamConverter(MaterialUid::class)] MaterialUid $material,
        #[ParamConverter(MaterialOfferConst::class)] ?MaterialOfferConst $offer = null,
        #[ParamConverter(MaterialVariationConst::class)] ?MaterialVariationConst $variation = null,
        #[ParamConverter(MaterialModificationConst::class)] ?MaterialModificationConst $modification = null,
    ): Response
    {

        $codes = $materialSignByOrder
            ->forOrder($order)
            ->material($material)
            ->offer($offer)
            ->variation($variation)
            ->modification($modification)
            //->withStatusDone()
            ->findAll();

        if($codes === false)
        {
            $this->addFlash('danger', 'Честных знаков не найдено');

            return $this->redirectToReferer();
        }

        $response = new StreamedResponse(function() use ($codes) {

            $handle = fopen('php://output', 'w+');

            // Запись данных
            foreach($codes as $data)
            {
                /** Обрезаем честный знак до длины */

                // Позиция для третьей группы
                $thirdGroupPos = -1;

                preg_match_all('/\((\d{2})\)/', $data['code_string'], $matches, PREG_OFFSET_CAPTURE);

                if(count($matches[0]) >= 3)
                {
                    $thirdGroupPos = $matches[0][2][1];
                }

                // Если находимся на третьей группе, обрезаем строку
                if($thirdGroupPos !== -1)
                {
                    $markingcode = substr($data['code_string'], 0, $thirdGroupPos);
                    // Убираем круглые скобки
                    $data['code_string'] = preg_replace('/\((\d{2})\)/', '$1', $markingcode);
                }

                fwrite($handle, $data['code_string'].PHP_EOL);
            }

            fclose($handle);
        });


        $filename = uniqid('document_sign_', false).'.txt';
        $response->headers->set('Content-Type', 'text/plain');
        $response->headers->set('Content-Disposition', 'attachment; filename="'.$filename.'"');

        return $response;
    }

    #[Route('/admin/material/sign/document/txt/parts/{part}', name: 'admin.txt.parts', methods: ['GET'])]
    public function parts(
        MaterialSignByPartInterface $materialSignByPart,
        #[ParamConverter(MaterialSignUid::class)] $part,
    ): Response
    {

        $codes = $materialSignByPart
            ->forPart($part)
            ->withStatusNew()
            ->withStatusDecommission()
            ->findAll();

        if($codes === false)
        {
            $this->addFlash('danger', 'Честных знаков не найдено');

            return $this->redirectToReferer();
        }

        $response = new StreamedResponse(function() use ($codes) {

            $handle = fopen('php://output', 'w+');

            // Запись данных
            foreach($codes as $data)
            {
                /** Обрезаем честный знак до длины */

                // Позиция для третьей группы
                $thirdGroupPos = -1;

                preg_match_all('/\((\d{2})\)/', $data['code_string'], $matches, PREG_OFFSET_CAPTURE);

                if(count($matches[0]) >= 3)
                {
                    $thirdGroupPos = $matches[0][2][1];
                }

                // Если находимся на третьей группе, обрезаем строку
                if($thirdGroupPos !== -1)
                {
                    $markingcode = substr($data['code_string'], 0, $thirdGroupPos);

                    // Убираем круглые скобки
                    $data['code_string'] = preg_replace('/\((\d{2})\)/', '$1', $markingcode);
                }

                fwrite($handle, $data['code_string'].PHP_EOL);

            }

            fclose($handle);
        });

        $filename = uniqid('document_sign_', false).'.txt';
        $response->headers->set('Content-Type', 'text/plain');
        $response->headers->set('Content-Disposition', 'attachment; filename="'.$filename.'"');

        return $response;

    }

}
