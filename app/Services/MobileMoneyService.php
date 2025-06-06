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
    protected $shapUrl;
    protected $shapApiId;
    protected $shapApiSecret;

    public function __construct()
    {
        // E-Billing config
        $this->ebillingUrl = env('EBILLING_SERVER_URL');
        $this->ebillingUsername = env('EBILLING_USERNAME');
        $this->ebillingSharedKey = env('EBILLING_SHARED_KEY');

        // SHAP config
        $this->shapUrl = env('SHAP_URL');
        $this->shapApiId = env('SHAP_API_ID');
        $this->shapApiSecret = env('SHAP_API_SECRET');
    }

    /**
     * Initier un dépôt via E-Billing
     */
    public function initiateDeposit(User $user, array $data)
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
                'description' => "Dépôt via {$paymentMethod}"
            ]);
        });

        // Créer la facture E-Billing
        $invoice = $this->createEbillingInvoice($user, $transaction);

        if ($invoice) {
            // Mettre à jour la transaction avec l'ID de facturation
            $transaction->update([
                'external_reference' => $invoice['bill_id']
            ]);

            return [
                'success' => true,
                'transaction' => $transaction,
                'invoice' => $invoice
            ];
        }

        // En cas d'échec, annuler la transaction
        $transaction->update(['status' => 'failed']);

        return [
            'success' => false,
            'message' => 'Impossible de créer la facture E-Billing'
        ];
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
     * Traiter le callback E-Billing
     */
    public function handleEbillingCallback(array $data)
    {
        Log::info('E-Billing callback received', $data);

        if (!isset($data['reference'])) {
            Log::warning('E-Billing callback missing reference');
            return false;
        }

        $transaction = Transaction::where('reference', $data['reference'])->first();

        if (!$transaction) {
            Log::warning('Transaction not found for reference: ' . $data['reference']);
            return false;
        }

        if ($transaction->status === 'completed') {
            Log::info('Transaction already completed: ' . $transaction->reference);
            return true;
        }

        // Mettre à jour la transaction
        DB::transaction(function () use ($transaction, $data) {
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
                    'ebilling_transaction_id' => $data['transactionid'] ?? null,
                    'ebilling_operator' => $data['paymentsystem'] ?? null,
                    'ebilling_amount' => $data['amount'] ?? null
                ])
            ]);
        });

        Log::info('Deposit completed successfully', [
            'transaction_id' => $transaction->id,
            'reference' => $transaction->reference,
            'amount' => $transaction->amount
        ]);

        return true;
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
