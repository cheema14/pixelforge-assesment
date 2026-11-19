<?php

namespace App\Console\Commands;

use App\Jobs\ReconcileGeneration;
use App\Jobs\SubmitGeneration;
use App\Models\Generation;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('pixelforge:reconcile')]
#[Description('Dispatch backup jobs for generations with missed webhooks or stuck submissions.')]
class ReconcilePixelforgeGenerations extends Command
{
    public function handle(): int
    {
        $pollCutoff = now()->subSeconds(30);

        Generation::query()
            ->active()
            ->whereNull('pixelforge_id')
            ->where('created_at', '<', now()->subMinutes((int) config('pixelforge.stale_submission_minutes')))
            ->where('updated_at', '<', $pollCutoff)
            ->limit(25)
            ->get()
            ->each(fn (Generation $generation) => SubmitGeneration::dispatch($generation));

        Generation::query()
            ->active()
            ->whereNotNull('pixelforge_id')
            ->where('updated_at', '<', $pollCutoff)
            ->limit(50)
            ->get()
            ->each(fn (Generation $generation) => ReconcileGeneration::dispatch($generation));

        Generation::query()
            ->awaitingSettlement()
            ->whereNotNull('pixelforge_id')
            ->where('updated_at', '<', $pollCutoff)
            ->limit(50)
            ->get()
            ->each(fn (Generation $generation) => ReconcileGeneration::dispatch($generation));

        return self::SUCCESS;
    }
}
