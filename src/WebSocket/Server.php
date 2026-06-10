<?php

namespace Phoenix\WebSocket;

class Server
{
    use Framing;

    private $server;
    private array $clients = [];
    private PubSub $pubsub;
    private int $port;
    private int $readBuffer;
    private array $writeBuffer = [];
    private int $maxConnections;
    private int $maxWriteBuffer;
    private int $heartbeatInterval;
    private array $lastPing = [];
    private int $nextClientId = 0;
    private const WRITE_CHUNK_SIZE = 8192;
    private const MAX_BUFFER_SIZE = 65536;
    private const MAX_FRAME_SIZE = 1048576;

    public function __construct(
        int $port = 8080,
        int $readBuffer = 8192,
        int $maxConnections = 1000,
        int $maxWriteBuffer = 524288,
        int $heartbeatInterval = 30,
    ) {
        $this->port = $port;
        $this->readBuffer = min($readBuffer, self::MAX_BUFFER_SIZE);
        $this->maxConnections = $maxConnections;
        $this->maxWriteBuffer = $maxWriteBuffer;
        $this->heartbeatInterval = $heartbeatInterval;
        $this->pubsub = new PubSub();
        UploadHandler::init();
    }

    public function run(): void
    {
        $this->server = @stream_socket_server("tcp://0.0.0.0:{$this->port}", $errno, $errstr);
        if (!$this->server) {
            fwrite(STDERR, "WebSocket server failed: $errstr\n");
            exit(1);
        }
        stream_set_blocking($this->server, false);
        echo "Phoenix WebSocket Server running on port {$this->port}\n";

        $lastHeartbeat = time();

        while (true) {
            $read = [$this->server];
            $write = [];
            $except = null;

            foreach ($this->clients as $id => $client) {
                $read[] = $client['socket'];
                if (!empty($this->writeBuffer[$id])) {
                    $write[] = $client['socket'];
                }
            }

            $tvSec = 0;
            $tvUsec = 200000;
            if (@stream_select($read, $write, $except, $tvSec, $tvUsec) === false) {
                continue;
            }

            $now = time();
            if ($now - $lastHeartbeat >= $this->heartbeatInterval) {
                $this->sendHeartbeats();
                $this->disconnectStaleClients($now);
                $lastHeartbeat = $now;
            }

            foreach ($write as $socket) {
                $this->flushWriteBuffer($socket);
            }

            foreach ($read as $socket) {
                if ($socket === $this->server) {
                    $this->accept();
                } else {
                    $this->handle($socket);
                }
            }
        }
    }

    private function accept(): void
    {
        if (count($this->clients) >= $this->maxConnections) {
            $client = @stream_socket_accept($this->server, 0);
            if ($client) {
                @fwrite($client, "HTTP/1.1 503 Service Unavailable\r\n\r\n");
                @fclose($client);
            }

            return;
        }

        $client = @stream_socket_accept($this->server, 0);
        if (!$client) {
            return;
        }
        stream_set_blocking($client, false);
        $id = $this->nextClientId++;
        $this->clients[$id] = [
            'socket' => $client,
            'handshake' => false,
            'user' => null,
            'buffer' => '',
            'connected_at' => time(),
            'last_activity' => time(),
        ];
    }

    private function handle($socket): void
    {
        $id = $this->findClientId($socket);
        if ($id === null || !isset($this->clients[$id])) {
            return;
        }

        $data = @fread($socket, $this->readBuffer);
        if ($data === false || $data === '') {
            $this->disconnect($socket);

            return;
        }

        $this->clients[$id]['last_activity'] = time();
        $this->clients[$id]['buffer'] .= $data;

        if (!$this->clients[$id]['handshake']) {
            if (strpos($this->clients[$id]['buffer'], "\r\n\r\n") === false) {
                return;
            }
            $this->handshake($socket, $this->clients[$id]['buffer']);
            $this->clients[$id]['buffer'] = '';

            return;
        }

        $payload = $this->decode($this->clients[$id]['buffer']);
        if ($payload === null) {
            if (strlen($this->clients[$id]['buffer']) > self::MAX_FRAME_SIZE) {
                $this->disconnect($socket);
            }

            return;
        }
        $this->clients[$id]['buffer'] = '';

        if ($payload === 'pong') {
            return;
        }

        $msg = json_decode($payload, true);
        if (!$msg) {
            return;
        }

        if (($msg['type'] ?? '') === 'ping') {
            $this->send($socket, ['type' => 'pong', 'time' => time()]);

            return;
        }

        if (($msg['type'] ?? '') === 'file_upload') {
            $fileInfo = UploadHandler::handle(
                $msg['data'] ?? '',
                $msg['name'] ?? 'unknown',
                $msg['mime'] ?? 'application/octet-stream',
                $this->clients[$id]['user']['name'] ?? 'Anonymous',
            );
            if ($fileInfo) {
                $this->pubsub->publish(array_merge(['type' => 'file'], $fileInfo));
            } else {
                $this->send($socket, ['type' => 'error', 'message' => 'Upload failed']);
            }

            return;
        }

        $this->pubsub->publish([
            'type' => 'message',
            'user' => $this->clients[$id]['user']['name'] ?? 'Anonymous',
            'message' => htmlspecialchars($msg['message'] ?? ''),
            'time' => date('H:i'),
        ]);
    }

