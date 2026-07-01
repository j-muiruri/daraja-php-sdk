<?php

declare(strict_types=1);

namespace Daraja\Laravel\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

/**
 * Middleware: VerifyMpesaIp
 *
 * Blocks incoming requests that do not originate from a known Safaricom IP.
 * Applied automatically to all M-Pesa webhook routes.
 *
 * Register in your HTTP kernel:
 *
 *   protected $middlewareAliases = [
 *       'mpesa.ip' => \Daraja\Laravel\Http\Middleware\VerifyMpesaIp::class,
 *   ];
 *
 * IP whitelist is sourced from config('daraja.safaricom_ips').
 * In sandbox/local environments, the check is bypassed automatically.
 */
final class VerifyMpesaIp
{
    public function handle(Request $request, Closure $next): mixed
    {
        // Skip IP check on sandbox/local — Safaricom sandbox uses various IPs
        if (!app()->isProduction()) {
            return $next($request);
        }

        /** @var list<string> $allowedIps */
        $allowedIps = config('daraja.safaricom_ips', []);

        // No whitelist configured — let it through (misconfiguration is logged)
        if (empty($allowedIps)) {
            logger()->warning('Daraja: safaricom_ips whitelist is empty. Skipping IP check.');

            return $next($request);
        }

        $clientIp = $request->ip() ?? '';

        if (!in_array($clientIp, $allowedIps, true)) {
            logger()->warning('Daraja: Blocked request from non-Safaricom IP', [
                'ip'   => $clientIp,
                'path' => $request->path(),
            ]);

            return response()->json(
                ['error' => 'Forbidden'],
                Response::HTTP_FORBIDDEN,
            );
        }

        return $next($request);
    }
}
