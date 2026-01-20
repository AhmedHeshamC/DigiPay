<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Services\Parsers\WebhookParserInterface;

class WebhookParserInterfaceTest extends TestCase
{
    public function test_interface_exists()
    {
        $this->assertTrue(interface_exists('App\Services\Parsers\WebhookParserInterface'));
    }

    public function test_interface_has_parse_method()
    {
        $reflection = new \ReflectionClass(WebhookParserInterface::class);

        $this->assertTrue($reflection->hasMethod('parse'));
    }

    public function test_concrete_class_can_implement_interface()
    {
        // Create a dummy parser to verify interface is implementable
        $dummyParser = new class implements WebhookParserInterface {
            public function parse(string $payload): array
            {
                return [];
            }
        };

        $this->assertInstanceOf(WebhookParserInterface::class, $dummyParser);
        $result = $dummyParser->parse('test payload');
        $this->assertIsArray($result);
    }
}
