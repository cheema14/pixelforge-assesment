<?php

namespace App\Services\Pixelforge;

use App\Support\Money;

final readonly class ProviderUsageReport
{
    /**
     * @param  array<string, int>  $costNanoByDate
     * @param  array<string, int>  $generationsByDate
     */
    public function __construct(
        public array $costNanoByDate,
        public array $generationsByDate,
        public int $totalCostNano,
        public int $totalGenerations,
    ) {}

    /**
     * Shape used when the report is cached as a plain array.
     *
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'costNanoByDate' => $this->costNanoByDate,
            'generationsByDate' => $this->generationsByDate,
            'totalCostNano' => $this->totalCostNano,
            'totalGenerations' => $this->totalGenerations,
        ];
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public static function fromStoredArray(array $data): self
    {
        return new self(
            costNanoByDate: (array) ($data['costNanoByDate'] ?? []),
            generationsByDate: (array) ($data['generationsByDate'] ?? []),
            totalCostNano: (int) ($data['totalCostNano'] ?? 0),
            totalGenerations: (int) ($data['totalGenerations'] ?? 0),
        );
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public static function fromArray(array $data): self
    {
        $costNanoByDate = [];
        $generationsByDate = [];

        foreach ((array) ($data['data'] ?? []) as $row) {
            $date = (string) ($row['date'] ?? '');

            if ($date === '') {
                continue;
            }

            $costNanoByDate[$date] = Money::fromDecimal((string) ($row['cost_usd'] ?? '0'));
            $generationsByDate[$date] = (int) ($row['generations'] ?? 0);
        }

        $total = $data['total'] ?? [];

        return new self(
            costNanoByDate: $costNanoByDate,
            generationsByDate: $generationsByDate,
            totalCostNano: isset($total['cost_usd'])
                ? Money::fromDecimal((string) $total['cost_usd'])
                : array_sum($costNanoByDate),
            totalGenerations: isset($total['generations'])
                ? (int) $total['generations']
                : array_sum($generationsByDate),
        );
    }
}
