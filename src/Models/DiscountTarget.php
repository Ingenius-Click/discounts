<?php

namespace Ingenius\Discounts\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class DiscountTarget extends Model
{
    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'campaign_id',
        'targetable_id',
        'targetable_type',
        'target_action',
        'metadata',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'metadata' => 'array',
    ];

    /**
     * Get the campaign that owns this target
     */
    public function campaign(): BelongsTo
    {
        return $this->belongsTo(DiscountCampaign::class, 'campaign_id');
    }

    /**
     * Get the parent targetable model (Product, Category, Shipment, etc.)
     */
    public function targetable(): MorphTo
    {
        return $this->morphTo();
    }
}
