<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Terrain;
use App\Models\TerrainHoraire;
use App\Models\Reservation;
use App\Models\HoraireBloque;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Carbon\Carbon;

class TerrainHoraireController extends Controller
{
    // ================= AJOUT / UPDATE HORAIRE =================
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'terrain_id' => 'required|exists:terrains,id',
            'jour' => 'required|string',
            'heure_ouverture' => 'nullable',
            'heure_fermeture' => 'nullable',
            'is_closed' => 'boolean'
        ]);

        if ($validator->fails()) {
            return response()->json($validator->errors(), 422);
        }

        $terrain = Terrain::find($request->terrain_id);

        // sécurité : appartient au fournisseur
        if ($terrain->fournisseur_id != $request->user()->id) {
            return response()->json(['message' => 'Non autorisé'], 403);
        }

        // update ou create
        $horaire = TerrainHoraire::updateOrCreate(
            [
                'terrain_id' => $request->terrain_id,
                'jour' => strtolower($request->jour)
            ],
            [
                'heure_ouverture' => $request->heure_ouverture,
                'heure_fermeture' => $request->heure_fermeture,
                'is_closed' => $request->is_closed ?? false,
            ]
        );

        return response()->json([
            'message' => 'Horaire enregistré',
            'data' => $horaire
        ]);
    }

    // ================= LISTE HORAIRE TERRAIN =================
    public function index($terrain_id)
    {
        return response()->json(
            TerrainHoraire::where('terrain_id', $terrain_id)->get()
        );
    }

    // ================= DELETE =================
    public function destroy(Request $request, $id)
    {
        $horaire = TerrainHoraire::find($id);

        if (!$horaire) {
            return response()->json(['message' => 'Introuvable'], 404);
        }

        if ($horaire->terrain->fournisseur_id != $request->user()->id) {
            return response()->json(['message' => 'Non autorisé'], 403);
        }

        $horaire->delete();

        return response()->json(['message' => 'Supprimé']);
    }

    // ================= DISPONIBILITÉ =================
public function disponibilite(Request $request, $terrain_id)
{
    $date = $request->date;

    if (!$date) {
        return response()->json(['message' => 'Date requise'], 400);
    }

    $dayName = strtolower(Carbon::parse($date)->locale('fr')->dayName);

    $horaire = TerrainHoraire::where('terrain_id', $terrain_id)
        ->where('jour', $dayName)
        ->first();

    if (!$horaire || $horaire->is_closed) {
        return response()->json([]);
    }

    $start = Carbon::parse($horaire->heure_ouverture);
    $end = Carbon::parse($horaire->heure_fermeture);

    $slots = [];

    while ($start < $end) {
        $slotStart = $start->format('H:i');
        $slotEnd = $start->copy()->addHour()->format('H:i');

        $isReserved = Reservation::where('terrain_id', $terrain_id)
            ->whereDate('date', $date)
            ->where('statut', 'confirmee')
            ->where('heure_debut', '<', $slotEnd)
            ->where('heure_fin', '>', $slotStart)
            ->exists();

        $isBlocked = HoraireBloque::where('terrain_id', $terrain_id)
            ->whereDate('date', $date)
            ->where('heure_debut', '<', $slotEnd)
            ->where('heure_fin', '>', $slotStart)
            ->exists();

        $slots[] = [
            'heure_debut' => $slotStart,
            'heure_fin' => $slotEnd,
            'disponible' => !$isReserved && !$isBlocked
        ];

        $start->addHour();
    }

    return response()->json($slots);
}
}
