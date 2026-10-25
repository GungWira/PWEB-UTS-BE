<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;
use MongoDB\Laravel\Schema\Blueprint;

/**
 * MongoDB membuat collection secara otomatis, tetapi index unik harus
 * didefinisikan sendiri. Index inilah yang mencegah NIK dan nomor urut
 * paslon ganda di level database.
 */
return new class extends Migration
{
    protected $connection = 'mongodb';

    public function up(): void
    {
        Schema::connection('mongodb')->create('voters', function (Blueprint $collection) {
            $collection->unique('nik');
            $collection->index('has_voted');
        });

        Schema::connection('mongodb')->create('candidates', function (Blueprint $collection) {
            $collection->unique('candidate_number');
        });

        Schema::connection('mongodb')->create('votes', function (Blueprint $collection) {
            $collection->index('candidate_id');
        });

        Schema::connection('mongodb')->create('admins', function (Blueprint $collection) {
            $collection->unique('username');
        });
    }

    public function down(): void
    {
        Schema::connection('mongodb')->drop('voters');
        Schema::connection('mongodb')->drop('candidates');
        Schema::connection('mongodb')->drop('votes');
        Schema::connection('mongodb')->drop('admins');
    }
};
