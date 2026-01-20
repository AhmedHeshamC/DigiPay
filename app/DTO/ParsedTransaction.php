<?php

namespace App\DTO;

readonly class ParsedTransaction
{
    /**
     * @param float $amount Transaction amount
     * @param string $reference Bank transaction reference
     * @param string $bankProvider Bank provider name (paytech, acme, etc.)
     * @param string $date Transaction date from bank
     * @param array<string, mixed> $metadata Additional transaction metadata
     */
    public function __construct(
        public float $amount,
        public string $reference,
        public string $bankProvider,
        public string $date,
        public array $metadata = []
    ) {}

    /**
     * Create ParsedTransaction from array.
     *
     * @param array<string, mixed> $data
     * @return self
     */
    public static function fromArray(array $data): self
    {
        return new self(
            amount: (float) $data['amount'],
            reference: $data['reference'],
            bankProvider: $data['bankProvider'],
            date: $data['date'],
            metadata: $data['metadata'] ?? []
        );
    }

    /**
     * Convert ParsedTransaction to array.
     *
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'amount' => $this->amount,
            'reference' => $this->reference,
            'bankProvider' => $this->bankProvider,
            'date' => $this->date,
            'metadata' => $this->metadata,
        ];
    }
}
