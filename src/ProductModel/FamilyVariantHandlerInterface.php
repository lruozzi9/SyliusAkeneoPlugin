<?php

declare(strict_types=1);

namespace Webgriffe\SyliusAkeneoPlugin\ProductModel;

use Sylius\Component\Core\Model\ProductInterface;

interface FamilyVariantHandlerInterface
{
    public function handle(ProductInterface $product, array $familyVariant);
}
