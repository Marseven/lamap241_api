<?php

namespace App\Services;

use App\Models\Transaction;
use App\Models\User;
use App\Models\Wallet;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class MobileMoneyService
{
    protected $ebillingUrl;
    protected $ebillingUsername;
    protected $ebillingSharedKey;
    protected $ebillingPushUrl;
    protected $shapUrl;
    protected $shapApiId;
    protected $shapApiSecret;

    public function __construct()
    {
        // E-Billing config
        $this->ebillingUrl = env('EBILLING_SERVER_URL') . '/api/v1/merchant/e_bills';
        $this->ebillingUsername = env('EBILLING_USERNAME');
        $this->ebillingSharedKey = env('EBILLING_SHARED_KEY');

        // SHAP config
        $this->shapUrl = env('SHAP_URL');
        $this->shapApiId = env('SHAP_API_ID');
        $this->shapApiSecret = env('SHAP_API_SECRET');
    }

    /**
     * Initier un dépôt via E-Billing avec polling
     */
    public function initiateDepositWithPolling(User $user, array $data)
    {
        $reference = $this->generateReference();
        $amount = $data['amount'];
        $paymentMethod = $data['payment_method'];
        $phoneNumber = $data['phone_number'];

        // Créer la transaction en attente
        $transaction = DB::transaction(function () use ($user, $reference, $amount, $paymentMethod, $phoneNumber) {
            $wallet = $user->wallet;

            return $wallet->transactions()->create([
                'user_id' => $user->id,
                'reference' => $reference,
                'type' => 'deposit',
                'amount' => $amount,
                'balance_before' => $wallet->balance,
                'balance_after' => $wallet->balance,
                'status' => 'pending',
                'payment_method' => $paymentMethod,
                'phone_number' => $phoneNumber,
                'description' => "Dépôt via {$paymentMethod}",
                'metadata' => [
                    'polling_started_at' => now()->toISOString(),
                    'max_polling_time' => 60 // 60 secondes
                ]
            ]);
        });

        // Créer la facture E-Billing
        $invoice = $this->createEbillingInvoice($user, $transaction);

        if (!$invoice) {
            $transaction->markAsFailed('Impossible de créer la facture E-Billing');
            return [
                'success' => false,
                'message' => 'Impossible de créer la facture E-Billing'
            ];
        }

        // Mettre à jour la transaction avec l'ID de facturation
        $transaction->update([
            'external_reference' => $invoice['bill_id'],
            'metadata' => array_merge($transaction->metadata ?? [], [
                'ebilling_bill_id' => $invoice['bill_id'],
                'ebilling_invoice_data' => $invoice
            ])
        ]);

        // Déclencher le PUSH USSD
        $pushResult = $this->triggerUssdPush($invoice['bill_id'], $phoneNumber, $paymentMethod);

        if (!$pushResult) {
            $transaction->markAsFailed('Échec du déclenchement PUSH USSD');
            return [
                'success' => false,
                'message' => 'Impossible de déclencher le paiement mobile'
            ];
        }

        // Démarrer le polling en arrière-plan
        $this->startTransactionPolling($transaction);

        return [
            'success' => true,
            'transaction' => $transaction,
            'invoice' => $invoice,
            'message' => 'Paiement initié. Suivez les instructions sur votre téléphone.',
            'polling_duration' => 60 // Informer le front du temps d'attente
        ];
    }

    /**
     * Déclencher le PUSH USSD vers le numéro
     */
    protected function triggerUssdPush($billId, $phoneNumber, $paymentMethod)
    {
        try {
            $payload = [
                'payer_msisdn' => $phoneNumber,
                'payment_system_name' => $paymentMethod,
            ];

            $response = Http::withBasicAuth($this->ebillingUsername, $this->ebillingSharedKey)
                ->withHeaders(['Content-Type' => 'application/json'])
                ->post($this->ebillingUrl . '/' . $billId . '/ussd_push', $payload);

            if ($response->successful()) {
                Log::info('PUSH USSD déclenché avec succès', [
                    'bill_id' => $billId,
                    'payer_msisdn' => $phoneNumber,
                    'payment_system_name' => $paymentMethod
                ]);
                return true;
            }

            Log::error('Échec du PUSH USSD', [
                'status' => $response->status(),
                'body' => $response->body(),
                'bill_id' => $billId
            ]);
        } catch (\Exception $e) {
            Log::error('Exception lors du PUSH USSD', [
                'message' => $e->getMessage(),
                'bill_id' => $billId
            ]);
        }

        return false;
    }

    /**
     * Démarrer le polling de la transaction
     */
    protected function startTransactionPolling(Transaction $transaction)
    {
        // Utiliser un job en arrière-plan pour le polling
        dispatch(function () use ($transaction) {
            $this->pollTransactionStatus($transaction);
        })->afterResponse();
    }

    /**
     * Polling du statut de la transaction (toutes les 5 secondes pendant 60 secondes)
     */
    protected function pollTransactionStatus(Transaction $transaction)
    {
        $startTime = now();
        $maxDuration = 60; // 60 secondes
        $interval = 5; // 5 secondes
        $attempts = 0;
        $maxAttempts = $maxDuration / $interval; // 12 tentatives

        Log::info('Démarrage du polling pour la transaction', [
            'transaction_id' => $transaction->id,
            'reference' => $transaction->reference,
            'max_attempts' => $maxAttempts
        ]);

        while ($attempts < $maxAttempts) {
            $attempts++;

            // Vérifier le statut
            $status = $this->checkEbillingTransactionStatus($transaction->external_reference);

            Log::info('Tentative de polling', [
                'transaction_id' => $transaction->id,
                'attempt' => $attempts,
                'status' => $status
            ]);

            if ($status === 'paid' || $status === 'completed') {
                // Transaction réussie
                $this->completeTransaction($transaction);
                Log::info('Transaction complétée via polling', [
                    'transaction_id' => $transaction->id,
                    'attempts' => $attempts
                ]);
                return;
            }

            if ($status === 'failed' || $status === 'cancelled') {
                // Transaction échouée
                $transaction->markAsFailed('Paiement échoué ou annulé');
                Log::info('Transaction échouée via polling', [
                    'transaction_id' => $transaction->id,
                    'attempts' => $attempts,
                    'status' => $status
                ]);
                return;
            }

            // Attendre avant la prochaine vérification
            if ($attempts < $maxAttempts) {
                sleep($interval);
            }
        }

        // Timeout atteint
        $transaction->markAsFailed('Timeout - Aucune réponse dans les 60 secondes');
        Log::warning('Timeout du polling', [
            'transaction_id' => $transaction->id,
            'total_attempts' => $attempts
        ]);
    }

    /**
     * Vérifier le statut d'une transaction E-Billing
     */
    protected function checkEbillingTransactionStatus($billId)
    {
        try {
            $response = Http::withBasicAuth($this->ebillingUsername, $this->ebillingSharedKey)
                ->get($this->ebillingUrl . '/' . $billId);

            if ($response->successful()) {
                $data = $response->json();
                return $data['status'] ?? 'pending';
            }

            Log::error('Erreur lors de la vérification du statut E-Billing', [
                'status' => $response->status(),
                'body' => $response->body(),
                'bill_id' => $billId
            ]);
        } catch (\Exception $e) {
            Log::error('Exception lors de la vérification du statut', [
                'message' => $e->getMessage(),
                'bill_id' => $billId
            ]);
        }

        return 'unknown';
    }

    /**
     * Compléter la transaction après paiement réussi
     */
    protected function completeTransaction(Transaction $transaction)
    {
        DB::transaction(function () use ($transaction) {
            $wallet = $transaction->wallet;

            // Mettre à jour le solde
            $wallet->balance += $transaction->amount;
            $wallet->total_deposited += $transaction->amount;
            $wallet->save();

            // Mettre à jour la transaction
            $transaction->update([
                'status' => 'completed',
                'balance_after' => $wallet->balance,
                'processed_at' => now(),
                'metadata' => array_merge($transaction->metadata ?? [], [
                    'completed_via_polling' => true,
                    'completed_at' => now()->toISOString()
                ])
            ]);
        });

        Log::info('Transaction complétée avec succès', [
            'transaction_id' => $transaction->id,
            'reference' => $transaction->reference,
            'amount' => $transaction->amount
        ]);
    }

    /**
     * Créer une facture E-Billing
     */
    protected function createEbillingInvoice(User $user, Transaction $transaction)
    {
        $payload = [
            'payer_email' => $user->email,
            'payer_msisdn' => $transaction->phone_number,
            'amount' => $transaction->amount,
            'short_description' => "Dépôt LaMap241 - {$transaction->reference}",
            'external_reference' => $transaction->reference,
            'payer_name' => $user->name,
            'expiry_period' => 60 // 60 minutes
        ];

        try {
            $response = Http::withBasicAuth($this->ebillingUsername, $this->ebillingSharedKey)
                ->withHeaders(['Content-Type' => 'application/json'])
                ->post($this->ebillingUrl, $payload);

            if ($response->successful()) {
                $data = $response->json();
                Log::info('E-Billing invoice created', [
                    'user_id' => $user->id,
                    'reference' => $transaction->reference,
                    'bill_id' => $data['e_bill']['bill_id'] ?? null
                ]);

                return $data['e_bill'] ?? null;
            }

            Log::error('E-Billing invoice creation failed', [
                'status' => $response->status(),
                'body' => $response->body()
            ]);
        } catch (\Exception $e) {
            Log::error('E-Billing exception', [
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
        }

        return null;
    }

    /**
     * Initier un retrait via SHAP
     */
    public function initiateWithdrawal(User $user, array $data)
    {
        $amount = $data['amount'];
        $paymentMethod = $data['payment_method'];
        $phoneNumber = $data['phone_number'];

        // Calculer les frais (2%)
        $fee = $amount * 0.02;
        $totalAmount = $amount + $fee;

        // Vérifier le solde
        if ($user->wallet->available_balance < $totalAmount) {
            return [
                'success' => false,
                'message' => 'Solde insuffisant'
            ];
        }

        // Créer la transaction de retrait
        $transaction = $user->wallet->withdraw($amount, $paymentMethod, $phoneNumber, $fee);

        if (!$transaction) {
            return [
                'success' => false,
                'message' => 'Impossible de créer la transaction'
            ];
        }

        // Obtenir le token SHAP
        $token = $this->getShapToken();

        if (!$token) {
            $transaction->markAsFailed('Impossible d\'obtenir le token SHAP');
            return [
                'success' => false,
                'message' => 'Service temporairement indisponible'
            ];
        }

        // Effectuer le payout
        $payout = $this->makeShapPayout($token, $transaction);

        if ($payout) {
            $transaction->update([
                'external_reference' => $payout['payout_id'],
                'status' => 'processing',
                'metadata' => array_merge($transaction->metadata ?? [], [
                    'shap_payout_id' => $payout['payout_id'],
                    'shap_transaction_id' => $payout['transaction_id']
                ])
            ]);

            return [
                'success' => true,
                'transaction' => $transaction,
                'payout' => $payout
            ];
        }

        // En cas d'échec, rembourser
        DB::transaction(function () use ($transaction) {
            $wallet = $transaction->wallet;
            $wallet->balance += abs($transaction->amount) + $transaction->fee;
            $wallet->save();

            $transaction->markAsFailed('Échec du payout SHAP');
        });

        return [
            'success' => false,
            'message' => 'Échec du retrait'
        ];
    }

    /**
     * Obtenir le token d'authentification SHAP
     */
    protected function getShapToken()
    {
        try {
            $response = Http::post($this->shapUrl . 'auth', [
                'api_id' => $this->shapApiId,
                'api_secret' => $this->shapApiSecret
            ]);

            if ($response->successful()) {
                $data = $response->json();
                return $data['access_token'] ?? null;
            }

            Log::error('SHAP auth failed', [
                'status' => $response->status(),
                'body' => $response->body()
            ]);
        } catch (\Exception $e) {
            Log::error('SHAP auth exception', [
                'message' => $e->getMessage()
            ]);
        }

        return null;
    }

    /**
     * Effectuer un payout via SHAP
     */
    protected function makeShapPayout($token, Transaction $transaction)
    {
        $payload = [
            'payment_system_name' => $this->mapPaymentMethodToShap($transaction->payment_method),
            'payout' => [
                'payee_msisdn' => $transaction->phone_number,
                'amount' => abs($transaction->amount),
                'external_reference' => $transaction->reference,
                'payout_type' => 'withdrawal'
            ]
        ];

        try {
            $response = Http::withToken($token)
                ->post($this->shapUrl . 'payout', $payload);

            if ($response->successful()) {
                $data = $response->json();

                if (isset($data['response']['state']) && $data['response']['state'] === 'success') {
                    Log::info('SHAP payout successful', [
                        'transaction_id' => $transaction->id,
                        'payout_id' => $data['response']['payout_id'] ?? null
                    ]);

                    return $data['response'];
                }
            }

            Log::error('SHAP payout failed', [
                'status' => $response->status(),
                'body' => $response->body()
            ]);
        } catch (\Exception $e) {
            Log::error('SHAP payout exception', [
                'message' => $e->getMessage()
            ]);
        }

        return null;
    }

    /**
     * Vérifier le solde SHAP
     */
    public function checkShapBalance($token = null)
    {
        if (!$token) {
            $token = $this->getShapToken();
        }

        if (!$token) {
            return null;
        }

        try {
            $response = Http::withToken($token)
                ->get($this->shapUrl . 'balance');

            if ($response->successful()) {
                $data = $response->json();
                return $data['data'] ?? [];
            }
        } catch (\Exception $e) {
            Log::error('SHAP balance check exception', [
                'message' => $e->getMessage()
            ]);
        }

        return null;
    }

    /**
     * Mapper les méthodes de paiement vers SHAP
     */
    protected function mapPaymentMethodToShap($method)
    {
        $mapping = [
            'airtel' => 'airtelmoney',
            'moov' => 'moovmoney4'
        ];

        return $mapping[$method] ?? $method;
    }

    /**
     * Générer une référence unique
     */
    protected function generateReference()
    {
        do {
            $reference = 'TXN-' . strtoupper(Str::random(10));
        } while (Transaction::where('reference', $reference)->exists());

        return $reference;
    }
}
