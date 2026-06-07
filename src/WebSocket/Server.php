<?php
namespace Phoenix\WebSocket;

class Server
{
    use Framing;

    private $server;
    private array $clients = [];
    private PubSub $pubsub;
    private int $port;

    public function __construct(int $port = 8080)
    {
        $this->port = $port;
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
            foreach ($this->clients as $client) {
                $read[] = $client['socket'];
            }
            $write = $except = null;
            if (@stream_select($read, $write, $except, 0, 200000) === false) continue;
            foreach ($read as $socket) {
                if ($socket === $this->server) {
                    $this->accept();
                } else {
                    $this->handle($socket);
                }
            }
            usleep(10000);
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
        ];
    }

    private function handle($socket): void
    {
        $data = @fread($socket, 8192);
        if ($data === false || $data === '') {
            $this->disconnect($socket);
            return;
        }
        $id = spl_object_id($socket);
        $client = &$this->clients[$id];
        if (!$client['handshake']) {
            $this->handshake($socket, $data);
            return;
        }
        $payload = $this->decode($data);
        if ($payload === null) return;
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
        foreach ($this->clients as $client) {
            if ($client['handshake'] && $client['user']) {
                @fwrite($client['socket'], $frame);
            }
        }
    }

    private function disconnect($socket): void
    {
        $id = spl_object_id($socket);
        unset($this->clients[$id]);
        @fclose($socket);
    }
}
