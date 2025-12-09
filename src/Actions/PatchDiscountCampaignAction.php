<?php

namespace Ingenius\Discounts\Actions;

use Exception;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Ingenius\Discounts\Models\DiscountCampaign;

class PatchDiscountCampaignAction
{
    public function handle(DiscountCampaign $campaign, array $data): DiscountCampaign
    {
        DB::beginTransaction();

        try {
            // Only update the fields that are present in the request
            $campaign->update(array_filter($data, function ($key) {
                return !in_array($key, ['conditions', 'targets']);
            }, ARRAY_FILTER_USE_KEY));

            DB::commit();
        } catch (Exception $e) {
            Log::error($e->getMessage());
            DB::rollBack();
            throw $e;
        }

        return $campaign->fresh();
    }
}
