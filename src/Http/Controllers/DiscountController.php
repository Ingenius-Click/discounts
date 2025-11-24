<?php

namespace Ingenius\Discounts\Http\Controllers;

use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Response;
use Ingenius\Auth\Helpers\AuthHelper;
use Ingenius\Core\Http\Controllers\Controller;
use Ingenius\Discounts\Actions\PaginateDiscountCampaignsAction;
use Ingenius\Discounts\Actions\StoreDiscountCampaignAction;
use Ingenius\Discounts\Actions\UpdateDiscountCampaignAction;
use Ingenius\Discounts\Http\Requests\StoreDiscountCampaignRequest;
use Ingenius\Discounts\Models\DiscountCampaign;

class DiscountController extends Controller
{
    use AuthorizesRequests;

    public function index(Request $request, PaginateDiscountCampaignsAction $action): JsonResponse
    {
        $user = AuthHelper::getUser();
        $this->authorizeForUser($user, 'viewAny', DiscountCampaign::class);

        return Response::api(
            message: __('Discount campaigns fetched successfully'),
            data: $action->handle($request->all()),
        );
    }

    public function store(StoreDiscountCampaignRequest $request, StoreDiscountCampaignAction $action): JsonResponse
    {
        $user = AuthHelper::getUser();
        $this->authorizeForUser($user, 'create', DiscountCampaign::class);

        $campaign = $action->handle($request->validated());

        return Response::api(
            message: __('Discount campaign created successfully'),
            data: $campaign,
            status: 201,
        );
    }

    public function show(DiscountCampaign $discountCampaign): JsonResponse
    {
        $user = AuthHelper::getUser();
        $this->authorizeForUser($user, 'view', $discountCampaign);

        return Response::api(
            message: __('Discount campaign fetched successfully'),
            data: $discountCampaign->load(['conditions', 'targets']),
        );
    }

    public function update(StoreDiscountCampaignRequest $request, DiscountCampaign $discountCampaign, UpdateDiscountCampaignAction $action): JsonResponse
    {
        $user = AuthHelper::getUser();
        $this->authorizeForUser($user, 'update', $discountCampaign);

        $campaign = $action->handle($discountCampaign, $request->validated());

        return Response::api(
            message: __('Discount campaign updated successfully'),
            data: $campaign,
        );
    }

    public function destroy(DiscountCampaign $discountCampaign): JsonResponse
    {
        $user = AuthHelper::getUser();
        $this->authorizeForUser($user, 'delete', $discountCampaign);

        $discountCampaign->delete();

        return Response::api(
            message: __('Discount campaign deleted successfully'),
        );
    }
}
