<?php

use App\Support\Money;

test('it parses exact decimal amounts into nanodollars', function () {
    expect(Money::fromDecimal('0.06318'))->toBe(63_180_000)
        ->and(Money::fromDecimal('1'))->toBe(1_000_000_000)
        ->and(Money::fromDecimal('12.5'))->toBe(12_500_000_000)
        ->and(Money::fromDecimal('-0.000000001'))->toBe(-1)
        ->and(Money::fromDecimal(' 3.000000000 '))->toBe(3_000_000_000);
});

test('it rejects amounts with more precision than nanodollars', function () {
    Money::fromDecimal('0.0000000001');
})->throws(InvalidArgumentException::class);

test('it rejects malformed amounts', function () {
    Money::fromDecimal('ten dollars');
})->throws(InvalidArgumentException::class);

test('it converts token counts at a per-million rate', function () {
    expect(Money::costForTokens(16, '5.00'))->toBe(80_000)
        ->and(Money::costForTokens(1100, '40.00'))->toBe(44_000_000)
        ->and(Money::costForTokens(1528, '12.50'))->toBe(19_100_000)
        ->and(Money::costForTokens(0, '40.00'))->toBe(0);
});

test('it rounds token costs half up', function () {
    // 1 token at $5 per million is $0.000005 exactly.
    expect(Money::costForTokens(1, '5.00'))->toBe(5_000);

    // 1 token at $0.000001 per million is 0.001 nano, which rounds to zero.
    expect(Money::costForTokens(1, '0.000001'))->toBe(0)
        // 1 token at $0.0005 per million is exactly half a nano and rounds up.
        ->and(Money::costForTokens(1, '0.0005'))->toBe(1);
});

test('it sums the bills for a provider usage report', function () {
    $cost = Money::providerCost(billableUsage()['usage'], billableUsage()['rates']);

    expect($cost)->toBe(52_500_000);
});

test('it refuses to price usage without every required rate', function () {
    $rates = billableUsage()['rates'];
    unset($rates['reasoning_tokens']);

    Money::providerCost(billableUsage()['usage'], $rates);
})->throws(InvalidArgumentException::class);

test('it applies the markup with half-up rounding', function () {
    expect(Money::withMarkup(52_500_000, 25))->toBe(65_625_000)
        ->and(Money::withMarkup(1, 25))->toBe(1)
        ->and(Money::withMarkup(3, 25))->toBe(4)
        ->and(Money::withMarkup(57_780_000, 25))->toBe(72_225_000);
});

test('it formats nanodollars for display', function () {
    expect(Money::format(72_187_500))->toBe('$0.072188')
        ->and(Money::format(5_000_000_000))->toBe('$5.00')
        ->and(Money::format(-6_562_500))->toBe('-$0.006563')
        ->and(Money::format(0))->toBe('$0.00')
        ->and(Money::format(63_180_000, 2))->toBe('$0.06');
});
