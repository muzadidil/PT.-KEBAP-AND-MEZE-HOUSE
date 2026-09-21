<?php

namespace Tests\Feature;

use Tests\TestCase;

/**
 * Tiap kunci bahasa Inggris punya terjemahan Indonesianya, dan sebaliknya.
 *
 * Kunci yang belum diterjemahkan tidak memunculkan galat apa pun: Laravel
 * jatuh ke bahasa bawaan, jadi satu label berbahasa Inggris muncul di tengah
 * halaman berbahasa Indonesia dan tidak ada yang tahu sampai ada yang
 * melihatnya. Yang sebaliknya lebih buruk lagi — kunci yang hanya ada di
 * berkas Indonesia berarti ada label yang tidak pernah dipakai sama sekali.
 */
class TranslationKeysTest extends TestCase
{
    /** @return array<int, string> */
    protected function keys(string $path): array
    {
        $flatten = function (array $lines, string $prefix = '') use (&$flatten): array {
            $keys = [];

            foreach ($lines as $key => $value) {
                $full = $prefix === '' ? (string) $key : $prefix.'.'.$key;

                $keys = is_array($value)
                    ? [...$keys, ...$flatten($value, $full)]
                    : [...$keys, $full];
            }

            return $keys;
        };

        $keys = $flatten(require $path);
        sort($keys);

        return $keys;
    }

    public function test_kedua_bahasa_punya_berkas_yang_sama(): void
    {
        $english = array_map('basename', glob(lang_path('en/*.php')));
        $indonesian = array_map('basename', glob(lang_path('id/*.php')));

        sort($english);
        sort($indonesian);

        $this->assertSame($english, $indonesian);
    }

    public function test_kedua_bahasa_punya_kunci_yang_sama(): void
    {
        foreach (glob(lang_path('en/*.php')) as $file) {
            $name = basename($file);

            $this->assertSame(
                $this->keys($file),
                $this->keys(lang_path('id/'.$name)),
                "Kunci di lang/en/{$name} dan lang/id/{$name} tidak sama.",
            );
        }
    }
}
