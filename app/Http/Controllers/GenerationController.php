<?php

namespace App\Http\Controllers;

use App\Actions\Generations\CreateGeneration;
use App\Enums\GenerationStatus;
use App\Http\Requests\StoreGenerationRequest;
use App\Http\Resources\GenerationResource;
use App\Services\CreditLedger;
use App\Services\Pixelforge\PricingService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

class GenerationController extends Controller
{
    public function index(Request $request): Response
    {
        $status = GenerationStatus::tryFrom((string) $request->query('status', ''));

        $generations = $request->user()
            ->generations()
            ->when($status !== null, fn ($query) => $query->where('status', $status->value))
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->paginate(12)
            ->withQueryString();

        return Inertia::render('Generations/Index', [
            'generations' => GenerationResource::collection($generations),
            'filter' => [
                'status' => $status?->value,
            ],
        ]);
    }

    public function create(
        Request $request,
        PricingService $pricing,
        CreditLedger $credits,
    ): Response {
        return Inertia::render('Generations/Create', [
            'pricing' => $pricing->pricing()->toArray(),
            'markupPercent' => (int) config('pixelforge.markup_percent'),
            'balanceNano' => $credits->balanceNano($request->user()),
            'initialPrompt' => Str::limit((string) $request->query('prompt', ''), 4000, ''),
        ]);
    }

    public function store(StoreGenerationRequest $request, CreateGeneration $create): RedirectResponse
    {
        $create->handle($request->user(), $request->validated());

        return to_route('generations.index')
            ->with('success', 'Generation queued. It will update here as Pixelforge works.');
    }
}
