<?php

use App\Models\Admin;
use App\Models\Candidate;
use App\Models\Vote;
use App\Models\Voter;
use App\Services\JwtService;
use Illuminate\Support\Facades\Hash;
use Tests\UsesMongo;

uses(UsesMongo::class);

function makeVoter(array $overrides = []): Voter
{
    return Voter::create(array_merge([
        'nik' => '5103010101900001',
        'name' => 'I Wayan Sudiarta',
        'birth_date' => '1990-01-01',
        'rt_rw' => '001/002',
        'has_voted' => false,
    ], $overrides));
}

function makeCandidate(array $overrides = []): Candidate
{
    return Candidate::create(array_merge([
        'candidate_number' => '01',
        'name' => 'Calon Pertama',
        'vice_name' => 'Wakil Pertama',
        'vision_mission' => '<h3>Visi</h3><p>Desa maju</p>',
        'photo_url' => null,
    ], $overrides));
}

function voterToken(Voter $voter): string
{
    return JwtService::create((string) $voter->_id, 'voter');
}

function makeAdminToken(): string
{
    $admin = Admin::create([
        'name' => 'Admin',
        'username' => 'admin',
        'password' => Hash::make('admin123'),
    ]);

    return JwtService::create((string) $admin->_id, 'admin');
}

it('memberi token saat nik dan tanggal lahir cocok', function () {
    makeVoter();

    $this->postJson('/api/v1/auth/voter/login', ['nik' => '5103010101900001', 'birth_date' => '1990-01-01'])
        ->assertOk()
        ->assertJsonStructure(['token', 'expires_in', 'voter' => ['id', 'nik', 'name', 'has_voted']]);
});

it('menolak login pemilih yang tidak terdaftar', function () {
    makeVoter();

    $this->postJson('/api/v1/auth/voter/login', ['nik' => '5103010101900001', 'birth_date' => '1991-01-01'])
        ->assertStatus(401);

    $this->postJson('/api/v1/auth/voter/login', ['nik' => '9999999999999999', 'birth_date' => '1990-01-01'])
        ->assertStatus(401);
});

it('memberi token admin dengan password yang benar', function () {
    makeAdminToken();

    $this->postJson('/api/v1/auth/admin/login', ['username' => 'admin', 'password' => 'admin123'])
        ->assertOk()
        ->assertJsonStructure(['token', 'admin' => ['id', 'username']]);
});

it('menolak password admin yang salah', function () {
    makeAdminToken();

    $this->postJson('/api/v1/auth/admin/login', ['username' => 'admin', 'password' => 'salah'])
        ->assertStatus(401);
});

it('tidak pernah mengirim password admin ke frontend', function () {
    makeAdminToken();

    $response = $this->postJson('/api/v1/auth/admin/login', ['username' => 'admin', 'password' => 'admin123']);

    expect($response->json('admin'))->not->toHaveKey('password');
});

it('mengembalikan data pemilih dari token lewat auth me', function () {
    $voter = makeVoter();

    $this->withToken(voterToken($voter))
        ->getJson('/api/v1/auth/me')
        ->assertOk()
        ->assertJsonPath('role', 'voter')
        ->assertJsonPath('user.nik', '5103010101900001');
});

it('mengirim visi misi paslon ke bilik suara', function () {
    $voter = makeVoter();
    makeCandidate();

    $this->withToken(voterToken($voter))
        ->getJson('/api/v1/vote/candidates')
        ->assertOk()
        ->assertJsonPath('candidates.0.vision_mission', '<h3>Visi</h3><p>Desa maju</p>');
});

it('mencatat suara dan menandai pemilih sudah memilih', function () {
    $voter = makeVoter();
    $candidate = makeCandidate();

    $this->withToken(voterToken($voter))
        ->postJson('/api/v1/vote', ['candidate_id' => (string) $candidate->_id])
        ->assertOk();

    expect(Vote::count())->toBe(1);
    expect($voter->fresh()->has_voted)->toBeTrue();
});

