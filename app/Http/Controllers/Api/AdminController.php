<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Admin;
use App\Models\Fournisseur;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;

class AdminController extends Controller
{
    // ================= REGISTER =================
    public function register(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255',
            'email' => 'required|email|unique:admins',
            'phone' => 'nullable|string|max:20',
            'image' => 'nullable|image|mimes:jpg,jpeg,png',
            'password' => 'required|min:6|confirmed',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'errors' => $validator->errors()
            ], 422);
        }

        $imagePath = null;
        if ($request->hasFile('image')) {
            $imagePath = $request->file('image')->store('admins', 'public');
        }

        $admin = Admin::create([
            'name' => $request->name,
            'email' => $request->email,
            'phone' => $request->phone,
            'image' => $imagePath,
            'password' => $request->password,
        ]);

        $token = $admin->createToken('admin-token')->plainTextToken;

        return response()->json([
            'message' => 'Admin créé avec succès',
            'token' => $token,
            'admin' => $admin
        ]);
    }

    // ================= LOGIN =================
    public function login(Request $request)
    {
        $request->validate([
            'email' => 'required|email',
            'password' => 'required'
        ]);

        $admin = Admin::where('email', $request->email)->first();

        if (!$admin || !Hash::check($request->password, $admin->password)) {
            return response()->json([
                'message' => 'Email ou mot de passe incorrect'
            ], 401);
        }

        $token = $admin->createToken('admin-token')->plainTextToken;

        return response()->json([
            'message' => 'Connexion admin réussie',
            'token' => $token,
            'admin' => $admin
        ]);
    }

    // ================= LOGOUT =================
    public function logout(Request $request)
    {
        $request->user()->currentAccessToken()->delete();

        return response()->json([
            'message' => 'Déconnexion admin réussie'
        ]);
    }

    // ================= LISTE FOURNISSEURS =================
    public function getAllFournisseurs()
    {
        $fournisseurs = Fournisseur::withCount('terrains')->orderBy('created_at', 'desc')->get();

        return response()->json($fournisseurs);
    }

    // ================= ACTIVER FOURNISSEUR =================
    public function activateFournisseur($id)
    {
        $fournisseur = Fournisseur::find($id);

        if (!$fournisseur) {
            return response()->json([
                'message' => 'Fournisseur non trouvé'
            ], 404);
        }

        $fournisseur->is_active = true;
        $fournisseur->save();

        return response()->json([
            'message' => 'Fournisseur activé',
            'fournisseur' => $fournisseur
        ]);
    }

    // ================= DESACTIVER FOURNISSEUR =================
    public function deactivateFournisseur($id)
    {
        $fournisseur = Fournisseur::find($id);

        if (!$fournisseur) {
            return response()->json([
                'message' => 'Fournisseur non trouvé'
            ], 404);
        }

        $fournisseur->is_active = false;
        $fournisseur->save();

        return response()->json([
            'message' => 'Fournisseur désactivé',
            'fournisseur' => $fournisseur
        ]);
    }

    // ================= DETAIL FOURNISSEUR =================
public function getFournisseur($id)
{
    $fournisseur = Fournisseur::withCount('terrains')->find($id);
    if (!$fournisseur) {
        return response()->json(['message' => 'Fournisseur introuvable'], 404);
    }
    return response()->json($fournisseur);
}

// ================= TERRAINS D'UN FOURNISSEUR =================
public function getTerrainsFournisseur($id)
{
    $terrains = \App\Models\Terrain::where('fournisseur_id', $id)
        ->withCount('reservations')
        ->latest()
        ->get();
    return response()->json($terrains);
}

// ================= RÉSERVATIONS D'UN FOURNISSEUR =================
public function getReservationsFournisseur($id)
{
    $terrainIds = \App\Models\Terrain::where('fournisseur_id', $id)->pluck('id');
    $reservations = \App\Models\Reservation::with(['user', 'terrain', 'payment'])
        ->whereIn('terrain_id', $terrainIds)
        ->orderBy('date', 'desc')
        ->get();
    return response()->json($reservations);
}

// ================= PAIEMENTS D'UN FOURNISSEUR =================
public function getPaymentsFournisseur($id)
{
    $terrainIds = \App\Models\Terrain::where('fournisseur_id', $id)->pluck('id');
    $reservationIds = \App\Models\Reservation::whereIn('terrain_id', $terrainIds)->pluck('id');
    $payments = \App\Models\Payment::with(['reservation.terrain', 'user'])
        ->whereIn('reservation_id', $reservationIds)
        ->orderBy('created_at', 'desc')
        ->get();
    return response()->json($payments);
}
}
