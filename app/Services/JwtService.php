<?php

namespace App\Services;

use Firebase\JWT\JWT;
use Firebase\JWT\Key;
use Throwable;

class JwtService
{
    /**
     * Buat token untuk satu pengguna beserta perannya.
     */
    public static function create(string $userId, string $role): string
    {
        $issuedAt = time();

        return JWT::encode([
            'sub' => $userId,
            'role' => $role,
            'iat' => $issuedAt,
            'exp' => $issuedAt + config('jwt.ttl'),
        ], config('jwt.secret'), config('jwt.algo'));
    }

    /**
     * Baca token. Mengembalikan null bila token tidak valid atau kedaluwarsa.
     *
     * @return array{sub: string, role: string, iat: int, exp: int}|null
     */
    public static function read(string $token): ?array
    {
        try {
            $payload = JWT::decode($token, new Key(config('jwt.secret'), config('jwt.algo')));

            return (array) $payload;
        } catch (Throwable) {
            return null;
        }
    }

    /**
     * Masa berlaku token dalam detik, untuk dikirim ke frontend.
     */
    public static function ttl(): int
    {
        return (int) config('jwt.ttl');
    }
}
