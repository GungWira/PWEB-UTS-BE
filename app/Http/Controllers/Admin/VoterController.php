<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Voter;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class VoterController extends Controller
{
    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'nik' => ['required', 'digits:16', Rule::unique('mongodb.voters', 'nik')],
            'name' => 'required|string|max:255',
            'birth_date' => 'required|date_format:Y-m-d',
            'rt_rw' => 'nullable|string|max:50',
        ]);

        $voter = Voter::create([
            'nik' => $data['nik'],
            'name' => $data['name'],
            'birth_date' => $data['birth_date'],
            'rt_rw' => $data['rt_rw'] ?? null,
            'has_voted' => false,
        ]);

        return response()->json([
            'message' => 'Pemilih berhasil ditambahkan.',
            'voter' => $voter->toApi(),
        ], 201);
    }

    public function resetStatus(string $id): JsonResponse
    {
        $voter = Voter::find($id);

        if (! $voter) {
            return response()->json(['message' => 'Data pemilih tidak ditemukan.'], 404);
        }

        $voter->has_voted = false;
        $voter->save();

        return response()->json(['message' => 'Status voting pemilih berhasil direset.']);
    }

    public function destroy(string $id): JsonResponse
    {
        $voter = Voter::find($id);

        if (! $voter) {
            return response()->json(['message' => 'Data pemilih tidak ditemukan.'], 404);
        }

        $voter->delete();

        return response()->json(['message' => 'Data pemilih berhasil dihapus.']);
    }
}
