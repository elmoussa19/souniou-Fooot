<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Reservation;
use App\Models\Terrain;
use App\Models\HoraireBloque;
use App\Models\Payment;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class ReservationController extends Controller
{
    // ================= CREATE RESERVATION =================
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'terrain_id'  => 'required|exists:terrains,id',
            'date'        => 'required|date',
            'heure_debut' => 'required|date_format:H:i',
            'heure_fin'   => 'required|date_format:H:i|after:heure_debut',
        ]);

        if ($validator->fails()) {
            return response()->json($validator->errors(), 422);
        }

        $terrainId  = $request->terrain_id;
        $date       = $request->date;
        $heureDebut = $request->heure_debut;
        $heureFin   = $request->heure_fin;
        $userId     = $request->user()->id;

        // ===== 1. Vérification des heures bloquées =====
        $isBlocked = HoraireBloque::where('terrain_id', $terrainId)
            ->whereDate('date', $date)
            ->where('heure_debut', '<', $heureFin)
            ->where('heure_fin', '>', $heureDebut)
            ->exists();

        if ($isBlocked) {
            return response()->json([
                'message' => 'Ce créneau est bloqué et indisponible'
            ], 409);
        }

        // ===== 2. Vérification des réservations CONFIRMÉES =====
        $existsConfirmed = Reservation::where('terrain_id', $terrainId)
            ->whereDate('date', $date)
            ->where('statut', 'confirmee')
            ->where('heure_debut', '<', $heureFin)
            ->where('heure_fin', '>', $heureDebut)
            ->exists();

        if ($existsConfirmed) {
            return response()->json([
                'message' => 'Ce créneau est déjà réservé et confirmé'
            ], 409);
        }

        // ===== 3. Vérification paiement_en_cours actif d'un AUTRE utilisateur =====
        /** @var \Illuminate\Database\Eloquent\Collection<int, Reservation> $reservationsEnCours */
        $reservationsEnCours = Reservation::where('terrain_id', $terrainId)
            ->whereDate('date', $date)
            ->where('statut', 'paiement_en_cours')
            ->where('user_id', '!=', $userId)
            ->where('heure_debut', '<', $heureFin)
            ->where('heure_fin', '>', $heureDebut)
            ->with('payment')
            ->get();

        foreach ($reservationsEnCours as $resEnCours) {
            // Typage explicite pour que l'IDE reconnaisse le Model
            /** @var Reservation $resEnCours */

            if ($resEnCours->hasPaiementEnCoursActif()) {
                return response()->json([
                    'message' => 'Un paiement est en cours pour ce créneau. Réessayez dans quelques minutes.'
                ], 409);
            }

            // Paiement expiré → libérer
            $resEnCours->update(['statut' => 'annulee']);

            $payment = $resEnCours->payment;
            if ($payment instanceof Payment) {
                $payment->update(['statut' => 'echoue']);
            }
        }

        // ===== 4. Vérification que l'utilisateur n'a PAS déjà ce créneau =====
        $existsUser = Reservation::where('terrain_id', $terrainId)
            ->where('user_id', $userId)
            ->whereDate('date', $date)
            ->whereIn('statut', ['en_attente', 'paiement_en_cours', 'confirmee'])
            ->where('heure_debut', '<', $heureFin)
            ->where('heure_fin', '>', $heureDebut)
            ->exists();

        if ($existsUser) {
            return response()->json([
                'message' => 'Vous avez déjà une réservation sur ce créneau'
            ], 409);
        }

        // ===== 5. Création de la réservation =====
        $reservation = Reservation::create([
            'terrain_id'  => $terrainId,
            'user_id'     => $userId,
            'date'        => $date,
            'heure_debut' => $heureDebut,
            'heure_fin'   => $heureFin,
            'statut'      => 'en_attente'
        ]);

        $reservation->load('terrain');

        return response()->json([
            'message' => 'Réservation créée avec succès, en attente de paiement',
            'data'    => $reservation
        ], 201);
    }

    // ================= MES RÉSERVATIONS (USER) =================
    public function mesReservations(Request $request)
    {
        $data = Reservation::with(['terrain', 'payment'])
            ->where('user_id', $request->user()->id)
            ->orderBy('date', 'desc')
            ->orderBy('heure_debut', 'desc')
            ->get();

        return response()->json($data);
    }

    // ================= RÉSERVATIONS DU FOURNISSEUR =================
    public function reservationsFournisseur(Request $request)
    {
        $terrains = Terrain::where('fournisseur_id', $request->user()->id)
            ->pluck('id');

        $reservations = Reservation::with(['user', 'terrain', 'payment'])
            ->whereIn('terrain_id', $terrains)
            ->orderBy('date', 'desc')
            ->orderBy('heure_debut', 'desc')
            ->get();

        return response()->json($reservations);
    }

    // ================= SHOW UNE RÉSERVATION =================
    public function show($id)
    {
        $reservation = Reservation::with(['user', 'terrain', 'payment'])->find($id);

        if (!$reservation) {
            return response()->json(['message' => 'Réservation introuvable'], 404);
        }

        return response()->json($reservation);
    }

    // ================= UPDATE STATUS (FOURNISSEUR) =================
    public function updateStatus(Request $request, $id)
    {
        $request->validate([
            'statut' => 'required|in:en_attente,annulee'
        ]);

        /** @var Reservation|null $reservation */
        $reservation = Reservation::find($id);

        if (!($reservation instanceof Reservation)) {
            return response()->json(['message' => 'Réservation introuvable'], 404);
        }

        /** @var Terrain|null $terrain */
        $terrain = Terrain::find($reservation->terrain_id);

        if (!($terrain instanceof Terrain)) {
            return response()->json(['message' => 'Terrain introuvable'], 404);
        }

        if ($terrain->fournisseur_id != $request->user()->id) {
            return response()->json(['message' => 'Non autorisé'], 403);
        }

        if (in_array($reservation->statut, ['confirmee', 'paiement_en_cours'])) {
            return response()->json([
                'message' => 'Impossible de modifier une réservation confirmée ou dont le paiement est en cours'
            ], 409);
        }

        $reservation->update(['statut' => $request->statut]);

        return response()->json([
            'message' => 'Statut mis à jour avec succès',
            'data'    => $reservation
        ]);
    }

    // ================= DELETE (USER) =================
    public function destroy(Request $request, $id)
    {
        /** @var Reservation|null $reservation */
        $reservation = Reservation::find($id);

        if (!($reservation instanceof Reservation)) {
            return response()->json(['message' => 'Réservation introuvable'], 404);
        }

        if ($reservation->user_id != $request->user()->id) {
            return response()->json(['message' => 'Non autorisé'], 403);
        }

        if ($reservation->statut === 'paiement_en_cours') {
            return response()->json([
                'message' => 'Impossible d\'annuler une réservation dont le paiement est en cours'
            ], 409);
        }

        if ($reservation->statut === 'confirmee') {
            return response()->json([
                'message' => 'Impossible d\'annuler une réservation déjà confirmée'
            ], 409);
        }

        $reservation->delete();

        return response()->json(['message' => 'Réservation annulée avec succès']);
    }
}
