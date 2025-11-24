<?php

namespace Ingenius\Discounts\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class DiscountUsage extends Model
{
    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'campaign_id',
        'customer_id',
        'orderable_id',
        'orderable_type',
        'discount_amount_applied',
        'used_at',
        'metadata',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'discount_amount_applied' => 'integer',
        'used_at' => 'datetime',
        'metadata' => 'array',
    ];

    /**
     * Get the campaign that this usage belongs to
     */
    public function campaign(): BelongsTo
    {
        return $this->belongsTo(DiscountCampaign::class, 'campaign_id');
    }

    /**
     * Get the parent orderable model (Order or any other orderable entity)
     */
    public function orderable(): MorphTo
    {
        return $this->morphTo();
    }
}
