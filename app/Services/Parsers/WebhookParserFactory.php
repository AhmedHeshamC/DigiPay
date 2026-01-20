<?php

namespace App\Services\Parsers;

use InvalidArgumentException;

class WebhookParserFactory
{
    /**
     * Create a parser instance for the given bank provider.
     *
     * @param string $bankProvider Bank provider name (e.g., 'paytech', 'acme')
     * @return WebhookParserInterface Parser instance
     * @throws InvalidArgumentException If bank provider is unknown
     */
    public static function create(string $bankProvider): WebhookParserInterface
    {
        return match (strtolower($bankProvider)) {
            'paytech' => new PayTechParser(),
            'acme' => new AcmeParser(),
            default => throw new InvalidArgumentException("Unknown bank provider: {$bankProvider}"),
        };
    }
}
