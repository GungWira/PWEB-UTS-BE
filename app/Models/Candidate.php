<?php

namespace App\Models;

use MongoDB\Laravel\Eloquent\Model;

class Candidate extends Model
{
    protected $connection = 'mongodb';

    protected $collection = 'candidates';

    protected $fillable = [
        'candidate_number',
        'name',
        'vice_name',
        'vision_mission',
        'photo_url',
    ];

    public function votes()
    {
        return $this->hasMany(Vote::class, 'candidate_id');
    }

    /**
     * Bentuk data paslon yang dikirim ke frontend.
     *
     * @return array<string, mixed>
     */
    public function toApi(int $voteCount = 0): array
    {
        return [
            'id' => (string) $this->_id,
            'candidate_number' => $this->candidate_number,
            'name' => $this->name,
            'vice_name' => $this->vice_name,
            'vision_mission' => $this->vision_mission,
            'photo_url' => $this->photo_url,
            'votes' => $voteCount,
        ];
    }
}
