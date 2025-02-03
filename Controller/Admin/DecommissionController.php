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
use BaksDev\Core\Listeners\Event\Security\RoleSecurity;
use BaksDev\Core\Type\UidType\ParamConverter;
use BaksDev\Materials\Catalog\Type\Id\MaterialUid;
use BaksDev\Materials\Catalog\Type\Offers\ConstId\MaterialOfferConst;
use BaksDev\Materials\Catalog\Type\Offers\Variation\ConstId\MaterialVariationConst;
use BaksDev\Materials\Catalog\Type\Offers\Variation\Modification\ConstId\MaterialModificationConst;
use BaksDev\Materials\Category\Type\Id\CategoryMaterialUid;
use BaksDev\Materials\Sign\Type\Id\MaterialSignUid;
use BaksDev\Materials\Sign\UseCase\Admin\Decommission\DecommissionMaterialSignDTO;
use BaksDev\Materials\Sign\UseCase\Admin\Decommission\DecommissionMaterialSignForm;
use BaksDev\Materials\Sign\UseCase\Admin\Decommission\DecommissionMaterialSignHandler;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\Routing\Attribute\Route;

#[AsController]
#[RoleSecurity('ROLE_MATERIAL_SIGN_STATUS')]
final class DecommissionController extends AbstractController
{
    #[Route('/admin/material/sign/decommission/{category}/{material}/{offer}/{variation}/{modification}', name: 'admin.decommission', methods: ['GET', 'POST'])]
    public function off(
        Request $request,
        DecommissionMaterialSignHandler $OffMaterialSignHandler,
        #[ParamConverter(CategoryMaterialUid::class)] $category = null,
        #[ParamConverter(MaterialUid::class)] $material = null,
        #[ParamConverter(MaterialOfferConst::class)] $offer = null,
        #[ParamConverter(MaterialVariationConst::class)] $variation = null,
        #[ParamConverter(MaterialModificationConst::class)] $modification = null
    ): Response
    {

        $OffMaterialSignDTO = new DecommissionMaterialSignDTO();
        $OffMaterialSignDTO->setCategory($category);
        $OffMaterialSignDTO->setMaterial($material);
        $OffMaterialSignDTO->setOffer($offer);
        $OffMaterialSignDTO->setVariation($variation);
        $OffMaterialSignDTO->setModification($modification);

        // Форма
        $form = $this->createForm(DecommissionMaterialSignForm::class, $OffMaterialSignDTO, [
            'action' => $this->generateUrl('materials-sign:admin.decommission'),
        ]);
        $form->handleRequest($request);

        if($form->isSubmitted() && $form->isValid() && $form->has('material_sign_off'))
        {
            $this->refreshTokenForm($form);

            $handle = $OffMaterialSignHandler->handle($OffMaterialSignDTO);

            $this->addFlash(
                'page.off',
                $handle instanceof MaterialSignUid ? 'success.off' : $handle,
                'materials-sign.admin',
                $handle
            );

            return $this->redirectToRoute('materials-sign:admin.index');
        }

        return $this->render(['form' => $form->createView()]);
    }
}
