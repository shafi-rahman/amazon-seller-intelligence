<?php

namespace Database\Seeders;

use App\Models\User;
use App\Modules\Workspace\Models\Workspace;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->call(RolePermissionSeeder::class);

        if (app()->isProduction()) {
            return;
        }

        $users = [
            ['name' => 'Platform Admin',  'email' => 'admin@asip.local',      'role' => 'platform_admin'],
            ['name' => 'Demo Seller',     'email' => 'seller@asip.local',     'role' => 'seller'],
            ['name' => 'Accountant User', 'email' => 'accountant@asip.local', 'role' => 'accountant'],
            ['name' => 'Agency User',     'email' => 'agency@asip.local',     'role' => 'agency'],
        ];

        foreach ($users as $userData) {
            $user = User::firstOrCreate(
                ['email' => $userData['email']],
                [
                    'name'     => $userData['name'],
                    'password' => Hash::make('password'),
                    'role'     => $userData['role'],
                ]
            );
            $user->syncRoles([$userData['role']]);

            if ($userData['role'] === 'seller') {
                $workspace = Workspace::firstOrCreate(
                    ['owner_id' => $user->id],
                    [
                        'name'        => 'Demo Store',
                        'slug'        => 'demo-store',
                        'marketplace' => 'IN',
                        'currency'    => 'INR',
                    ]
                );
                $workspace->members()->syncWithoutDetaching([$user->id => ['role' => 'owner']]);
            }
        }
    }
}
