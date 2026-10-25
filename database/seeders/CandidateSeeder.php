<?php

namespace Database\Seeders;

use App\Models\Candidate;
use Illuminate\Database\Seeder;

class CandidateSeeder extends Seeder
{
    public function run(): void
    {
        $candidates = [
            [
                'candidate_number' => '01',
                'name' => 'I Wayan Sudiarta',
                'vice_name' => 'Ni Luh Putu Anggreni',
                'vision_mission' => '<h3>Visi</h3><p>Mewujudkan desa yang <strong>mandiri</strong>, transparan, dan sejahtera.</p><h3>Misi</h3><ol><li>Membuka data anggaran desa kepada warga.</li><li>Memperbaiki jalan dan saluran irigasi.</li><li>Mendampingi UMKM dan kelompok tani.</li></ol>',
            ],
            [
                'candidate_number' => '02',
                'name' => 'I Made Gede Pratama',
                'vice_name' => 'Ni Kadek Ayu Lestari',
                'vision_mission' => '<h3>Visi</h3><p>Desa digital yang melayani warga dengan <strong>cepat dan adil</strong>.</p><h3>Misi</h3><ol><li>Digitalisasi layanan administrasi desa.</li><li>Pelatihan keterampilan untuk pemuda desa.</li><li>Penguatan posyandu dan layanan kesehatan.</li></ol>',
            ],
        ];

        foreach ($candidates as $candidate) {
            Candidate::updateOrCreate(
                ['candidate_number' => $candidate['candidate_number']],
                [
                    'name' => $candidate['name'],
                    'vice_name' => $candidate['vice_name'],
                    'vision_mission' => $candidate['vision_mission'],
                    'photo_url' => null,
                ]
            );
        }
    }
}
