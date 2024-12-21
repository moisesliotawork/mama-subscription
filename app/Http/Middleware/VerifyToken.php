<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class VerifyToken
{
    public function handle(Request $request, Closure $next)
    {
        $token = $request->header('Authorization');

        // Verifica que el token sea válido
        if ($token !== 'f8423bb2-10c9-4d0f-8300-aaf8fea18c72') {
            return response()->json(['status' => false, 'message' => 'Unauthorized'], 401);
        }

        return $next($request);
    }
}
