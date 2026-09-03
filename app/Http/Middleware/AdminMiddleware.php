<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class AdminMiddleware
{
    public function handle(Request $request, Closure $next): Response
    {
        $admin = $request->user();

        if (!$admin) {
            return response()->json([
                'error' => 'Unauthenticated. Only authenticated admins can access this resource.'
            ], 401);
        }

        if ($admin->role !== 'admin') {
            return response()->json([
                'error' => 'Unauthorized. Only admins can perform this action.'
            ], 403);
        }

        return $next($request);
    }
}