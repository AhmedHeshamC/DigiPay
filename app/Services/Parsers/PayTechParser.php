<?php

namespace App\Services\Parsers;

class PayTechParser implements WebhookParserInterface
{
    /**
     * Parse PayTech webhook payload.
     *
     * Format: date,amount#reference#key1/value1/key2/value2
     * Example: 20250615156,50#202506159000001#note/debt payment/internal_reference/A462JE81
     *
     * @param string $payload Raw webhook payload
     * @return array Array of parsed transaction data
     */
    public function parse(string $payload): array
    {
        if (empty($payload)) {
            return [];
        }

        $transactions = [];

        // Split by newlines for multi-line payloads
        $lines = explode("\n", trim($payload));

        foreach ($lines as $line) {
            $line = trim($line);
            if (empty($line)) {
                continue;
            }

            $transaction = $this->parseLine($line);
            if ($transaction !== null) {
                $transactions[] = $transaction;
            }
        }

        return $transactions;
    }

    /**
     * Parse a single PayTech line.
     *
     * @param string $line Single line from payload
     * @return array|null Parsed transaction or null if invalid
     */
    private function parseLine(string $line): ?array
    {
        // Split by # first: date,amount#reference#metadata
        $parts = explode('#', $line);

        if (count($parts) < 2) {
            return null;
        }

        // Parse date and amount (first segment)
        $firstSegment = $parts[0];
        if (!str_contains($firstSegment, ',')) {
            return null;
        }

        [$date, $amount] = explode(',', $firstSegment, 2);

        // Reference is second segment
        $reference = $parts[1];

        // Parse metadata (third segment onwards)
        $metadata = [];
        if (isset($parts[2])) {
            $metadata = $this->parseMetadata($parts[2]);
        }

        return [
            'date' => $date,
            'amount' => (float) $amount,
            'reference' => $reference,
            'metadata' => $metadata,
        ];
    }

    /**
     * Parse key/value pairs from metadata segment.
     *
     * Format: key1/value1/key2/value2
     *
     * @param string $metadataString Metadata segment
     * @return array Associative array of metadata
     */
    private function parseMetadata(string $metadataString): array
    {
        $metadata = [];
        $pairs = explode('/', $metadataString);

        for ($i = 0; $i < count($pairs); $i += 2) {
            if (isset($pairs[$i + 1])) {
                $key = $pairs[$i];
                $value = $pairs[$i + 1];
                $metadata[$key] = $value;
            }
        }

        return $metadata;
    }
}
