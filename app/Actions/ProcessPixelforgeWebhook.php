<?php

namespace App\Actions;

use App\Actions\Generations\ApplyProviderSnapshot;
use App\Models\Generation;
use App\Models\WebhookEvent;
use App\Services\Pixelforge\PixelforgeGeneration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use InvalidArgumentException;

final class ProcessPixelforgeWebhook
{
    public function __construct(private readonly ApplyProviderSnapshot $apply) {}

    /**
     * Events are recorded and applied inside one transaction. If applying
     * fails, the event row rolls back, so Pixelforge's retry can process it.
     *
     * @param  array<string, mixed>  $event
     */
    public function handle(array $event): void
    {
        $eventId = $event['id'] ?? null;
        $type = $event['type'] ?? null;

        if (! is_string($eventId) || $eventId === '' || strlen($eventId) > 255) {
            throw new InvalidArgumentException('The webhook event id is invalid.');
        }

        if (! is_string($type) || $type === '' || strlen($type) > 64) {
            throw new InvalidArgumentException('The webhook event type is invalid.');
        }

        if ($type === 'ping') {
            return;
        }

        if (! str_starts_with($type, 'generation.')) {
            return;
        }

        $object = data_get($event, 'data.object');

        if (! is_array($object)) {
            throw new InvalidArgumentException('The webhook event has no generation object.');
        }

        $snapshot = PixelforgeGeneration::fromArray($object);

        DB::transaction(function () use ($event, $eventId, $type, $snapshot): void {
            $inserted = DB::table((new WebhookEvent)->getTable())->insertOrIgnore([
                'event_id' => $eventId,
                'type' => $type,
                'generation_id' => null,
                'payload' => json_encode($event, JSON_THROW_ON_ERROR),
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            if ($inserted === 0) {
                return;
            }

            $generation = $this->findGeneration($snapshot);

            if ($generation === null) {
                Log::warning('Received a Pixelforge webhook for an unknown generation.', [
                    'event_id' => $eventId,
                    'provider_generation_id' => $snapshot->id,
                ]);

                DB::table((new WebhookEvent)->getTable())
                    ->where('event_id', $eventId)
                    ->update(['processed_at' => now(), 'updated_at' => now()]);

                return;
            }

            DB::table((new WebhookEvent)->getTable())
                ->where('event_id', $eventId)
                ->update(['generation_id' => $generation->id, 'updated_at' => now()]);

            $this->apply->handle($generation, $snapshot);

            DB::table((new WebhookEvent)->getTable())
                ->where('event_id', $eventId)
                ->update(['processed_at' => now(), 'updated_at' => now()]);
        });
    }

    private function findGeneration(PixelforgeGeneration $snapshot): ?Generation
    {
        $appGenerationId = $snapshot->metadata['app_generation_id'] ?? null;

        if (is_string($appGenerationId) && $appGenerationId !== '') {
            $generation = Generation::query()->find($appGenerationId);

            if ($generation !== null) {
                return $generation;
            }
        }

        return Generation::query()
            ->where('pixelforge_id', $snapshot->id)
            ->first();
    }
}
