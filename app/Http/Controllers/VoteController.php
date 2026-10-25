<?php

namespace App\Http\Controllers;

use App\Models\Candidate;
use App\Models\Vote;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class VoteController extends Controller
{
    public function candidates(): JsonResponse
    {
        $candidates = Candidate::orderBy('candidate_number')->get();

        return response()->json([
            'candidates' => $candidates->map(fn (Candidate $candidate) => $candidate->toApi())->all(),
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'candidate_id' => 'required|string',
        ]);

        $voter = $request->attributes->get('auth_user');

        if ($voter->has_voted) {
            return response()->json(['message' => 'Anda sudah menggunakan hak pilih!'], 422);
        }

        $candidate = Candidate::find($data['candidate_id']);

        if (! $candidate) {
            return response()->json(['message' => 'Pasangan calon tidak ditemukan.'], 422);
        }

        Vote::create(['candidate_id' => (string) $candidate->_id]);

        $voter->has_voted = true;
        $voter->save();

        return response()->json(['message' => 'Suara Anda berhasil dikirim.']);
    }
}
