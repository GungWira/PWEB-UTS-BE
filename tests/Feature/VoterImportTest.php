<?php

use App\Models\Admin;
use App\Models\Voter;
use App\Services\JwtService;
use Illuminate\Http\UploadedFile;
use Tests\UsesMongo;

uses(UsesMongo::class);

const CSV_HEADER = "nik,nama,tanggal_lahir,rt_rw\n";

function adminToken(): string
{
    $admin = Admin::create([
        'name' => 'Admin Uji',
        'username' => 'admin_uji_'.uniqid(),
        'password' => bcrypt('rahasia'),
    ]);

    return JwtService::create((string) $admin->_id, 'admin');
}

function previewCsv(string $contents, string $name = 'dpt.csv'): array
{
    return test()->withToken(adminToken())
        ->post('/api/v1/admin/voters/import/preview', [
            'file' => UploadedFile::fake()->createWithContent($name, $contents),
        ])
        ->assertOk()
        ->json();
}

it('menyediakan template csv berisi baris header', function () {
    $response = $this->withToken(adminToken())->get('/api/v1/admin/voters/template');

    $response->assertOk();
    expect($response->streamedContent())
        ->toContain('nik,nama,tanggal_lahir,rt_rw')
        ->toContain('5103010101900001');
});

it('menghitung baris valid tanpa menyimpan apa pun', function () {
    $result = previewCsv(CSV_HEADER.
        "5103010101900001,I Wayan Sudiarta,1990-01-01,001/002\n".
        "5103011503880002,Ni Luh Anggreni,1988-03-15,002/002\n"
    );

    expect($result['total_read'])->toBe(2);
    expect($result['total_valid'])->toBe(2);
    expect($result['invalid'])->toBeEmpty();
    expect(Voter::count())->toBe(0);
});

it('menandai nik yang bukan 16 digit', function () {
    $result = previewCsv(CSV_HEADER."12345,Budi Pendek,1990-01-01,001/002\n");

    expect($result['total_valid'])->toBe(0);
    expect($result['invalid'][0]['reason'])->toContain('16 digit');
});

it('menandai nik notasi ilmiah excel', function () {
    $result = previewCsv(CSV_HEADER."5.1030101019E+15,Budi Notasi,1990-01-01,001/002\n");

    expect($result['invalid'][0]['reason'])->toContain('notasi ilmiah');
});

it('mewajibkan tanggal lahir', function () {
    $result = previewCsv(CSV_HEADER."5103010101900001,Tanpa Tanggal,,001/002\n");

    expect($result['invalid'][0]['reason'])->toContain('tanggal lahir');
});

it('menormalkan tanggal dd/mm/yyyy', function () {
    $result = previewCsv(CSV_HEADER."5103010101900001,Wayan Slash,17/05/1990,001/002\n");

    expect($result['invalid'])->toBeEmpty();
    expect($result['valid_rows'][0]['birth_date'])->toBe('1990-05-17');
});

it('hanya mengambil baris pertama saat nik ganda dalam berkas', function () {
    $result = previewCsv(CSV_HEADER.
        "5103010101900001,Wayan Pertama,1990-01-01,001/002\n".
        "5103010101900001,Wayan Kedua,1990-01-01,003/004\n"
    );

    expect($result['total_valid'])->toBe(1);
    expect($result['valid_rows'][0]['name'])->toBe('Wayan Pertama');
    expect($result['duplicate_in_file'][0]['reason'])->toContain('ganda dalam berkas');
});

it('melewati nik yang sudah terdaftar di dpt', function () {
    Voter::create(['nik' => '5103010101900001', 'name' => 'Sudah Ada', 'birth_date' => '1990-01-01', 'has_voted' => false]);

    $result = previewCsv(CSV_HEADER.
        "5103010101900001,Duplikat Database,1990-01-01,001/002\n".
        "5103011503880002,Pemilih Baru,1988-03-15,002/002\n"
    );

    expect($result['total_valid'])->toBe(1);
    expect($result['valid_rows'][0]['nik'])->toBe('5103011503880002');
    expect($result['duplicate_in_database'])->toHaveCount(1);
});

