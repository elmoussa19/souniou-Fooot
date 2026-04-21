<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Fournisseur;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;

class FournisseurController extends Controller
{
    // ================= REGISTER =================
    public function register(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255',
            'email' => 'required|email|unique:fournisseurs',
            'phone' => 'nullable|string|max:20',
            'image' => 'nullable|image|mimes:jpg,jpeg,png',
            'password' => 'required|min:6|confirmed',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'errors' => $validator->errors()
            ], 422);
        }

        // Upload image
        $imagePath = null;
        if ($request->hasFile('image')) {
            $imagePath = $request->file('image')->store('fournisseurs', 'public');
        }

        $fournisseur = Fournisseur::create([
            'name' => $request->name,
            'email' => $request->email,
            'phone' => $request->phone,
            'image' => $imagePath,
            'is_active' => false, // actif par défaut
            'password' => $request->password, // hash auto
        ]);

        $token = $fournisseur->createToken('fournisseur-token')->plainTextToken;

        return response()->json([
            'message' => 'Fournisseur créé avec succès',
            'token' => $token,
            'fournisseur' => $fournisseur
        ]);
    }

    // ================= LOGIN =================
    public function login(Request $request)
    {
        $request->validate([
            'email' => 'required|email',
            'password' => 'required'
        ]);

        $fournisseur = Fournisseur::where('email', $request->email)->first();

        if (!$fournisseur || !Hash::check($request->password, $fournisseur->password)) {
            return response()->json([
                'message' => 'Email ou mot de passe incorrect'
            ], 401);
        }

        // Vérifier si actif
        if (!$fournisseur->is_active) {
            return response()->json([
                'message' => 'Compte désactivé'
            ], 403);
        }

        $token = $fournisseur->createToken('fournisseur-token')->plainTextToken;

        return response()->json([
            'message' => 'Connexion réussie',
            'token' => $token,
            'fournisseur' => $fournisseur
        ]);
    }

    // ================= LOGOUT =================
    public function logout(Request $request)
    {
        $request->user()->currentAccessToken()->delete();

        return response()->json([
            'message' => 'Déconnexion réussie'
        ]);
    }
}
