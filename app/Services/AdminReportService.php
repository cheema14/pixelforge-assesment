<?php

namespace App\Services;

use App\Enums\GenerationStatus;
use App\Models\Generation;
use App\Services\Pixelforge\PixelforgeClient;
use App\Services\Pixelforge\PixelforgeException;
use App\Services\Pixelforge\ProviderUsageReport;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Cache;

final class AdminReportService
{
    public function __construct(private readonly PixelforgeClient $client) {}

    /**
     * @return array<string, mixed>
     */
    public function build(CarbonImmutable $from, CarbonImmutable $to): array
    {
        $from = $from->startOfDay();
        $to = $to->endOfDay();
        $fromDate = $from->toDateString();
        $toDate = $to->toDateString();

        $generations = Generation::query()
            ->where('status', GenerationStatus::Succeeded->value)
            ->whereNotNull('settled_at')
            ->whereNotNull('completed_at')
            ->whereBetween('completed_at', [$from, $to])
            ->with('user:id,name,email')
            ->get(['id', 'user_id', 'completed_at', 'charged_nano', 'provider_cost_nano']);

        $daily = [];
        $perUser = [];

        foreach ($generations as $generation) {
            $date = $generation->completed_at->toDateString();
            $chargedNano = (int) $generation->charged_nano;
            $providerCostNano = (int) $generation->provider_cost_nano;

            $daily[$date] ??= ['generations' => 0, 'chargedNano' => 0, 'providerCostNano' => 0];
            $daily[$date]['generations']++;
            $daily[$date]['chargedNano'] += $chargedNano;
            $daily[$date]['providerCostNano'] += $providerCostNano;

            $user = $generation->user;
            $perUser[$user->id] ??= [
                'userId' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'generations' => 0,
                'chargedNano' => 0,
                'providerCostNano' => 0,
            ];
            $perUser[$user->id]['generations']++;
            $perUser[$user->id]['chargedNano'] += $chargedNano;
            $perUser[$user->id]['providerCostNano'] += $providerCostNano;
        }

        /** @var ProviderUsageReport|null $usage */
        $usage = null;
        $usageError = null;

        try {
            $usage = Cache::remember(
                "pixelforge.usage:{$fromDate}:{$toDate}",
                now()->addSeconds((int) config('pixelforge.provider_usage_cache_seconds')),
                fn (): array => $this->client->usage($from, $to)->toArray(),
            );

            $usage = ProviderUsageReport::fromStoredArray((array) $usage);
        } catch (PixelforgeException $exception) {
            $usageError = $exception->getMessage();
        }

        $days = [];
        $cursor = $from->startOfDay();
        $lastDay = $to->startOfDay();

        while ($cursor->lte($lastDay)) {
            $date = $cursor->toDateString();
            $row = $daily[$date] ?? ['generations' => 0, 'chargedNano' => 0, 'providerCostNano' => 0];
            $providerUsageNano = $usage !== null ? ($usage->costNanoByDate[$date] ?? null) : null;
            $providerUsageGenerations = $usage !== null ? ($usage->generationsByDate[$date] ?? null) : null;
            $differenceNano = $providerUsageNano !== null
                ? $row['providerCostNano'] - $providerUsageNano
                : null;

            $days[] = [
                'date' => $date,
                'generations' => $row['generations'],
                'chargedNano' => $row['chargedNano'],
                'providerCostNano' => $row['providerCostNano'],
                'profitNano' => $row['chargedNano'] - $row['providerCostNano'],
                'providerUsageNano' => $providerUsageNano,
                'providerUsageGenerations' => $providerUsageGenerations,
                'differenceNano' => $differenceNano,
                'reconciled' => $differenceNano === 0,
            ];

            $cursor = $cursor->addDay();
        }

        $users = array_values($perUser);
        usort($users, fn (array $a, array $b): int => $b['chargedNano'] <=> $a['chargedNano']);

        $totalGenerations = array_sum(array_column($days, 'generations'));
        $totalChargedNano = array_sum(array_column($days, 'chargedNano'));
        $totalProviderCostNano = array_sum(array_column($days, 'providerCostNano'));
        $providerUsageTotalNano = $usage?->totalCostNano;
        $differenceTotalNano = $providerUsageTotalNano !== null
            ? $totalProviderCostNano - $providerUsageTotalNano
            : null;

        return [
            'period' => [
                'from' => $fromDate,
                'to' => $toDate,
            ],
            'days' => $days,
            'users' => $users,
            'totals' => [
                'generations' => $totalGenerations,
                'chargedNano' => $totalChargedNano,
                'providerCostNano' => $totalProviderCostNano,
                'profitNano' => $totalChargedNano - $totalProviderCostNano,
                'providerUsageNano' => $providerUsageTotalNano,
                'providerUsageGenerations' => $usage?->totalGenerations,
                'differenceNano' => $differenceTotalNano,
                'reconciled' => $differenceTotalNano === 0,
            ],
            'usageError' => $usageError,
            'markupPercent' => (int) config('pixelforge.markup_percent'),
        ];
    }
}
