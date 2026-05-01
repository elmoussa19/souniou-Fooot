<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

use App\Http\Controllers\Api\UserController;
use App\Http\Controllers\Api\FournisseurController;
use App\Http\Controllers\Api\AdminController;
use App\Http\Controllers\Api\HoraireBloqueController;
use App\Http\Controllers\Api\PaymentController;
use App\Http\Controllers\Api\ReservationController;
use App\Http\Controllers\Api\TerrainController;
use App\Http\Controllers\Api\TerrainHoraireController;
use App\Http\Controllers\Api\FavoriController;
/*
|--------------------------------------------------------------------------
| PAYTECH — Routes publiques (PayTech appelle directement, pas de auth)
|--------------------------------------------------------------------------
*/
Route::post('/payments/ipn',    [PaymentController::class, 'ipn']);
Route::get('/payments/success', [PaymentController::class, 'success']);
Route::get('/payments/cancel',  [PaymentController::class, 'cancel']);

/*
|--------------------------------------------------------------------------
| USER ROUTES
|--------------------------------------------------------------------------
*/
Route::post('/register', [UserController::class, 'register']);
Route::post('/login',    [UserController::class, 'login']);

Route::middleware('auth:sanctum')->group(function () {
    Route::post('/logout', [UserController::class, 'logout']);

    // Réservations
    Route::post('/reservations',        [ReservationController::class, 'store']);
    Route::get('/mes-reservations',     [ReservationController::class, 'mesReservations']);
    Route::delete('/reservations/{id}', [ReservationController::class, 'destroy']);

    // Paiements — IMPORTANT : les routes statiques avant les routes dynamiques {id}
    Route::post('/payments',                    [PaymentController::class, 'store']);
    Route::get('/my-payments',                  [PaymentController::class, 'myPayments']);   // ?filtre=partiel|complet
    Route::get('/my-payments/partiels',         [PaymentController::class, 'partiels']);     // paiements partiels à compléter
    Route::post('/payments/{id}/complete',      [PaymentController::class, 'complete']);     // compléter un paiement partiel
    Route::get('/payments/{id}',                [PaymentController::class, 'show']);

     Route::prefix('favoris')->group(function () {
        Route::get('/',                     [FavoriController::class, 'index']);    // liste des terrains favoris
        Route::post('/',                    [FavoriController::class, 'store']);    // ajouter aux favoris
        Route::post('/toggle',              [FavoriController::class, 'toggle']);   // toggle (ajouter ou retirer)
        Route::get('/check/{terrain_id}',   [FavoriController::class, 'check']);    // vérifier si en favori
        Route::delete('/{terrain_id}',      [FavoriController::class, 'destroy']); // retirer des favoris
    });
});

/*
|--------------------------------------------------------------------------
| FOURNISSEUR ROUTES
|--------------------------------------------------------------------------
*/
Route::post('/fournisseur/register', [FournisseurController::class, 'register']);
Route::post('/fournisseur/login',    [FournisseurController::class, 'login']);

Route::middleware(['auth:sanctum', 'is_fournisseur'])->group(function () {
    Route::post('/fournisseur/logout', [FournisseurController::class, 'logout']);

    // Gestion terrains
    Route::post('/terrains',        [TerrainController::class, 'store']);
    Route::get('/my-terrains',      [TerrainController::class, 'myTerrains']);
    Route::put('/terrains/{id}',    [TerrainController::class, 'update']);
    Route::delete('/terrains/{id}', [TerrainController::class, 'destroy']);

    // Horaires terrain
    Route::post('/terrain-horaires',        [TerrainHoraireController::class, 'store']);
    Route::delete('/terrain-horaires/{id}', [TerrainHoraireController::class, 'destroy']);
    Route::post('/horaire-bloques',         [HoraireBloqueController::class, 'store']);
    Route::put('/horaire-bloques/{id}',     [HoraireBloqueController::class, 'update']);
    Route::delete('/horaire-bloques/{id}',  [HoraireBloqueController::class, 'destroy']);

    // Réservations de ses terrains
    Route::get('/fournisseur/reservations', [ReservationController::class, 'reservationsFournisseur']);
    Route::put('/reservations/{id}/status', [ReservationController::class, 'updateStatus']);
});

/*
|--------------------------------------------------------------------------
| TERRAINS — Publiques
|--------------------------------------------------------------------------
*/
Route::get('/terrains/popular',       [TerrainController::class, 'popular']);
Route::get('/terrains/least-popular', [TerrainController::class, 'leastPopular']);
Route::get('/terrains',               [TerrainController::class, 'index']);
Route::get('/terrains/{id}',          [TerrainController::class, 'show']);

/*
|--------------------------------------------------------------------------
| HORAIRES — Publiques
|--------------------------------------------------------------------------
*/
Route::get('/terrain-horaires/{terrain_id}',   [TerrainHoraireController::class, 'index']);
Route::get('/terrains/{id}/disponibilites',    [TerrainHoraireController::class, 'disponibilite']);
Route::get('/horaire-bloques/{terrain_id}',    [HoraireBloqueController::class, 'index']);
Route::get('/horaire-bloques/show/{id}',       [HoraireBloqueController::class, 'show']);
Route::get('/reservations/{id}',               [ReservationController::class, 'show']);

/*
|--------------------------------------------------------------------------
| ADMIN ROUTES
|--------------------------------------------------------------------------
*/
Route::post('/admin/register', [AdminController::class, 'register']);
Route::post('/admin/login',    [AdminController::class, 'login']);

Route::middleware(['auth:sanctum', 'is_admin'])->group(function () {
    Route::post('/admin/logout',                          [AdminController::class, 'logout']);
    Route::get('/admin/fournisseurs',                     [AdminController::class, 'getAllFournisseurs']);
    Route::get('/admin/fournisseur/{id}',                 [AdminController::class, 'getFournisseur']);
    Route::get('/admin/fournisseur/{id}/terrains',        [AdminController::class, 'getTerrainsFournisseur']);
    Route::get('/admin/fournisseur/{id}/reservations',    [AdminController::class, 'getReservationsFournisseur']);
    Route::get('/admin/fournisseur/{id}/payments',        [AdminController::class, 'getPaymentsFournisseur']);
    Route::post('/admin/fournisseur/{id}/activate',       [AdminController::class, 'activateFournisseur']);
    Route::post('/admin/fournisseur/{id}/deactivate',     [AdminController::class, 'deactivateFournisseur']);
});

/*
|--------------------------------------------------------------------------
| TEST AUTH
|--------------------------------------------------------------------------
*/
Route::middleware('auth:sanctum')->get('/user', function (Request $request) {
    return $request->user();
});
