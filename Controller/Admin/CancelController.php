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
use BaksDev\Materials\Sign\Entity\MaterialSign;
use BaksDev\Materials\Sign\Repository\MaterialSignByPart\MaterialSignByPartInterface;
use BaksDev\Materials\Sign\Type\Event\MaterialSignEventUid;
use BaksDev\Materials\Sign\Type\Id\MaterialSignUid;
use BaksDev\Materials\Sign\UseCase\Admin\Status\MaterialSignCancelDTO;
use BaksDev\Materials\Sign\UseCase\Admin\Status\MaterialSignCancelForm;
use BaksDev\Materials\Sign\UseCase\Admin\Status\MaterialSignStatusHandler;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\Routing\Attribute\Route;

#[AsController]
#[RoleSecurity('ROLE_MATERIAL_SIGN_STATUS')]
final class CancelController extends AbstractController
{
    #[Route('/admin/material/sign/cancel/{part}', name: 'admin.cancel', methods: ['GET', 'POST'])]
    public function edit(
        Request $request,
        #[ParamConverter(MaterialSignUid::class)] $part,
        MaterialSignByPartInterface $MaterialSignByPart,
        MaterialSignStatusHandler $MaterialSignStatusHandler,
    ): Response
    {

        $MaterialSignStatusDTO = new MaterialSignCancelDTO($this->getProfileUid());
        $MaterialSignStatusDTO->setId(new MaterialSignEventUid());

        // Форма
        $form = $this
            ->createForm(MaterialSignCancelForm::class, $MaterialSignStatusDTO, [
                'action' => $this->generateUrl('materials-sign:admin.cancel', ['part' => $part]),
            ])
            ->handleRequest($request);

        if($form->isSubmitted() && $form->isValid() && $form->has('material_sign_cancel'))
        {
            $this->refreshTokenForm($form);

            /** Получаем все честные знаки по партии */
            $signs = $MaterialSignByPart
                ->forPart($part)
                ->withStatusDecommission()
                ->findAll();

            if(false === $signs)
            {
                $this->addFlash(
                    'page.cancel',
                    'danger.cancel',
                    'materials-sign.admin',
                    'Группы не найдено'
                );

                return $this->redirectToRoute('materials-sign:admin.index');
            }


            foreach($signs as $sign)
            {
                $MaterialSignStatusDTO = new MaterialSignCancelDTO($this->getProfileUid());
                $MaterialSignStatusDTO->setId(new MaterialSignEventUid($sign['sign_event']));

                $handle = $MaterialSignStatusHandler->handle($MaterialSignStatusDTO);

            }

            $this->addFlash(
                'page.cancel',
                $handle instanceof MaterialSign ? 'success.cancel' : 'danger.cancel',
                'materials-sign.admin',
                $handle
            );

            return $this->redirectToRoute('materials-sign:admin.index');
        }

        return $this->render(['form' => $form->createView()]);
    }
}
