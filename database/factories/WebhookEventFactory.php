<?php

namespace Database\Factories;

use App\Models\WebhookEvent;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<WebhookEvent>
 */
class WebhookEventFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'event_id' => 'evt_'.Str::lower(Str::random(20)),
            'type' => 'generation.processing',
            'generation_id' => null,
            'payload' => [],
            'processed_at' => now(),
        ];
    }
}
