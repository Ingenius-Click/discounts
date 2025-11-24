<?php

namespace Ingenius\Discounts\Services;

use Ingenius\Discounts\Applicators\FixedAmountDiscountApplicator;
use Ingenius\Discounts\Applicators\PercentageDiscountApplicator;
use Ingenius\Discounts\Interfaces\IDiscountApplicator;

class DiscountApplicatorFactory
{
    /**
     * @var array<IDiscountApplicator>
     */
    protected array $applicators = [];

    public function __construct()
    {
        // Register default applicators
        $this->register(new PercentageDiscountApplicator());
        $this->register(new FixedAmountDiscountApplicator());
    }

    /**
     * Register a new applicator
     */
    public function register(IDiscountApplicator $applicator): void
    {
        $this->applicators[$applicator->getType()] = $applicator;
    }

    /**
     * Get applicator for a specific discount type
     *
     * @param string $discountType
     * @return IDiscountApplicator|null
     */
    public function getApplicator(string $discountType): ?IDiscountApplicator
    {
        return $this->applicators[$discountType] ?? null;
    }

    /**
     * Check if an applicator exists for the given type
     */
    public function hasApplicator(string $discountType): bool
    {
        return isset($this->applicators[$discountType]);
    }

    /**
     * Get all registered applicators
     *
     * @return array<IDiscountApplicator>
     */
    public function getAllApplicators(): array
    {
        return $this->applicators;
    }
}
