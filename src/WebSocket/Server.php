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
    private const MAX_BUFFER_SIZE = 65536;
    private const WRITE_CHUNK_SIZE = 8192;

    public function __construct(int $port = 8080, int $readBuffer = 8192)
    {
        $this->port = $port;
        $this->readBuffer = min($readBuffer, self::MAX_BUFFER_SIZE);
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

            if (@stream_select($read, $write, $except, 0, 200000) === false) continue;

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
        $client = @stream_socket_accept($this->server, 0);
        if (!$client) return;
        stream_set_blocking($client, false);
        $id = spl_object_id($client);
        $this->clients[$id] = [
            'socket' => $client,
            'handshake' => false,
            'user' => null,
            'buffer' => '',
        ];
    }

    private function handle($socket): void
    {
        $id = spl_object_id($socket);
        $client = &$this->clients[$id];

        $data = @fread($socket, $this->readBuffer);
        if ($data === false || $data === '') {
            $this->disconnect($socket);
            return;
        }

        $client['buffer'] .= $data;

        if (!$client['handshake']) {
            if (strpos($client['buffer'], "\r\n\r\n") === false) return;
            $this->handshake($socket, $client['buffer']);
            $client['buffer'] = '';
            return;
        }

        $payload = $this->decode($client['buffer']);
        if ($payload === null) return;
        $client['buffer'] = '';

        $msg = json_decode($payload, true);
        if (!$msg) return;

        if (($msg['type'] ?? '') === 'file_upload') {
            $fileInfo = UploadHandler::handle(
                $msg['data'] ?? '',
                $msg['name'] ?? 'unknown',
                $msg['mime'] ?? 'application/octet-stream',
                $client['user']['name'] ?? 'Anonymous'
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
            'user' => $client['user']['name'] ?? 'Anonymous',
            'message' => htmlspecialchars($msg['message'] ?? ''),
            'time' => date('H:i')
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
        fwrite($socket, $response);
        $id = spl_object_id($socket);
        $this->clients[$id]['handshake'] = true;
        $this->clients[$id]['user'] = ['name' => 'Anonymous', 'id' => $id];
        $this->pubsub->publish(['type' => 'system', 'message' => 'A user joined']);
    }

    private function broadcastToAll(array $msg): void
    {
        $frame = $this->encode(json_encode($msg));
        foreach ($this->clients as $id => $client) {
            if ($client['handshake'] && $client['user']) {
                $this->writeBuffer[$id] = ($this->writeBuffer[$id] ?? '') . $frame;
            }
        }
    }

    private function flushWriteBuffer($socket): void
    {
        $id = spl_object_id($socket);
        if (empty($this->writeBuffer[$id])) return;

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

    private function disconnect($socket): void
    {
        $id = spl_object_id($socket);
        unset($this->clients[$id], $this->writeBuffer[$id]);
        @fclose($socket);
    }
}
