<?php

namespace Database\Factories;

use App\Models\User;
use App\Modules\Workspace\Models\Workspace;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Workspace>
 */
class WorkspaceFactory extends Factory
{
    protected $model = Workspace::class;

    public function definition(): array
    {
        $name = fake()->company();

        return [
            'name'        => $name,
            'slug'        => Str::slug($name).'-'.Str::random(4),
            'owner_id'    => User::factory(),
            'marketplace' => 'IN',
            'currency'    => 'INR',
            'settings'    => [],
        ];
    }
}
