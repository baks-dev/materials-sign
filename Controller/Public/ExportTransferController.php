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

namespace BaksDev\Materials\Sign\Controller\Public;


use BaksDev\Core\Controller\AbstractController;
use BaksDev\Materials\Sign\Repository\MaterialSignReport\MaterialSignReportInterface;
use BaksDev\Users\Profile\UserProfile\Entity\UserProfile;
use DateTimeImmutable;
use DateTimeInterface;
use InvalidArgumentException;
use Symfony\Bridge\Doctrine\Attribute\MapEntity;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\Routing\Attribute\Route;

#[AsController]
final class ExportTransferController extends AbstractController
{
    /**
     * Отчет о «Честных знаках» на передачу честных знаков юр. лицу по свойству:
     * profile == {profile}
     * profile != seller
     * status = process
     */
    #[Route('/materials/signs/export/transfer/{profile}', name: 'public.export.transfer', methods: ['GET', 'POST'])]
    public function index(
        #[MapEntity] UserProfile $profile,
        Request $request,
        MaterialSignReportInterface $MaterialSignReport,
    ): Response
    {
        /**
         * Присваиваем дату начала отчетного периода
         */

        $from = $request->get('from');

        if(empty($from))
        {
            throw new InvalidArgumentException('Параметр «from» должен быть допустимой датой.');
        }

        $from = new DateTimeImmutable($from);

        /**
         * Присваиваем конечную дату отчетного периода
         */

        $to = $request->get('to');

        if(empty($to))
        {
            throw new InvalidArgumentException('Параметр «to» должен быть допустимой датой.');
        }

        $to = new DateTimeImmutable($to);

        /** Получаем только со статусом в процессе за указанный период */

        $data = $MaterialSignReport
            ->fromProfile($profile->getId())
            ->onlyStatusProcess()
            ->dateFrom($from)
            ->dateTo($to)
            ->findAll();

        if(false === $data || false === $data->valid())
        {
            return new JsonResponse([
                'status' => 404,
                'message' => 'За указанный период честных знаков не найдено'
            ], status: 404);
        }

        $rows = null;

        foreach($data as $key => $item)
        {
            /** Номер заказа */
            $rows[$key]['number'] = $item->getNumber();

            /** Дата доставки в формате ISO8601 */
            $rows[$key]['date'] = $item->getDate()->format(DateTimeInterface::ATOM);

            $rows[$key]['clientinn'] = $item->getInn();

            $rows[$key]['clientKPP'] = $item->getKpp();

            /** Полная стоимость заказа */
            $rows[$key]['documentamount'] = $item->getTotalPrice()->getValue();

            if(false === $item->getProducts() || false === $item->getProducts()->valid())
            {
                continue;
            }

            foreach($item->getProducts() as $i => $MaterialSignReportProductDTO)
            {
                $rows[$key]['goods'][$i]['good'] = $MaterialSignReportProductDTO->getArticle(); // артикул
                $rows[$key]['goods'][$i]['count'] = $MaterialSignReportProductDTO->getCount(); // количество
                $rows[$key]['goods'][$i]['price'] = $MaterialSignReportProductDTO->getPrice()->getValue(); // цена товара
                $rows[$key]['goods'][$i]['amount'] = $MaterialSignReportProductDTO->getAmount()->getValue(); // стоимость с учетом количества
                $rows[$key]['goods'][$i]['markingcode'] = $MaterialSignReportProductDTO->codeSmallFormat(); // короткий код
            }
        }

        return new JsonResponse($rows);
    }
}
