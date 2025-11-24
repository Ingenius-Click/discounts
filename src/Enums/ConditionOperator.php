<?php

namespace Ingenius\Discounts\Enums;

enum ConditionOperator: string
{
    case GREATER_THAN_OR_EQUAL = '>=';
    case GREATER_THAN = '>';
    case LESS_THAN_OR_EQUAL = '<=';
    case LESS_THAN = '<';
    case EQUALS = '==';
    case NOT_EQUALS = '!=';
    case IN = 'in';
    case NOT_IN = 'not_in';

    /**
     * Get the string value of the enum
     */
    public function toString(): string
    {
        return $this->value;
    }
}
