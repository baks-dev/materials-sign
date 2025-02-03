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

namespace BaksDev\Materials\Sign\Repository\MaterialSignByOrder;

use BaksDev\Materials\Catalog\Entity\Material;
use BaksDev\Materials\Catalog\Type\Id\MaterialUid;
use BaksDev\Materials\Catalog\Type\Offers\ConstId\MaterialOfferConst;
use BaksDev\Materials\Catalog\Type\Offers\Variation\ConstId\MaterialVariationConst;
use BaksDev\Materials\Catalog\Type\Offers\Variation\Modification\ConstId\MaterialModificationConst;
use BaksDev\Orders\Order\Entity\Order;
use BaksDev\Orders\Order\Type\Id\OrderUid;
use BaksDev\Users\Profile\UserProfile\Entity\UserProfile;
use BaksDev\Users\Profile\UserProfile\Type\Id\UserProfileUid;

interface MaterialSignByOrderInterface
{
    /** Фильтр по сырью */

    public function material(Material|MaterialUid|string $material): self;

    public function offer(MaterialOfferConst|string|null|false $offer): self;

    public function variation(MaterialVariationConst|string|null|false $variation): self;

    public function modification(MaterialModificationConst|string|null|false $modification): self;

    /** Фильтр по заказу */

    public function profile(UserProfile|UserProfileUid|string $profile): self;

    public function forOrder(Order|OrderUid|string $order): self;

    /**
     * Возвращает знаки со статусом Done «Выполнен»
     */
    public function withStatusDone(): self;

    /**
     * Метод возвращает все штрихкоды «Честный знак» для печати по идентификатору заказа
     * По умолчанию возвращает знаки со статусом Process «В процессе»
     */
    public function findAll(): array|false;
}
