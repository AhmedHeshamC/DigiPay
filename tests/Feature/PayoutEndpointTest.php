<?php

namespace Tests\Feature;

use Tests\TestCase;

class PayoutEndpointTest extends TestCase
{
    public function test_returns_xml_with_required_fields()
    {
        $data = [
            'date' => '2025-02-25 06:33:00+03',
            'amount' => 177.39,
            'currency' => 'SAR',
        ];

        $response = $this->postJson('/api/v1/payouts/xml', $data, [
            'Accept' => 'application/xml',
        ]);

        $response->assertStatus(200);
        $response->assertHeader('content-type', 'application/xml; charset=UTF-8');
        $this->assertStringContainsString('<PaymentRequestMessage>', $response->getContent());
        $this->assertStringContainsString('<Date>2025-02-25 06:33:00+03</Date>', $response->getContent());
        $this->assertStringContainsString('<Amount>177.39</Amount>', $response->getContent());
        $this->assertStringContainsString('<Currency>SAR</Currency>', $response->getContent());
    }

    public function test_includes_optional_notes_when_provided()
    {
        $data = [
            'date' => '2025-02-25 06:33:00+03',
            'amount' => 100.00,
            'currency' => 'USD',
            'notes' => 'Payment for services',
        ];

        $response = $this->postJson('/api/v1/payouts/xml', $data, [
            'Accept' => 'application/xml',
        ]);

        $response->assertStatus(200);
        $this->assertStringContainsString('<Notes>Payment for services</Notes>', $response->getContent());
    }

    public function test_omits_payment_type_when_99()
    {
        $data = [
            'date' => '2025-02-25 06:33:00+03',
            'amount' => 100.00,
            'currency' => 'USD',
            'paymentType' => 99,
        ];

        $response = $this->postJson('/api/v1/payouts/xml', $data, [
            'Accept' => 'application/xml',
        ]);

        $response->assertStatus(200);
        $this->assertStringNotContainsString('<PaymentType>', $response->getContent());
    }

    public function test_requires_date_field()
    {
        $data = [
            'amount' => 100.00,
            'currency' => 'USD',
        ];

        $response = $this->postJson('/api/v1/payouts/xml', $data, [
            'Accept' => 'application/xml',
        ]);

        $response->assertStatus(422); // Validation error
    }

    public function test_requires_amount_field()
    {
        $data = [
            'date' => '2025-02-25 06:33:00+03',
            'currency' => 'USD',
        ];

        $response = $this->postJson('/api/v1/payouts/xml', $data, [
            'Accept' => 'application/xml',
        ]);

        $response->assertStatus(422);
    }

    public function test_requires_currency_field()
    {
        $data = [
            'date' => '2025-02-25 06:33:00+03',
            'amount' => 100.00,
        ];

        $response = $this->postJson('/api/v1/payouts/xml', $data, [
            'Accept' => 'application/xml',
        ]);

        $response->assertStatus(422);
    }

    public function test_handles_all_fields()
    {
        $data = [
            'date' => '2025-02-25 06:33:00+03',
            'amount' => 500.00,
            'currency' => 'EUR',
            'notes' => 'Test payment',
            'paymentType' => 1,
            'chargeDetails' => 'OUR',
        ];

        $response = $this->postJson('/api/v1/payouts/xml', $data, [
            'Accept' => 'application/xml',
        ]);

        $response->assertStatus(200);
        $content = $response->getContent();
        $this->assertStringContainsString('<Notes>Test payment</Notes>', $content);
        $this->assertStringContainsString('<PaymentType>1</PaymentType>', $content);
        $this->assertStringContainsString('<ChargeDetails>OUR</ChargeDetails>', $content);
    }

    public function test_returns_utf8_encoded_xml()
    {
        $data = [
            'date' => '2025-02-25 06:33:00+03',
            'amount' => 100.00,
            'currency' => 'SAR',
            'notes' => 'مبلغ الدفع', // Arabic text
        ];

        $response = $this->postJson('/api/v1/payouts/xml', $data, [
            'Accept' => 'application/xml',
        ]);

        $response->assertStatus(200);
        $response->assertHeader('content-type', 'application/xml; charset=UTF-8');
        $this->assertStringContainsString('مبلغ الدفع', $response->getContent());
    }
}
