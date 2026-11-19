<?php

use App\Http\Middleware\EstablishStaffTenant;
use App\Http\Middleware\HandleAppearance;
use App\Http\Middleware\HandleInertiaRequests;
use App\Http\Middleware\SetLocale;
use App\Services\Verification\PhoneVerificationDeliveryException;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Middleware\AddLinkHeadersForPreloadedAssets;
use Illuminate\Http\Request;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->alias([
            'staff.tenant' => EstablishStaffTenant::class,
        ]);

        $middleware->encryptCookies(except: ['appearance', 'sidebar_state']);

        $middleware->web(append: [
            SetLocale::class,
            HandleAppearance::class,
            HandleInertiaRequests::class,
            AddLinkHeadersForPreloadedAssets::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->render(function (PhoneVerificationDeliveryException $exception, Request $request) {
            $message = 'The verification message could not be sent right now. Please wait a moment and try again.';
            $field = $request->is('*/resend') ? 'resend' : 'phone_number';

            if ($request->expectsJson()) {
                return response()->json([
                    'message' => $message,
                    'errors' => [$field => [$message]],
                ], 503);
            }

            return back()->withErrors([$field => $message])->withInput($request->only('phone_number', 'tracking_reference'));
        });
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );
    })->create();
