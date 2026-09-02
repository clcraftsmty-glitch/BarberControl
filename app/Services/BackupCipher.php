<?php

namespace App\Services;

use RuntimeException;

class BackupCipher
{
    private const MAGIC = "BCBK1\0";

    private const CHUNK_SIZE = 1048576;

    public function encrypt(string $source, string $destination): void
    {
        $input = fopen($source, 'rb');
        $output = fopen($destination, 'wb');
        if (! is_resource($input) || ! is_resource($output)) {
            throw new RuntimeException('No fue posible abrir los archivos para cifrar el respaldo.');
        }

        fwrite($output, self::MAGIC);
        $counter = 0;
        while (! feof($input)) {
            $plain = fread($input, self::CHUNK_SIZE);
            if ($plain === false || $plain === '') {
                break;
            }
            $nonce = random_bytes(12);
            $tag = '';
            $cipher = openssl_encrypt(
                $plain,
                'aes-256-gcm',
                $this->key(),
                OPENSSL_RAW_DATA,
                $nonce,
                $tag,
                pack('N', $counter),
            );
            if ($cipher === false) {
                throw new RuntimeException('OpenSSL no pudo cifrar el respaldo.');
            }
            fwrite($output, pack('N', strlen($cipher)).$nonce.$tag.$cipher);
            $counter++;
        }

        fclose($input);
        fclose($output);
    }

    public function decrypt(string $source, string $destination): void
    {
        $input = fopen($source, 'rb');
        $output = fopen($destination, 'wb');
        if (! is_resource($input) || ! is_resource($output)) {
            throw new RuntimeException('No fue posible abrir el respaldo para descifrarlo.');
        }
        if (fread($input, strlen(self::MAGIC)) !== self::MAGIC) {
            throw new RuntimeException('El archivo no tiene el formato cifrado de BarberControl.');
        }

        $counter = 0;
        while (! feof($input)) {
            $lengthBytes = fread($input, 4);
            if ($lengthBytes === '' || $lengthBytes === false) {
                break;
            }
            if (strlen($lengthBytes) !== 4) {
                throw new RuntimeException('El respaldo cifrado está incompleto.');
            }
            $length = unpack('N', $lengthBytes)[1];
            $nonce = $this->readExactly($input, 12);
            $tag = $this->readExactly($input, 16);
            $cipher = $this->readExactly($input, $length);
            $plain = openssl_decrypt(
                $cipher,
                'aes-256-gcm',
                $this->key(),
                OPENSSL_RAW_DATA,
                $nonce,
                $tag,
                pack('N', $counter),
            );
            if ($plain === false) {
                throw new RuntimeException('El respaldo fue alterado o la clave de cifrado no coincide.');
            }
            fwrite($output, $plain);
            $counter++;
        }

        fclose($input);
        fclose($output);
    }

    private function key(): string
    {
        $configured = (string) config('security.backups.encryption_key');
        if ($configured === '') {
            throw new RuntimeException('BACKUP_ENCRYPTION_KEY o APP_KEY debe estar configurada.');
        }
        if (str_starts_with($configured, 'base64:')) {
            $configured = base64_decode(substr($configured, 7), true) ?: $configured;
        }

        return hash('sha256', $configured, true);
    }

    /** @param resource $stream */
    private function readExactly($stream, int $length): string
    {
        $buffer = '';
        while (strlen($buffer) < $length && ! feof($stream)) {
            $chunk = fread($stream, $length - strlen($buffer));
            if ($chunk === false) {
                break;
            }
            $buffer .= $chunk;
        }
        if (strlen($buffer) !== $length) {
            throw new RuntimeException('El respaldo cifrado está incompleto.');
        }

        return $buffer;
    }
}
