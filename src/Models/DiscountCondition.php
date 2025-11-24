<?php

namespace Ingenius\Discounts\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DiscountCondition extends Model
{
    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'campaign_id',
        'condition_type',
        'operator',
        'value',
        'logic_operator',
        'priority',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'value' => 'array',
        'priority' => 'integer',
    ];

    /**
     * Get the campaign that owns this condition
     */
    public function campaign(): BelongsTo
    {
        return $this->belongsTo(DiscountCampaign::class, 'campaign_id');
    }
}
