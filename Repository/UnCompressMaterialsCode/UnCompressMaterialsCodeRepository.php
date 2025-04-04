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

namespace BaksDev\Materials\Sign\Repository\UnCompressMaterialsCode;

use BaksDev\Core\Doctrine\DBALQueryBuilder;
use BaksDev\Materials\Sign\Entity\Code\MaterialSignCode;
use BaksDev\Materials\Sign\Entity\MaterialSign;
use Generator;

final readonly class UnCompressMaterialsCodeRepository implements UnCompressMaterialsCodeInterface
{
    public function __construct(private DBALQueryBuilder $DBALQueryBuilder) {}

    /**
     * Метод возвращает идентификаторы изображений без CDN и его класс Entity
     */
    public function findAll(): Generator|false
    {
        $dbal = $this->DBALQueryBuilder->createQueryBuilder(self::class);

        $dbal
            ->addSelect('material_sign.id AS identifier')
            ->from(MaterialSign::class, 'material_sign');

        $dbal
            ->addSelect('material_sign_code.name')
            ->leftJoin(
                'material_sign',
                MaterialSignCode::class,
                'material_sign_code',
                'material_sign_code.main = material_sign.id AND material_sign_code.cdn IS FALSE'
            );

        $dbal->where('material_sign_code.name IS NOT NULL');

        return $dbal->fetchAllHydrate(UnCompressMaterialsCodeResult::class);
    }
}