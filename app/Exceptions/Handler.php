<?php

namespace App\Exceptions;

use App\Services\AdminNotificationService;
use Illuminate\Foundation\Exceptions\Handler as ExceptionHandler;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Throwable;

class Handler extends ExceptionHandler
{
    /**
     * The list of the inputs that are never flashed to the session on validation exceptions.
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
            if (!$this->shouldNotifySuperAdmin($e)) {
                return;
            }

            try {
                $request = request();

                $path = '/' . ltrim($request->path(), '/');
                $method = strtoupper($request->method());

                $portal = $this->detectPortal($path);
                $area = $this->detectArea($path);

                $fingerprint = 'system_problem:' . sha1(
                    get_class($e)
                    . '|'
                    . $method
                    . '|'
                    . $path
                );

                /*
                 * Prevent repeated notifications for the same error
                 * from flooding the Super Admin bell.
                 */
                if (!Cache::add($fingerprint, true, now()->addMinutes(5))) {
                    return;
                }

                $message =
                    "An unexpected server error occurred while processing "
                    . "{$method} {$path}.";

                $actionUrl =
                    $this->actionUrlForArea(
                        $portal,
                        $area
                    );

                app(AdminNotificationService::class)
                    ->systemProblem(
                        $portal,
                        $area,
                        $message,
                        $actionUrl
                    );

            } catch (Throwable $notificationException) {
                /*
                 * Never allow notification failure to create
                 * another application exception.
                 */
                Log::error(
                    'System problem notification could not be created',
                    [
                        'message' =>
                            $notificationException->getMessage(),
                        'original_exception' =>
                            get_class($e),
                    ]
                );
            }
        });
    }

    /**
     * Only notify Super Admin for genuine server-side failures.
     */
    private function shouldNotifySuperAdmin(
        Throwable $e
    ): bool {
        if ($e instanceof HttpExceptionInterface) {
            $statusCode = $e->getStatusCode();

            /*
             * Ignore normal client/auth/validation style errors.
             */
            if ($statusCode < 500) {
                return false;
            }
        }

        return true;
    }

    /**
     * Identify which GreenHaven portal caused the error.
     */
    private function detectPortal(
        string $path
    ): string {
        if (
            str_starts_with(
                $path,
                '/api/tenant'
            )
        ) {
            return 'Tenant Portal';
        }

        if (
            str_starts_with(
                $path,
                '/api/admin'
            )
        ) {
            return 'Admin Portal';
        }

        return 'Customer/Public Portal';
    }

    /**
     * Identify the main functional area.
     */
    private function detectArea(
        string $path
    ): string {
        $areas = [
            'subscriptions' =>
                'Subscriptions',

            'payments' =>
                'Payments',

            'orders' =>
                'Orders',

            'cart' =>
                'Cart / Checkout',

            'inventory' =>
                'Inventory',

            'products' =>
                'Products',

            'categories' =>
                'Categories',

            'reports' =>
                'Reports',

            'tenants' =>
                'Tenant Management',

            'users' =>
                'User Management',

            'notifications' =>
                'Notifications',

            'profile' =>
                'Profile / Settings',
        ];

        foreach (
            $areas as $needle => $label
        ) {
            if (
                str_contains(
                    $path,
                    '/' . $needle
                )
            ) {
                return $label;
            }
        }

        return 'General';
    }

    /**
     * Send the Super Admin to the most relevant page.
     */
    private function actionUrlForArea(
        string $portal,
        string $area
    ): string {
        return match ($area) {
            'Subscriptions' =>
                '/super-admin/subscriptions',

            'Payments' =>
                '/super-admin/payments',

            'Orders' =>
                '/super-admin/orders',

            'Inventory',
            'Products' =>
                '/super-admin/products',

            'Categories' =>
                '/super-admin/categories',

            'Reports' =>
                '/super-admin/reports',

            'Tenant Management' =>
                '/super-admin/tenants',

            'User Management' =>
                '/super-admin/users',

            default =>
                '/super-admin/dashboard',
        };
    }
}
