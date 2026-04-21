<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use App\Models\Fournisseur;

class IsFournisseur
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (!$user instanceof Fournisseur) {
            return response()->json([
                'message' => 'Accès refusé (Fournisseur uniquement)'
            ], 403);
        }

        return $next($request);
    }
}
