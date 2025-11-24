<?php

namespace Ingenius\Discounts\Interfaces;

use Ingenius\Discounts\DataTransferObjects\DiscountContext;
use Ingenius\Discounts\DataTransferObjects\DiscountResult;
use Ingenius\Discounts\Models\DiscountCampaign;

/**
 * Interface for discount applicators
 * Each discount type implements this interface
 */
interface IDiscountApplicator
{
    /**
     * Apply the discount to the given context
     *
     * @param DiscountCampaign $campaign The discount campaign to apply
     * @param DiscountContext $context The context (cart or order data)
     * @return DiscountResult The result of applying the discount
     */
    public function apply(DiscountCampaign $campaign, DiscountContext $context): DiscountResult;

    /**
     * Check if this applicator can handle the given discount type
     *
     * @param string $discountType The discount type
     * @return bool
     */
    public function supports(string $discountType): bool;

    /**
     * Get the discount type this applicator handles
     *
     * @return string
     */
    public function getType(): string;
}
