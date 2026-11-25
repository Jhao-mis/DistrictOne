<?php

if (!function_exists('format_person_name')) {
    function format_person_name(?string $name): string
    {
        $name = trim((string) preg_replace('/\s+/', ' ', trim((string) $name)));
        if ($name === '') {
            return '';
        }

        if (function_exists('mb_convert_case')) {
            return mb_convert_case($name, MB_CASE_TITLE, 'UTF-8');
        }

        return ucwords(strtolower($name));
    }
}
