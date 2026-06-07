<?php

namespace Phoenix\Blockchain;

interface BlockchainInterface
{
    public function getAddress(): string;
    public function getBalance(): string;
    public function send(string $to, float $amount, string $memo = ''): string;
    public function mintToken(string $name, string $symbol, int $decimals = 8): string;
}
