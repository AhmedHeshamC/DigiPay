<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Services\Parsers\PayTechParser;

class PayTechParserTest extends TestCase
{
    public function test_parses_basic_paytech_payload()
    {
        $payload = '20250615156,50#202506159000001#note/debt payment march/internal_reference/A462JE81';
        $parser = new PayTechParser();

        $result = $parser->parse($payload);

        $this->assertCount(1, $result);
        $this->assertEquals(50.00, $result[0]['amount']);
        $this->assertEquals('202506159000001', $result[0]['reference']);
        $this->assertEquals('debt payment march', $result[0]['metadata']['note']);
        $this->assertEquals('A462JE81', $result[0]['metadata']['internal_reference']);
    }

    public function test_parses_amount_with_decimal_places()
    {
        $payload = '20250615156,125.50#REF123';
        $parser = new PayTechParser();

        $result = $parser->parse($payload);

        $this->assertEquals(125.50, $result[0]['amount']);
    }

    public function test_returns_empty_array_for_empty_payload()
    {
        $payload = '';
        $parser = new PayTechParser();

        $result = $parser->parse($payload);

        $this->assertIsArray($result);
        $this->assertCount(0, $result);
    }

    public function test_parses_multiple_lines_in_single_payload()
    {
        $payload = "20250615156,50#REF1#note/test1\n20250615157,75#REF2#note/test2";
        $parser = new PayTechParser();

        $result = $parser->parse($payload);

        $this->assertCount(2, $result);
        $this->assertEquals(50.00, $result[0]['amount']);
        $this->assertEquals('REF1', $result[0]['reference']);
        $this->assertEquals(75.00, $result[1]['amount']);
        $this->assertEquals('REF2', $result[1]['reference']);
    }

    public function test_handles_missing_metadata_gracefully()
    {
        $payload = '20250615156,50#REF123';
        $parser = new PayTechParser();

        $result = $parser->parse($payload);

        $this->assertEquals(50.00, $result[0]['amount']);
        $this->assertEquals('REF123', $result[0]['reference']);
        $this->assertIsArray($result[0]['metadata']);
        $this->assertEmpty($result[0]['metadata']);
    }

    public function test_implements_webhook_parser_interface()
    {
        $parser = new PayTechParser();
        $this->assertInstanceOf('App\Services\Parsers\WebhookParserInterface', $parser);
    }
}
