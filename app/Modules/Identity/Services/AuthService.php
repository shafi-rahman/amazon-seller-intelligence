<?php

namespace App\Modules\Identity\Services;

use App\Models\AuditLog;
use App\Models\User;
use App\Modules\Workspace\Models\Workspace;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class AuthService
{
    public function register(array $data): array
    {
        $user = User::create([
            'name'     => $data['name'],
            'email'    => $data['email'],
            'password' => $data['password'],
            'role'     => 'seller',
        ]);

        $user->assignRole('seller');

        $workspace = Workspace::create([
            'name'       => $data['workspace_name'] ?? $data['name']."'s Workspace",
            'slug'       => Str::slug($data['workspace_name'] ?? $data['name'].'-workspace').'-'.Str::random(4),
            'owner_id'   => $user->id,
            'marketplace'=> $data['marketplace'] ?? 'IN',
            'currency'   => $data['currency'] ?? 'INR',
        ]);

        $workspace->members()->attach($user->id, ['role' => 'owner']);

        AuditLog::create([
            'user_id'    => $user->id,
            'action'     => 'auth.register',
            'entity_type'=> User::class,
            'entity_id'  => $user->id,
            'new_values' => ['email' => $user->email, 'role' => $user->role],
            'ip_address' => request()->ip(),
            'user_agent' => request()->userAgent(),
        ]);

        return compact('user', 'workspace');
    }

    public function login(string $email, string $password): User
    {
        if (!Auth::attempt(['email' => $email, 'password' => $password])) {
            // 422 with a clean JSON body (Laravel's standard failed-login shape).
            // AuthenticationException here would 500 — its handler tries to redirect
            // to a non-existent 'login' route.
            throw ValidationException::withMessages([
                'email' => [__('auth.failed')],
            ]);
        }

        $user = Auth::user();

        if (request()->hasSession()) {
            request()->session()->regenerate();
        }

        AuditLog::create([
            'user_id'   => $user->id,
            'action'    => 'auth.login',
            'ip_address'=> request()->ip(),
            'user_agent'=> request()->userAgent(),
        ]);

        return $user;
    }

    public function logout(): void
    {
        $userId = Auth::id();

        Auth::guard('web')->logout();

        if (request()->hasSession()) {
            request()->session()->invalidate();
            request()->session()->regenerateToken();
        }

        AuditLog::create([
            'user_id'   => $userId,
            'action'    => 'auth.logout',
            'ip_address'=> request()->ip(),
            'user_agent'=> request()->userAgent(),
        ]);
    }

    public function sendPasswordReset(string $email): void
    {
        Password::sendResetLink(['email' => $email]);
    }

    public function resetPassword(array $data): void
    {
        $status = Password::reset(
            $data,
            function (User $user, string $password) {
                $user->forceFill(['password' => Hash::make($password)])->save();
            }
        );

        if ($status !== Password::PASSWORD_RESET) {
            throw new \RuntimeException(__($status));
        }
    }
}
