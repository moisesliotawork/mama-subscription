<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class VerifyIP
{
    /**
     * Lista blanca de IPs permitidas.
     *
     * @var array
     */
    protected $whitelistedIPs = [
        '45.175.213.98',
        '200.74.203.91',
        '190.202.123.66',
    ];

    /**
     * Maneja una solicitud entrante.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Closure  $next
     * @return mixed
     */
    public function handle(Request $request, Closure $next)
    {
        $clientIP = $request->ip();

        // Verificar si la IP está en la lista blanca
        if (!in_array($clientIP, $this->whitelistedIPs)) {
            return response()->json(['status' => false, 'message' => 'IP no autorizada'], 403);
        }

        return $next($request);
    }
}
