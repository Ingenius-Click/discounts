<?php

namespace Ingenius\Discounts\Enums;

enum ConditionType: string
{
    case MIN_CART_VALUE = 'min_cart_value';
    case MIN_QUANTITY = 'min_quantity';
    case CUSTOMER_SEGMENT = 'customer_segment';
    case HAS_PRODUCT = 'has_product';
    case FIRST_ORDER = 'first_order';
    case DATE_RANGE = 'date_range';

    /**
     * Get the string value of the enum
     */
    public function toString(): string
    {
        return $this->value;
    }
}
