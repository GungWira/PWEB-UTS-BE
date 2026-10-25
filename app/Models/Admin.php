<?php

namespace App\Models;

use MongoDB\Laravel\Eloquent\Model;

class Admin extends Model
{
    protected $connection = 'mongodb';

    protected $collection = 'admins';

    protected $fillable = [
        'name',
        'username',
        'password',
    ];

    protected $hidden = [
        'password',
    ];

    /**
     * @return array<string, mixed>
     */
    public function toApi(): array
    {
        return [
            'id' => (string) $this->_id,
            'name' => $this->name,
            'username' => $this->username,
        ];
    }
}
