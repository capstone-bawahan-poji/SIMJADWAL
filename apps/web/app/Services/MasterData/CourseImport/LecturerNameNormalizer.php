<?php

namespace App\Services\MasterData\CourseImport;

final class LecturerNameNormalizer
{
    private const PREFIX_TITLES = '/^((prof|dr|ir|drs|dra|hj|h)\.\s*)+/u';

    public static function normalize(string $name): string
    {
        $name = mb_strtolower(trim((string) preg_replace('/\s+/u', ' ', $name)));
        $name = (string) preg_replace(self::PREFIX_TITLES, '', $name);

        $name = explode(',', $name)[0];

        $tokens = explode(' ', trim($name));
        while (count($tokens) > 1 && str_contains((string) end($tokens), '.')) {
            array_pop($tokens);
        }

        $name = (string) preg_replace('/[^\p{L}\p{N}\s]/u', '', implode(' ', $tokens));

        return trim((string) preg_replace('/\s+/u', ' ', $name));
    }
}