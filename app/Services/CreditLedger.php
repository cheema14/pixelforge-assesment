<?php

namespace App\Services;

use App\Enums\CreditTransactionType;
use App\Models\CreditTransaction;
use App\Models\Generation;
use App\Models\User;

final class CreditLedger
{
    public function balanceNano(User $user): int
    {
        return (int) CreditTransaction::query()
            ->whereBelongsTo($user)
            ->sum('amount_nano');
    }

    public function purchase(User $user, int $amountNano, string $description): CreditTransaction
    {
        return $this->record($user, CreditTransactionType::Purchase, $amountNano, $description);
    }

    public function grantWelcomeCredit(User $user): ?CreditTransaction
    {
        $amountNano = (int) config('pixelforge.welcome_credit_nano');

        if ($amountNano <= 0) {
            return null;
        }

        return $this->record(
            $user,
            CreditTransactionType::WelcomeBonus,
            $amountNano,
            'Welcome credit',
        );
    }

    public function reserveForGeneration(User $user, Generation $generation, int $amountNano): CreditTransaction
    {
        return $this->record(
            $user,
            CreditTransactionType::GenerationReserve,
            -$amountNano,
            'Reserved for generation',
            $generation,
        );
    }

    public function refundGeneration(User $user, Generation $generation, int $amountNano): CreditTransaction
    {
        return $this->record(
            $user,
            CreditTransactionType::GenerationRefund,
            $amountNano,
            'Refunded failed generation',
            $generation,
        );
    }

    /**
     * Release the reservation and charge the real amount. A positive adjustment
     * refunds part of the reservation; a negative one charges the overage.
     */
    public function settleGeneration(
        User $user,
        Generation $generation,
        int $reservedNano,
        int $actualNano,
    ): ?CreditTransaction {
        $adjustment = $reservedNano - $actualNano;

        if ($adjustment === 0) {
            return null;
        }

        return $this->record(
            $user,
            CreditTransactionType::GenerationSettlement,
            $adjustment,
            'Settled actual generation cost',
            $generation,
        );
    }

    private function record(
        User $user,
        CreditTransactionType $type,
        int $amountNano,
        string $description,
        ?Generation $generation = null,
    ): CreditTransaction {
        return $user->creditTransactions()->create([
            'generation_id' => $generation?->id,
            'type' => $type,
            'amount_nano' => $amountNano,
            'description' => $description,
        ]);
    }
}
