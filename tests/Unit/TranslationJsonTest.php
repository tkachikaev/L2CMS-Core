<?php

namespace Tests\Unit;

use JsonException;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class TranslationJsonTest extends TestCase
{
    /** @return array<string,array{string}> */
    public static function builtInLocales(): array
    {
        return [
            'Russian' => ['ru'],
            'English' => ['en'],
        ];
    }

    /** @throws JsonException */
    #[DataProvider('builtInLocales')]
    public function test_built_in_json_translations_have_no_case_insensitive_duplicate_keys(string $locale): void
    {
        $content = file_get_contents(lang_path($locale.'.json'));
        $this->assertIsString($content);

        /** @var array<string,string> $translations */
        $translations = json_decode($content, true, 512, JSON_THROW_ON_ERROR);
        $normalized = [];

        foreach (array_keys($translations) as $key) {
            $caseInsensitiveKey = mb_strtolower($key, 'UTF-8');

            if (array_key_exists($caseInsensitiveKey, $normalized)) {
                $this->fail("Case-insensitive duplicate translation keys: {$normalized[$caseInsensitiveKey]} and {$key}");
            }

            $normalized[$caseInsensitiveKey] = $key;
        }
    }
}
