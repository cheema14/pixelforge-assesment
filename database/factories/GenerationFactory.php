<?php

namespace Database\Factories;

use App\Enums\GenerationStatus;
use App\Models\Generation;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Generation>
 */
class GenerationFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'pixelforge_id' => 'gen_'.Str::lower(Str::random(20)),
            'status' => GenerationStatus::Queued,
            'prompt' => fake()->sentence(12),
            'size' => '1024x1024',
            'quality' => 'medium',
            'reference_images' => [],
            'estimated_provider_cost_nano' => 10_000_000,
            'max_provider_cost_nano' => 14_000_000,
            'estimated_charge_nano' => 12_500_000,
            'max_charge_nano' => 17_500_000,
        ];
    }

    public function processing(): static
    {
        return $this->state(fn (): array => [
            'status' => GenerationStatus::Processing,
            'submitted_at' => now()->subMinute(),
            'started_at' => now()->subSeconds(45),
        ]);
    }

    public function succeeded(): static
    {
        return $this->state(fn (): array => [
            'status' => GenerationStatus::Succeeded,
            'submitted_at' => now()->subMinutes(2),
            'started_at' => now()->subMinutes(2),
            'completed_at' => now()->subMinute(),
            'settled_at' => now()->subMinute(),
            'usage' => [
                'input_text_tokens' => 100,
                'input_image_tokens' => 0,
                'output_tokens' => 1100,
                'reasoning_tokens' => 640,
            ],
            'provider_cost_nano' => 16_550_000,
            'charged_nano' => 20_687_500,
            'image_url' => 'https://cdn.pixelforge.test/generations/'.Str::lower(Str::random(12)).'.png',
            'image_url_expires_at' => now()->addDay(),
        ]);
    }

    public function failed(): static
    {
        return $this->state(fn (): array => [
            'status' => GenerationStatus::Failed,
            'submitted_at' => now()->subMinutes(2),
            'completed_at' => now()->subMinute(),
            'settled_at' => now()->subMinute(),
            'provider_cost_nano' => 0,
            'charged_nano' => 0,
            'error_code' => 'content_policy_violation',
            'error_message' => 'The prompt was rejected by the provider.',
        ]);
    }
}
