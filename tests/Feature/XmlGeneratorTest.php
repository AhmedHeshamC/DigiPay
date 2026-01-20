<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Services\XmlGeneratorService;

class XmlGeneratorTest extends TestCase
{
    public function test_generates_xml_with_required_fields()
    {
        $data = [
            'date' => '2025-02-25 06:33:00+03',
            'amount' => 177.39,
            'currency' => 'SAR',
        ];

        $service = new XmlGeneratorService();
        $xml = $service->generate($data);

        $this->assertStringContainsString('<PaymentRequestMessage>', $xml);
        $this->assertStringContainsString('<TransferInfo>', $xml);
        $this->assertStringContainsString('<Date>2025-02-25 06:33:00+03</Date>', $xml);
        $this->assertStringContainsString('<Amount>177.39</Amount>', $xml);
        $this->assertStringContainsString('<Currency>SAR</Currency>', $xml);
        $this->assertStringContainsString('</TransferInfo>', $xml);
        $this->assertStringContainsString('</PaymentRequestMessage>', $xml);
    }

    public function test_omits_notes_tag_when_empty()
    {
        $data = [
            'date' => '2025-02-25 06:33:00+03',
            'amount' => 177.39,
            'currency' => 'SAR',
            'notes' => '', // Empty - should omit
        ];

        $service = new XmlGeneratorService();
        $xml = $service->generate($data);

        $this->assertStringNotContainsString('<Notes>', $xml);
    }

    public function test_includes_notes_tag_when_provided()
    {
        $data = [
            'date' => '2025-02-25 06:33:00+03',
            'amount' => 177.39,
            'currency' => 'SAR',
            'notes' => 'Payment for services',
        ];

        $service = new XmlGeneratorService();
        $xml = $service->generate($data);

        $this->assertStringContainsString('<Notes>Payment for services</Notes>', $xml);
    }

    public function test_omits_payment_type_when_value_is_99()
    {
        $data = [
            'date' => '2025-02-25 06:33:00+03',
            'amount' => 177.39,
            'currency' => 'SAR',
            'paymentType' => 99, // Should omit
        ];

        $service = new XmlGeneratorService();
        $xml = $service->generate($data);

        $this->assertStringNotContainsString('<PaymentType>', $xml);
    }

    public function test_includes_payment_type_when_not_99()
    {
        $data = [
            'date' => '2025-02-25 06:33:00+03',
            'amount' => 177.39,
            'currency' => 'SAR',
            'paymentType' => 1, // Should include
        ];

        $service = new XmlGeneratorService();
        $xml = $service->generate($data);

        $this->assertStringContainsString('<PaymentType>1</PaymentType>', $xml);
    }

    public function test_omits_charge_details_when_value_is_sha()
    {
        $data = [
            'date' => '2025-02-25 06:33:00+03',
            'amount' => 177.39,
            'currency' => 'SAR',
            'chargeDetails' => 'SHA', // Should omit
        ];

        $service = new XmlGeneratorService();
        $xml = $service->generate($data);

        $this->assertStringNotContainsString('<ChargeDetails>', $xml);
    }

    public function test_includes_charge_details_when_not_sha()
    {
        $data = [
            'date' => '2025-02-25 06:33:00+03',
            'amount' => 177.39,
            'currency' => 'SAR',
            'chargeDetails' => 'OUR',
        ];

        $service = new XmlGeneratorService();
        $xml = $service->generate($data);

        $this->assertStringContainsString('<ChargeDetails>OUR</ChargeDetails>', $xml);
    }

    public function test_generates_valid_xml_structure()
    {
        $data = [
            'date' => '2025-02-25 06:33:00+03',
            'amount' => 100.00,
            'currency' => 'USD',
            'notes' => 'Test payment',
            'paymentType' => 1,
            'chargeDetails' => 'OUR',
        ];

        $service = new XmlGeneratorService();
        $xml = $service->generate($data);

        // Verify valid XML by attempting to parse it
        $parsed = simplexml_load_string($xml);
        $this->assertInstanceOf(\SimpleXMLElement::class, $parsed);
        $this->assertEquals('2025-02-25 06:33:00+03', (string) $parsed->TransferInfo->Date);
        $this->assertEquals(100.00, (float) $parsed->TransferInfo->Amount);
        $this->assertEquals('USD', (string) $parsed->TransferInfo->Currency);
    }

    public function test_handles_all_optional_fields_missing()
    {
        $data = [
            'date' => '2025-02-25 06:33:00+03',
            'amount' => 50.00,
            'currency' => 'EUR',
        ];

        $service = new XmlGeneratorService();
        $xml = $service->generate($data);

        // Should only have required fields
        $this->assertStringContainsString('<Date>', $xml);
        $this->assertStringContainsString('<Amount>', $xml);
        $this->assertStringContainsString('<Currency>', $xml);
        $this->assertStringNotContainsString('<Notes>', $xml);
        $this->assertStringNotContainsString('<PaymentType>', $xml);
        $this->assertStringNotContainsString('<ChargeDetails>', $xml);
    }

    public function test_uses_utf8_encoding()
    {
        $data = [
            'date' => '2025-02-25 06:33:00+03',
            'amount' => 100.00,
            'currency' => 'SAR',
            'notes' => 'مبلغ الدفع', // Arabic text
        ];

        $service = new XmlGeneratorService();
        $xml = $service->generate($data);

        // Verify UTF-8 encoding works
        $this->assertStringContainsString('مبلغ الدفع', $xml);
        $parsed = simplexml_load_string($xml);
        $this->assertEquals('مبلغ الدفع', (string) $parsed->TransferInfo->Notes);
    }
}
