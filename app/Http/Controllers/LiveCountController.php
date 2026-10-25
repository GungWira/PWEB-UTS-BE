<?php

namespace App\Http\Controllers;

use App\Models\Candidate;
use App\Models\Vote;
use App\Models\Voter;
use Illuminate\Http\JsonResponse;

class LiveCountController extends Controller
{
    public function index(): JsonResponse
    {
        $totalDpt = Voter::count();
        $totalVoted = Voter::where('has_voted', true)->count();

        $candidates = Candidate::orderBy('candidate_number')->get()->map(function (Candidate $candidate) {
            return [
                'id' => (string) $candidate->_id,
                'candidate_number' => $candidate->candidate_number,
                'name' => $candidate->name,
                'votes' => Vote::where('candidate_id', (string) $candidate->_id)->count(),
            ];
        });

        return response()->json([
            'stats' => [
                'total_dpt' => $totalDpt,
                'total_voted' => $totalVoted,
                'total_unvoted' => max(0, $totalDpt - $totalVoted),
                'percentage' => $totalDpt > 0 ? round(($totalVoted / $totalDpt) * 100, 1) : 0,
            ],
            'candidates' => $candidates->all(),
        ]);
    }
}
