<?php

namespace Ingenius\Discounts\Actions;

use Exception;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Ingenius\Discounts\Models\DiscountCampaign;
use Ingenius\Discounts\Enums\TargetType;

class StoreDiscountCampaignAction {

    public function handle(array $data): DiscountCampaign {

        DB::beginTransaction();

        try {
            // Set default priority if not provided
            if (!isset($data['priority'])) {
                $data['priority'] = 10;
            }

            $campaign = DiscountCampaign::create($data);

            if(isset($data['conditions']) && is_array($data['conditions'])) {
                foreach($data['conditions'] as $conditionData) {
                    // Set default priority for conditions if not provided
                    if (!isset($conditionData['priority'])) {
                        $conditionData['priority'] = 10;
                    }
                    $campaign->conditions()->create($conditionData);
                }
            }

            if(isset($data['targets']) && is_array($data['targets'])) {
                foreach($data['targets'] as $targetData) {
                    // Transform string type to namespace
                    if (isset($targetData['targetable_type'])) {
                        $targetType = TargetType::tryFrom($targetData['targetable_type']);
                        if ($targetType) {
                            $targetData['targetable_type'] = $targetType->getNamespace();
                        }
                    }
                    $campaign->targets()->create($targetData);
                }
            }

            DB::commit();
        } catch (Exception $e) {
            Log::error($e->getMessage());
            DB::rollBack();
            throw $e;
        }

        return $campaign->fresh();
    }

}