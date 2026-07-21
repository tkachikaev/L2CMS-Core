<?php

namespace Tests\Unit;

use App\Services\GameAccounts\MobiusClassNames;
use Tests\TestCase;

class MobiusClassNamesTest extends TestCase
{
    public function test_known_legacy_class_keeps_its_label(): void
    {
        $this->assertSame('Dark Mystic', app(MobiusClassNames::class)->name(38));
    }

    public function test_unknown_modern_class_uses_safe_numeric_fallback(): void
    {
        $this->assertSame('Class #123', app(MobiusClassNames::class)->name(123));
    }
}
