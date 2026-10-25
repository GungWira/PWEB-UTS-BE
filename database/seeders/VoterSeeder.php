<?php

namespace Database\Seeders;

use App\Models\Voter;
use Illuminate\Database\Seeder;

class VoterSeeder extends Seeder
{
    public function run(): void
    {
        $voters = [
            ['nik' => '5103010101900001', 'name' => 'I Wayan Sudiarta', 'birth_date' => '1990-01-01', 'rt_rw' => '001/002'],
            ['nik' => '5103011503880002', 'name' => 'Ni Luh Putu Anggreni', 'birth_date' => '1988-03-15', 'rt_rw' => '002/002'],
            ['nik' => '5103012207950003', 'name' => 'I Made Gede Pratama', 'birth_date' => '1995-07-22', 'rt_rw' => '003/004'],
            ['nik' => '5103010911020004', 'name' => 'Ni Kadek Ayu Lestari', 'birth_date' => '2002-11-09', 'rt_rw' => '001/004'],
            ['nik' => '5103013004990005', 'name' => 'I Ketut Bagus Mahendra', 'birth_date' => '1999-04-30', 'rt_rw' => '004/005'],
        ];

        foreach ($voters as $voter) {
            Voter::updateOrCreate(
                ['nik' => $voter['nik']],
                [
                    'name' => $voter['name'],
                    'birth_date' => $voter['birth_date'],
                    'rt_rw' => $voter['rt_rw'],
                    'has_voted' => false,
                ]
            );
        }
    }
}
