<?php

namespace App\Http\Controllers\Api\V1\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\LoginRequest;
use App\Http\Requests\Auth\RegisterCandidateRequest;
use App\Http\Requests\Auth\UpdateOwnProfileRequest;
use App\Http\Resources\UserResource;
use App\Services\AuthService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AuthController extends Controller
{
    public function __construct(
        private readonly AuthService $authService
    ) {}

    public function register(RegisterCandidateRequest $request): JsonResponse
    {
        $result = $this->authService->registerCandidate($request->validated(), $request);

        return ApiResponse::created([
            'user' => new UserResource($result['user']),
            'token' => $result['token'],
            'redirect_to' => $result['user']->defaultRedirectPath(),
        ], 'Candidate account registered successfully.');
    }

    public function login(LoginRequest $request): JsonResponse
    {
        $result = $this->authService->login($request->validated(), $request);

        return ApiResponse::success([
            'user' => new UserResource($result['user']),
            'token' => $result['token'],
            'redirect_to' => $result['user']->defaultRedirectPath(),
        ], 'Signed in successfully.');
    }

    public function logout(Request $request): JsonResponse
    {
        /** @var \App\Models\User $user */
        $user = $request->user();
        $this->authService->logout($user, $request);

        return ApiResponse::success(null, 'Signed out successfully.');
    }

    public function me(Request $request): JsonResponse
    {
        /** @var \App\Models\User $user */
        $user = $request->user()->load(['role.permissions', 'department', 'candidate']);

        return ApiResponse::success(new UserResource($user), 'Authenticated user retrieved.');
    }

    public function updateProfile(UpdateOwnProfileRequest $request): JsonResponse
    {
        /** @var \App\Models\User $user */
        $user = $request->user();
        $updated = $this->authService->updateOwnProfile($user, $request->validated());

        return ApiResponse::success(new UserResource($updated), 'Profile updated successfully.');
    }
}
