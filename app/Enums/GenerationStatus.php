<?php

namespace App\Enums;

enum GenerationStatus: string
{
    case Queued = 'queued';
    case Processing = 'processing';
    case Succeeded = 'succeeded';
    case Failed = 'failed';

    public function isTerminal(): bool
    {
        return $this === self::Succeeded || $this === self::Failed;
    }

    public function isActive(): bool
    {
        return ! $this->isTerminal();
    }

    /**
     * Provider statuses only ever move forward. The terminal statuses share
     * the final rank so a late success can never overwrite a recorded failure.
     */
    public function rank(): int
    {
        return match ($this) {
            self::Queued => 0,
            self::Processing => 1,
            self::Succeeded, self::Failed => 2,
        };
    }

    public function label(): string
    {
        return match ($this) {
            self::Queued => 'Queued',
            self::Processing => 'Processing',
            self::Succeeded => 'Succeeded',
            self::Failed => 'Failed',
        };
    }
}
