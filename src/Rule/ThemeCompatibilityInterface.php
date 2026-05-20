<?php

namespace GrowthOrchestrator\Rule;

interface ThemeCompatibilityInterface
{
    /**
     * @param int $userId the user id.
     * 
     * @return bool true if the user has completed the profile.
     */
    public function isProfileCompleted(int $userId): bool;

    /**
     * @param int $userId the user id.
     * 
     * @return bool true if the user has at least one product.
     */
    public function hasProduct(int $userId): bool;

    /**
     * @param int $userId the user id.
     * 
     * @return bool true if the user has a subscription.
     */
    public function hasSubscription(int $userId): bool;

    /**
     * @param int $userId the user id.
     * 
     * @return string the status of the subscription.
     */
    public function getSubscriptionStatus(int $userId): string;

    /**
     * @param int $userId the user id.
     * 
     * @return integer the calculated days number value to expiry.
     */
    public function getCalculatedDaysToExpiry(int $userId): int;
}
