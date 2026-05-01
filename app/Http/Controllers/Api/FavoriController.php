<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Favori;
use App\Models\Terrain;
use Illuminate\Http\Request;

class FavoriController extends Controller
{
    // ── GET /api/favoris ─────────────────────────────────────────
    public function index(Request $request)
    {
        $user = $request->user();

        $terrains = $user->terrainsFavoris()->get()->map(function (Terrain $terrain) {
            $data              = $terrain->toArray();
            $data['is_favori'] = true;
            return $data;
        });

        return response()->json([
            'success' => true,
            'data'    => $terrains,
        ]);
    }

    // ── POST /api/favoris ────────────────────────────────────────
    public function store(Request $request)
    {
        $request->validate([
            'terrain_id' => 'required|exists:terrains,id',
        ]);

        $userId    = $request->user()->id;
        $terrainId = (int) $request->terrain_id;

        /** @var Favori|null $exists */
        $exists = Favori::query()
            ->where('user_id', '=', $userId)
            ->where('terrain_id', '=', $terrainId)
            ->first();

        if ($exists) {
            return response()->json([
                'success' => false,
                'message' => 'Ce terrain est déjà dans vos favoris.',
            ], 409);
        }

        Favori::query()->create([
            'user_id'    => $userId,
            'terrain_id' => $terrainId,
        ]);

        return response()->json([
            'success'   => true,
            'message'   => 'Terrain ajouté aux favoris.',
            'is_favori' => true,
        ], 201);
    }

    // ── DELETE /api/favoris/{terrain_id} ─────────────────────────
    public function destroy(Request $request, int $terrainId)
    {
        $userId = $request->user()->id;

        $deleted = Favori::query()
            ->where('user_id', '=', $userId)
            ->where('terrain_id', '=', $terrainId)
            ->delete();

        if (!$deleted) {
            return response()->json([
                'success' => false,
                'message' => 'Ce terrain n\'est pas dans vos favoris.',
            ], 404);
        }

        return response()->json([
            'success'   => true,
            'message'   => 'Terrain retiré des favoris.',
            'is_favori' => false,
        ]);
    }

    // ── GET /api/favoris/check/{terrain_id} ──────────────────────
    public function check(Request $request, int $terrainId)
    {
        $userId = $request->user()->id;

        $isFavori = Favori::query()
            ->where('user_id', '=', $userId)
            ->where('terrain_id', '=', $terrainId)
            ->exists();

        return response()->json([
            'success'   => true,
            'is_favori' => $isFavori,
        ]);
    }

    // ── POST /api/favoris/toggle ─────────────────────────────────
    public function toggle(Request $request)
    {
        $request->validate([
            'terrain_id' => 'required|exists:terrains,id',
        ]);

        $userId    = $request->user()->id;
        $terrainId = (int) $request->terrain_id;

        /** @var Favori|null $favori */
        $favori = Favori::query()
            ->where('user_id', '=', $userId)
            ->where('terrain_id', '=', $terrainId)
            ->first();

        if ($favori) {
            Favori::query()
                ->where('user_id', '=', $userId)
                ->where('terrain_id', '=', $terrainId)
                ->delete();
            return response()->json([
                'success'   => true,
                'is_favori' => false,
                'message'   => 'Terrain retiré des favoris.',
            ]);
        }

        Favori::query()->create([
            'user_id'    => $userId,
            'terrain_id' => $terrainId,
        ]);

        return response()->json([
            'success'   => true,
            'is_favori' => true,
            'message'   => 'Terrain ajouté aux favoris.',
        ]);
    }
}
