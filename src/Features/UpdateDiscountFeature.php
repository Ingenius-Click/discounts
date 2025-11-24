<?php

namespace Ingenius\Discounts\Features;

use Ingenius\Core\Interfaces\FeatureInterface;

class UpdateDiscountFeature implements FeatureInterface
{
    public function getIdentifier(): string
    {
        return 'update-discount';
    }

    public function getName(): string
    {
        return __('Update discount');
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
