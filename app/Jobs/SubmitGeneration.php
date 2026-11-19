<?php

namespace App\Jobs;

use App\Actions\Generations\ApplyProviderSnapshot;
use App\Models\Generation;
use App\Services\Pixelforge\PixelforgeClient;
use App\Services\Pixelforge\PixelforgeException;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Throwable;

class SubmitGeneration implements ShouldBeUnique, ShouldQueue
{
    use Queueable;

    public int $tries = 6;

    public int $timeout = 30;

    public int $uniqueFor = 600;

    public bool $deleteWhenMissingModels = true;

    public function __construct(public Generation $generation) {}

    public function uniqueId(): string
    {
        return $this->generation->id;
    }

    public function handle(PixelforgeClient $client, ApplyProviderSnapshot $apply): void
    {
        $generation = $this->generation->fresh();

        if ($generation === null || $generation->pixelforge_id !== null || $generation->status->isTerminal()) {
            return;
        }

        $parameters = [
            'prompt' => $generation->prompt,
            'size' => $generation->size,
            'quality' => $generation->quality,
            'metadata' => ['app_generation_id' => $generation->id],
        ];

        if ($generation->reference_images !== []) {
            $parameters['reference_images'] = $generation->reference_images;
        }

        try {
            $snapshot = $client->createGeneration($parameters, $generation->id);
        } catch (PixelforgeException $exception) {
            if (! $exception->retryable) {
                $apply->fail(
                    $generation,
                    $exception->providerCode ?? 'submission_failed',
                    $exception->getMessage(),
                );

                return;
            }

            if ($this->attempts() >= $this->tries) {
                throw $exception;
            }

            $delay = $exception->retryAfterSeconds ?? min(120, 5 * (2 ** ($this->attempts() - 1)));
            $this->release($delay);

            return;
        }

        $apply->handle($generation, $snapshot);

        $generation->refresh();

        if ($generation->status->isActive()) {
            ReconcileGeneration::dispatch($generation)
                ->delay(now()->addSeconds((int) config('pixelforge.reconcile_interval_seconds')));
        }
    }

    public function failed(?Throwable $exception): void
    {
        $generation = $this->generation->fresh();

        if ($generation === null || $generation->status->isTerminal()) {
            return;
        }

        app(ApplyProviderSnapshot::class)->fail(
            $generation,
            'submission_unavailable',
            'Pixelforge could not be reached after several attempts. No credits were charged.',
        );
    }
}
