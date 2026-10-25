<?php

use App\Services\JwtService;

/**
 * Tes ini sengaja hanya menyentuh lapisan token dan middleware, sehingga
 * bisa dijalankan tanpa koneksi MongoDB.
 */
it('membuat token yang bisa dibaca kembali', function () {
    $token = JwtService::create('507f1f77bcf86cd799439011', 'voter');
    $payload = JwtService::read($token);

    expect($payload)->not->toBeNull();
    expect($payload['sub'])->toBe('507f1f77bcf86cd799439011');
    expect($payload['role'])->toBe('voter');
    expect($payload['exp'])->toBeGreaterThan(time());
});

it('menolak token yang dirusak', function () {
    $token = JwtService::create('507f1f77bcf86cd799439011', 'admin');

    expect(JwtService::read($token.'xyz'))->toBeNull();
    expect(JwtService::read('bukan.token.sama.sekali'))->toBeNull();
});

it('menolak token yang ditandatangani kunci lain', function () {
    $token = JwtService::create('507f1f77bcf86cd799439011', 'admin');

    config(['jwt.secret' => str_repeat('b', 64)]);

    expect(JwtService::read($token))->toBeNull();
});

it('menolak token yang sudah kedaluwarsa', function () {
    config(['jwt.ttl' => -10]);

    $token = JwtService::create('507f1f77bcf86cd799439011', 'voter');

    expect(JwtService::read($token))->toBeNull();
});

it('menolak akses tanpa token', function (string $method, string $uri) {
    $this->json($method, $uri)
        ->assertStatus(401)
        ->assertJsonStructure(['message']);
})->with([
    ['get', '/api/v1/auth/me'],
    ['get', '/api/v1/vote/candidates'],
    ['post', '/api/v1/vote'],
    ['get', '/api/v1/admin/dashboard'],
    ['post', '/api/v1/admin/voters'],
    ['get', '/api/v1/admin/voters/template'],
    ['post', '/api/v1/admin/voters/import/preview'],
    ['post', '/api/v1/admin/reset-election'],
    ['post', '/api/v1/admin/candidates'],
]);

it('menolak token yang tidak valid dengan 401', function () {
    $this->withToken('token.palsu.banget')
        ->getJson('/api/v1/admin/dashboard')
        ->assertStatus(401);
});

it('menolak pemilih yang mencoba masuk route admin dengan 403', function () {
    $token = JwtService::create('507f1f77bcf86cd799439011', 'voter');

    $this->withToken($token)
        ->getJson('/api/v1/admin/dashboard')
        ->assertStatus(403)
        ->assertJsonStructure(['message']);
});

it('menolak admin yang mencoba masuk route pemilih dengan 403', function () {
    $token = JwtService::create('507f1f77bcf86cd799439011', 'admin');

    $this->withToken($token)
        ->getJson('/api/v1/vote/candidates')
        ->assertStatus(403);
});

it('membiarkan route publik diakses tanpa token', function () {
    // Validasi jalan lebih dulu daripada database, jadi 422 membuktikan
    // request sudah lolos lapisan autentikasi.
    $this->postJson('/api/v1/auth/voter/login', [])
        ->assertStatus(422)
        ->assertJsonValidationErrors(['nik', 'birth_date']);

    $this->postJson('/api/v1/auth/admin/login', [])
        ->assertStatus(422)
        ->assertJsonValidationErrors(['username', 'password']);
});

it('selalu membalas json walau klien lupa header accept', function () {
    // Tanpa header Accept, Laravel biasanya me-redirect saat validasi gagal.
    $this->post('/api/v1/auth/admin/login', [])
        ->assertStatus(422)
        ->assertHeader('content-type', 'application/json')
        ->assertJsonValidationErrors(['username', 'password']);
});

it('membalas json untuk route api yang tidak ada', function () {
    $this->get('/api/v1/tidak-ada')
        ->assertStatus(404)
        ->assertHeader('content-type', 'application/json');
});
