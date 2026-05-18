<?php

namespace App\Shared\Helpers;

class Cleaner
{
    public static function cleanString(string $input): string
    {
        $formattedText = preg_replace('/\s+/', ' ', trim($input));
        $formattedText = mb_convert_case($formattedText, MB_CASE_TITLE, "UTF-8");

        return $formattedText;
    }
}