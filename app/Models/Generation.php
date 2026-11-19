<?php

namespace App\Models;

use App\Enums\GenerationStatus;
use Database\Factories\GenerationFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'user_id',
    'pixelforge_id',
    'status',
    'prompt',
    'size',
    'quality',
    'reference_images',
    'rates',
    'usage',
    'estimated_provider_cost_nano',
    'max_provider_cost_nano',
    'estimated_charge_nano',
    'max_charge_nano',
    'provider_cost_nano',
    'charged_nano',
    'image_url',
    'image_url_expires_at',
    'error_code',
    'error_message',
    'submitted_at',
    'started_at',
    'completed_at',
    'settled_at',
])]
class Generation extends Model
{
    /** @use HasFactory<GenerationFactory> */
    use HasFactory, HasUlids;

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function creditTransactions(): HasMany
    {
        return $this->hasMany(CreditTransaction::class);
    }

    public function isActive(): bool
    {
        return $this->status->isActive();
    }

    public function isSettled(): bool
    {
        return $this->settled_at !== null;
    }

    public function hasImageUrlExpired(): bool
    {
        return $this->image_url_expires_at !== null && $this->image_url_expires_at->isPast();
    }

    #[Scope]
    protected function active(Builder $query): void
    {
        $query->whereIn('status', [
            GenerationStatus::Queued->value,
            GenerationStatus::Processing->value,
        ]);
    }

    #[Scope]
    protected function awaitingSettlement(Builder $query): void
    {
        $query->where('status', GenerationStatus::Succeeded->value)
            ->whereNull('settled_at');
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => GenerationStatus::class,
            'reference_images' => 'array',
            'rates' => 'array',
            'usage' => 'array',
            'estimated_provider_cost_nano' => 'integer',
            'max_provider_cost_nano' => 'integer',
            'estimated_charge_nano' => 'integer',
            'max_charge_nano' => 'integer',
            'provider_cost_nano' => 'integer',
            'charged_nano' => 'integer',
            'image_url_expires_at' => 'immutable_datetime',
            'submitted_at' => 'immutable_datetime',
            'started_at' => 'immutable_datetime',
            'completed_at' => 'immutable_datetime',
            'settled_at' => 'immutable_datetime',
        ];
    }
}
