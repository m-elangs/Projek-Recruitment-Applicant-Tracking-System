<?php

namespace App\Exceptions;

use App\Support\ApiResponse;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Exceptions\Handler as ExceptionHandler;
use Illuminate\Http\Exceptions\ThrottleRequestsException;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Throwable;

class Handler extends ExceptionHandler
{
    protected $levels = [];

    protected $dontReport = [
        BusinessRuleException::class,
    ];

    protected $dontFlash = [
        'current_password',
        'password',
        'password_confirmation',
    ];

    public function register(): void
    {
        $this->renderable(function (Throwable $e, Request $request) {
            if (! $request->is('api/*') && ! $request->expectsJson()) {
                return null;
            }

            if ($e instanceof BusinessRuleException) {
                return ApiResponse::error($e->errorCode, $e->getMessage(), $e->httpStatus, $e->details);
            }

            if ($e instanceof ValidationException) {
                return ApiResponse::error(
                    'VALIDATION_ERROR',
                    $e->getMessage(),
                    422,
                    $e->errors()
                );
            }

            if ($e instanceof AuthenticationException) {
                return ApiResponse::error('UNAUTHENTICATED', 'Authentication is required to access this resource.', 401);
            }

            if ($e instanceof AuthorizationException || $e instanceof AccessDeniedHttpException) {
                return ApiResponse::error(
                    'FORBIDDEN',
                    $e->getMessage() ?: 'You are not authorized to perform this action.',
                    403
                );
            }

            if ($e instanceof ModelNotFoundException || $e instanceof NotFoundHttpException) {
                return ApiResponse::error('RESOURCE_NOT_FOUND', 'The requested resource could not be found.', 404);
            }

            if ($e instanceof ThrottleRequestsException) {
                return ApiResponse::error('RATE_LIMITED', 'Too many requests. Please slow down and try again shortly.', 429);
            }

            if ($e instanceof HttpExceptionInterface) {
                return ApiResponse::error(
                    'HTTP_ERROR',
                    $e->getMessage() ?: 'Request could not be processed.',
                    $e->getStatusCode()
                );
            }

            return ApiResponse::error(
                'INTERNAL_SERVER_ERROR',
                config('app.debug') ? $e->getMessage() : 'An unexpected server error occurred.',
                500
            );
        });
    }
}
