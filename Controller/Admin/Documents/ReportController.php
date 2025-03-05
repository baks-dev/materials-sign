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
use BaksDev\Materials\Sign\Forms\MaterialSignReport\MaterialSignReportDTO;
use BaksDev\Materials\Sign\Forms\MaterialSignReport\MaterialSignReportForm;
use BaksDev\Materials\Sign\Repository\MaterialSignReport\MaterialSignReportInterface;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\ResponseHeaderBag;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\Routing\Attribute\Route;

#[AsController]
#[RoleSecurity(['ROLE_ORDERS', 'ROLE_MATERIAL_SIGN'])]
final class ReportController extends AbstractController
{
    #[Route('/admin/material/sign/report', name: 'admin.report', methods: ['GET', 'POST'])]
    public function off(
        Request $request,
        MaterialSignReportInterface $MaterialSignReport
    ): Response
    {
        $MaterialSignReportDTO = new MaterialSignReportDTO();

        // Форма
        $form = $this
            ->createForm(
                MaterialSignReportForm::class,
                $MaterialSignReportDTO,
                ['action' => $this->generateUrl('materials-sign:admin.report'),]
            )
            ->handleRequest($request);

        if($form->isSubmitted() && $form->isValid() && $form->has('material_sign_report'))
        {
            $this->refreshTokenForm($form);

            $data = $MaterialSignReport
                ->fromProfile($MaterialSignReportDTO->getProfile())
                ->fromSeller($MaterialSignReportDTO->getSeller())
                ->dateFrom($MaterialSignReportDTO->getFrom())
                ->dateTo($MaterialSignReportDTO->getTo())
                ->setMaterial($MaterialSignReportDTO->getMaterial())
                ->setOffer($MaterialSignReportDTO->getOffer())
                ->setVariation($MaterialSignReportDTO->getVariation())
                ->setModification($MaterialSignReportDTO->getModification())
                ->findAll();

            if(empty($data))
            {
                return $this->redirectToRoute('materials-sign:admin.index');
            }


            $codes = array_column($data, 'code');

            /** Если передача - удаляем только круглые скобки   */
            if(true === $MaterialSignReportDTO->getProfile()?->equals($MaterialSignReportDTO->getSeller()))
            {
                $codes = array_map(function($item) {
                    return preg_replace('/((d+))/', '$1', $item);
                }, $codes);
            }

            if(false === $MaterialSignReportDTO->getProfile()?->equals($MaterialSignReportDTO->getSeller()))
            {
                array_unshift($codes, "1", "КИЗ");

                $codes = array_map(function($data) {

                    // Позиция для третьей группы
                    $thirdGroupPos = -1;

                    preg_match_all('/\((\d{2})\)/', $data, $matches, PREG_OFFSET_CAPTURE);

                    if(count($matches[0]) >= 3)
                    {
                        $thirdGroupPos = $matches[0][2][1];
                    }

                    // Если находимся на третьей группе, обрезаем строку
                    if($thirdGroupPos !== -1)
                    {
                        $markingcode = substr($data, 0, $thirdGroupPos);
                        // Убираем круглые скобки
                        $data = preg_replace('/\((\d{2})\)/', '$1', $markingcode);
                    }

                    str_replace('"', '""', $data);

                    return $data;

                }, $codes);


            }

            $response = new StreamedResponse(function() use ($codes) {

                $handle = fopen('php://output', 'w+');
                fputcsv($handle, $codes);
                fclose($handle);

            }, Response::HTTP_OK);


            $filename = $MaterialSignReportDTO->getProfile()?->getAttr().'-'.
                $MaterialSignReportDTO->getSeller()?->getAttr().'('.
                $MaterialSignReportDTO->getFrom()->format(('d.m.Y')).'-'.
                $MaterialSignReportDTO->getTo()->format(('d.m.Y')).').csv';


            $response->headers->set('Content-Type', 'text/csv');
            $response->headers->set('Content-Disposition', 'attachment; filename="'.$filename.'"');

            return $response;
        }

        return $this->render(['form' => $form->createView()]);
    }
}
