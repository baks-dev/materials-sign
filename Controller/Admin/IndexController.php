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

namespace BaksDev\Materials\Sign\Controller\Admin;

use BaksDev\Core\Controller\AbstractController;
use BaksDev\Core\Form\Search\SearchDTO;
use BaksDev\Core\Form\Search\SearchForm;
use BaksDev\Core\Listeners\Event\Security\RoleSecurity;
use BaksDev\Materials\Catalog\Forms\MaterialFilter\Admin\MaterialFilterDTO;
use BaksDev\Materials\Catalog\Forms\MaterialFilter\Admin\MaterialFilterForm;
use BaksDev\Materials\Sign\Forms\MaterialSignFilter\MaterialSignFilterDTO;
use BaksDev\Materials\Sign\Forms\MaterialSignFilter\MaterialSignFilterForm;
use BaksDev\Materials\Sign\Repository\GroupMaterialSigns\GroupMaterialSignsInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\Routing\Attribute\Route;

#[AsController]
#[RoleSecurity('ROLE_MATERIAL_SIGN')]
final class IndexController extends AbstractController
{
    #[Route('/admin/material/signs/{page<\d+>}', name: 'admin.index', methods: ['GET', 'POST'])]
    public function index(
        Request $request,
        GroupMaterialSignsInterface $groupMaterialSigns,
        int $page = 0,
    ): Response
    {

        // Поиск
        $search = new SearchDTO();

        $searchForm = $this
            ->createForm(
                type: SearchForm::class,
                data: $search,
                options: ['action' => $this->generateUrl('materials-sign:admin.index')]
            )
            ->handleRequest($request);


        /**
         * Фильтр сырья по ТП
         */
        $filter = new MaterialFilterDTO($request);
        $filter->allVisible();

        $filterForm = $this->createForm(MaterialFilterForm::class, $filter, [
            'action' => $this->generateUrl('materials-sign:admin.index'),
        ]);
        $filterForm->handleRequest($request);

        /**
         * Фильтр статусам и даже
         */
        $filterSign = new MaterialSignFilterDTO();
        $filterSignForm = $this->createForm(MaterialSignFilterForm::class, $filterSign, [
            'action' => $this->generateUrl('materials-sign:admin.index'),
        ]);
        $filterSignForm->handleRequest($request);


        // Получаем список
        $MaterialSign = $groupMaterialSigns
            ->search($search)
            ->filter($filter)
            ->status($filterSign)
            ->findPaginator();


        return $this->render(
            [
                'query' => $MaterialSign,
                'search' => $searchForm->createView(),
                'filter' => $filterForm->createView(),
                'status' => $filterSignForm->createView(),
            ]
        );
    }
}
