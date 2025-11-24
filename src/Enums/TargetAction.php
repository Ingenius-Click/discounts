<?php

namespace Ingenius\Discounts\Enums;

enum TargetAction: string
{
    case APPLY_TO = 'apply_to';     // Discount applies to these items
    case REQUIRES = 'requires';     // Must have these items to use discount
    case EXCLUDES = 'excludes';     // Cannot have these items to use discount

    /**
     * Get the string value of the enum
     */
    public function toString(): string
    {
        return $this->value;
    }
}
