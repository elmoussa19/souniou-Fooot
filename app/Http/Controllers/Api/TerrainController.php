<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Terrain;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Storage;


class TerrainController extends Controller
{
    // ================= LIST ALL TERRAIN =================
    public function index()
    {
        return response()->json(
            Terrain::with('fournisseur')->latest()->get()
        );
    }

    // ================= CREATE TERRAIN =================
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'name'           => 'required|string|max:255',
            'type'           => 'required|string',
            'latitude'       => 'required|numeric',
            'longitude'      => 'required|numeric',

            'image1'         => 'nullable|image|mimes:jpg,jpeg,png|max:2048',
            'image2'         => 'nullable|image|mimes:jpg,jpeg,png|max:2048',
            'image3'         => 'nullable|image|mimes:jpg,jpeg,png|max:2048',

            'adresse'        => 'required|string',
            'quartier'       => 'required|string',
            'ville'          => 'required|string',

            'type_surface'   => 'required|string',
            'capacite'       => 'nullable|integer|min:1',
            'prix_par_heure' => 'required|numeric|min:1000',
        ]);

        if ($validator->fails()) {
            return response()->json($validator->errors(), 422);
        }

        // Upload des images
        $image1 = $request->file('image1')?->store('terrains', 'public');
        $image2 = $request->file('image2')?->store('terrains', 'public');
        $image3 = $request->file('image3')?->store('terrains', 'public');

        $terrain = Terrain::create([
            'name'           => $request->name,
            'type'           => $request->type,
            'latitude'       => $request->latitude,
            'longitude'      => $request->longitude,

            'image1'         => $image1,
            'image2'         => $image2,
            'image3'         => $image3,

            'adresse'        => $request->adresse,
            'quartier'       => $request->quartier,
            'ville'          => $request->ville,

            'type_surface'   => $request->type_surface,
            'capacite'       => $request->capacite,
            'prix_par_heure' => $request->prix_par_heure,

            'fournisseur_id' => $request->user()->id,
        ]);

        return response()->json([
            'message' => 'Terrain créé avec succès',
            'terrain' => $terrain
        ]);
    }

    // ================= SHOW ONE TERRAIN =================
    public function show(int $id)
    {
        $terrain = Terrain::with('fournisseur')->find($id);

        if (!$terrain) {
            return response()->json(['message' => 'Terrain introuvable'], 404);
        }

        return response()->json($terrain);
    }

    // ================= UPDATE TERRAIN (avec gestion des 3 images) =================
    public function update(Request $request, int $id)
    {
        $terrain = Terrain::find($id);

        if (!$terrain) {
            return response()->json(['message' => 'Terrain introuvable'], 404);
        }

        // Sécurité : seul le propriétaire peut modifier
        if ($terrain->fournisseur_id != $request->user()->id) {
            return response()->json(['message' => 'Non autorisé'], 403);
        }

        $validator = Validator::make($request->all(), [
            'name'           => 'sometimes|required|string|max:255',
            'type'           => 'sometimes|required|string',
            'latitude'       => 'sometimes|required|numeric',
            'longitude'      => 'sometimes|required|numeric',
            'adresse'        => 'sometimes|required|string',
            'quartier'       => 'sometimes|required|string',
            'ville'          => 'sometimes|required|string',
            'type_surface'   => 'sometimes|required|string',
            'capacite'       => 'nullable|integer|min:1',
            'prix_par_heure' => 'sometimes|required|numeric|min:1000',

            'image1'         => 'nullable|image|mimes:jpg,jpeg,png|max:2048',
            'image2'         => 'nullable|image|mimes:jpg,jpeg,png|max:2048',
            'image3'         => 'nullable|image|mimes:jpg,jpeg,png|max:2048',
        ]);

        if ($validator->fails()) {
            return response()->json($validator->errors(), 422);
        }

        // Gestion des images (seulement si une nouvelle image est envoyée)
        if ($request->hasFile('image1')) {
            $terrain->image1 = $request->file('image1')->store('terrains', 'public');
        }
        if ($request->hasFile('image2')) {
            $terrain->image2 = $request->file('image2')->store('terrains', 'public');
        }
        if ($request->hasFile('image3')) {
            $terrain->image3 = $request->file('image3')->store('terrains', 'public');
        }

        // Mise à jour des autres champs
        $terrain->update($request->only([
            'name', 'type', 'latitude', 'longitude', 'adresse',
            'quartier', 'ville', 'type_surface', 'capacite', 'prix_par_heure'
        ]));

        return response()->json([
            'message' => 'Terrain mis à jour avec succès',
            'terrain' => $terrain
        ]);
    }

    // ================= DELETE TERRAIN =================
    public function destroy(Request $request, $id)
    {
        $terrain = Terrain::find($id);

        if (!$terrain) {
            return response()->json(['message' => 'Terrain introuvable'], 404);
        }

        if ($terrain->fournisseur_id != $request->user()->id) {
            return response()->json(['message' => 'Non autorisé'], 403);
        }

        $terrain->delete();

        return response()->json(['message' => 'Terrain supprimé avec succès']);
    }

    // ================= TERRAIN PAR FOURNISSEUR =================
    public function myTerrains(Request $request)
    {
        return response()->json(
            Terrain::where('fournisseur_id', $request->user()->id)->get()
        );
    }

    // ================= TERRAINS LES PLUS POPULAIRES (Top 4) =================
    public function popular()
    {
        $terrains = Terrain::with('fournisseur')
            ->withCount('reservations')
            ->orderByDesc('reservations_count')
            ->limit(4)
            ->get();

        if ($terrains->isEmpty() || $terrains->every(fn($t) => $t->reservations_count == 0)) {
            $terrains = Terrain::with('fournisseur')->latest()->limit(4)->get();
        }

        return response()->json($terrains);
    }

    // ================= TERRAINS LES MOINS POPULAIRES (Bottom 4) =================
    public function leastPopular()
    {
        $terrains = Terrain::with('fournisseur')
            ->withCount('reservations')
            ->orderBy('reservations_count', 'asc')
            ->limit(4)
            ->get();

        if ($terrains->isEmpty() || $terrains->every(fn($t) => $t->reservations_count == 0)) {
            $terrains = Terrain::with('fournisseur')->oldest()->limit(4)->get();
        }

        return response()->json($terrains);
    }

    // ================= UPDATE IMAGE 1 =================
