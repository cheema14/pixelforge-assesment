<?php

namespace App\Jobs;

use App\Actions\Generations\ApplyProviderSnapshot;
use App\Models\Generation;
use App\Services\Pixelforge\PixelforgeClient;
use App\Services\Pixelforge\PixelforgeException;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class ReconcileGeneration implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public int $timeout = 30;

    public bool $deleteWhenMissingModels = true;

    public function __construct(public Generation $generation) {}

    public function handle(PixelforgeClient $client, ApplyProviderSnapshot $apply): void
    {
        $generation = $this->generation->fresh();

        if ($generation === null || $generation->pixelforge_id === null || $generation->isSettled()) {
            return;
        }

        try {
            $snapshot = $client->generation($generation->pixelforge_id);
        } catch (PixelforgeException $exception) {
            if ($exception->retryable) {
                throw $exception;
            }

            if ($exception->isNotFound()) {
                $apply->fail(
                    $generation,
                    'generation_not_found',
                    'Pixelforge no longer has this generation. No credits were charged.',
                );
            }

            return;
        }

        $apply->handle($generation, $snapshot);

        $generation->refresh();

        $maxAge = now()->subMinutes((int) config('pixelforge.max_processing_minutes'));

        if ($generation->isActive()) {
            if ($generation->created_at->lt($maxAge)) {
                $apply->fail(
                    $generation,
                    'processing_timeout',
                    'The generation did not finish in time. No credits were charged.',
                );

                return;
            }

            self::dispatch($generation)
                ->delay(now()->addSeconds((int) config('pixelforge.reconcile_interval_seconds')));

            return;
        }

        if (! $generation->isSettled() && $generation->created_at->gt($maxAge)) {
            self::dispatch($generation)
                ->delay(now()->addSeconds((int) config('pixelforge.reconcile_interval_seconds')));
        }
    }
}
