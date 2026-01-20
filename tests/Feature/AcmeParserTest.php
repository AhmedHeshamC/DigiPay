<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Services\Parsers\AcmeParser;

class AcmeParserTest extends TestCase
{
    public function test_parses_basic_acme_payload()
    {
        // Acme format: date//amount//reference
        $payload = '20250615//99.50//ACME-REF-001';
        $parser = new AcmeParser();

        $result = $parser->parse($payload);

        $this->assertCount(1, $result);
        $this->assertEquals('20250615', $result[0]['date']);
        $this->assertEquals(99.50, $result[0]['amount']);
        $this->assertEquals('ACME-REF-001', $result[0]['reference']);
    }

    public function test_parses_amount_with_decimal_places()
    {
        $payload = '20250615//125.75//ACME-REF-002';
        $parser = new AcmeParser();

        $result = $parser->parse($payload);

        $this->assertEquals(125.75, $result[0]['amount']);
    }

    public function test_returns_empty_array_for_empty_payload()
    {
        $payload = '';
        $parser = new AcmeParser();

        $result = $parser->parse($payload);

        $this->assertIsArray($result);
        $this->assertCount(0, $result);
    }

    public function test_parses_multiple_lines_in_single_payload()
    {
        $payload = "20250615//50.00//ACME-REF1\n20250615//75.00//ACME-REF2";
        $parser = new AcmeParser();

        $result = $parser->parse($payload);

        $this->assertCount(2, $result);
        $this->assertEquals(50.00, $result[0]['amount']);
        $this->assertEquals('ACME-REF1', $result[0]['reference']);
        $this->assertEquals(75.00, $result[1]['amount']);
        $this->assertEquals('ACME-REF2', $result[1]['reference']);
    }

    public function test_handles_payload_with_extra_segments()
    {
        // Acme may include additional segments after reference
        $payload = '20250615//50.00//ACME-REF1//extra//data';
        $parser = new AcmeParser();

        $result = $parser->parse($payload);

        $this->assertEquals(50.00, $result[0]['amount']);
        $this->assertEquals('ACME-REF1', $result[0]['reference']);
        // Extra segments can be stored in metadata
        $this->assertIsArray($result[0]['metadata']);
    }

    public function test_implements_webhook_parser_interface()
    {
        $parser = new AcmeParser();
        $this->assertInstanceOf('App\Services\Parsers\WebhookParserInterface', $parser);
    }
}
