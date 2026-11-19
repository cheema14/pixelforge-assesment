<?php

namespace App\Http\Controllers\Webhooks;

use App\Actions\ProcessPixelforgeWebhook;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use InvalidArgumentException;

class PixelforgeWebhookController extends Controller
{
    public function __invoke(Request $request, ProcessPixelforgeWebhook $process): Response
    {
        try {
            $process->handle((array) $request->json()->all());
        } catch (InvalidArgumentException $exception) {
            report($exception);

            abort(400, 'Invalid Pixelforge webhook payload.');
        }

        return response()->noContent();
    }
}