it('menyimpan suara tanpa jejak ke pemilih', function () {
    $voter = makeVoter();
    $candidate = makeCandidate();

    $this->withToken(voterToken($voter))->postJson('/api/v1/vote', ['candidate_id' => (string) $candidate->_id]);

    $vote = Vote::first()->toArray();

    expect($vote)->not->toHaveKey('voter_id');
    expect(json_encode($vote))->not->toContain((string) $voter->_id);
    expect(json_encode($vote))->not->toContain('5103010101900001');
});

it('menolak pemilih yang mencoba memilih dua kali', function () {
    $voter = makeVoter(['has_voted' => true]);
    $candidate = makeCandidate();

    $this->withToken(voterToken($voter))
        ->postJson('/api/v1/vote', ['candidate_id' => (string) $candidate->_id])
        ->assertStatus(422);

    expect(Vote::count())->toBe(0);
});

it('menolak suara untuk paslon yang tidak ada', function () {
    $voter = makeVoter();

    $this->withToken(voterToken($voter))
        ->postJson('/api/v1/vote', ['candidate_id' => '507f1f77bcf86cd799439011'])
        ->assertStatus(422);

    expect($voter->fresh()->has_voted)->toBeFalse();
});

it('menghitung partisipasi di live count tanpa perlu token', function () {
    makeVoter();
    makeVoter(['nik' => '5103010101900002', 'has_voted' => true]);
    $candidate = makeCandidate();
    Vote::create(['candidate_id' => (string) $candidate->_id]);

    $this->getJson('/api/v1/live-count')
        ->assertOk()
        ->assertJsonPath('stats.total_dpt', 2)
        ->assertJsonPath('stats.total_voted', 1)
        ->assertJsonPath('stats.percentage', 50)
        ->assertJsonPath('candidates.0.votes', 1);
});

it('tidak membocorkan visi misi di endpoint live count publik', function () {
    makeCandidate();

    $response = $this->getJson('/api/v1/live-count');

    expect($response->json('candidates.0'))->not->toHaveKey('vision_mission');
});

it('membersihkan script dari visi misi paslon', function () {
    $this->withToken(makeAdminToken())
        ->post('/api/v1/admin/candidates', [
            'candidate_number' => '01',
            'name' => 'Calon',
            'vice_name' => 'Wakil',
            'vision_mission' => '<p onclick="alert(1)">Visi</p><script>alert("xss")</script>',
        ])
        ->assertStatus(201);

    $saved = Candidate::first()->vision_mission;

    expect($saved)->toBe('<p>Visi</p>');
});

it('menghapus suara paslon ketika paslonnya dihapus', function () {
    $token = makeAdminToken();
    $candidate = makeCandidate();
    Vote::create(['candidate_id' => (string) $candidate->_id]);

    $this->withToken($token)
        ->deleteJson('/api/v1/admin/candidates/'.$candidate->_id)
        ->assertOk();

    expect(Vote::count())->toBe(0);
    expect(Candidate::count())->toBe(0);
});

it('mereset seluruh suara dan status dpt', function () {
    $token = makeAdminToken();
    makeVoter(['has_voted' => true]);
    $candidate = makeCandidate();
    Vote::create(['candidate_id' => (string) $candidate->_id]);

    $this->withToken($token)->postJson('/api/v1/admin/reset-election')->assertOk();

    expect(Vote::count())->toBe(0);
    expect(Voter::where('has_voted', true)->count())->toBe(0);
});

it('menolak nik ganda saat menambah dpt manual', function () {
    $token = makeAdminToken();
    makeVoter();

    $this->withToken($token)
        ->postJson('/api/v1/admin/voters', [
            'nik' => '5103010101900001',
            'name' => 'Nama Lain',
            'birth_date' => '1992-02-02',
        ])
        ->assertStatus(422)
        ->assertJsonValidationErrors('nik');
});

it('mewajibkan tanggal lahir saat menambah dpt manual', function () {
    $this->withToken(makeAdminToken())
        ->postJson('/api/v1/admin/voters', ['nik' => '5103010101900009', 'name' => 'Tanpa Tanggal'])
        ->assertStatus(422)
        ->assertJsonValidationErrors('birth_date');
});
