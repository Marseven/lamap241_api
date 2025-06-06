<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Transaction;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class WalletController extends Controller
{
    /**
     * Get wallet balance.
     */
    public function balance(Request $request)
    {
        $wallet = $request->user()->wallet;

        return response()->json([
            'balance' => $wallet->balance,
            'bonus_balance' => $wallet->bonus_balance,
            'locked_balance' => $wallet->locked_balance,
            'available_balance' => $wallet->available_balance,
            'total_balance' => $wallet->total_balance,
            'total_deposited' => $wallet->total_deposited,
            'total_withdrawn' => $wallet->total_withdrawn,
            'total_won' => $wallet->total_won,
            'total_lost' => $wallet->total_lost,
            'is_active' => $wallet->is_active,
        ]);
    }

    /**
     * Get transactions history.
     */
    public function transactions(Request $request)
    {
        $validated = $request->validate([
            'type' => 'sometimes|string|in:deposit,withdrawal,game_bet,game_win,bonus,commission,refund',
            'status' => 'sometimes|string|in:pending,processing,completed,failed,cancelled',
            'from_date' => 'sometimes|date',
            'to_date' => 'sometimes|date',
            'limit' => 'sometimes|integer|min:1|max:100',
            'page' => 'sometimes|integer|min:1',
        ]);

        $query = $request->user()->transactions();

        // Filtres
        if (isset($validated['type'])) {
            $query->where('type', $validated['type']);
        }

        if (isset($validated['status'])) {
            $query->where('status', $validated['status']);
        }

        if (isset($validated['from_date'])) {
            $query->whereDate('created_at', '>=', $validated['from_date']);
        }

        if (isset($validated['to_date'])) {
            $query->whereDate('created_at', '<=', $validated['to_date']);
        }

        $transactions = $query->orderBy('created_at', 'desc')
            ->paginate($validated['limit'] ?? 20);

        return response()->json([
            'transactions' => $transactions->items(),
            'pagination' => [
                'current_page' => $transactions->currentPage(),
                'last_page' => $transactions->lastPage(),
                'per_page' => $transactions->perPage(),
                'total' => $transactions->total(),
            ]
        ]);
    }

    /**
     * Deposit money.
     */
    public function deposit(Request $request)
    {
        $validated = $request->validate([
            'amount' => 'required|numeric|min:500|max:1000000',
            'payment_method' => 'required|string|in:airtel,moov',
            'phone_number' => 'required|string|regex:/^(\+241|0)[0-9]{8}$/',
        ]);

        $user = $request->user();
        $wallet = $user->wallet;

        // Créer la transaction en attente
        $transaction = DB::transaction(function () use ($wallet, $validated, $user) {
            return $wallet->transactions()->create([
                'user_id' => $user->id,
                'reference' => 'DEP-' . uniqid(),
                'type' => 'deposit',
                'amount' => $validated['amount'],
                'balance_before' => $wallet->balance,
                'balance_after' => $wallet->balance, // Sera mis à jour après confirmation
                'status' => 'pending',
                'payment_method' => $validated['payment_method'],
                'phone_number' => $validated['phone_number'],
            ]);
        });

        // Simuler l'appel API Mobile Money
        $this->processMobileMoneyDeposit($transaction);

        return response()->json([
            'transaction' => $transaction,
            'message' => 'Dépôt en cours de traitement. Veuillez confirmer sur votre téléphone.',
        ]);
    }

    /**
     * Withdraw money.
     */
    public function withdraw(Request $request)
    {
        $validated = $request->validate([
            'amount' => 'required|numeric|min:1000|max:500000',
            'payment_method' => 'required|string|in:airtel,moov',
            'phone_number' => 'required|string|regex:/^(\+241|0)[0-9]{8}$/',
        ]);

        $user = $request->user();
        $wallet = $user->wallet;

        // Calculer les frais (5%)
        $fee = $validated['amount'] * 0.05;
        $totalAmount = $validated['amount'] + $fee;

        // Vérifier le solde
        if ($wallet->available_balance < $totalAmount) {
            return response()->json([
                'message' => 'Solde insuffisant. Solde disponible: ' . number_format($wallet->available_balance, 0, ',', ' ') . ' FCFA',
                'available_balance' => $wallet->available_balance,
                'required_amount' => $totalAmount,
                'fee' => $fee,
            ], 400);
        }

        // Créer la transaction
        $transaction = $wallet->withdraw(
            $validated['amount'],
            $validated['payment_method'],
            $validated['phone_number'],
            $fee
        );

        if (!$transaction) {
            return response()->json([
                'message' => 'Impossible de traiter le retrait'
            ], 400);
        }

        // Simuler le traitement
        $this->processMobileMoneyWithdrawal($transaction);

        return response()->json([
            'transaction' => $transaction,
            'message' => 'Retrait en cours de traitement.',
            'amount' => $validated['amount'],
            'fee' => $fee,
            'total' => $totalAmount,
        ]);
    }

    /**
     * Get transaction details.
     */
    public function transactionDetails(Request $request, $reference)
    {
        $transaction = $request->user()->transactions()
            ->where('reference', $reference)
            ->firstOrFail();

        return response()->json([
            'transaction' => $transaction
        ]);
    }

    /**
     * Simulate Mobile Money deposit processing.
     */
    private function processMobileMoneyDeposit(Transaction $transaction)
    {
        // En production, ici on appellerait l'API Mobile Money
        // Pour le MVP, on simule une confirmation automatique après 2 secondes

        dispatch(function () use ($transaction) {
            sleep(2);

            // Simuler 90% de succès
            if (rand(1, 10) <= 9) {
                DB::transaction(function () use ($transaction) {
                    $wallet = $transaction->wallet;

                    // Mettre à jour le solde
                    $wallet->deposit(
                        $transaction->amount,
                        $transaction->payment_method,
                        $transaction->phone_number
                    );

                    // Marquer la transaction comme complétée
                    $transaction->markAsCompleted('MM-' . uniqid());
                });
            } else {
                $transaction->markAsFailed('Transaction échouée par l\'opérateur');
            }
        })->afterResponse();
    }

    /**
     * Simulate Mobile Money withdrawal processing.
     */
    private function processMobileMoneyWithdrawal(Transaction $transaction)
    {
        // En production, ici on appellerait l'API Mobile Money
        dispatch(function () use ($transaction) {
            sleep(3);

            // Simuler 95% de succès pour les retraits
            if (rand(1, 20) <= 19) {
                $transaction->markAsCompleted('MM-' . uniqid());
            } else {
                // En cas d'échec, rembourser
                DB::transaction(function () use ($transaction) {
                    $wallet = $transaction->wallet;
                    $refundAmount = abs($transaction->amount) + $transaction->fee;

                    $wallet->balance += $refundAmount;
                    $wallet->save();

                    $transaction->markAsFailed('Transaction échouée par l\'opérateur');
                });
            }
        })->afterResponse();
    }
}
