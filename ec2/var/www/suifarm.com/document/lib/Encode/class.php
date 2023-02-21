<?php

declare(strict_types=1);

class Encode
{
    static private array $cache = [];

    static private function getSalt(string $path): string
    {
        return isset(self::$cache[$path]) ? self::$cache[$path] : (self::$cache[$path] = Secret::get($path));
    }

    static public function encode(string $key, string $value)
    {
        return (string)openssl_encrypt($value, 'aes-256-ecb', self::getSalt($key));
    }

    static public function decode(string $key, string $value)
    {
        return (string)openssl_decrypt($value, 'aes-256-ecb', self::getSalt($key));
    }
}
