<?php

namespace App\Support;

class KeywordList
{
    /**
     * @return list<string>
     */
    public static function split(?string $value): array
    {
        if ($value === null || trim($value) === '') {
            return [];
        }

        $keywords = [];

        foreach (explode(',', $value) as $part) {
            $keyword = trim($part, " \t\n\r\0\x0B,");

            if ($keyword !== '') {
                $keywords[] = $keyword;
            }
        }

        return $keywords;
    }
}
