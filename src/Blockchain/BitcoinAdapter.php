<?php

namespace Phoenix\Blockchain;

final class BitcoinAdapter implements BlockchainInterface
{
    private string $address;
    private float $balance;

    public function __construct(?string $privateKey = null)
    {
        $this->address = 'bc1q' . bin2hex(random_bytes(20));
        $this->balance = 0.0;
    }

    public function getAddress(): string
    {
        return $this->address;
    }

    public function getBalance(): string
    {
        return sprintf('%.8f BTC', $this->balance);
    }

    public function send(string $to, float $amount, string $memo = ''): string
    {
        if ($amount > $this->balance) {
            throw new \RuntimeException('Insufficient balance');
        }
        $this->balance -= $amount;

        return 'btc_tx_' . bin2hex(random_bytes(16));
    }

    public function mintToken(string $name, string $symbol, int $decimals = 8): string
    {
        return 'ordinal_' . $name . '_' . bin2hex(random_bytes(8));
    }

    public function fund(float $amount): void
    {
        $this->balance += $amount;
    }
}
