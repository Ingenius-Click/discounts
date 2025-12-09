<?php

namespace Ingenius\Discounts\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Ingenius\Discounts\Enums\TargetType;

class DiscountCampaignEditingResource extends JsonResource {

    public function toArray(Request $request): array {

        return [
            'id' => $this->id,
            'code' => $this->code,
            'name' => $this->name,
            'description' => $this->description,
            'discount_type' => $this->discount_type,
            'discount_value' => $this->discount_value,
            'start_date' => $this->start_date,
            'end_date' => $this->end_date,
            'is_active' => $this->is_active,
            'priority' => $this->priority,
            'is_stackable' => $this->is_stackable,
            'max_uses_total' => $this->max_uses_total,
            'max_uses_per_customer' => $this->max_uses_per_customer,
            'current_uses' => $this->current_uses,
            'metadata' => $this->metadata,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,

            'targets' => $this->when($this->relationLoaded('targets'), function () {
                return $this->targets->map(function ($target) {
                    return [
                        'id' => $target->id,
                        'target_action' => $target->target_action,
                        'targetable_type' => TargetType::fromNamespace($target->targetable_type)?->toString(),
                        'targetable_id' => $target->targetable_id,
                        'metadata' => $target->metadata,
                    ];
                });
            }),

            'conditions' => $this->conditions
        ];

    }

}