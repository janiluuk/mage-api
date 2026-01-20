<?php

namespace App\Exceptions;

use Throwable;
use Illuminate\Http\JsonResponse;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\Response;
use Illuminate\Foundation\Exceptions\Handler as ExceptionHandler;

class Handler extends ExceptionHandler
{
    /**
     * A list of the exception types that are not reported.
     *
     * @var array
     */
    protected $dontReport = [

    ];

    /**
     * A list of the inputs that are never flashed for validation exceptions.
     *
     * @var array
     */
    protected $dontFlash = [
        'current_password',
        'password',
        'password_confirmation',
    ];

    /**
     * Register the exception handling callbacks for the application.
     *
     * @return void
     */
    public function register()
    {
	  $this->reportable(function (Throwable $e) {
    		if (app()->bound('sentry')) {
		      app('sentry')->captureException($e);
	        }
	  });
    }

    public function render($request, Throwable $exception): Response
    {
        if ($exception instanceof BaseException) {
            // Map custom error codes to HTTP status codes
            $httpStatusCode = $this->mapErrorCodeToHttpStatus($exception->getCode());
            
            // Check if this is an API request (by path or Accept header)
            $isApiRequest = $request->expectsJson() || $request->is('api/*');
            
            // For API requests, return JSON response
            if ($isApiRequest) {
                return new JsonResponse(
                    [
                        'error' => [
                            'message' => $exception->getMessage(),
                            'code' => $exception->getCode(),
                        ],
                    ],
                    $httpStatusCode
                );
            }
            
            // For web requests, let the parent handle it (will redirect to login if needed)
            // But we need to convert BaseException to something Laravel understands
            if ($httpStatusCode === JsonResponse::HTTP_UNAUTHORIZED) {
                // Convert to AuthenticationException for proper handling
                return parent::render($request, new AuthenticationException($exception->getMessage()));
            }
            
            // For other HTTP codes, use abort
            abort($httpStatusCode, $exception->getMessage());
        }

        return parent::render($request, $exception);
    }

    /**
     * Map custom error codes to HTTP status codes
     */
    protected function mapErrorCodeToHttpStatus(int $code): int
    {
        // If it's already a valid HTTP status code, use it
        if ($code >= 100 && $code <= 599) {
            return $code;
        }

        // Map custom error codes to HTTP status codes
        return match ($code) {
            ErrorCode::USER_NOT_AUTHORIZED_EXCEPTION => JsonResponse::HTTP_UNAUTHORIZED, // 401
            ErrorCode::USER_IS_NOT_ADMINISTRATOR => JsonResponse::HTTP_FORBIDDEN, // 403
            ErrorCode::INVALID_OR_EXPIRED_TOKEN => JsonResponse::HTTP_UNAUTHORIZED, // 401
            ErrorCode::TOKEN_EXPIRED => JsonResponse::HTTP_UNAUTHORIZED, // 401
            ErrorCode::ORDER_NOT_BELONG_CURRENT_USER => JsonResponse::HTTP_FORBIDDEN, // 403
            ErrorCode::WALLET_NOT_BELONG_TO_CURRENT_USER => JsonResponse::HTTP_FORBIDDEN, // 403
            ErrorCode::USER_ALREADY_VERIFIED_EXCEPTION => JsonResponse::HTTP_CONFLICT, // 409
            ErrorCode::CHAT_ALREADY_CREATED_EXCEPTION => JsonResponse::HTTP_CONFLICT, // 409
            ErrorCode::EMAIL_TOKEN_NOT_MATCH => JsonResponse::HTTP_BAD_REQUEST, // 400
            ErrorCode::PASSED_USER_PASSWORD_NOT_MATCH_CURRENT => JsonResponse::HTTP_BAD_REQUEST, // 400
            ErrorCode::ORDER_REQUEST_LIMIT_EXCEEDED => JsonResponse::HTTP_TOO_MANY_REQUESTS, // 429
            default => JsonResponse::HTTP_BAD_REQUEST, // 400
        };
    }

    protected function unauthenticated($request, AuthenticationException $exception): Response
    {
        // Check if this is an API request
        if ($request->expectsJson() || $request->is('api/*')) {
            return new JsonResponse([
                'error' => [
                    'message' => $exception->getMessage(),
                ],
            ], JsonResponse::HTTP_UNAUTHORIZED);
        }
        
        // For web requests, redirect to login
        return redirect()->guest(route('login'));
    }

    protected function invalidJson($request, ValidationException $exception): JsonResponse
    {
        return new JsonResponse([
            'message' => $exception->getMessage(),
            'errors' => $exception->errors(),
        ], $exception->status);
    }

    protected function convertValidationExceptionToResponse(ValidationException $e, $request)
    {
        // Always return JSON for API routes and administration routes
        if ($request->is('api/*') || $request->is('administration/*')) {
            return $this->invalidJson($request, $e);
        }

        return parent::convertValidationExceptionToResponse($e, $request);
    }
}
