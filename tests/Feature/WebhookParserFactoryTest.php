<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Services\Parsers\WebhookParserFactory;
use App\Services\Parsers\PayTechParser;
use App\Services\Parsers\AcmeParser;
use App\Services\Parsers\WebhookParserInterface;
use InvalidArgumentException;

class WebhookParserFactoryTest extends TestCase
{
    public function test_returns_paytech_parser_for_paytech_bank()
    {
        $parser = WebhookParserFactory::create('paytech');

        $this->assertInstanceOf(PayTechParser::class, $parser);
        $this->assertInstanceOf(WebhookParserInterface::class, $parser);
    }

    public function test_returns_acme_parser_for_acme_bank()
    {
        $parser = WebhookParserFactory::create('acme');

        $this->assertInstanceOf(AcmeParser::class, $parser);
        $this->assertInstanceOf(WebhookParserInterface::class, $parser);
    }

    public function test_is_case_insensitive()
    {
        $parser1 = WebhookParserFactory::create('PayTech');
        $parser2 = WebhookParserFactory::create('PAYTECH');
        $parser3 = WebhookParserFactory::create('paytech');

        $this->assertInstanceOf(PayTechParser::class, $parser1);
        $this->assertInstanceOf(PayTechParser::class, $parser2);
        $this->assertInstanceOf(PayTechParser::class, $parser3);
    }

    public function test_throws_exception_for_unknown_bank()
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Unknown bank provider: unknown');

        WebhookParserFactory::create('unknown');
    }

    public function test_returns_new_instance_each_time()
    {
        $parser1 = WebhookParserFactory::create('paytech');
        $parser2 = WebhookParserFactory::create('paytech');

        $this->assertNotSame($parser1, $parser2);
    }
}
