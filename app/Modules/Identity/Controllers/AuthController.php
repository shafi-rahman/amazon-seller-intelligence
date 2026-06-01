<?php

namespace App\Modules\Identity\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Identity\Requests\ForgotPasswordRequest;
use App\Modules\Identity\Requests\LoginRequest;
use App\Modules\Identity\Requests\RegisterRequest;
use App\Modules\Identity\Requests\ResetPasswordRequest;
use App\Modules\Identity\Resources\UserResource;
use App\Modules\Identity\Services\AuthService;
use App\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AuthController extends Controller
{
    use ApiResponse;

    public function __construct(private readonly AuthService $authService) {}

    public function register(RegisterRequest $request): JsonResponse
    {
        $result = $this->authService->register($request->validated());

        return $this->created([
            'user'      => new UserResource($result['user']),
            'workspace' => [
                'id'   => $result['workspace']->id,
                'name' => $result['workspace']->name,
                'slug' => $result['workspace']->slug,
            ],
        ]);
    }

    public function login(LoginRequest $request): JsonResponse
    {
        $user = $this->authService->login(
            $request->validated('email'),
            $request->validated('password'),
        );

        return $this->success(['user' => new UserResource($user)]);
    }

    public function logout(): JsonResponse
    {
        $this->authService->logout();

        return $this->noContent();
    }

    public function me(Request $request): JsonResponse
    {
        $user = $request->user()->load('workspaces');

        return $this->success(new UserResource($user));
    }

    public function forgotPassword(ForgotPasswordRequest $request): JsonResponse
    {
        $this->authService->sendPasswordReset($request->validated('email'));

        return $this->success(['message' => 'Password reset link sent to your email.']);
    }

    public function resetPassword(ResetPasswordRequest $request): JsonResponse
    {
        $this->authService->resetPassword($request->validated());

        return $this->success(['message' => 'Password reset successfully.']);
    }
}
