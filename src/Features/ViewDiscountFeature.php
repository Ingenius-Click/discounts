<?php

namespace Ingenius\Discounts\Features;

use Ingenius\Core\Interfaces\FeatureInterface;

class ViewDiscountFeature implements FeatureInterface
{
    public function getIdentifier(): string
    {
        return 'view-discount';
    }

    public function getName(): string
    {
        return __('View discount');
    }

    public function getGroup(): string
    {
        return __('Discounts');
    }

    public function getPackage(): string
    {
        return 'discounts';
    }

    public function isBasic(): bool
    {
        return true;
    }
}
