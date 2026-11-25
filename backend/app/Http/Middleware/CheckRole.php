<?php

namespace App\Http\Middleware;

use App\Support\ApiResponse;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class CheckRole
{
    public function handle(Request $request, Closure $next, string ...$roles): Response
    {
        $user = $request->user();

        if (! $user) {
            return ApiResponse::error('UNAUTHENTICATED', 'Authentication is required.', 401);
        }

        if (! $user->is_active) {
            return ApiResponse::error('ACCOUNT_DISABLED', 'Your account has been deactivated.', 403);
        }

        if (! $user->hasRole($roles)) {
            return ApiResponse::error(
                'FORBIDDEN',
                'Your role does not have access to this area.',
                403,
                ['allowed_roles' => $roles]
            );
        }

        return $next($request);
    }
}
