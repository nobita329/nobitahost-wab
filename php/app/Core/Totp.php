<?php
/** TOTP (RFC 6238) implementation — replaces otplib. */
final class Totp
{
    private const ALPHABET = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';

    public static function secret(int $length = 20): string
    {
        $out = '';
        $bytes = random_bytes($length);
        for ($i = 0; $i < $length; $i++) {
            $out .= self::ALPHABET[ord($bytes[$i]) % 32];
        }
        return $out;
    }

    public static function base32Decode(string $secret): string
    {
        $secret = strtoupper(preg_replace('/[^A-Za-z2-7]/', '', $secret) ?? '');
        if ($secret === '') return '';
        $bits = '';
        for ($i = 0, $n = strlen($secret); $i < $n; $i++) {
            $pos = strpos(self::ALPHABET, $secret[$i]);
            if ($pos === false) continue;
            $bits .= str_pad(decbin($pos), 5, '0', STR_PAD_LEFT);
        }
        $out = '';
        foreach (str_split($bits, 8) as $byte) {
            if (strlen($byte) === 8) $out .= chr(bindec($byte));
        }
        return $out;
    }

    public static function code(string $secret, ?int $time = null, int $period = 30, int $digits = 6): string
    {
        $time = $time ?? time();
        $counter = intdiv($time, $period);
        $bin = pack('N*', 0, $counter);
        $hash = hash_hmac('sha1', $bin, self::base32Decode($secret), true);
        $offset = ord($hash[strlen($hash) - 1]) & 0x0F;
        $part = substr($hash, $offset, 4);
        $value = unpack('N', $part)[1] & 0x7FFFFFFF;
        return str_pad((string) ($value % (10 ** $digits)), $digits, '0', STR_PAD_LEFT);
    }

    public static function verify(string $secret, string $code, int $window = 1, int $period = 30): bool
    {
        $code = preg_replace('/\D/', '', $code) ?? '';
        if ($code === '' || $secret === '') return false;
        $now = time();
        for ($i = -$window; $i <= $window; $i++) {
            if (hash_equals(self::code($secret, $now + $i * $period), str_pad($code, 6, '0', STR_PAD_LEFT))) {
                return true;
            }
        }
        return false;
    }

    public static function provisioningUri(string $secret, string $account, string $issuer): string
    {
        return sprintf(
            'otpauth://totp/%s:%s?secret=%s&issuer=%s&algorithm=SHA1&digits=6&period=30',
            rawurlencode($issuer),
            rawurlencode($account),
            $secret,
            rawurlencode($issuer)
        );
    }
}
