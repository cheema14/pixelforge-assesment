<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;

class VerifyPixelforgeSignature
{
    public function handle(Request $request, Closure $next): Response
    {
        $secret = (string) config('pixelforge.webhook_secret');

        if ($secret === '') {
            Log::error('Pixelforge webhook received but no signing secret is configured.');

            abort(500, 'Webhook signing secret is not configured.');
        }

        $header = (string) $request->header('Pixelforge-Signature');
        $signature = [];

        foreach (explode(',', $header) as $part) {
            [$key, $value] = array_pad(explode('=', trim($part), 2), 2, '');
            $signature[$key] = $value;
        }

        $timestamp = (string) ($signature['t'] ?? '');
        $provided = (string) ($signature['v1'] ?? '');

        if (! ctype_digit($timestamp) || $provided === '') {
            abort(400, 'Invalid Pixelforge signature header.');
        }

        $tolerance = (int) config('pixelforge.webhook_tolerance_seconds');

        if (abs(time() - (int) $timestamp) > $tolerance) {
            abort(400, 'Pixelforge signature timestamp is outside the tolerance window.');
        }

        $expected = hash_hmac('sha256', "{$timestamp}.{$request->getContent()}", $secret);

        if (! hash_equals($expected, $provided)) {
            abort(400, 'Invalid Pixelforge signature.');
        }

        return $next($request);
    }
}
