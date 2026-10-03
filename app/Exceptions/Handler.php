<?php

namespace App\Exceptions;

use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Exceptions\Handler as ExceptionHandler;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Throwable;

class Handler extends ExceptionHandler
{
    /**
     * A list of exception types with their corresponding custom log levels.
     *
     * @var array<class-string<\Throwable>, \Psr\Log\LogLevel::*>
     */
    protected $levels = [
        //
    ];

    /**
     * A list of the exception types that are not reported.
     *
     * @var array<int, class-string<\Throwable>>
     */
    protected $dontReport = [
        //
    ];

    /**
     * A list of the inputs that are never flashed to the session on validation exceptions.
     *
     * @var array<int, string>
     */
    protected $dontFlash = [
        'current_password',
        'password',
        'password_confirmation',
    ];

    /**
     * Register the exception handling callbacks for the application.
     */
    public function register(): void
    {
        $this->reportable(function (Throwable $e) {
            //
        });

        // API clients must always get JSON — never an HTML debug page. This
        // also means an accidental APP_DEBUG=true in production cannot leak
        // stack traces, file paths or credentials through the API.
        $this->renderable(function (Throwable $e, Request $request) {
            if (!$request->is('api/*') && !$request->expectsJson()) {
                return null;
            }

            // Let Laravel format validation errors as usual (422 + fields).
            if ($e instanceof ValidationException) {
                return null;
            }

            if ($e instanceof AuthenticationException) {
                return response()->json(['message' => 'Unauthenticated.'], 401);
            }

            if ($e instanceof AuthorizationException) {
                return response()->json(['message' => 'You do not have permission to perform this action.'], 403);
            }

            if ($e instanceof ModelNotFoundException || $e instanceof NotFoundHttpException) {
                return response()->json(['message' => 'The requested resource was not found.'], 404);
            }

            if ($e instanceof HttpExceptionInterface) {
                $status = $e->getStatusCode();

                return response()->json([
                    'message' => $e->getMessage() ?: 'Request could not be completed.',
                ], $status);
            }

            // Unexpected failure: log the detail under a short reference the
            // user can quote, so the exact line can be found in laravel.log
            // without exposing the stack trace to the client.
            $ref = strtoupper(substr(bin2hex(random_bytes(4)), 0, 8));

            logger()->error("[ERR-{$ref}] {$e->getMessage()}", [
                'ref' => $ref,
                'exception' => get_class($e),
                'file' => $e->getFile() . ':' . $e->getLine(),
                'url' => $request->fullUrl(),
                'user_id' => optional($request->user())->id,
            ]);
            report($e);

            $payload = [
                'message' => "A server error occurred (ref: ERR-{$ref}). Please try again, or quote this reference to support.",
                'error_ref' => $ref,
            ];

            // With APP_DEBUG on, surface the real cause — otherwise a server
            // problem is undiagnosable without shell access to the log.
            if (config('app.debug')) {
                $payload['debug'] = [
                    'exception' => get_class($e),
                    'error' => $e->getMessage(),
                    'at' => $e->getFile() . ':' . $e->getLine(),
                ];
            }

            return response()->json($payload, 500);
        });
    }
}
