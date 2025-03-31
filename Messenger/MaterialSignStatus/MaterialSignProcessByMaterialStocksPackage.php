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

namespace BaksDev\Materials\Sign\Messenger\MaterialSignStatus;


use BaksDev\Core\Deduplicator\DeduplicatorInterface;
use BaksDev\Core\Messenger\MessageDispatchInterface;
use BaksDev\Materials\Catalog\Repository\CurrentMaterialIdentifier\CurrentIdentifierMaterialByValueInterface;
use BaksDev\Materials\Sign\Entity\MaterialSign;
use BaksDev\Materials\Sign\Messenger\MaterialSignMatrixCode\MaterialSignMatrixCodeMessage;
use BaksDev\Materials\Sign\Repository\MaterialSignNew\MaterialSignNewInterface;
use BaksDev\Materials\Sign\Type\Id\MaterialSignUid;
use BaksDev\Materials\Sign\UseCase\Admin\Status\MaterialSignProcessDTO;
use BaksDev\Materials\Sign\UseCase\Admin\Status\MaterialSignStatusHandler;
use BaksDev\Orders\Order\Type\Id\OrderUid;
use BaksDev\Products\Product\Repository\CurrentProductIdentifier\CurrentProductIdentifierByConstInterface;
use BaksDev\Products\Product\Repository\ProductMaterials\ProductMaterialsInterface;
use BaksDev\Products\Product\Type\Material\MaterialUid;
use BaksDev\Products\Stocks\Entity\Stock\Event\ProductStockEvent;
use BaksDev\Products\Stocks\Entity\Stock\Event\ProductStockEventInterface;
use BaksDev\Products\Stocks\Entity\Stock\Products\ProductStockProduct;
use BaksDev\Products\Stocks\Messenger\ProductStockMessage;
use BaksDev\Products\Stocks\Repository\CurrentProductStocks\CurrentProductStocksInterface;
use BaksDev\Products\Stocks\Repository\ProductStocksById\ProductStocksByIdInterface;
use BaksDev\Products\Stocks\Repository\ProductStocksEvent\ProductStocksEventInterface;
use BaksDev\Products\Stocks\Type\Status\ProductStockStatus\ProductStockStatusPackage;
use BaksDev\Users\Profile\UserProfile\Repository\UserByUserProfile\UserByUserProfileInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\Attribute\Target;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

/**
 * При статусе складской заявки Package «Упаковка» - резервируем честный знак в статус Process «В процессе»
 */
