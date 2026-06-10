<?php

namespace Phoenix\AI;

use Phoenix\Blockchain\BlockchainInterface;

class CryptoAgent extends Agent
{
    private array $wallets = [];

    public function __construct(
        string $name = 'CryptoAgent',
        string $role = 'You are a crypto trading AI agent',
        string $goal = 'Manage cryptocurrency portfolios and execute trades',
    ) {
        parent::__construct($name, $role, $goal);
    }

    public function addWallet(string $chain, BlockchainInterface $wallet): void
    {
        $this->wallets[$chain] = $wallet;
        $this->notify('wallet_added', ['chain' => $chain, 'address' => $wallet->getAddress()]);
    }

    public function getPortfolio(): array
    {
        $portfolio = [];
        foreach ($this->wallets as $chain => $wallet) {
            $portfolio[$chain] = [
                'address' => $wallet->getAddress(),
                'balance' => $wallet->getBalance(),
            ];
        }

        return $portfolio;
    }

    public function analyze(string $input): string
    {
        $keywords = ['buy', 'sell', 'trade', 'swap', 'send', 'balance', 'portfolio'];
        $inputLower = strtolower($input);

        foreach ($keywords as $keyword) {
            if (str_contains($inputLower, $keyword)) {
                return $this->handleAction($keyword, $input);
            }
        }

        return $this->think($input);
    }

    private function handleAction(string $action, string $input): string
    {
        return match ($action) {
            'balance' => $this->formatPortfolio(),
            'portfolio' => $this->formatPortfolio(),
            default => "CryptoAgent: {$action} action requested - " . $this->think($input),
        };
    }

    private function formatPortfolio(): string
    {
        $portfolio = $this->getPortfolio();
        if (empty($portfolio)) {
            return 'No wallets connected.';
        }
        $output = "Portfolio:\n";
        foreach ($portfolio as $chain => $data) {
            $output .= "  {$chain}: {$data['balance']} ({$data['address']})\n";
        }

        return $output;
    }
}
