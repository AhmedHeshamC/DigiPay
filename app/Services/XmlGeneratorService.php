<?php

namespace App\Services;

class XmlGeneratorService
{
    /**
     * Generate XML from array data.
     *
     * @param array<string, mixed> $data
     * @return string XML output
     */
    public function generate(array $data): string
    {
        $xml = '<?xml version="1.0" encoding="UTF-8"?>';
        $xml .= '<PaymentRequestMessage>';
        $xml .= '<TransferInfo>';

        // Required fields
        $xml .= '<Date>' . htmlspecialchars($data['date']) . '</Date>';
        $xml .= '<Amount>' . htmlspecialchars($data['amount']) . '</Amount>';
        $xml .= '<Currency>' . htmlspecialchars($data['currency']) . '</Currency>';

        // Optional: Notes (omit if empty)
        if (!empty($data['notes'])) {
            $xml .= '<Notes>' . htmlspecialchars($data['notes']) . '</Notes>';
        }

        // Optional: PaymentType (omit if 99)
        if (isset($data['paymentType']) && $data['paymentType'] != 99) {
            $xml .= '<PaymentType>' . htmlspecialchars($data['paymentType']) . '</PaymentType>';
        }

        // Optional: ChargeDetails (omit if SHA)
        if (isset($data['chargeDetails']) && strtoupper($data['chargeDetails']) !== 'SHA') {
            $xml .= '<ChargeDetails>' . htmlspecialchars($data['chargeDetails']) . '</ChargeDetails>';
        }

        $xml .= '</TransferInfo>';
        $xml .= '</PaymentRequestMessage>';

        return $xml;
    }
}
