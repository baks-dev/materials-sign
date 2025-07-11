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
use BaksDev\Materials\Sign\Forms\MaterialSignTransfer\MaterialSignTransferDTO;
use BaksDev\Materials\Sign\Forms\MaterialSignTransfer\MaterialSignTransferForm;
use BaksDev\Materials\Sign\Repository\MaterialSignReport\MaterialSignReportInterface;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Contracts\Translation\TranslatorInterface;

#[AsController]
#[RoleSecurity(['ROLE_ORDERS', 'ROLE_MATERIAL_SIGN'])]
final class TransferController extends AbstractController
{
    #[Route('/admin/material/sign/transfer', name: 'admin.transfer', methods: ['GET', 'POST'])]
    public function off(
        Request $request,
        MaterialSignReportInterface $MaterialSignReport,
        TranslatorInterface $translator,
    ): Response
    {
        $MaterialSignReportDTO = new MaterialSignTransferDTO()
            ->setProfile($this->getProfileUid());

        // Форма
        $form = $this
            ->createForm(
                type: MaterialSignTransferForm::class,
                data: $MaterialSignReportDTO,
                options: ['action' => $this->generateUrl('materials-sign:admin.transfer'),]
            )
            ->handleRequest($request);

        if($form->isSubmitted() && $form->isValid() && $form->has('material_sign_transfer'))
        {
            $this->refreshTokenForm($form);

            $MaterialSignReport
                ->fromProfile($MaterialSignReportDTO->getProfile())
                ->fromSeller($MaterialSignReportDTO->getSeller())
                ->dateFrom($MaterialSignReportDTO->getFrom())
                ->dateTo($MaterialSignReportDTO->getTo());

            /** Получаем только в процессе */
            $data = $MaterialSignReport
                ->onlyStatusProcess()
                ->findAll();

            if(false === $data)
            {
                $this->addFlash(
                    'Отчет о передаче честных знаков',
                    'Отчета за указанный период не найдено',
                    'materials-sign.admin'
                );

                return $this->redirectToReferer();
            }


            // Создаем новый объект Spreadsheet
            $spreadsheet = new Spreadsheet();
            $writer = new Xlsx($spreadsheet);

            // Получаем текущий активный лист
            $sheet = $spreadsheet->getActiveSheet();


            $key = 1;

            foreach($data as $item)
            {
                if(false === $item->getProducts() || false === $item->getProducts()->valid())
                {
                    continue;
                }

                foreach($item->getProducts() as $product)
                {
                    $name = $product->getName();

                    if($product->getVariationValue())
                    {
                        $name = trim($name).' '.(
                            $product->getVariationReference() ?
                                $translator->trans(id: $product->getVariationValue(), domain: $product->getVariationReference()) :
                                $product->getVariationValue()
                            );
                    }

                    if($product->getModificationValue())
                    {
                        $name = trim($name).' '.(
                            $product->getModificationReference() ?
                                $translator->trans(id: $product->getModificationValue(), domain: $product->getModificationReference()) :
                                $product->getVariationValue()
                            );
                    }

                    if($product->getOfferValue())
                    {
                        $name = trim($name).' '.(
                            $product->getOfferReference() ?
                                $translator->trans(id: $product->getOfferValue(), domain: $product->getOfferReference()) :
                                $product->getOfferValue()
                            );
                    }

                    $sheet->setCellValue('A'.$key, trim($name)); // Наименование товара
                    $sheet->setCellValue('B'.$key, $product->getGtin()); // GTIN
                    $sheet->setCellValue('C'.$key, $product->codeSmallFormat()); // Код маркировки/агрегата

                    $key++;
                }
            }

            $filename = $MaterialSignReportDTO->getProfile()?->getAttr().'-'.
                $MaterialSignReportDTO->getSeller()?->getAttr().'('.
                $MaterialSignReportDTO->getFrom()->format(('d.m.Y')).'-'.
                $MaterialSignReportDTO->getTo()->format(('d.m.Y')).').xlsx';


            $response = new StreamedResponse(function() use ($writer) {
                $writer->save('php://output');
            }, Response::HTTP_OK);


            // Redirect output to a client’s web browser (Xls)
            $response->headers->set('Content-Type', 'application/vnd.ms-excel');
            $response->headers->set('Content-Disposition', 'attachment;filename="'.str_replace('"', '', $filename).'"');
            $response->headers->set('Cache-Control', 'max-age=0');

            return $response;
        }

        return $this->render(['form' => $form->createView()]);
    }
}
