<?php

declare(strict_types=1);

namespace Beziworld\Ambro\Data;

use Beziworld\Ambro\Support\Values;

/**
 * Cash on delivery: the amount (decimal string) and the bank account the money is transferred to.
 *
 * The currency is set on the shipment; for foreign recipients it must match
 * the recipient country (error 1143), and Germany does not support COD
 * (error 1139).
 */
final readonly class CashOnDelivery
{
    public string $amount;

    public string $accountNumber;

    public function __construct(string|int|float $amount, string $accountNumber)
    {
        $this->amount = Values::decimal($amount);
        $this->accountNumber = (string) preg_replace('/\D+/', '', $accountNumber);
    }

    /**
     * @return array<string, string>
     */
    public function toSoap(): array
    {
        return [
            'Amount' => $this->amount,
            'RetAccountNo' => $this->accountNumber,
        ];
    }
}
