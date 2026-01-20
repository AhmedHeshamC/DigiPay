<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\DTO\ParsedTransaction;

class ParsedTransactionTest extends TestCase
{
    public function test_creates_parsed_transaction_with_required_fields()
    {
        $dto = new ParsedTransaction(
            amount: 100.50,
            reference: 'REF123',
            bankProvider: 'paytech',
            date: '20250615'
        );

        $this->assertEquals(100.50, $dto->amount);
        $this->assertEquals('REF123', $dto->reference);
        $this->assertEquals('paytech', $dto->bankProvider);
        $this->assertEquals('20250615', $dto->date);
    }

    public function test_creates_parsed_transaction_with_metadata()
    {
        $metadata = ['note' => 'test', 'internal_ref' => 'ABC123'];
        $dto = new ParsedTransaction(
            amount: 50.00,
            reference: 'REF456',
            bankProvider: 'acme',
            date: '20250616',
            metadata: $metadata
        );

        $this->assertEquals($metadata, $dto->metadata);
    }

    public function test_metadata_defaults_to_empty_array()
    {
        $dto = new ParsedTransaction(
            amount: 75.00,
            reference: 'REF789',
            bankProvider: 'paytech',
            date: '20250617'
        );

        $this->assertIsArray($dto->metadata);
        $this->assertEmpty($dto->metadata);
    }

    public function test_is_immutable()
    {
        $dto = new ParsedTransaction(
            amount: 100.00,
            reference: 'REF001',
            bankProvider: 'paytech',
            date: '20250618'
        );

        // Attempting to modify should fail or create new instance
        $this->expectException(\Error::class);
        $dto->amount = 200.00;
    }

    public function test_can_be_created_from_array()
    {
        $data = [
            'amount' => 125.75,
            'reference' => 'REF999',
            'bankProvider' => 'acme',
            'date' => '20250619',
            'metadata' => ['key' => 'value'],
        ];

        $dto = ParsedTransaction::fromArray($data);

        $this->assertEquals(125.75, $dto->amount);
        $this->assertEquals('REF999', $dto->reference);
        $this->assertEquals('acme', $dto->bankProvider);
        $this->assertEquals('20250619', $dto->date);
        $this->assertEquals(['key' => 'value'], $dto->metadata);
    }

    public function test_can_convert_to_array()
    {
        $dto = new ParsedTransaction(
            amount: 99.99,
            reference: 'REF888',
            bankProvider: 'paytech',
            date: '20250620',
            metadata: ['note' => 'payment']
        );

        $array = $dto->toArray();

        $this->assertEquals(99.99, $array['amount']);
        $this->assertEquals('REF888', $array['reference']);
        $this->assertEquals('paytech', $array['bankProvider']);
        $this->assertEquals('20250620', $array['date']);
        $this->assertEquals(['note' => 'payment'], $array['metadata']);
    }
}
