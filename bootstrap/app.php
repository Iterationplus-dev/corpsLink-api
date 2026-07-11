<?php

use App\Console\Commands\ExpireAbandonedBookings;
use App\Console\Commands\SendDepartureReminders;
use App\Console\Commands\SendSeatHoldExpiryWarnings;
use App\Exceptions\ApiException;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        // This is a pure API — there's no login page to redirect guests to.
        // Without this, Laravel's default redirectGuestsTo(route('login'))
        // throws RouteNotFoundException (no route literally named "login")
        // for any unauthenticated request that doesn't send an explicit
        // Accept: application/json header, producing a raw 500 instead of
        // the clean 401 JSON the AuthenticationException handler below
        // already renders for api/* requests.
        $middleware->redirectGuestsTo(fn () => null);

        // env() (not app()->environment()) because the container's 'env'
        // binding isn't resolvable this early in the bootstrap lifecycle.
        if (env('APP_ENV') !== 'testing') {
            $middleware->throttleWithRedis();
        }
    })
    ->withSchedule(function (Schedule $schedule): void {
        $schedule->command(SendSeatHoldExpiryWarnings::class)->everyMinute();
        $schedule->command(SendDepartureReminders::class)->everyFiveMinutes();
        $schedule->command(ExpireAbandonedBookings::class)->everyMinute();

        // Prunes PendingRegistration, VerificationCode, and SeatHold rows
        // (all Prunable since Phases 1–2) — never actually scheduled until now.
        $schedule->command('model:prune')->daily();
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*'),
        );

        $exceptions->render(function (ApiException $e, Request $request) {
            if ($request->is('api/*')) {
                return $e->render();
            }
        });

        $exceptions->render(function (ValidationException $e, Request $request) {
            if ($request->is('api/*')) {
                $fields = [];
                foreach ($e->errors() as $field => $messages) {
                    foreach ($messages as $message) {
                        $fields[] = ['field' => $field, 'message' => $message];
                    }
                }

                return response()->json([
                    'error' => [
                        'code' => 'validation_error',
                        'message' => $e->getMessage(),
                        'fields' => $fields,
                    ],
                ], $e->status);
            }
        });

        $exceptions->render(function (AuthenticationException $e, Request $request) {
            if ($request->is('api/*')) {
                return response()->json([
                    'error' => [
                        'code' => 'unauthenticated',
                        'message' => 'Authentication required.',
                    ],
                ], 401);
            }
        });

        $exceptions->render(function (AuthorizationException $e, Request $request) {
            if ($request->is('api/*')) {
                return response()->json([
                    'error' => [
                        'code' => 'forbidden',
                        'message' => $e->getMessage() ?: 'This action is unauthorized.',
                    ],
                ], 403);
            }
        });

        $exceptions->render(function (ModelNotFoundException $e, Request $request) {
            if ($request->is('api/*')) {
                return response()->json([
                    'error' => [
                        'code' => 'not_found',
                        'message' => 'The requested resource was not found.',
                    ],
                ], 404);
            }
        });

        $exceptions->render(function (NotFoundHttpException $e, Request $request) {
            if ($request->is('api/*')) {
                return response()->json([
                    'error' => [
                        'code' => 'not_found',
                        'message' => $e->getPrevious() instanceof ModelNotFoundException
                            ? 'The requested resource was not found.'
                            : 'This endpoint does not exist.',
                    ],
                ], 404);
            }
        });

        $exceptions->render(function (HttpExceptionInterface $e, Request $request) {
            if ($request->is('api/*')) {
                return response()->json([
                    'error' => [
                        'code' => 'http_error',
                        'message' => $e->getMessage() ?: 'An error occurred.',
                    ],
                ], $e->getStatusCode());
            }
        });
    })->create();
