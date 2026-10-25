<?php

namespace App\Models;

use MongoDB\Laravel\Eloquent\Model;

/**
 * Suara disimpan anonim: hanya menyimpan paslon yang dipilih,
 * tanpa kaitan apa pun ke pemilih.
 */
class Vote extends Model
{
    protected $connection = 'mongodb';

    protected $collection = 'votes';

    protected $fillable = [
        'candidate_id',
    ];
}
