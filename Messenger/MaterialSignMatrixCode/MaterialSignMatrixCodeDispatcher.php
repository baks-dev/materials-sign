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

namespace BaksDev\Materials\Sign\Messenger\MaterialSignMatrixCode;


use BaksDev\Core\Cache\AppCacheInterface;
use BaksDev\Materials\Sign\Repository\MaterialSignCode\MaterialSignCodeInterface;
use BaksDev\Materials\Sign\Repository\MaterialSignCode\MaterialSignCodeResult;
use DateInterval;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Contracts\Cache\ItemInterface;

#[AsMessageHandler(priority: 0)]
final class MaterialSignMatrixCodeDispatcher
{
    public function __construct(
        private readonly MaterialSignCodeInterface $MaterialSignCode,
        private readonly AppCacheInterface $cache,
        #[Autowire(env: 'CDN_HOST')] private readonly string $CDN_HOST,
    ) {}

    public function __invoke(MaterialSignMatrixCodeMessage $message): void
    {
        $MaterialSignCode = $this->MaterialSignCode
            ->forMaterialSign($message->getId())
            ->find();

        if(false === ($MaterialSignCode instanceof MaterialSignCodeResult))
        {
            return;
        }

        if(false === $MaterialSignCode->isCdn())
        {
            return;
        }

        $cache = $this->cache->init('files-res');
        $key = md5($MaterialSignCode->getName().'small'.$MaterialSignCode->getExt());

        $cache->get($key, function(ItemInterface $item) use ($MaterialSignCode): string {

            $item->expiresAfter(DateInterval::createFromDateString('1 hour'));
            $path = sprintf('https://%s%s/%s.%s', $this->CDN_HOST, $MaterialSignCode->getName(), 'small', $MaterialSignCode->getExt());
            $image = file_get_contents($path);

            return sprintf('data:image/%s;base64,%s', $MaterialSignCode->getExt(), base64_encode($image));
        });

    }
}
