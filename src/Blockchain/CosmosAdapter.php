<?php

namespace Phoenix\Blockchain;

final class CosmosAdapter implements BlockchainInterface
{
    private string $address;
    private float $balance;

    public function __construct(?string $privateKey = null)
    {
        $this->address = 'cosmos1' . bin2hex(random_bytes(20));
        $this->balance = 0.0;
    }

    public function getAddress(): string
    {
        return $this->address;
    }

    public function getBalance(): string
    {
        return sprintf('%.6f ATOM', $this->balance);
    }

    public function send(string $to, float $amount, string $memo = ''): string
    {
        if ($amount > $this->balance) {
            throw new \RuntimeException('Insufficient ATOM balance');
        }
        $this->balance -= $amount;

        return 'cosmos_tx_' . bin2hex(random_bytes(16));
    }

    public function mintToken(string $name, string $symbol, int $decimals = 6): string
    {
        return 'cw20_' . $symbol . '_' . bin2hex(random_bytes(8));
    }

    public function fund(float $amount): void
    {
        $this->balance += $amount;
    }
}
