<?php

namespace Phoenix\AI;

use Phoenix\Core\Traits\Observable;

class Agent
{
    use Observable;

    protected string $name;
    protected string $role;
    protected string $goal;
    protected array $memory = [];
    protected bool $running = false;
    private string $apiKey;
    private string $model;
    private string $apiUrl;

    public function __construct(
        string $name = 'Agent',
        string $role = 'You are a helpful AI agent',
        string $goal = 'Help users achieve their goals',
        ?string $apiKey = null,
        string $model = 'gpt-4o',
        string $apiUrl = 'https://api.openai.com/v1/chat/completions'
    ) {
        $this->name = $name;
        $this->role = $role;
        $this->goal = $goal;
        $this->apiKey = $apiKey ?? getenv('OPENAI_API_KEY') ?: '';
        $this->model = $model;
        $this->apiUrl = $apiUrl;
    }

    public function getName(): string { return $this->name; }
    public function getRole(): string { return $this->role; }
    public function getGoal(): string { return $this->goal; }
    public function getMemory(): array { return $this->memory; }

    public function think(string $input): string
    {
        $this->memory[] = ['role' => 'user', 'content' => $input];
        $response = $this->process($input);
        $this->memory[] = ['role' => 'assistant', 'content' => $response];
        $this->notify('thought', ['input' => $input, 'output' => $response]);
        return $response;
    }

    protected function process(string $input): string
    {
        if (empty($this->apiKey)) {
            return "Agent {$this->name} processing: {$input}";
        }

        $messages = [
            ['role' => 'system', 'content' => "{$this->role}. Goal: {$this->goal}"],
            ...array_slice($this->memory, -20),
        ];

        $payload = json_encode([
            'model' => $this->model,
            'messages' => $messages,
            'temperature' => 0.7,
            'max_tokens' => 1024,
        ]);

        $ch = curl_init($this->apiUrl);
        curl_setopt_array($ch, [
            CURLOPT_POST => true,
            CURLOPT_HTTPHEADER => [
                'Content-Type: application/json',
                'Authorization: Bearer ' . $this->apiKey,
            ],
            CURLOPT_POSTFIELDS => $payload,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 60,
            CURLOPT_SSL_VERIFYPEER => true,
        ]);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);

        if ($response === false) {
            throw new \RuntimeException("OpenAI API request failed: {$error}");
        }

        $data = json_decode($response, true);

        if ($httpCode !== 200) {
            $message = $data['error']['message'] ?? "HTTP {$httpCode}";
            throw new \RuntimeException("OpenAI API error: {$message}");
        }

        return $data['choices'][0]['message']['content'] ?? '';
    }

    public function start(): void
    {
        $this->running = true;
        $this->notify('started', ['agent' => $this->name]);
    }

    public function stop(): void
    {
        $this->running = false;
        $this->notify('stopped', ['agent' => $this->name]);
    }

    public function isRunning(): bool
    {
        return $this->running;
    }

    public function setApiKey(string $apiKey): void
    {
        $this->apiKey = $apiKey;
    }

    public function setModel(string $model): void
    {
        $this->model = $model;
    }

    public function clearMemory(): void
    {
        $this->memory = [];
    }

    public function toArray(): array
    {
        return [
            'name' => $this->name,
            'role' => $this->role,
            'goal' => $this->goal,
            'running' => $this->running,
            'memory_count' => count($this->memory),
        ];
    }
}
