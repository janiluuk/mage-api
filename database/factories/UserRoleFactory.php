<?php

namespace Database\Factories;

use App\Constant\UserRoleConstant;
use App\Models\UserRole;
use Illuminate\Database\Eloquent\Factories\Factory;

class UserRoleFactory extends Factory
{
    protected $model = UserRole::class;

    public function definition(): array
    {
        return [
            'type' => UserRoleConstant::REGISTERED,
        ];
    }

    public function administrator(): static
    {
        return $this->state(fn (array $attributes) => [
            'type' => UserRoleConstant::ADMINISTRATOR,
        ]);
    }

    public function superAdministrator(): static
    {
        return $this->state(fn (array $attributes) => [
            'type' => UserRoleConstant::SUPER_ADMINISTRATOR,
        ]);
    }
}
