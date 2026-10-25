<?php

namespace App\Http\Middleware;

use App\Models\Admin;
use App\Models\Voter;
use App\Services\JwtService;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class JwtAuth
{
    /**
     * Periksa token Bearer dan pastikan perannya sesuai.
     *
     * Pemakaian di route: ->middleware('jwt:admin') atau ->middleware('jwt:voter')
     */
    public function handle(Request $request, Closure $next, ?string $role = null): Response
    {
        $token = $request->bearerToken();

        if (! $token) {
            return response()->json(['message' => 'Token tidak ditemukan. Silakan login kembali.'], 401);
        }

        $payload = JwtService::read($token);

        if (! $payload) {
            return response()->json(['message' => 'Sesi Anda telah berakhir. Silakan login kembali.'], 401);
        }

        if ($role !== null && $payload['role'] !== $role) {
            return response()->json(['message' => 'Anda tidak memiliki akses ke sumber daya ini.'], 403);
        }

        $user = $payload['role'] === 'admin'
            ? Admin::find($payload['sub'])
            : Voter::find($payload['sub']);

        if (! $user) {
            return response()->json(['message' => 'Akun tidak ditemukan.'], 401);
        }

        $request->attributes->set('auth_user', $user);
        $request->attributes->set('auth_role', $payload['role']);

        return $next($request);
    }
}
