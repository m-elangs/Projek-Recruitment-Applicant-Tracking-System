<?php

namespace App\Http\Middleware;

use App\Support\ApiResponse;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class CheckPermission
{
    public function handle(Request $request, Closure $next, string ...$permissions): Response
    {
        $user = $request->user();

        if (! $user) {
            return ApiResponse::error('UNAUTHENTICATED', 'Authentication is required.', 401);
        }

        if (! $user->is_active) {
            return ApiResponse::error('ACCOUNT_DISABLED', 'Your account has been deactivated.', 403);
        }

        foreach ($permissions as $permission) {
            if ($user->hasPermission($permission)) {
                return $next($request);
            }
        }

        return ApiResponse::error(
            'FORBIDDEN',
            'You do not have the required permission to perform this action.',
            403,
            ['required_permissions' => $permissions]
        );
    }
}
