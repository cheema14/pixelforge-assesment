<?php

namespace App\Enums;

enum CreditTransactionType: string
{
    case Purchase = 'purchase';
    case WelcomeBonus = 'welcome_bonus';
    case GenerationReserve = 'generation_reserve';
    case GenerationSettlement = 'generation_settlement';
    case GenerationRefund = 'generation_refund';

    public function label(): string
    {
        return match ($this) {
            self::Purchase => 'Credit purchase',
            self::WelcomeBonus => 'Welcome credit',
            self::GenerationReserve => 'Reserved for generation',
            self::GenerationSettlement => 'Generation settlement',
            self::GenerationRefund => 'Generation refund',
        };
    }
}
