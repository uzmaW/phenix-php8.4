<?php

namespace Phoenix\Blockchain;

final class SolanaAdapter implements BlockchainInterface
{
    private string $address;
    private float $balance;

    public function __construct(?string $privateKey = null)
    {
        $this->address = base58_encode(random_bytes(32));
        $this->balance = 0.0;
    }

    public function getAddress(): string
    {
        return $this->address;
    }

    public function getBalance(): string
    {
        return sprintf('%.9f SOL', $this->balance);
    }

    public function send(string $to, float $amount, string $memo = ''): string
    {
        if ($amount > $this->balance) {
            throw new \RuntimeException('Insufficient SOL balance');
        }
        $this->balance -= $amount;
        return bin2hex(random_bytes(32));
    }

    public function mintToken(string $name, string $symbol, int $decimals = 9): string
    {
        return 'spl_' . $symbol . '_' . bin2hex(random_bytes(8));
    }

    public function fund(float $amount): void
    {
        $this->balance += $amount;
    }
}
