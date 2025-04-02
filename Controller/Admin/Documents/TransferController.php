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

            /** Получаем только в процессе либо выполненные и удаляем только круглые скобки */
            $data = $MaterialSignReport
                ->onlyStatusProcessOrDone()
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

            foreach($data as $key => $code)
            {

                preg_match_all('/\(([A-Za-z0-9]{2})\)((?:(?!\([A-Za-z0-9]{2}\)).)*)/', $code['code'], $matches, PREG_SET_ORDER);

                $name = $code['material_name'];

                $name = trim($name).' '.(isset($code['material_variation_reference']) ? $translator->trans($code['material_variation_value'], domain: $code['material_variation_reference']) : $code['material_variation_value']);
                $name = trim($name).' '.(isset($code['material_modification_reference']) ? $translator->trans($code['material_modification_value'], domain: $code['material_modification_reference']) : $code['material_modification_value']);
                $name = trim($name).' '.(isset($code['material_offer_reference']) ? $translator->trans($code['material_offer_value'], domain: $code['material_offer_reference']) : $code['material_offer_value']);

                $sheet->setCellValue('A'.$key, trim($name)); // Наименование товара


                /**
                 *
                 * 0 => array:3 [
                 *      0 => "(01)04603766681641"
                 *      1 => "01"
                 *      2 => "04603766681641"
                 * ]
                 *
                 * 1 => array:3 [
                 *      0 => "(21)5fcCjIHGk-GCd"
                 *      1 => "21"
                 *      2 => "5fcCjIHGk-GCd"
                 * ]
                 *
                 * 2 => array:3 [
                 *      0 => "(91)EE10"
                 *      1 => "91"
                 *      2 => "EE10"
                 * ]
                 *
                 * 3 => array:3 [
                 *      0 => "(92)KYSaBIDv2w0ziz777fykgv0N0/CystKMUp4uE0F6goU="
                 *      1 => "92"
                 *      2 => "KYSaBIDv2w0ziz777fykgv0N0/CystKMUp4uE0F6goU="
                 * ]
                 */

                $sheet->setCellValue('B'.$key, $matches[0][2] ?? 'GTIN не определен'); // GTIN
                $sheet->setCellValue('C'.$key, $matches[0][1].$matches[0][2].$matches[1][1].$matches[1][2]); // Код маркировки/агрегата

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
