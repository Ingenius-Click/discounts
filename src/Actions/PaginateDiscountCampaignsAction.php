<?php

namespace Ingenius\Discounts\Actions;

use Illuminate\Pagination\LengthAwarePaginator;
use Ingenius\Discounts\Models\DiscountCampaign;

class PaginateDiscountCampaignsAction {

    public function handle(array $filters = []): LengthAwarePaginator {

        $query = DiscountCampaign::query();

        if(!isset($filters['sorts'])) {
            $query->latest();
        }

        return table_handler_paginate($filters, $query);
    }

}