<?php

namespace App\Exceptions\Shared;

use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Database\QueryException;
use Illuminate\Http\Exceptions\ThrottleRequestsException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Throwable;

/**
 * Renders API errors in the contract shape {"message", "code", "errors"} (docs/api/openapi.yaml).
 * Returns null for requests outside api/* so web pages keep Laravel's default rendering.
 */
class ApiExceptionRenderer
{
    /** PostgreSQL foreign_key_violation: a RESTRICT FK blocked the delete. */
    private const FOREIGN_KEY_VIOLATION = '23503';

    public function __invoke(Throwable $e, Request $request): ?JsonResponse
    {
        if (! $request->is('api/*')) {
            return null;
        }

        return match (true) {
            $e instanceof ApiException => $this->json($e->getMessage(), $e->errorCode, $e->status, $e->errors),
            $e instanceof ValidationException => $this->json($e->getMessage(), 'VALIDATION_FAILED', 422, $e->errors()),
            $e instanceof AuthenticationException => $this->json(__('Unauthenticated.'), 'UNAUTHENTICATED', 401),
            $e instanceof AuthorizationException => $this->authorization($e),
            $e instanceof ModelNotFoundException,
            $e instanceof NotFoundHttpException => $this->json(__('Not found.'), 'NOT_FOUND', 404),
            $e instanceof ThrottleRequestsException => $this->json(__('Too many requests.'), 'TOO_MANY_REQUESTS', 429)
                ->withHeaders($e->getHeaders()),
            $e instanceof QueryException && $e->getCode() === self::FOREIGN_KEY_VIOLATION => $this->json(
                __('The resource is still referenced by other data.'), 'RESOURCE_IN_USE', 409,
            ),
            $e instanceof HttpExceptionInterface && $e->getStatusCode() === 403 => $this->json(__('Forbidden.'), 'FORBIDDEN', 403),
            default => null,
        };
    }

    private function authorization(AuthorizationException $e): JsonResponse
    {
        $status = $e->status() ?? 403;
        $code = $e->response()?->code();

        return $this->json(
            $status === 403 ? __('Forbidden.') : $e->getMessage(),
            is_string($code) && $code !== '' ? $code : 'FORBIDDEN',
            $status,
        );
    }

    /**
     * @param  array<string, mixed>  $errors
     */
    private function json(string $message, string $code, int $status, array $errors = []): JsonResponse
    {
        return new JsonResponse(array_filter([
            'message' => $message,
            'code' => $code,
            'errors' => $errors ?: null,
        ], fn ($value) => $value !== null), $status);
    }
}
