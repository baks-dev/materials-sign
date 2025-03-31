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

#[AsController]
#[RoleSecurity(['ROLE_ORDERS', 'ROLE_MATERIAL_SIGN'])]
final class TransferController extends AbstractController
{
    #[Route('/admin/material/sign/transfer', name: 'admin.transfer', methods: ['GET', 'POST'])]
    public function off(
        Request $request,
        MaterialSignReportInterface $MaterialSignReport
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

            //            $data = $MaterialSignReport
            //                ->fromProfile($MaterialSignReportDTO->getProfile())
            //                ->fromSeller($MaterialSignReportDTO->getSeller())
            //                ->dateFrom($MaterialSignReportDTO->getFrom())
            //                ->dateTo($MaterialSignReportDTO->getTo())
            //                ->setMaterial($MaterialSignReportDTO->getMaterial())
            //                ->setOffer($MaterialSignReportDTO->getOffer())
            //                ->setVariation($MaterialSignReportDTO->getVariation())
            //                ->setModification($MaterialSignReportDTO->getModification())
            //                ->findAll();

            //            if(empty($data))
            //            {
            //                return $this->redirectToRoute('materials-sign:admin.index');
            //            }

            // Создаем новый объект Spreadsheet
            $spreadsheet = new Spreadsheet();


            // Получаем текущий активный лист
            $sheet = $spreadsheet->getActiveSheet();

            // Заполняем данные в ячейках
            $sheet->setCellValue('A1', 'Имя');
            $sheet->setCellValue('B1', 'Возраст');
            $sheet->setCellValue('A2', 'Иван');
            $sheet->setCellValue('B2', 25);
            $sheet->setCellValue('A3', 'Мария');
            $sheet->setCellValue('B3', 30);

            // Создаем объект Writer и сохраняем файл
            $writer = new Xlsx($spreadsheet);

            $MaterialSignReport
                ->fromProfile($MaterialSignReportDTO->getProfile())
                ->fromSeller($MaterialSignReportDTO->getSeller())
                ->dateFrom($MaterialSignReportDTO->getFrom())
                ->dateTo($MaterialSignReportDTO->getTo())
                ->setMaterial($MaterialSignReportDTO->getMaterial())
                ->setOffer($MaterialSignReportDTO->getOffer())
                ->setVariation($MaterialSignReportDTO->getVariation())
                ->setModification($MaterialSignReportDTO->getModification());

            /** Получаем только в процессе либо выполненные и удаляем только круглые скобки */
            $data = $MaterialSignReport
                ->onlyStatusProcessOrDone()
                ->findAll();

            $codes = array_column($data, 'code');

            $codes = array_map(static function($item) {
                return preg_replace('/((d+))/', '$1', $item);
            }, $codes);


            $filename = $MaterialSignReportDTO->getProfile()?->getAttr().'-'.
                $MaterialSignReportDTO->getSeller()?->getAttr().'('.
                $MaterialSignReportDTO->getFrom()->format(('d.m.Y')).'-'.
                $MaterialSignReportDTO->getTo()->format(('d.m.Y')).').xlsx';

            $response = new StreamedResponse();

            $response->headers->set('Content-Type', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
            $response->headers->set('Content-Disposition', 'attachment;filename="'.$filename.'"');
            $response->setPrivate();
            $response->headers->addCacheControlDirective('no-cache', true);
            $response->headers->addCacheControlDirective('must-revalidate', true);

            $response->setCallback(function() use ($writer) {
                $writer->save('php://output');
            });

            return $response;
        }

        return $this->render(['form' => $form->createView()]);
    }
}
