<?php

namespace Phoenix\Blockchain;

final class EthereumAdapter implements BlockchainInterface
{
    private string $address;
    private string $privateKey;
    private float $balance;

    public function __construct(?string $privateKey = null)
    {
        $this->privateKey = $privateKey ?? bin2hex(random_bytes(32));
        $this->address = '0x' . bin2hex(random_bytes(20));
        $this->balance = 0.0;
    }

    public function getAddress(): string
    {
        return $this->address;
    }

    public function getBalance(): string
    {
        return sprintf('%.6f ETH', $this->balance);
    }

    public function send(string $to, float $amount, string $memo = ''): string
    {
        if ($amount > $this->balance) {
            throw new \RuntimeException('Insufficient ETH balance');
        }
        $this->balance -= $amount;

        return '0x' . bin2hex(random_bytes(32));
    }

    public function mintToken(string $name, string $symbol, int $decimals = 18): string
    {
        return 'erc20_' . $symbol . '_' . bin2hex(random_bytes(8));
    }

    public function getPrivateKey(): string
    {
        return $this->privateKey;
    }

    public function fund(float $amount): void
    {
        $this->balance += $amount;
    }
}
