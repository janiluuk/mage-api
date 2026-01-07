<?php

declare(strict_types=1);

namespace App\Repositories\WalletType;

use App\Models\WalletType;
use Illuminate\Support\Collection;
use App\Repositories\BaseRepository;
use Illuminate\Support\Facades\Cache;

class WalletTypeRepository extends BaseRepository
{
    public function getAll(): Collection
    {
        // Cache wallet types for 1 hour as they rarely change
        return Cache::remember('wallet_types_all', 3600, function () {
            return WalletType::all();
        });
    }
}
