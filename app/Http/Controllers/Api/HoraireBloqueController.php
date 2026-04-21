<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\HoraireBloque;
use App\Models\Terrain;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class HoraireBloqueController extends Controller
{
    // ================= CREATE =================
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'terrain_id' => 'required|exists:terrains,id',
            'date' => 'required|date',
            'heure_debut' => 'required',
            'heure_fin' => 'required',
            'raison' => 'nullable|string'
        ]);

        if ($validator->fails()) {
            return response()->json($validator->errors(), 422);
        }

        $terrain = Terrain::find($request->terrain_id);

        // sécurité fournisseur
        if ($terrain->fournisseur_id != $request->user()->id) {
            return response()->json(['message' => 'Non autorisé'], 403);
        }

        $bloque = HoraireBloque::create($request->all());

        return response()->json([
            'message' => 'Créneau bloqué',
            'data' => $bloque
        ]);
    }

    // ================= LIST =================
    public function index($terrain_id)
    {
        $data = HoraireBloque::where('terrain_id', $terrain_id)->get();

        return response()->json($data);
    }

    // ================= SHOW =================
    public function show($id)
    {
        $bloque = HoraireBloque::find($id);

        if (!$bloque) {
            return response()->json(['message' => 'Introuvable'], 404);
        }

        return response()->json($bloque);
    }

    // ================= UPDATE =================
    public function update(Request $request, $id)
    {
        $bloque = HoraireBloque::find($id);

        if (!$bloque) {
            return response()->json(['message' => 'Introuvable'], 404);
        }

        if ($bloque->terrain->fournisseur_id != $request->user()->id) {
            return response()->json(['message' => 'Non autorisé'], 403);
        }

        $bloque->update($request->all());

        return response()->json([
            'message' => 'Mis à jour',
            'data' => $bloque
        ]);
    }

    // ================= DELETE =================
    public function destroy(Request $request, $id)
    {
        $bloque = HoraireBloque::find($id);

        if (!$bloque) {
            return response()->json(['message' => 'Introuvable'], 404);
        }

        if ($bloque->terrain->fournisseur_id != $request->user()->id) {
            return response()->json(['message' => 'Non autorisé'], 403);
        }

        $bloque->delete();

        return response()->json([
            'message' => 'Supprimé'
        ]);
    }
}
