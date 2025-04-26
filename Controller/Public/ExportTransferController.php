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
use BaksDev\Core\Form\Search\SearchDTO;
use BaksDev\Core\Form\Search\SearchForm;
use BaksDev\Core\Listeners\Event\Security\RoleSecurity;
use BaksDev\Materials\Sign\Repository\MaterialSignReport\MaterialSignReportInterface;
use BaksDev\Users\Profile\UserProfile\Entity\UserProfile;
use DateTimeImmutable;
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
            ->fromProfile($profile)
            ->onlyStatusProcess()
            ->dateFrom($from)
            ->dateTo($to)
            ->findAll();

        return new JsonResponse([]);
    }
}
