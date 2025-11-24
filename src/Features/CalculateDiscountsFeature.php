<?php

namespace Ingenius\Discounts\Features;

use Ingenius\Core\Interfaces\FeatureInterface;

class CalculateDiscountsFeature implements FeatureInterface
{
    public function getIdentifier(): string
    {
        return 'calculate-discounts';
    }

    public function getName(): string
    {
        return __('Calculate discounts');
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
