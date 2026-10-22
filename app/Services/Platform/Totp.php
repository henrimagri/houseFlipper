<?php

namespace App\Services\Platform;

// RFC 6238 / RFC 4226; no network, persistence or provider dependency.
class Totp
{
    public function secret(): string
    {
        $alphabet = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';
        $bits = implode('', array_map(fn ($n) => str_pad(decbin($n), 8, '0', STR_PAD_LEFT), array_values(unpack('C*', random_bytes(20)))));
        return implode('', array_map(fn ($part) => $alphabet[bindec($part)], str_split($bits, 5)));
    }

    public function code(string $secret, int $time, int $digits = 6): string
    {
        $bits = '';
        foreach (str_split($secret) as $char) {
            $index = strpos('ABCDEFGHIJKLMNOPQRSTUVWXYZ234567', $char);
            if ($index === false) throw new \InvalidArgumentException('Invalid base32 secret');
            $bits .= str_pad(decbin($index), 5, '0', STR_PAD_LEFT);
        }
        $key = '';
        foreach (str_split($bits, 8) as $byte) if (strlen($byte) === 8) $key .= chr(bindec($byte));
        $step = intdiv($time, 30);
        $hash = hash_hmac('sha1', pack('N2', intdiv($step, 4294967296), $step % 4294967296), $key, true);
        $offset = ord($hash[19]) & 15;
        $value = unpack('N', substr($hash, $offset, 4))[1] & 0x7fffffff;
        return str_pad((string) ($value % (10 ** $digits)), $digits, '0', STR_PAD_LEFT);
    }

    public function matchingStep(string $secret, string $code, int $time): ?int
    {
        if (! preg_match('/^\d{6}$/D', $code)) return null;
        foreach ([0, -1, 1] as $delta) {
            if (hash_equals($this->code($secret, $time + $delta * 30), $code)) return intdiv($time, 30) + $delta;
        }
        return null;
    }
}
