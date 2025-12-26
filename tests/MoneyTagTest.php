<?php

namespace Daugt\Commerce\Tests;

use Daugt\Commerce\Tags\DaugtCommerceTags;

class MoneyTagTest extends TestCase
{
    public function test_money_tag_formats_value_with_currency(): void
    {
        $tags = $this->makeTags([
            'value' => 10,
            'currency' => 'EUR',
            'locale' => 'de_DE',
        ]);

        $formatted = $tags->money();

        $this->assertMatchesRegularExpression('/10,00(?:\s|\x{00A0})?€/u', $formatted);
    }

    public function test_money_tag_formats_usd_locale(): void
    {
        $tags = $this->makeTags([
            'value' => 10,
            'currency' => 'USD',
            'locale' => 'en_US',
        ]);

        $formatted = $tags->money();

        $this->assertMatchesRegularExpression('/\$10\.00/', $formatted);
    }

    private function makeTags(array $params): DaugtCommerceTags
    {
        $tags = new DaugtCommerceTags();
        $tags->setProperties([
            'parser' => null,
            'content' => '',
            'context' => [],
            'params' => $params,
            'tag' => 'daugt_commerce:money',
            'tag_method' => 'money',
        ]);

        return $tags;
    }
}
