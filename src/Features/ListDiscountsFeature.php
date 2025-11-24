<?php

namespace Ingenius\Discounts\Features;

use Ingenius\Core\Interfaces\FeatureInterface;

class ListDiscountsFeature implements FeatureInterface
{
    public function getIdentifier(): string
    {
        return 'list-discounts';
    }

    public function getName(): string
    {
        return __('List discounts');
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
