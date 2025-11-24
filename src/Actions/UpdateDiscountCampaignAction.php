<?php

namespace Ingenius\Discounts\Actions;

use Exception;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Ingenius\Discounts\Models\DiscountCampaign;

class UpdateDiscountCampaignAction
{
    public function handle(DiscountCampaign $campaign, array $data): DiscountCampaign
    {
        DB::beginTransaction();

        try {
            $campaign->update($data);

            if (isset($data['conditions']) && is_array($data['conditions'])) {
                $campaign->conditions()->delete();
                foreach ($data['conditions'] as $conditionData) {
                    $campaign->conditions()->create($conditionData);
                }
            }

            if (isset($data['targets']) && is_array($data['targets'])) {
                $campaign->targets()->delete();
                foreach ($data['targets'] as $targetData) {
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