#[AsMessageHandler(priority: -5)]
final readonly class MaterialSignProcessByMaterialStocksPackage
{
    public function __construct(
        #[Target('materialsSignLogger')] private LoggerInterface $logger,
        private ProductStocksByIdInterface $ProductStocks,

        private ProductStocksEventInterface $ProductStocksEventRepository,
        private CurrentProductStocksInterface $CurrentProductStocks,


        private UserByUserProfileInterface $userByUserProfile,
        private DeduplicatorInterface $deduplicator,
        private CurrentProductIdentifierByConstInterface $CurrentProductIdentifierByConst,
        private ProductMaterialsInterface $ProductMaterials,
        private CurrentIdentifierMaterialByValueInterface $CurrentIdentifierMaterialByValue,
        private MaterialSignNewInterface $MaterialSignNew,
        private MaterialSignStatusHandler $MaterialSignStatusHandler,
        private MessageDispatchInterface $messageDispatch
    ) {}

    public function __invoke(ProductStockMessage $message): void
    {
        $Deduplicator = $this->deduplicator
            ->namespace('materials-sign')
            ->deduplication([
                $message,
                self::class
            ]);

        if($Deduplicator->isExecuted())
        {
            return;
        }

        $ProductStockEvent = $this->ProductStocksEventRepository
            ->forEvent($message->getEvent())
            ->find();


        if(false === ($ProductStockEvent instanceof ProductStockEvent))
        {
            $this->logger->critical('products-sign: Не найдено событие ProductStock', [$message, self::class.':'.__LINE__]);


            return;
        }

        if(false === $ProductStockEvent->equalsProductStockStatus(ProductStockStatusPackage::class))
        {
            $this->logger->notice(
                'Не резервируем честный знак: Складская заявка не является Package «Упаковка»',
                [$message, self::class.':'.__LINE__]
            );

            return;
        }

        if(false === ($ProductStockEvent->getOrder() instanceof OrderUid))
        {
            $this->logger->notice(
                'Не резервируем честный знак: упаковка без идентификатора заказа',
                [$message, self::class.':'.__LINE__]
            );

            return;
        }

        /**
         * Определяем пользователя профилю в заявке
         */

        $CurrentProductStockEvent = $this->CurrentProductStocks
            ->getCurrentEvent($message->getId());

        if(false === ($CurrentProductStockEvent instanceof ProductStockEvent))
        {
            return;
        }

        $UserProfileUid = $CurrentProductStockEvent->getStocksProfile();

        $User = $this
            ->userByUserProfile
            ->forProfile($UserProfileUid)
            ->find();

        if(false === $User)
        {
            $this->logger
                ->critical(
                    sprintf(
                        'products-sign: Невозможно зарезервировать «Честный знак»! Пользователь профиля %s не найден ',
                        $UserProfileUid
                    ),
                    [$message, self::class.':'.__LINE__]
                );

            return;
        }


        // Получаем всю продукцию в ордере со статусом Package (УПАКОВКА)
        $products = $this->ProductStocks->getProductsPackageStocks($message->getId());

        if(empty($products))
        {
            $this->logger->warning(
                'Заявка на упаковку не имеет продукции в коллекции',
                [$message, self::class.':'.__LINE__]
            );

            return;
        }

        $this->logger->info('Добавляем резерв кода Честный знак статус Process «В процессе»:');

        /**
         * Резервируем честный знак Process «В процессе»
         *
         * @var ProductStockProduct $product
         */


        // Идентификатор группы честных знаков
        $MaterialSignUid = new MaterialSignUid();

        foreach($products as $product)
        {

            /** Получаем идентификаторы продукции */
            $CurrentProductIdentifier = $this->CurrentProductIdentifierByConst
                ->forProduct($product->getProduct())
                ->forOfferConst($product->getOffer())
                ->forVariationConst($product->getVariation())
                ->forModificationConst($product->getModification())
                ->find();


            if(false === $CurrentProductIdentifier)
            {
                continue;
            }

            /** Получаем список материалов продукции */

            $ProductMaterials = $this->ProductMaterials
                ->forEvent($CurrentProductIdentifier->getEvent())
                ->findAll();

            if(false === ($ProductMaterials || $ProductMaterials->valid()))
            {
                continue;
            }

            /** @var MaterialUid $ProductMaterial */
            foreach($ProductMaterials as $ProductMaterial)
            {
                /** Получаем материал согласно торговому предложению (value) */
                $CurrentMaterialDTO = $this->CurrentIdentifierMaterialByValue
                    ->forMaterial($ProductMaterial)
                    ->forOfferValue($CurrentProductIdentifier->getOfferValue())
                    ->forVariationValue($CurrentProductIdentifier->getVariationValue())
                    ->forModificationValue($CurrentProductIdentifier->getModificationValue())
                    ->find();

                if(false === $CurrentMaterialDTO)
                {
                    continue;
                }

                $total = $product->getTotal();

                for($i = 1; $i <= $total; $i++)
                {
                    $MaterialSignEvent = $this->MaterialSignNew
                        ->forUser($User)
                        ->forProfile($UserProfileUid)
                        ->forMaterial($CurrentMaterialDTO->getMaterial())
                        ->forOfferConst($CurrentMaterialDTO->getOfferConst())
                        ->forVariationConst($CurrentMaterialDTO->getVariationConst())
                        ->forModificationConst($CurrentMaterialDTO->getModificationConst())
                        ->getOneMaterialSign();

                    if(false === $MaterialSignEvent)
                    {
                        $this->logger->warning(
                            'Честный знак на сырьё не найдено',
                            [$ProductStockEvent, $CurrentMaterialDTO, self::class.':'.__LINE__]
                        );

                        break;
                    }

                    $MaterialSignProcessDTO = new MaterialSignProcessDTO(
                        $UserProfileUid,
                        $ProductStockEvent->getOrder()
                    );
                    $ProductSignInvariableDTO = $MaterialSignProcessDTO->getInvariable();
                    $ProductSignInvariableDTO->setPart($MaterialSignUid);

                    $MaterialSignEvent->getDto($MaterialSignProcessDTO);

                    $MaterialSign = $this->MaterialSignStatusHandler->handle($MaterialSignProcessDTO);

                    if(false === ($MaterialSign instanceof MaterialSign))
                    {
                        $this->logger->critical(
                            sprintf('%s: Ошибка при обновлении статуса честного знака на сырье', $MaterialSign),
                            [$MaterialSignProcessDTO, self::class.':'.__LINE__]
                        );

                        continue 2;
                    }

                    $this->logger->info(
                        'Отметили Честный знак Process «В процессе» на сырье',
                        [$MaterialSignEvent, self::class.':'.__LINE__]
                    );

                    /** Прогреваем кеш стикеров честных знаков заказа */
                    $MaterialSignMatrixCodeMessage = new MaterialSignMatrixCodeMessage($MaterialSign);

                    $this->messageDispatch->dispatch(
                        message: $MaterialSignMatrixCodeMessage,
                        transport: 'files-res'
                    );

                }
            }
        }

        $Deduplicator->save();
    }
}
