<?php

namespace Daugt\Commerce\Tests;

use Daugt\Commerce\Support\MoneyFormatter;

class MoneyFormatterTest extends TestCase
{
    public function test_format_returns_string_with_currency_and_number(): void
    {
        $formatted = MoneyFormatter::format(10, 'EUR', 'de_DE');

        $this->assertIsString($formatted);
        $this->assertMatchesRegularExpression('/10/', $formatted);
        $this->assertTrue(
            str_contains($formatted, '€') || str_contains($formatted, 'EUR')
        );
    }

    public function test_fallback_formatting_when_intl_unavailable(): void
    {
        if (class_exists(\NumberFormatter::class)) {
            $this->markTestSkipped('Intl NumberFormatter available; fallback not used.');
        }

        $this->assertSame('€10,00', MoneyFormatter::format(10, 'EUR', 'de_DE'));
        $this->assertSame('$10.00', MoneyFormatter::format(10, 'USD', 'en_US'));
    }
}
