<?php

namespace App\Http\Controllers;

use App\Http\Requests\PurchaseCreditsRequest;
use App\Http\Resources\CreditTransactionResource;
use App\Services\CreditLedger;
use App\Support\Money;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class CreditController extends Controller
{
    public function index(Request $request, CreditLedger $credits): Response
    {
        $transactions = $request->user()
            ->creditTransactions()
            ->latest('id')
            ->paginate(20);

        return Inertia::render('Credits/Index', [
            'balanceNano' => $credits->balanceNano($request->user()),
            'transactions' => CreditTransactionResource::collection($transactions),
            'presets' => [5, 10, 25, 50],
            'maxPurchase' => 500,
            'markupPercent' => (int) config('pixelforge.markup_percent'),
        ]);
    }

    public function store(PurchaseCreditsRequest $request, CreditLedger $credits): RedirectResponse
    {
        $amountNano = Money::fromDecimal((string) $request->validated('amount'));

        $credits->purchase(
            $request->user(),
            $amountNano,
            'Credit purchase (demo)',
        );

        return back()->with('success', Money::format($amountNano).' was added to your balance.');
    }
}
