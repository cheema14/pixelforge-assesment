<?php

namespace App\Actions\Generations;

use App\Enums\GenerationStatus;
use App\Jobs\SubmitGeneration;
use App\Models\Generation;
use App\Models\User;
use App\Services\CreditLedger;
use App\Services\Pixelforge\PricingService;
use App\Support\Money;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class CreateGeneration
{
    public function __construct(
        private readonly PricingService $pricing,
        private readonly CreditLedger $credits,
    ) {}

    /**
     * @param  array{prompt: string, size: string, quality: string, reference_images?: list<string>}  $data
     */
    public function handle(User $user, array $data): Generation
    {
        $referenceImages = array_values($data['reference_images'] ?? []);
        $estimate = $this->pricing->estimate(
            $data['prompt'],
            $data['size'],
            $data['quality'],
            $referenceImages,
        );

        return DB::transaction(function () use ($user, $data, $referenceImages, $estimate): Generation {
            $lockedUser = User::query()
                ->whereKey($user->id)
                ->lockForUpdate()
                ->firstOrFail();

            $balanceNano = $this->credits->balanceNano($lockedUser);

            if ($balanceNano < $estimate->chargeNano) {
                throw ValidationException::withMessages([
                    'balance' => sprintf(
                        'This generation needs about %s in credits, but your balance is %s. Add credits to continue.',
                        Money::format($estimate->chargeNano),
                        Money::format(max(0, $balanceNano)),
                    ),
                ]);
            }

            $generation = Generation::create([
                'user_id' => $lockedUser->id,
                'status' => GenerationStatus::Queued,
                'prompt' => $data['prompt'],
                'size' => $data['size'],
                'quality' => $data['quality'],
                'reference_images' => $referenceImages,
                'estimated_provider_cost_nano' => $estimate->providerCostNano,
                'max_provider_cost_nano' => $estimate->maxProviderCostNano,
                'estimated_charge_nano' => $estimate->chargeNano,
                'max_charge_nano' => $estimate->maxChargeNano,
            ]);

            $this->credits->reserveForGeneration($lockedUser, $generation, $estimate->chargeNano);

            SubmitGeneration::dispatch($generation)->afterCommit();

            return $generation;
        });
    }
}
