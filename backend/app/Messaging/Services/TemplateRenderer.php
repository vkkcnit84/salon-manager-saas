<?php

namespace App\Messaging\Services;

class TemplateRenderer
{
    /** Replaces {{1}}, {{2}}... with the positional variables. */
    public static function render(string $body, array $variables): string
    {
        foreach (array_values($variables) as $i => $v) {
            $body = str_replace('{{'.($i + 1).'}}', (string) $v, $body);
        }

        return $body;
    }
}
