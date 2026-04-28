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

            $reservation = Reservation::with('terrain')
                ->lockForUpdate()
                ->findOrFail($request->reservation_id);

            if ($reservation->user_id !== $request->user()->id) {
                return response()->json(['message' => 'Non autorisé'], 403);
            }

            if (in_array($reservation->statut, ['confirmee', 'annulee'])) {
                return response()->json(['message' => 'Cette réservation n\'est pas éligible au paiement'], 409);
            }

            // Vérifier et libérer paiement en_attente expiré
            $paiementEnCours = Payment::where('reservation_id', $reservation->id)
                ->where('statut', 'en_attente')
                ->latest()
                ->first();

            if ($paiementEnCours instanceof Payment) {
                if (!$paiementEnCours->isExpiredAndPending()) {
                    return response()->json([
                        'message' => 'Un paiement est déjà en cours pour cette réservation. Réessayez dans quelques minutes.'
                    ], 409);
                }

                $paiementEnCours->update(['statut' => 'echoue']);
                $reservation->update(['statut' => 'en_attente']);
                $reservation->refresh();
            }

            // Vérifier paiement déjà validé
            if (Payment::where('reservation_id', $reservation->id)
                ->where('statut', 'valide')
                ->exists()) {
                return response()->json(['message' => 'Cette réservation est déjà payée'], 409);
            }

            // Calcul du montant
            $terrain = $reservation->terrain;
            if (!$terrain || !$terrain->prix_par_heure) {
                return response()->json(['message' => 'Prix du terrain non disponible'], 422);
            }

            $debut = Carbon::parse($reservation->heure_debut);
            $fin   = Carbon::parse($reservation->heure_fin);
            if ($fin->lt($debut)) {
                $fin->addDay();
            }

            $dureeHeures = max(1, $fin->diffInHours($debut));
            $montantTotal  = $terrain->prix_par_heure * $dureeHeures;
            $montantAPayer = $request->type === 'entier' ? $montantTotal : round($montantTotal * 0.5);
            $reste         = $montantTotal - $montantAPayer;

            // Vérifications créneau et horaire bloqué
            $creneauPris = Reservation::where('terrain_id', $reservation->terrain_id)
                ->whereDate('date', $reservation->date)
                ->where('id', '!=', $reservation->id)
                ->where('statut', 'confirmee')
                ->where('heure_debut', '<', $reservation->heure_fin)
                ->where('heure_fin', '>', $reservation->heure_debut)
                ->exists();

            if ($creneauPris) {
                $reservation->update(['statut' => 'annulee']);
                return response()->json(['message' => 'Ce créneau vient d\'être pris. Votre réservation a été annulée.'], 409);
            }

            $isBlocked = HoraireBloque::where('terrain_id', $reservation->terrain_id)
                ->whereDate('date', $reservation->date)
                ->where('heure_debut', '<', $reservation->heure_fin)
                ->where('heure_fin', '>', $reservation->heure_debut)
                ->exists();

            if ($isBlocked) {
                return response()->json(['message' => 'Ce créneau est bloqué'], 409);
            }

            // Appel PayTech
            $paytechResult = $this->initierPaiementPaytech(
                reservation: $reservation,
                terrain: $terrain,
                montantAPayer: $montantAPayer,
                userId: $request->user()->id,
                type: $request->type,
                methode: $request->methode,
                montantTotal: $montantTotal,
                reste: $reste,
                contexte: 'initial'
            );

            if ($paytechResult['error']) {
                return response()->json(['message' => $paytechResult['message']], $paytechResult['code']);
            }

            // Créer le paiement
            $payment = Payment::create([
                'reservation_id'     => $reservation->id,
                'user_id'            => $request->user()->id,
                'montant_total'      => $montantTotal,
                'montant_paye'       => $montantAPayer,
                'reste'              => $reste,
                'type'               => $request->type,
                'methode'            => $request->methode,
                'statut'             => 'en_attente',
                'paytech_token'      => $paytechResult['token'],
                'paiement_initie_at' => Carbon::now(),
            ]);

            $reservation->update(['statut' => 'paiement_en_cours']);

            return response()->json([
                'message'      => 'Paiement initié avec succès',
                'redirect_url' => $paytechResult['redirect_url'],
                'payment_id'   => $payment->id,
            ], 201);
        });
    }

    // ================= COMPLÉTER UN PAIEMENT PARTIEL =================
    public function complete(Request $request, $id)
    {
        $validator = Validator::make($request->all(), [
            'methode' => 'required|in:wave,orange_money,card',
        ]);

        if ($validator->fails()) {
            return response()->json($validator->errors(), 422);
        }

        return DB::transaction(function () use ($request, $id) {

            $paiementOriginal = Payment::with('reservation.terrain')
                ->lockForUpdate()
                ->findOrFail($id);

            if ($paiementOriginal->user_id !== $request->user()->id) {
                return response()->json(['message' => 'Non autorisé'], 403);
            }

            if ($paiementOriginal->statut !== 'valide' || $paiementOriginal->type !== 'partiel' || $paiementOriginal->reste <= 0) {
                return response()->json(['message' => 'Ce paiement ne peut pas être complété'], 409);
            }

            $reservation = $paiementOriginal->reservation;
            $terrain     = $reservation->terrain;

            if ($reservation->statut === 'annulee') {
                return response()->json(['message' => 'Cette réservation a été annulée'], 409);
            }

            // Vérifier aucun complément en cours actif
            $complementEnCours = Payment::where('reservation_id', $reservation->id)
                ->where('statut', 'en_attente')
                ->where('id', '!=', $paiementOriginal->id)
                ->latest()
                ->first();

            if ($complementEnCours instanceof Payment) {
                if (!$complementEnCours->isExpiredAndPending()) {
                    return response()->json(['message' => 'Un complément de paiement est déjà en cours. Réessayez dans quelques minutes.'], 409);
                }
                $complementEnCours->update(['statut' => 'echoue']);
            }

            $montantReste = (float) $paiementOriginal->reste;

            $paytechResult = $this->initierPaiementPaytech(
                reservation: $reservation,
                terrain: $terrain,
                montantAPayer: $montantReste,
                userId: $request->user()->id,
                type: 'entier',                    // Important : on utilise 'entier' pour compatibilité
                methode: $request->methode,
                montantTotal: (float) $paiementOriginal->montant_total,
                reste: 0,
                contexte: 'complement',
                paiementOriginalId: $paiementOriginal->id
            );

            if ($paytechResult['error']) {
                return response()->json(['message' => $paytechResult['message']], $paytechResult['code']);
            }

            $complement = Payment::create([
                'reservation_id'     => $reservation->id,
                'user_id'            => $request->user()->id,
                'montant_total'      => $paiementOriginal->montant_total,
                'montant_paye'       => $montantReste,
                'reste'              => 0,
                'type'               => 'entier',
                'methode'            => $request->methode,
                'statut'             => 'en_attente',
                'paytech_token'      => $paytechResult['token'],
                'paiement_initie_at' => Carbon::now(),
            ]);

            return response()->json([
                'message'              => 'Complément de paiement initié avec succès',
                'redirect_url'         => $paytechResult['redirect_url'],
                'complement_id'        => $complement->id,
                'paiement_original_id' => $paiementOriginal->id,
                'montant_a_payer'      => $montantReste,
            ], 201);
        });
    }

    // ================= IPN — Webhook PayTech =================
    public function ipn(Request $request)
    {
        $apiKey    = env('PAYTECH_API_KEY');
        $apiSecret = env('PAYTECH_API_SECRET');

        if (
            $request->input('api_key_sha256')    !== hash('sha256', $apiKey) ||
            $request->input('api_secret_sha256') !== hash('sha256', $apiSecret)
        ) {
            Log::warning('IPN PayTech: signature invalide', $request->all());
            return response()->json(['message' => 'Signature invalide'], 403);
        }

        $customField = json_decode($request->input('custom_field'), true) ?? [];
        $reservationId      = (int) ($customField['reservation_id'] ?? 0);
        $contexte           = $customField['contexte'] ?? 'initial';
        $paiementOriginalId = $customField['paiement_original_id'] ?? null;

        return DB::transaction(function () use ($request, $reservationId, $contexte, $paiementOriginalId) {

            $reservation = Reservation::lockForUpdate()->find($reservationId);
            if (!$reservation) {
                return response()->json(['message' => 'Réservation introuvable'], 404);
            }

            $payment = Payment::where('reservation_id', $reservationId)
                ->where('statut', 'en_attente')
                ->latest()
                ->first();

            if (!$payment) {
                Log::warning('IPN: paiement en_attente introuvable', ['reservation_id' => $reservationId]);
                return response()->json(['message' => 'Paiement introuvable'], 404);
            }

            $typeEvent = $request->input('type_event');

            Log::info('IPN PayTech reçu', [
                'type_event' => $typeEvent,
                'contexte'   => $contexte,
                'reservation_id' => $reservationId,
            ]);

            if ($typeEvent === 'sale_complete') {

                // Cas du complément
                if ($contexte === 'complement' && $paiementOriginalId) {
                    $paiementOriginal = Payment::lockForUpdate()->find($paiementOriginalId);

                    if ($paiementOriginal) {
                        $payment->update([
                            'statut'         => 'valide',
                            'transaction_id' => $request->input('ref_command'),
                        ]);

                        $paiementOriginal->update([
                            'reste' => 0,
                            'type'  => 'entier',
                        ]);

                        Log::info('IPN: complément validé', ['original_id' => $paiementOriginalId]);
                        return response()->json(['message' => 'OK - complément validé']);
                    }
                }

                // Cas paiement initial
                $creneauPris = Reservation::where('terrain_id', $reservation->terrain_id)
                    ->whereDate('date', $reservation->date)
                    ->where('id', '!=', $reservation->id)
                    ->where('statut', 'confirmee')
                    ->where('heure_debut', '<', $reservation->heure_fin)
                    ->where('heure_fin', '>', $reservation->heure_debut)
                    ->exists();

                if ($creneauPris) {
                    $payment->update(['statut' => 'rembourse', 'transaction_id' => $request->input('ref_command')]);
                    $reservation->update(['statut' => 'annulee']);
                    return response()->json(['message' => 'OK - remboursement requis']);
                }

                $payment->update([
                    'statut'         => 'valide',
                    'transaction_id' => $request->input('ref_command'),
                ]);
                $reservation->update(['statut' => 'confirmee']);

                return response()->json(['message' => 'OK']);
            }

            // Échec ou annulation
            if (in_array($typeEvent, ['sale_canceled', 'sale_failed'])) {
                $payment->update(['statut' => 'echoue']);
                if ($contexte !== 'complement') {
                    $reservation->update(['statut' => 'en_attente']);
                }
                return response()->json(['message' => 'OK']);
            }

            return response()->json(['message' => 'Événement non traité'], 200);
        });
    }

    // ================= MES PAIEMENTS & PAIEMENTS À COMPLÉTER =================
    public function myPayments(Request $request)
    {
        $query = Payment::with(['reservation.terrain'])
            ->where('user_id', $request->user()->id);

        $filtre = $request->query('filtre');

        if ($filtre === 'partiel') {
            $query->where('statut', 'valide')
                  ->where('type', 'partiel')
                  ->where('reste', '>', 0);
        } elseif ($filtre === 'complet') {
            $query->where('statut', 'valide')
                  ->where(function ($q) {
                      $q->where('type', 'entier')->orWhere('reste', '<=', 0);
                  });
        }

        $payments = $query->orderBy('created_at', 'desc')->get();

        return response()->json($payments);
    }

    public function partiels(Request $request)
    {
        $payments = Payment::with(['reservation.terrain'])
            ->where('user_id', $request->user()->id)
            ->where('statut', 'valide')
            ->where('type', 'partiel')
            ->where('reste', '>', 0)
            ->orderBy('created_at', 'desc')
            ->get()
            ->map(function ($payment) {
                return [
                    'id'              => $payment->id,
                    'reservation_id'  => $payment->reservation_id,
                    'terrain'         => $payment->reservation->terrain->name ?? null,
                    'date_reservation'=> $payment->reservation->date ?? null,
                    'heure_debut'     => $payment->reservation->heure_debut ?? null,
                    'heure_fin'       => $payment->reservation->heure_fin ?? null,
                    'montant_total'   => $payment->montant_total,
                    'montant_paye'    => $payment->montant_paye,
                    'reste_a_payer'   => $payment->reste,
                    'methode'         => $payment->methode,
                    'created_at'      => $payment->created_at,
                ];
            });

        return response()->json([
            'count'    => $payments->count(),
            'payments' => $payments,
        ]);
    }

    public function cancel(Request $request)
    {
        return response()->json(['message' => 'Paiement annulé', 'statut' => 'cancel']);
    }

    public function success(Request $request)
    {
        return response()->json(['message' => 'Paiement en cours de validation', 'statut' => 'success']);
    }

    public function show($id)
    {
        $payment = Payment::with(['reservation.terrain', 'user'])->find($id);
        if (!$payment) {
            return response()->json(['message' => 'Paiement introuvable'], 404);
        }
        return response()->json($payment);
    }

    // ================= HELPER PRIVÉ : APPEL PAYTECH (avec debug renforcé) =================
    private function initierPaiementPaytech(
        $reservation,
        $terrain,
        float $montantAPayer,
        int $userId,
        string $type,
        string $methode,
        float $montantTotal,
        float $reste,
        string $contexte = 'initial',
        ?int $paiementOriginalId = null
    ): array {
        $methodesPaytech = [
            'wave'         => 'Wave',
            'orange_money' => 'Orange Money',
            'card'         => 'Carte Bancaire',
        ];

        $customField = [
            'reservation_id' => $reservation->id,
            'user_id'        => $userId,
            'type'           => $type,
            'montant_total'  => $montantTotal,
            'montant_paye'   => $montantAPayer,
            'reste'          => $reste,
            'methode'        => $methode,
            'contexte'       => $contexte,
        ];

        if ($paiementOriginalId) {
            $customField['paiement_original_id'] = $paiementOriginalId;
        }

        $itemName    = $contexte === 'complement' ? 'Complément réservation ' . $terrain->name : 'Réservation ' . $terrain->name;
        $commandName = $contexte === 'complement' ? 'Complément terrain ' . $terrain->name : 'Réservation terrain ' . $terrain->name;

        $paytechResponse = Http::withHeaders([
            'API_KEY'    => env('PAYTECH_API_KEY'),
            'API_SECRET' => env('PAYTECH_API_SECRET'),
        ])->post('https://paytech.sn/api/payment/request-payment', [
            'item_name'      => $itemName,
            'item_price'     => (int) $montantAPayer,
            'currency'       => 'XOF',
            'ref_command'    => 'REF-' . $reservation->id . '-' . time(),
            'command_name'   => $commandName,
            'env'            => env('PAYTECH_ENV', 'test'),
            'ipn_url'        => env('PAYTECH_IPN_URL'),
            'success_url'    => env('PAYTECH_SUCCESS_URL'),
            'cancel_url'     => env('PAYTECH_CANCEL_URL'),
            'target_payment' => $methodesPaytech[$methode] ?? 'Wave',
            'custom_field'   => json_encode($customField),
        ]);

        // Debug renforcé
        Log::info("PayTech response [{$contexte}]", [
            'status'             => $paytechResponse->status(),
            'body'               => $paytechResponse->body(),
            'api_key_present'    => !empty(env('PAYTECH_API_KEY')),
            'api_secret_present' => !empty(env('PAYTECH_API_SECRET')),
        ]);

        if (!$paytechResponse->successful()) {
            Log::error("PayTech HTTP Error [{$contexte}]", [
                'status' => $paytechResponse->status(),
                'body'   => $paytechResponse->body(),
            ]);
            return [
                'error'   => true,
                'message' => 'PayTech indisponible. Réessayez. (' . $paytechResponse->status() . ')',
                'code'    => 502,
            ];
        }

        $paytechData = $paytechResponse->json();

        if (!isset($paytechData['success']) || $paytechData['success'] != 1) {
            Log::error("PayTech business error [{$contexte}]", ['data' => $paytechData]);
            return [
                'error'   => true,
                'message' => $paytechData['errors'][0] ?? 'Erreur PayTech. Vérifiez vos clés API.',
                'code'    => 502,
            ];
        }

        return [
            'error'        => false,
            'redirect_url' => $paytechData['redirect_url'],
            'token'        => $paytechData['token'] ?? null,
        ];
    }
}
