<?php
declare(strict_types=1);

namespace RetroWEB\Transport\Direct;

final class SmartInput
{
    public function resolve(string $input): string
    {
        $input = trim($input);
        if ($input === '') return '';

        if (preg_match('#^https?://#i', $input)) return $input;

        // A host name (optionally followed by a path/query) is a URL. Spaces,
        // dots at the edges and email-like input deliberately make it a search.
        if (!str_contains($input, ' ')
            && !str_contains($input, '@')
            && preg_match('/^(?:[a-z0-9](?:[a-z0-9-]{0,62}[a-z0-9])?\.)+[a-z]{2,63}(?::[0-9]{1,5})?(?:[\/\?#].*)?$/i', $input)) {
            return 'https://' . $input;
        }

        return 'https://html.duckduckgo.com/html/?q=' . rawurlencode(mb_substr($input, 0, 200, 'UTF-8'));
    }
}
