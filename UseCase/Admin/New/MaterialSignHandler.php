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

namespace BaksDev\Materials\Sign\UseCase\Admin\New;

use BaksDev\Core\Entity\AbstractHandler;
use BaksDev\Core\Messenger\MessageDispatchInterface;
use BaksDev\Core\Validator\ValidatorCollectionInterface;
use BaksDev\Files\Resources\Messenger\Request\Images\CDNUploadImageMessage;
use BaksDev\Files\Resources\Upload\File\FileUploadInterface;
use BaksDev\Files\Resources\Upload\Image\ImageUploadInterface;
use BaksDev\Materials\Sign\Entity\Code\MaterialSignCode;
use BaksDev\Materials\Sign\Entity\Event\MaterialSignEvent;
use BaksDev\Materials\Sign\Entity\MaterialSign;
use BaksDev\Materials\Sign\Messenger\MaterialSignMessage;
use BaksDev\Materials\Sign\Repository\ExistsMaterialSignCode\ExistsMaterialSignCodeInterface;
use Doctrine\ORM\EntityManagerInterface;

final class MaterialSignHandler extends AbstractHandler
{
    public function __construct(
        private readonly ExistsMaterialSignCodeInterface $existsMaterialSignCode,

        EntityManagerInterface $entityManager,
        MessageDispatchInterface $messageDispatch,
        ValidatorCollectionInterface $validatorCollection,
        ImageUploadInterface $imageUpload,
        FileUploadInterface $fileUpload,
    )
    {
        parent::__construct($entityManager, $messageDispatch, $validatorCollection, $imageUpload, $fileUpload);
    }


    public function handle(MaterialSignDTO $command): MaterialSign|string|false
    {
        /** Делаем проверку на дубли */
        $Invariable = $command->getInvariable();
        $Barcode = $command->getCode();

        $isExistsBarcode = $this->existsMaterialSignCode->isExists(
            $Invariable->getUsr(),
            $Barcode->getCode()
        );

        if($isExistsBarcode === true)
        {
            return false;
        }


        $this
            ->setCommand($command)
            ->preEventPersistOrUpdate(MaterialSign::class, MaterialSignEvent::class);

        /** Валидация всех объектов */
        if($this->validatorCollection->isInvalid())
        {
            return $this->validatorCollection->getErrorUniqid();
        }

        $this->flush();

        /* Отправляем сообщение в шину */
        $this->messageDispatch->dispatch(
            message: new MaterialSignMessage($this->main->getId(), $this->main->getEvent(), $command->getEvent()),
            transport: 'materials-sign'
        );

        /* Загружаем файл обложки раздела на CDN */
        $this->messageDispatch->dispatch(
            message: new CDNUploadImageMessage($this->main->getId(), MaterialSignCode::class, $Barcode->getName()),
            transport: 'files-res'
        );

        return $this->main;
    }
}
