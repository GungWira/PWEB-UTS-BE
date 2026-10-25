<?php

namespace Tests;

use App\Models\Admin;
use App\Models\Candidate;
use App\Models\Vote;
use App\Models\Voter;

/**
 * MongoDB tidak mendukung rollback transaksi seperti RefreshDatabase pada SQL,
 * jadi setiap tes dimulai dengan koleksi yang sudah dikosongkan.
 */
trait UsesMongo
{
    protected function setUpUsesMongo(): void
    {
        Voter::truncate();
        Candidate::truncate();
        Vote::truncate();
        Admin::truncate();
    }
}