public function updateImage1(Request $request, int $id)
{
    $terrain = Terrain::find($id);

    if (!$terrain) {
        return response()->json(['message' => 'Terrain introuvable'], 404);
    }

    if ($terrain->fournisseur_id != $request->user()->id) {
        return response()->json(['message' => 'Non autorisé'], 403);
    }

    $validator = Validator::make($request->all(), [
        'image1' => 'required|image|mimes:jpg,jpeg,png|max:2048',
    ]);

    if ($validator->fails()) {
        return response()->json($validator->errors(), 422);
    }

    // Supprimer l'ancienne image si elle existe
    if ($terrain->image1) {
        Storage::disk('public')->delete($terrain->image1);
    }

    $terrain->image1 = $request->file('image1')->store('terrains', 'public');
    $terrain->save();

    return response()->json([
        'message' => 'Image 1 mise à jour avec succès',
        'image1'  => $terrain->image1
    ]);
}

// ================= UPDATE IMAGE 2 =================
public function updateImage2(Request $request, int $id)
{
    $terrain = Terrain::find($id);

    if (!$terrain) {
        return response()->json(['message' => 'Terrain introuvable'], 404);
    }

    if ($terrain->fournisseur_id != $request->user()->id) {
        return response()->json(['message' => 'Non autorisé'], 403);
    }

    $validator = Validator::make($request->all(), [
        'image2' => 'required|image|mimes:jpg,jpeg,png|max:2048',
    ]);

    if ($validator->fails()) {
        return response()->json($validator->errors(), 422);
    }

    if ($terrain->image2) {
        Storage::disk('public')->delete($terrain->image2);
    }

    $terrain->image2 = $request->file('image2')->store('terrains', 'public');
    $terrain->save();

    return response()->json([
        'message' => 'Image 2 mise à jour avec succès',
        'image2'  => $terrain->image2
    ]);
}

// ================= UPDATE IMAGE 3 =================
public function updateImage3(Request $request, int $id)
{
    $terrain = Terrain::find($id);

    if (!$terrain) {
        return response()->json(['message' => 'Terrain introuvable'], 404);
    }

    if ($terrain->fournisseur_id != $request->user()->id) {
        return response()->json(['message' => 'Non autorisé'], 403);
    }

    $validator = Validator::make($request->all(), [
        'image3' => 'required|image|mimes:jpg,jpeg,png|max:2048',
    ]);

    if ($validator->fails()) {
        return response()->json($validator->errors(), 422);
    }

    if ($terrain->image3) {
        Storage::disk('public')->delete($terrain->image3);
    }

    $terrain->image3 = $request->file('image3')->store('terrains', 'public');
    $terrain->save();

    return response()->json([
        'message' => 'Image 3 mise à jour avec succès',
        'image3'  => $terrain->image3
    ]);
}
}
