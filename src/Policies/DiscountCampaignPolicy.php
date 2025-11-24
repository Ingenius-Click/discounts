<?php

namespace Ingenius\Discounts\Policies;

use Ingenius\Discounts\Constants\DiscountPermissions;
use Ingenius\Discounts\Models\DiscountCampaign;

class DiscountCampaignPolicy
{
    public function viewAny($user)
    {
        $userClass = tenant_user_class();

        if ($user && is_object($user) && is_a($user, $userClass)) {
            return $user->can(DiscountPermissions::DISCOUNTS_VIEW);
        }

        return false;
    }

    public function view($user, DiscountCampaign $discountCampaign)
    {
        $userClass = tenant_user_class();

        if ($user && is_object($user) && is_a($user, $userClass)) {
            return $user->can(DiscountPermissions::DISCOUNTS_VIEW);
        }

        return false;
    }

    public function create($user)
    {
        $userClass = tenant_user_class();

        if ($user && is_object($user) && is_a($user, $userClass)) {
            return $user->can(DiscountPermissions::DISCOUNTS_CREATE);
        }

        return false;
    }

    public function update($user, DiscountCampaign $discountCampaign)
    {
        $userClass = tenant_user_class();

        if ($user && is_object($user) && is_a($user, $userClass)) {
            return $user->can(DiscountPermissions::DISCOUNTS_EDIT);
        }

        return false;
    }

    public function delete($user, DiscountCampaign $discountCampaign)
    {
        $userClass = tenant_user_class();

        if ($user && is_object($user) && is_a($user, $userClass)) {
            return $user->can(DiscountPermissions::DISCOUNTS_DELETE);
        }

        return false;
    }
}
