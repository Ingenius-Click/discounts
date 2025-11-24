<?php

namespace Ingenius\Discounts\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class DiscountCampaign extends Model
{
    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'code',
        'name',
        'description',
        'discount_type',
        'discount_value',
        'start_date',
        'end_date',
        'is_active',
        'priority',
        'is_stackable',
        'max_uses_total',
        'max_uses_per_customer',
        'current_uses',
        'metadata',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'discount_value' => 'integer',
        'start_date' => 'datetime',
        'end_date' => 'datetime',
        'is_active' => 'boolean',
        'priority' => 'integer',
        'is_stackable' => 'boolean',
        'max_uses_total' => 'integer',
        'max_uses_per_customer' => 'integer',
        'current_uses' => 'integer',
        'metadata' => 'array',
    ];

    /**
     * Get the conditions for this campaign
     */
    public function conditions(): HasMany
    {
        return $this->hasMany(DiscountCondition::class, 'campaign_id');
    }

    /**
     * Get the targets for this campaign
     */
    public function targets(): HasMany
    {
        return $this->hasMany(DiscountTarget::class, 'campaign_id');
    }

    /**
     * Get the usage records for this campaign
     */
    public function usages(): HasMany
    {
        return $this->hasMany(DiscountUsage::class, 'campaign_id');
    }

    /**
     * Scope a query to only include active campaigns
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true)
            ->where('start_date', '<=', now())
            ->where('end_date', '>=', now());
    }

    /**
     * Scope a query to filter by discount type
     */
    public function scopeOfType($query, string $type)
    {
        return $query->where('discount_type', $type);
    }

    /**
     * Scope a query to order by priority
     */
    public function scopeByPriority($query, string $direction = 'desc')
    {
        return $query->orderBy('priority', $direction);
    }

    /**
     * Check if campaign has reached its usage limit
     */
    public function hasReachedLimit(): bool
    {
        if ($this->max_uses_total === null) {
            return false;
        }

        return $this->current_uses >= $this->max_uses_total;
    }

    /**
     * Check if a customer has reached their usage limit
     */
    public function customerHasReachedLimit(?int $customerId): bool
    {
        if ($customerId === null || $this->max_uses_per_customer === null) {
            return false;
        }

        $customerUsage = $this->usages()
            ->where('customer_id', $customerId)
            ->count();

        return $customerUsage >= $this->max_uses_per_customer;
    }
}
