<?php

namespace Ingenius\Discounts\Enums;

enum DiscountType: string
{
    case PERCENTAGE = 'percentage';
    case FIXED_AMOUNT = 'fixed_amount';
    case BOGO = 'bogo'; // Buy One Get One

    /**
     * Get the string value of the enum
     */
    public function toString(): string
    {
        return $this->value;
    }
}
