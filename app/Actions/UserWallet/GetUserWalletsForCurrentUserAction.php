<?php

declare(strict_types=1);

namespace App\Actions\UserWallet;

use Auth;
use App\Repositories\UserWallet\Criterion\UserIdCriterion;
use App\Repositories\UserWallet\UserWalletRepositoryInterface;
use Illuminate\Support\Facades\Cache;

final class GetUserWalletsForCurrentUserAction
{
    private UserWalletRepositoryInterface $userWalletRepositoryInterface;

    public function __construct(UserWalletRepositoryInterface $userWalletRepositoryInterface)
    {
        $this->userWalletRepositoryInterface = $userWalletRepositoryInterface;
    }

    public function execute(): GetUserWalletsForCurrentUserResponse
    {
        $userId = Auth::id();
        $cacheKey = "user_wallets_{$userId}";

        // Cache user wallet information for 5 minutes (300 seconds)
        // Shorter TTL than categories since wallet data changes more frequently
        $userWallets = Cache::remember($cacheKey, 300, function () use ($userId) {
            $criteria[] = new UserIdCriterion($userId);
            return $this->userWalletRepositoryInterface->findByCriteria($criteria);
        });

        return new GetUserWalletsForCurrentUserResponse($userWallets);
    }

    /**
     * Clear cache for a specific user's wallets
     * This is called by Add/Update/Delete actions to invalidate cache
     */
    public static function clearUserWalletCache(int $userId): void
    {
        Cache::forget("user_wallets_{$userId}");
    }
}
