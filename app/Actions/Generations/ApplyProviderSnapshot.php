<?php

namespace App\Actions\Generations;

use App\Enums\GenerationStatus;
use App\Models\Generation;
use App\Services\CreditLedger;
use App\Services\Pixelforge\PixelforgeGeneration;
use App\Support\Money;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

final class ApplyProviderSnapshot
{
    public function __construct(private readonly CreditLedger $credits) {}

    /**
     * Apply a provider snapshot without ever moving a generation backwards.
     * Safe to call for duplicated and out-of-order webhook deliveries.
     */
    public function handle(Generation $generation, PixelforgeGeneration $snapshot): void
    {
        DB::transaction(function () use ($generation, $snapshot): void {
            $generation = Generation::query()
                ->whereKey($generation->id)
                ->lockForUpdate()
                ->firstOrFail();

            if ($generation->status->isTerminal() && $generation->isSettled()) {
                return;
            }

            if ($generation->pixelforge_id !== null && $generation->pixelforge_id !== $snapshot->id) {
                Log::warning('Ignored a provider snapshot for a mismatched generation id.', [
                    'generation_id' => $generation->id,
                    'expected_provider_id' => $generation->pixelforge_id,
                    'received_provider_id' => $snapshot->id,
                ]);

                return;
            }

            $status = $snapshot->status->rank() > $generation->status->rank()
                ? $snapshot->status
                : $generation->status;

            $isFailed = $snapshot->status === GenerationStatus::Failed;

            $generation->fill([
                'pixelforge_id' => $snapshot->id,
                'status' => $status,
                'rates' => $snapshot->rates !== [] ? $snapshot->rates : $generation->rates,
                'usage' => $snapshot->usage ?? $generation->usage,
                'image_url' => $snapshot->imageUrl ?? $generation->image_url,
                'image_url_expires_at' => $snapshot->imageUrlExpiresAt ?? $generation->image_url_expires_at,
                'submitted_at' => $generation->submitted_at ?? now(),
                'started_at' => $snapshot->startedAt ?? $generation->started_at,
                'completed_at' => $snapshot->completedAt ?? $generation->completed_at,
                'error_code' => $isFailed ? Str::limit((string) $snapshot->errorCode, 64, '') : null,
                'error_message' => $isFailed ? Str::limit((string) $snapshot->errorMessage, 1000) : null,
            ]);

            if ($status->isTerminal() && $generation->completed_at === null) {
                $generation->completed_at = now();
            }

            $generation->save();

            if ($status->isTerminal() && ! $generation->isSettled()) {
                $this->settle($generation);
            }
        });
    }

    /**
     * Record a local failure (submission rejected, unrecoverable provider
     * error, or reconciliation timeout) and refund the reservation.
     */
    public function fail(Generation $generation, string $errorCode, string $errorMessage): void
    {
        DB::transaction(function () use ($generation, $errorCode, $errorMessage): void {
            $generation = Generation::query()
                ->whereKey($generation->id)
                ->lockForUpdate()
                ->firstOrFail();

            if ($generation->status->isTerminal()) {
                return;
            }

            $generation->fill([
                'status' => GenerationStatus::Failed,
                'error_code' => Str::limit($errorCode, 64, ''),
                'error_message' => Str::limit($errorMessage, 1000),
                'completed_at' => now(),
            ])->save();

            $this->settle($generation);
        });
    }

    private function settle(Generation $generation): void
    {
        if ($generation->isSettled()) {
            return;
        }

        $user = $generation->user()->firstOrFail();

        if ($generation->status === GenerationStatus::Failed) {
            $this->credits->refundGeneration($user, $generation, $generation->estimated_charge_nano);

            $generation->fill([
                'provider_cost_nano' => 0,
                'charged_nano' => 0,
                'settled_at' => now(),
            ])->save();

            return;
        }

        if ($generation->usage === null || $generation->rates === null || $generation->rates === []) {
            Log::warning('Succeeded generation is missing billable usage; leaving it open for reconciliation.', [
                'generation_id' => $generation->id,
            ]);

            return;
        }

        $providerCostNano = Money::providerCost($generation->usage, $generation->rates);
        $chargedNano = Money::withMarkup($providerCostNano, (int) config('pixelforge.markup_percent'));

        $this->credits->settleGeneration(
            $user,
            $generation,
            $generation->estimated_charge_nano,
            $chargedNano,
        );

        $generation->fill([
            'provider_cost_nano' => $providerCostNano,
            'charged_nano' => $chargedNano,
            'settled_at' => now(),
        ])->save();
    }
}
