<?php

namespace App\Http\Controllers\Api\V1\Auth;

use App\Exceptions\BusinessRuleException;
use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\ForgotPasswordRequest;
use App\Http\Requests\Auth\ResetPasswordRequest;
use App\Models\User;
use App\Services\AuditLogService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;

class PasswordResetController extends Controller
{
    public function __construct(
        private readonly AuditLogService $auditLog
    ) {}

    public function sendResetLink(ForgotPasswordRequest $request): JsonResponse
    {
        Password::broker()->sendResetLink($request->only('email'));

        return ApiResponse::success(
            null,
            'If an account exists for that email address, a password reset link has been sent.'
        );
    }

    public function reset(ResetPasswordRequest $request): JsonResponse
    {
        $status = Password::broker()->reset(
            $request->only('email', 'password', 'password_confirmation', 'token'),
            function (User $user, string $password) {
                $user->forceFill([
                    'password' => $password,
                    'remember_token' => Str::random(60),
                ])->save();

                $user->tokens()->delete();

                $this->auditLog->log(
                    eventType: 'auth.password_reset',
                    auditable: $user,
                    description: "Password reset completed for {$user->email}",
                    actor: $user
                );
            }
        );

        if ($status !== Password::PASSWORD_RESET) {
            throw new BusinessRuleException(
                'PASSWORD_RESET_FAILED',
                __($status),
                422
            );
        }

        return ApiResponse::success(null, 'Your password has been reset successfully.');
    }
}
