<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use App\Models\Admin;

class IsAdmin
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        // Vérifie si c'est un admin
        if (!$user instanceof Admin) {
            return response()->json([
                'message' => 'Accès refusé (Admin uniquement)'
            ], 403);
        }

        return $next($request);
    }
}
