<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Candidate;
use App\Models\Vote;
use App\Models\Voter;
use Illuminate\Http\JsonResponse;

class DashboardController extends Controller
{
    public function index(): JsonResponse
    {
        $totalDpt = Voter::count();
        $totalVoted = Voter::where('has_voted', true)->count();

        $voters = Voter::orderBy('created_at', 'desc')->get();
        $candidates = Candidate::orderBy('candidate_number')->get();

        return response()->json([
            'stats' => [
                'total_dpt' => $totalDpt,
                'total_voted' => $totalVoted,
                'total_unvoted' => max(0, $totalDpt - $totalVoted),
                'total_candidates' => $candidates->count(),
            ],
            'voters' => $voters->map(fn (Voter $voter) => $voter->toApi())->all(),
            'candidates' => $candidates
                ->map(fn (Candidate $candidate) => $candidate->toApi(
                    Vote::where('candidate_id', (string) $candidate->_id)->count()
                ))
                ->all(),
        ]);
    }

    /**
     * Hapus semua suara dan kembalikan status seluruh DPT ke belum memilih.
     */
    public function resetElection(): JsonResponse
    {
        Vote::truncate();
        Voter::query()->update(['has_voted' => false]);

        return response()->json(['message' => 'Data hasil pemilihan berhasil direset total.']);
    }
}
