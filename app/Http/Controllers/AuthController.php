<?php

namespace App\Http\Controllers;

use App\Models\Admin;
use App\Models\Voter;
use App\Services\JwtService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

class AuthController extends Controller
{
    public function loginVoter(Request $request): JsonResponse
    {
        $data = $request->validate([
            'nik' => 'required|string',
            'birth_date' => 'required|date_format:Y-m-d',
        ]);

        $voter = Voter::where('nik', $data['nik'])
            ->where('birth_date', $data['birth_date'])
            ->first();

        if (! $voter) {
            return response()->json([
                'message' => 'NIK atau Tanggal Lahir tidak terdaftar dalam DPT!',
            ], 401);
        }

        return response()->json([
            'token' => JwtService::create((string) $voter->_id, 'voter'),
            'expires_in' => JwtService::ttl(),
            'voter' => $voter->toApi(),
        ]);
    }

    public function loginAdmin(Request $request): JsonResponse
    {
        $data = $request->validate([
            'username' => 'required|string',
            'password' => 'required|string',
        ]);

        $admin = Admin::where('username', $data['username'])->first();

        if (! $admin || ! Hash::check($data['password'], $admin->password)) {
            return response()->json([
                'message' => 'Username atau Password salah!',
            ], 401);
        }

        return response()->json([
            'token' => JwtService::create((string) $admin->_id, 'admin'),
            'expires_in' => JwtService::ttl(),
            'admin' => $admin->toApi(),
        ]);
    }

    /**
     * Kembalikan data pengguna dari token yang sedang dipakai.
     */
    public function me(Request $request): JsonResponse
    {
        $role = $request->attributes->get('auth_role');
        $user = $request->attributes->get('auth_user');

        return response()->json([
            'role' => $role,
            'user' => $user->toApi(),
        ]);
    }
}