it('membaca berkas pemisah titik koma dan bom utf-8', function () {
    $result = previewCsv("\xEF\xBB\xBF"."nik;nama;tanggal_lahir;rt_rw\n5103010101900001;Wayan Semicolon;1990-01-01;001/002\n");

    expect($result['invalid'])->toBeEmpty();
    expect($result['valid_rows'][0]['name'])->toBe('Wayan Semicolon');
});

it('mengabaikan baris kosong', function () {
    $result = previewCsv(CSV_HEADER."5103010101900001,Wayan Ada,1990-01-01,001/002\n,,,\n\n");

    expect($result['total_read'])->toBe(1);
    expect($result['invalid'])->toBeEmpty();
});

it('menolak berkas yang bukan csv', function () {
    $this->withToken(adminToken())
        ->post('/api/v1/admin/voters/import/preview', [
            'file' => UploadedFile::fake()->createWithContent('dpt.xlsx', 'apa saja'),
        ])
        ->assertStatus(422);
});

it('menyimpan baris yang dikonfirmasi admin', function () {
    $rows = [
        ['nik' => '5103010101900001', 'name' => 'Wayan Valid', 'birth_date' => '1990-01-01', 'rt_rw' => '001/002'],
        ['nik' => '5103011503880002', 'name' => 'Luh Valid', 'birth_date' => '1988-03-15', 'rt_rw' => null],
    ];

    $this->withToken(adminToken())
        ->postJson('/api/v1/admin/voters/import/confirm', ['rows' => $rows])
        ->assertOk()
        ->assertJsonPath('imported', 2);

    expect(Voter::count())->toBe(2);
    expect(Voter::where('nik', '5103011503880002')->first()->birth_date)->toBe('1988-03-15');
});

it('memvalidasi ulang baris yang dikirim balik browser', function () {
    // Baris yang dimanipulasi di sisi klien harus ditolak server.
    $rows = [
        ['nik' => '999', 'name' => 'Nik Dipalsukan', 'birth_date' => '1990-01-01', 'rt_rw' => null],
        ['nik' => '5103010101900001', 'name' => 'Wayan Asli', 'birth_date' => '1990-01-01', 'rt_rw' => null],
    ];

    $this->withToken(adminToken())
        ->postJson('/api/v1/admin/voters/import/confirm', ['rows' => $rows])
        ->assertOk()
        ->assertJsonPath('imported', 1)
        ->assertJsonPath('skipped', 1);

    expect(Voter::count())->toBe(1);
    expect(Voter::first()->nik)->toBe('5103010101900001');
});

it('tidak menduplikasi nik yang sudah ada saat konfirmasi', function () {
    Voter::create(['nik' => '5103010101900001', 'name' => 'Masuk Lebih Dulu', 'birth_date' => '1990-01-01', 'has_voted' => false]);

    $this->withToken(adminToken())
        ->postJson('/api/v1/admin/voters/import/confirm', [
            'rows' => [['nik' => '5103010101900001', 'name' => 'Pendatang', 'birth_date' => '1990-01-01', 'rt_rw' => null]],
        ])
        ->assertStatus(422);

    expect(Voter::count())->toBe(1);
    expect(Voter::first()->name)->toBe('Masuk Lebih Dulu');
});

it('menghasilkan pemilih yang langsung bisa login setelah diimpor', function () {
    $this->withToken(adminToken())
        ->postJson('/api/v1/admin/voters/import/confirm', [
            'rows' => [['nik' => '5103010101900001', 'name' => 'Wayan Login', 'birth_date' => '1990-01-01', 'rt_rw' => null]],
        ])
        ->assertOk();

    $this->postJson('/api/v1/auth/voter/login', ['nik' => '5103010101900001', 'birth_date' => '1990-01-01'])
        ->assertOk()
        ->assertJsonStructure(['token', 'voter' => ['id', 'nik', 'name', 'has_voted']]);
});