    private function handshake($socket, $headers): void
    {
        if (!preg_match('/Sec-WebSocket-Key: (.*)\r\n/', $headers, $matches)) {
            fclose($socket);

            return;
        }
        $key = base64_encode(pack('H*', sha1($matches[1] . '258EAFA5-E914-47DA-95CA-C5AB0DC85B11')));
        $response = "HTTP/1.1 101 Switching Protocols\r\nUpgrade: websocket\r\nConnection: Upgrade\r\nSec-WebSocket-Accept: $key\r\n\r\n";
        @fwrite($socket, $response);
        $id = $this->findClientId($socket);
        if ($id === null) {
            return;
        }
        $this->clients[$id]['handshake'] = true;
        $this->clients[$id]['user'] = ['name' => 'Anonymous', 'id' => $id];
        $this->pubsub->publish(['type' => 'system', 'message' => 'A user joined']);
    }

    private function broadcastToAll(array $msg): void
    {
        $frame = $this->encode(json_encode($msg));
        $frameLen = strlen($frame);
        $toDisconnect = [];

        foreach ($this->clients as $id => $client) {
            if ($client['handshake'] && $client['user']) {
                $currentLen = strlen($this->writeBuffer[$id] ?? '');
                if ($currentLen + $frameLen > $this->maxWriteBuffer) {
                    $toDisconnect[] = $client['socket'];

                    continue;
                }
                $this->writeBuffer[$id] = ($this->writeBuffer[$id] ?? '') . $frame;
            }
        }

        foreach ($toDisconnect as $socket) {
            $this->disconnect($socket);
        }
    }

    private function flushWriteBuffer($socket): void
    {
        $id = $this->findClientId($socket);
        if ($id === null || empty($this->writeBuffer[$id])) {
            return;
        }

        $chunk = substr($this->writeBuffer[$id], 0, self::WRITE_CHUNK_SIZE);
        $written = @fwrite($socket, $chunk);

        if ($written === false) {
            $this->disconnect($socket);

            return;
        }

        if ($written === 0) {
            return;
        }

        if ($written < strlen($chunk)) {
            $this->writeBuffer[$id] = substr($this->writeBuffer[$id], $written);
        } else {
            unset($this->writeBuffer[$id]);
        }
    }

    private function sendHeartbeats(): void
    {
        $ping = $this->encode(json_encode(['type' => 'ping', 'time' => time()]));
        foreach ($this->clients as $id => $client) {
            if ($client['handshake']) {
                @fwrite($client['socket'], $ping);
                $this->lastPing[$id] = time();
            }
        }
    }

    private function disconnectStaleClients(int $now): void
    {
        foreach ($this->clients as $id => $client) {
            if (!$client['handshake']) {
                if ($now - $client['connected_at'] > 30) {
                    $this->disconnect($client['socket']);
                }

                continue;
            }

            if ($now - $client['last_activity'] > $this->heartbeatInterval * 3) {
                $this->disconnect($client['socket']);
            }
        }
    }

    private function disconnect($socket): void
    {
        $id = $this->findClientId($socket);
        if ($id !== null) {
            unset(
                $this->clients[$id],
                $this->writeBuffer[$id],
                $this->lastPing[$id],
            );
        }
        @fclose($socket);
    }

    private function findClientId($socket): ?int
    {
        foreach ($this->clients as $id => $client) {
            if ($client['socket'] === $socket) {
                return $id;
            }
        }

        return null;
    }

    public function getConnectionCount(): int
    {
        return count($this->clients);
    }
}
