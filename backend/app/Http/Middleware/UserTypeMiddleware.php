<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class UserTypeMiddleware
{
    public function handle(Request $request, Closure $next, string ...$types): Response
    {
        $user = $request->user();

        if (!$user) {
            return response()->json(['message' => 'Unauthorized'], Response::HTTP_UNAUTHORIZED);
        }

        if (!in_array($user->user_type, $types)) {
            return response()->json(['message' => 'Forbidden'], Response::HTTP_FORBIDDEN);
        }

        return $next($request);
    }
}
