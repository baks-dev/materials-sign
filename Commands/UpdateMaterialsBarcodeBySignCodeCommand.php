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

namespace BaksDev\Materials\Sign\Commands;

use BaksDev\Materials\Catalog\Entity\Info\MaterialInfo;
use BaksDev\Materials\Catalog\Entity\Offers\MaterialOffer;
use BaksDev\Materials\Catalog\Entity\Offers\Variation\MaterialVariation;
use BaksDev\Materials\Catalog\Entity\Offers\Variation\Modification\MaterialModification;
use BaksDev\Materials\Catalog\Repository\AllMaterialsIdentifier\AllMaterialsIdentifierInterface;
use BaksDev\Materials\Catalog\Type\Barcode\MaterialBarcode;
use BaksDev\Materials\Catalog\Type\Offers\Id\MaterialOfferUid;
use BaksDev\Materials\Catalog\Type\Offers\Variation\Id\MaterialVariationUid;
use BaksDev\Materials\Catalog\Type\Offers\Variation\Modification\Id\MaterialModificationUid;
use BaksDev\Materials\Sign\Repository\BarcodeByMaterial\BarcodeByMaterialInterface;
use BaksDev\Materials\Sign\UseCase\Admin\Barcode\MaterialBarcode\MaterialBarcodeDTO;
use BaksDev\Materials\Sign\UseCase\Admin\Barcode\MaterialBarcode\MaterialBarcodeHandler;
use BaksDev\Materials\Sign\UseCase\Admin\Barcode\MaterialModificationBarcode\MaterialModificationBarcodeDTO;
use BaksDev\Materials\Sign\UseCase\Admin\Barcode\MaterialModificationBarcode\MaterialModificationBarcodeHandler;
use BaksDev\Materials\Sign\UseCase\Admin\Barcode\MaterialOfferBarcode\MaterialOfferBarcodeDTO;
use BaksDev\Materials\Sign\UseCase\Admin\Barcode\MaterialOfferBarcode\MaterialOfferBarcodeHandler;
use BaksDev\Materials\Sign\UseCase\Admin\Barcode\MaterialVariationBarcode\MaterialVariationBarcodeDTO;
use BaksDev\Materials\Sign\UseCase\Admin\Barcode\MaterialVariationBarcode\MaterialVariationBarcodeHandler;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'baks:update:materials-barcode:sign-code',
    description: 'Обновляет штрихкоды сырья согласно GTIN честного знака'
)]
class UpdateMaterialsBarcodeBySignCodeCommand extends Command
{
    public function __construct(
        private readonly AllMaterialsIdentifierInterface $AllMaterialsIdentifierRepository,
        private readonly BarcodeByMaterialInterface $BarcodeByMaterialRepository,

        private readonly MaterialBarcodeHandler $MaterialBarcodeHandler,
        private readonly MaterialOfferBarcodeHandler $MaterialOfferBarcodeHandler,
        private readonly MaterialVariationBarcodeHandler $MaterialVariationBarcodeHandler,
        private readonly MaterialModificationBarcodeHandler $MaterialModificationBarcodeHandler
    )
    {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addArgument('argument', InputArgument::OPTIONAL, 'Описание аргумента');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        /** Получаем всё сырьё в системе  */
        $materials = $this->AllMaterialsIdentifierRepository->findAllResult();

        if(false === $materials->valid())
        {
            $io->warning('Сырья для обновления не найдено');
            return Command::INVALID;
        }


        foreach($materials as $MaterialsIdentifierResult)
        {
            /** Получаем один честный знак */
            $materialBarcode = $this->BarcodeByMaterialRepository
                ->forMaterial($MaterialsIdentifierResult->getMaterialId())
                ->forOfferConst($MaterialsIdentifierResult->getOfferConst())
                ->forVariationConst($MaterialsIdentifierResult->getVariationConst())
                ->forModificationConst($MaterialsIdentifierResult->getModificationConst())
                ->find();

            if(false === ($materialBarcode instanceof MaterialBarcode))
            {
                continue;
            }

            /**
             * Обновляем штрихкод модификации множественного варианта торгового предложения
             */
            if($MaterialsIdentifierResult->getModificationId() instanceof MaterialModificationUid)
            {
                $MaterialModificationBarcodeDTO = new MaterialModificationBarcodeDTO(
                    $MaterialsIdentifierResult->getModificationId(),
                    $materialBarcode,
                );

                $MaterialModification = $this->MaterialModificationBarcodeHandler
                    ->handle($MaterialModificationBarcodeDTO);

                if(false === ($MaterialModification instanceof MaterialModification))
                {
                    $io->error('Ошибка %s при обновлении штрихкода MaterialModification');
                    return Command::INVALID;
                }

                $io->writeln(sprintf('<fg=green>Обновили штрихкод MaterialModification %s</>', $materialBarcode));

                continue;
            }


            /**
             * Обновляем штрихкод множественного варианта торгового предложения
             */
            if($MaterialsIdentifierResult->getVariationId() instanceof MaterialVariationUid)
            {
                $MaterialVariationBarcodeDTO = new MaterialVariationBarcodeDTO(
                    $MaterialsIdentifierResult->getVariationId(),
                    $materialBarcode,
                );

                $MaterialVariation = $this->MaterialVariationBarcodeHandler->handle($MaterialVariationBarcodeDTO);

                if(false === ($MaterialVariation instanceof MaterialVariation))
                {
                    $io->error('Ошибка %s при обновлении штрихкода MaterialVariation');
                    return Command::INVALID;
                }

                $io->writeln(sprintf('<fg=green>Обновили штрихкод MaterialVariation %s</>', $materialBarcode));

                continue;
            }


            /**
             * Обновляем штрихкод торгового предложения
             */
            if($MaterialsIdentifierResult->getOfferId() instanceof MaterialOfferUid)
            {
                $MaterialOfferBarcodeDTO = new MaterialOfferBarcodeDTO(
                    $MaterialsIdentifierResult->getOfferId(),
                    $materialBarcode,
                );

                $MaterialOffer = $this->MaterialOfferBarcodeHandler->handle($MaterialOfferBarcodeDTO);

                if(false === ($MaterialOffer instanceof MaterialOffer))
                {
                    $io->error('Ошибка %s при обновлении штрихкода MaterialOffer');
                    return Command::INVALID;
                }

                $io->writeln(sprintf('<fg=green>Обновили штрихкод MaterialOffer %s</>', $materialBarcode));

                continue;
            }

            /**
             * Обновляем штрихкод сырья
             */

            $materialBarcodeDTO = new MaterialBarcodeDTO(
                $MaterialsIdentifierResult->getMaterialId(),
                $materialBarcode,
            );

            $MaterialInfo = $this->MaterialBarcodeHandler->handle($materialBarcodeDTO);

            if(false === ($MaterialInfo instanceof MaterialInfo))
            {
                $io->error('Ошибка %s при обновлении штрихкода MaterialInfo');
                return Command::INVALID;
            }

            $io->writeln(sprintf('<fg=green>Обновили штрихкод Material %s</>', $materialBarcode));
        }


        $io->success('baks:update:materials:barcode:by:sign:code');

        return Command::SUCCESS;
    }
}