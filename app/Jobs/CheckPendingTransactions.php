<?php

namespace App\Jobs;

use App\Models\Transaction;
use App\Services\MobileMoneyService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class CheckPendingTransactions implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * Execute the job.
     */
    public function handle(MobileMoneyService $mobileMoneyService): void
    {
        // Vérifier les transactions en attente de plus de 5 minutes
        $pendingTransactions = Transaction::where('status', 'pending')
            ->where('created_at', '<=', now()->subMinutes(5))
            ->get();

        foreach ($pendingTransactions as $transaction) {
            // Vérifier si la transaction a expiré (plus d'une heure)
            if ($transaction->created_at <= now()->subHour()) {
                $transaction->markAsFailed('Transaction expirée');
                Log::info('Transaction marked as failed due to timeout', [
                    'transaction_id' => $transaction->id,
                    'reference' => $transaction->reference
                ]);
                continue;
            }

            // Pour les dépôts, vérifier le statut E-Billing
            if ($transaction->type === 'deposit' && $transaction->external_reference) {
                $this->checkEbillingStatus($transaction);
            }

            // Pour les retraits, vérifier le statut SHAP
            if ($transaction->type === 'withdrawal' && $transaction->external_reference) {
                $this->checkShapStatus($transaction, $mobileMoneyService);
            }
        }
    }

    /**
     * Vérifier le statut E-Billing d'une transaction
     */
    private function checkEbillingStatus(Transaction $transaction): void
    {
        // Implémenter la vérification du statut E-Billing
        // Utiliser l'API E-Billing pour vérifier le statut de la facture
        Log::info('Checking E-Billing status for transaction', [
            'transaction_id' => $transaction->id,
            'bill_id' => $transaction->external_reference
        ]);
    }

    /**
     * Vérifier le statut SHAP d'une transaction
     */
    private function checkShapStatus(Transaction $transaction, MobileMoneyService $mobileMoneyService): void
    {
        // Implémenter la vérification du statut SHAP
        // Utiliser l'API SHAP pour vérifier le statut du payout
        Log::info('Checking SHAP status for transaction', [
            'transaction_id' => $transaction->id,
            'payout_id' => $transaction->external_reference
        ]);
    }
}
