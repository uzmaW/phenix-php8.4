<?php

namespace Phoenix\WebSocket;

trait Framing
{
    private function decode(string $data): ?string
    {
        if (strlen($data) < 2) {
            return null;
        }
        $opcode = ord($data[0]) & 0x0F;
        if ($opcode !== 1) {
            return null;
        }
        $masked = ord($data[1]) & 0x80;
        $length = ord($data[1]) & 0x7F;
        $offset = 2;
        if ($length === 126) {
            $length = unpack('n', substr($data, 2, 2))[1];
            $offset = 4;
        } elseif ($length === 127) {
            $length = unpack('J', substr($data, 2, 8))[1];
            $offset = 10;
        }
        $mask = $masked ? substr($data, $offset, 4) : null;
        $offset += $masked ? 4 : 0;
        $payload = substr($data, $offset, $length);
        if ($masked) {
            for ($i = 0; $i < $length; $i++) {
                $payload[$i] = $payload[$i] ^ $mask[$i % 4];
            }
        }

        return $payload;
    }

    private function encode(string $payload, int $opcode = 1): string
    {
        $payload = (string) $payload;
        $length = strlen($payload);
        $frame = chr(0x80 | $opcode);
        if ($length <= 125) {
            $frame .= chr($length);
        } elseif ($length <= 65535) {
            $frame .= chr(126) . pack('n', $length);
        } else {
            $frame .= chr(127) . pack('J', $length);
        }

        return $frame . $payload;
    }

    private function send($socket, array $msg): void
    {
        $frame = $this->encode(json_encode($msg));
        @fwrite($socket, $frame);
    }
}
