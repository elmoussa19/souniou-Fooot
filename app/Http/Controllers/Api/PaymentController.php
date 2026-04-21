<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Payment;
use App\Models\Reservation;
use App\Models\HoraireBloque;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;

class PaymentController extends Controller
{
    // ================= INITIER LE PAIEMENT (PayTech) =================
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'reservation_id' => 'required|exists:reservations,id',
            'methode'        => 'required|in:wave,orange_money,card',
            'type'           => 'required|in:partiel,entier',
        ]);

        if ($validator->fails()) {
            return response()->json($validator->errors(), 422);
        }

        return DB::transaction(function () use ($request) {

            // ===== Charger la réservation avec verrou =====
            $reservation = Reservation::with('terrain')
                ->lockForUpdate()
                ->findOrFail($request->reservation_id);

            // ===== Sécurité utilisateur =====
            if ($reservation->user_id !== $request->user()->id) {
                return response()->json(['message' => 'Non autorisé'], 403);
            }

            // ===== Statuts bloquants =====
            if ($reservation->statut === 'confirmee') {
                return response()->json(['message' => 'Cette réservation est déjà confirmée'], 409);
            }

            if ($reservation->statut === 'annulee') {
                return response()->json(['message' => 'Cette réservation a été annulée'], 409);
            }

            // ===== Vérifier paiement en_attente sur CETTE réservation =====
            // On charge le MODEL Eloquent complet (pas stdClass)
            $paiementEnCours = Payment::where('reservation_id', $reservation->id)
                ->where('statut', 'en_attente')
                ->latest()
                ->first(); // retourne un Model Payment ou null

            if ($paiementEnCours instanceof Payment) {
                if (!$paiementEnCours->isExpiredAndPending()) {
                    // Paiement actif non expiré → bloquer
                    return response()->json([
                        'message' => 'Un paiement est déjà en cours pour cette réservation. Réessayez dans quelques minutes.'
                    ], 409);
                }

                // Paiement expiré → libérer
                $paiementEnCours->update(['statut' => 'echoue']);
                $reservation->update(['statut' => 'en_attente']);
                $reservation->refresh(); // recharger après update
            }

            // ===== Vérifier paiement déjà validé =====
            $dejaPaye = Payment::where('reservation_id', $reservation->id)
                ->where('statut', 'valide')
                ->exists();

            if ($dejaPaye) {
                return response()->json(['message' => 'Cette réservation est déjà payée'], 409);
            }

            // ===== Calcul du montant =====
            $terrain = $reservation->terrain;

            if (!$terrain || !$terrain->prix_par_heure) {
                return response()->json(['message' => 'Prix du terrain non disponible'], 422);
            }

            $debut = Carbon::parse($reservation->heure_debut);
            $fin   = Carbon::parse($reservation->heure_fin);

            if ($fin->lt($debut)) {
                $fin->addDay();
            }

            $dureeHeures = $fin->diffInHours($debut);
            if ($dureeHeures <= 0) $dureeHeures = 1;

            $montantTotal  = $terrain->prix_par_heure * $dureeHeures;
            $montantAPayer = $request->type === 'entier'
                ? $montantTotal
                : round($montantTotal * 0.5);
            $reste = $montantTotal - $montantAPayer;

            // ===== Vérification créneau toujours libre =====
            $creneauPris = Reservation::where('terrain_id', $reservation->terrain_id)
                ->whereDate('date', $reservation->date)
                ->where('id', '!=', $reservation->id)
                ->where('statut', 'confirmee')
                ->where('heure_debut', '<', $reservation->heure_fin)
                ->where('heure_fin', '>', $reservation->heure_debut)
                ->exists();

            if ($creneauPris) {
                $reservation->update(['statut' => 'annulee']);
                return response()->json([
                    'message' => 'Ce créneau vient d\'être pris. Votre réservation a été annulée.'
                ], 409);
            }

            // ===== Vérification horaire bloqué =====
            $isBlocked = HoraireBloque::where('terrain_id', $reservation->terrain_id)
                ->whereDate('date', $reservation->date)
                ->where('heure_debut', '<', $reservation->heure_fin)
                ->where('heure_fin', '>', $reservation->heure_debut)
                ->exists();

            if ($isBlocked) {
                return response()->json(['message' => 'Ce créneau est bloqué'], 409);
            }

            // ===== Correspondance méthode → label PayTech =====
            $methodesPaytech = [
                'wave'         => 'Wave',
                'orange_money' => 'Orange Money',
                'card'         => 'Carte Bancaire',
            ];
            $targetPayment = $methodesPaytech[$request->methode] ?? 'Wave';

            // ===== Appel API PayTech =====
            $paytechResponse = Http::withHeaders([
                'API_KEY'    => env('PAYTECH_API_KEY'),
                'API_SECRET' => env('PAYTECH_API_SECRET'),
            ])->post('https://paytech.sn/api/payment/request-payment', [
                'item_name'      => 'Réservation ' . $terrain->name,
                'item_price'     => (int) $montantAPayer,
                'currency'       => 'XOF',
                'ref_command'    => 'REF-' . $reservation->id . '-' . time(),
                'command_name'   => 'Réservation terrain ' . $terrain->name,
                'env'            => env('PAYTECH_ENV', 'test'),
                'ipn_url'        => env('PAYTECH_IPN_URL'),
                'success_url'    => env('PAYTECH_SUCCESS_URL'),
                'cancel_url'     => env('PAYTECH_CANCEL_URL'),
                'target_payment' => $targetPayment,
                'custom_field'   => json_encode([
                    'reservation_id' => $reservation->id,
                    'user_id'        => $request->user()->id,
                    'type'           => $request->type,
                    'montant_total'  => $montantTotal,
                    'montant_paye'   => $montantAPayer,
                    'reste'          => $reste,
                    'methode'        => $request->methode,
                ]),
            ]);

            // ===== Log complet pour debug =====
            Log::info('PayTech response', [
                'status'  => $paytechResponse->status(),
                'body'    => $paytechResponse->body(),
            ]);

            // ===== Vérifier la réponse PayTech =====
            if (!$paytechResponse->successful()) {
                return response()->json([
                    'message' => 'PayTech indisponible. Réessayez. (' . $paytechResponse->status() . ')'
                ], 502);
            }

            $paytechData = $paytechResponse->json();

            // PayTech retourne success=1 et redirect_url si tout va bien
            if (!isset($paytechData['success']) || $paytechData['success'] != 1) {
                Log::error('PayTech échec', ['data' => $paytechData]);
                return response()->json([
                    'message' => $paytechData['errors'][0] ?? 'Erreur PayTech. Vérifiez vos clés API.'
                ], 502);
            }

            $redirectUrl  = $paytechData['redirect_url'];
            $paytechToken = $paytechData['token'] ?? null;

            // ===== Créer le paiement en attente =====
            $payment = Payment::create([
                'reservation_id'     => $reservation->id,
                'user_id'            => $request->user()->id,
                'montant_total'      => $montantTotal,
                'montant_paye'       => $montantAPayer,
                'reste'              => $reste,
                'type'               => $request->type,
                'methode'            => $request->methode,
                'statut'             => 'en_attente',
                'paytech_token'      => $paytechToken,
                'paiement_initie_at' => Carbon::now(),
            ]);

            // ===== Verrouiller la réservation =====
            $reservation->update(['statut' => 'paiement_en_cours']);

            return response()->json([
                'message'      => 'Paiement initié avec succès',
                'redirect_url' => $redirectUrl,
                'payment_id'   => $payment->id,
            ], 201);
        });
    }

    // ================= IPN — Webhook PayTech =================
    // Route PUBLIQUE — PayTech appelle cette URL après paiement
    public function ipn(Request $request)
    {
        $apiKey    = env('PAYTECH_API_KEY');
        $apiSecret = env('PAYTECH_API_SECRET');

        // ===== Vérification signature PayTech =====
        $receivedKeyHash    = $request->input('api_key_sha256');
        $receivedSecretHash = $request->input('api_secret_sha256');

        if (
            $receivedKeyHash    !== hash('sha256', $apiKey) ||
            $receivedSecretHash !== hash('sha256', $apiSecret)
        ) {
            Log::warning('IPN PayTech: signature invalide', $request->all());
            return response()->json(['message' => 'Signature invalide'], 403);
        }

        // ===== Extraire custom_field =====
        $customField = json_decode($request->input('custom_field'), true);

        if (!$customField || !isset($customField['reservation_id'])) {
            Log::error('IPN PayTech: custom_field manquant', $request->all());
            return response()->json(['message' => 'custom_field manquant'], 400);
        }

        $reservationId = (int) $customField['reservation_id'];

        return DB::transaction(function () use ($request, $reservationId) {

            // ===== Charger la réservation avec verrou =====
            $reservation = Reservation::lockForUpdate()->find($reservationId);

            if (!($reservation instanceof Reservation)) {
                return response()->json(['message' => 'Réservation introuvable'], 404);
            }

            // ===== Charger le paiement en_attente lié =====
            $payment = Payment::where('reservation_id', $reservationId)
                ->where('statut', 'en_attente')
                ->latest()
                ->first();

            if (!($payment instanceof Payment)) {
                Log::warning('IPN: paiement en_attente introuvable', ['reservation_id' => $reservationId]);
                return response()->json(['message' => 'Paiement introuvable'], 404);
            }

            $typeEvent = $request->input('type_event');

            Log::info('IPN PayTech reçu', [
                'type_event'     => $typeEvent,
                'reservation_id' => $reservationId,
                'ref_command'    => $request->input('ref_command'),
            ]);

            // ===== Paiement réussi =====
            if ($typeEvent === 'sale_complete') {

                // Vérifier si le créneau a été pris entre temps par quelqu'un d'autre
                $creneauPris = Reservation::where('terrain_id', $reservation->terrain_id)
                    ->whereDate('date', $reservation->date)
                    ->where('id', '!=', $reservation->id)
                    ->where('statut', 'confirmee')
                    ->where('heure_debut', '<', $reservation->heure_fin)
                    ->where('heure_fin', '>', $reservation->heure_debut)
                    ->exists();

                if ($creneauPris) {
                    $payment->update([
                        'statut'         => 'rembourse',
                        'transaction_id' => $request->input('ref_command'),
                    ]);
                    $reservation->update(['statut' => 'annulee']);

                    Log::warning('IPN: créneau pris entre temps, remboursement requis', [
                        'reservation_id' => $reservationId,
                    ]);
                    return response()->json(['message' => 'OK - remboursement requis']);
                }

                // Créneau libre → confirmer
                $payment->update([
                    'statut'         => 'valide',
                    'transaction_id' => $request->input('ref_command'),
                ]);
                $reservation->update(['statut' => 'confirmee']);

                Log::info('IPN: réservation confirmée', ['reservation_id' => $reservationId]);
                return response()->json(['message' => 'OK']);
            }

            // ===== Paiement annulé ou échoué =====
            if (in_array($typeEvent, ['sale_canceled', 'sale_failed'])) {
                $payment->update(['statut' => 'echoue']);
                $reservation->update(['statut' => 'en_attente']);

                Log::info('IPN: paiement échoué/annulé', ['reservation_id' => $reservationId]);
                return response()->json(['message' => 'OK']);
            }

            Log::warning('IPN: type_event non traité', ['type_event' => $typeEvent]);
            return response()->json(['message' => 'Événement non traité'], 200);
        });
    }

    // ================= CANCEL =================
    public function cancel(Request $request)
    {
        return response()->json([
            'message' => 'Paiement annulé',
            'statut'  => 'cancel',
        ]);
    }

    // ================= SUCCESS =================
    public function success(Request $request)
    {
        // La vraie confirmation vient de l'IPN, pas d'ici
        return response()->json([
            'message' => 'Paiement en cours de validation',
            'statut'  => 'success',
        ]);
    }

    // ================= MES PAIEMENTS =================
    public function myPayments(Request $request)
    {
        $payments = Payment::with(['reservation.terrain'])
            ->where('user_id', $request->user()->id)
            ->orderBy('created_at', 'desc')
            ->get();

        return response()->json($payments);
    }

    // ================= SHOW ONE PAYMENT =================
    public function show($id)
    {
        $payment = Payment::with(['reservation.terrain', 'user'])->find($id);

        if (!$payment) {
            return response()->json(['message' => 'Paiement introuvable'], 404);
        }

        return response()->json($payment);
    }
}
