<?php

namespace Ingenius\Discounts\Http\Controllers;

use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Response;
use Ingenius\Auth\Helpers\AuthHelper;
use Ingenius\Core\Http\Controllers\Controller;
use Ingenius\Discounts\Actions\PaginateDiscountCampaignsAction;
use Ingenius\Discounts\Actions\PatchDiscountCampaignAction;
use Ingenius\Discounts\Actions\StoreDiscountCampaignAction;
use Ingenius\Discounts\Actions\UpdateDiscountCampaignAction;
use Ingenius\Discounts\Http\Requests\PatchDiscountCampaignRequest;
use Ingenius\Discounts\Http\Requests\StoreDiscountCampaignRequest;
use Ingenius\Discounts\Http\Resources\DiscountCampaignEditingResource;
use Ingenius\Discounts\Http\Resources\DiscountCampaignResource;
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
            code: 201,
        );
    }

    public function show(DiscountCampaign $discountCampaign): JsonResponse
    {
        $user = AuthHelper::getUser();
        $this->authorizeForUser($user, 'view', $discountCampaign);

        // Load conditions and targets (but not targetable for shop cart since it's a service, not a model)
        $discountCampaign->load(['conditions', 'targets']);

        return Response::api(
            message: __('Discount campaign fetched successfully'),
            data: new DiscountCampaignResource($discountCampaign),
        );
    }

    public function edit(DiscountCampaign $discountCampaign): JsonResponse
    {
        $user = AuthHelper::getUser();
        $this->authorizeForUser($user, 'update', $discountCampaign);

        $discountCampaign->load(['conditions', 'targets']);

        return Response::api(
            message: __('Discount campaign fetched successfully for editing'),
            data: new DiscountCampaignEditingResource($discountCampaign),
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

    public function patch(PatchDiscountCampaignRequest $request, DiscountCampaign $discountCampaign, PatchDiscountCampaignAction $action): JsonResponse
    {
        $user = AuthHelper::getUser();
        $this->authorizeForUser($user, 'update', $discountCampaign);

        $campaign = $action->handle($discountCampaign, $request->validated());

        return Response::api(
            message: __('Discount campaign updated successfully'),
            data: new DiscountCampaignResource($campaign->load(['conditions', 'targets'])),
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
