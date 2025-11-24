<?php

namespace Ingenius\Discounts\Features;

use Ingenius\Core\Interfaces\FeatureInterface;

class DeleteDiscountFeature implements FeatureInterface
{
    public function getIdentifier(): string
    {
        return 'delete-discount';
    }

    public function getName(): string
    {
        return __('Delete discount');
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
