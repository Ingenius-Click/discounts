<?php

namespace Ingenius\Discounts\Features;

use Ingenius\Core\Interfaces\FeatureInterface;

class CreateDiscountFeature implements FeatureInterface
{
    public function getIdentifier(): string
    {
        return 'create-discount';
    }

    public function getName(): string
    {
        return __('Create discount');
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
