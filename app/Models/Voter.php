<?php

namespace App\Models;

use MongoDB\Laravel\Eloquent\Model;

class Voter extends Model
{
    protected $connection = 'mongodb';

    protected $collection = 'voters';

    protected $fillable = [
        'nik',
        'name',
        'birth_date',
        'rt_rw',
        'has_voted',
    ];

    protected function casts(): array
    {
        return [
            'has_voted' => 'boolean',
        ];
    }

    /**
     * Bentuk data pemilih yang dikirim ke frontend.
     *
     * @return array<string, mixed>
     */
    public function toApi(): array
    {
        return [
            'id' => (string) $this->_id,
            'nik' => $this->nik,
            'name' => $this->name,
            'birth_date' => $this->birth_date,
            'rt_rw' => $this->rt_rw,
            'has_voted' => (bool) $this->has_voted,
        ];
    }
}
