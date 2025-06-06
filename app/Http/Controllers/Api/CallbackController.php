<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\MobileMoneyService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class CallbackController extends Controller
{
    protected $mobileMoneyService;

    public function __construct(MobileMoneyService $mobileMoneyService)
    {
        $this->mobileMoneyService = $mobileMoneyService;
    }

    /**
     * Handle E-Billing callback notification
     *
     * @param Request $request
     * @return \Illuminate\Http\Response
     */
    public function ebillingNotification(Request $request)
    {
        Log::info('E-Billing notification received', $request->all());

        try {
            $data = $request->all();

            // Valider les données requises
            if (!isset($data['reference'])) {
                Log::warning('E-Billing notification missing reference');
                return response()->json(['error' => 'Missing reference'], 400);
            }

            // Traiter le callback
            $result = $this->mobileMoneyService->handleEbillingCallback($data);

            if ($result) {
                Log::info('E-Billing notification processed successfully');
                return response()->json(['success' => true], 200);
            } else {
                Log::error('E-Billing notification processing failed');
                return response()->json(['error' => 'Processing failed'], 500);
            }
        } catch (\Exception $e) {
            Log::error('E-Billing notification exception', [
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json(['error' => 'Internal error'], 500);
        }
    }

    /**
     * Handle E-Billing redirect callback
     *
     * @param Request $request
     * @return \Illuminate\Http\RedirectResponse
     */
    public function ebillingRedirect(Request $request)
    {
        Log::info('E-Billing redirect received', $request->all());

        // Récupérer l'ID de la facture
        $invoiceNumber = $request->input('invoice_number');
        $status = $request->input('status', 'unknown');

        // Rediriger vers le frontend avec le statut
        $frontendUrl = env('FRONTEND_URL', 'http://localhost:3000');

        if ($status === 'success') {
            return redirect($frontendUrl . '/wallet?payment=success&invoice=' . $invoiceNumber);
        } else {
            return redirect($frontendUrl . '/wallet?payment=failed&invoice=' . $invoiceNumber);
        }
    }

    /**
     * Handle SHAP webhook for payout status
     *
     * @param Request $request
     * @return \Illuminate\Http\Response
     */
    public function shapWebhook(Request $request)
    {
        Log::info('SHAP webhook received', $request->all());

        try {
            $data = $request->all();

            // Valider la signature du webhook (si applicable)
            // $this->validateShapWebhookSignature($request);

            if (isset($data['payout_id']) && isset($data['status'])) {
                // Traiter le statut du payout
                $this->processShapPayoutStatus($data);
            }

            return response()->json(['success' => true], 200);
        } catch (\Exception $e) {
            Log::error('SHAP webhook exception', [
                'message' => $e->getMessage()
            ]);

            return response()->json(['error' => 'Processing failed'], 500);
        }
    }

    /**
     * Process SHAP payout status update
     *
     * @param array $data
     * @return void
     */
    protected function processShapPayoutStatus(array $data)
    {
        $payoutId = $data['payout_id'];
        $status = $data['status'];

        // Trouver la transaction associée
        $transaction = \App\Models\Transaction::where('external_reference', $payoutId)
            ->orWhere('metadata->shap_payout_id', $payoutId)
            ->first();

        if (!$transaction) {
            Log::warning('Transaction not found for SHAP payout', ['payout_id' => $payoutId]);
            return;
        }

        // Mettre à jour le statut selon la réponse SHAP
        switch ($status) {
            case 'success':
            case 'completed':
                $transaction->markAsCompleted();
                Log::info('Withdrawal completed', [
                    'transaction_id' => $transaction->id,
                    'payout_id' => $payoutId
                ]);
                break;

            case 'failed':
            case 'rejected':
                // Rembourser en cas d'échec
                DB::transaction(function () use ($transaction) {
                    $wallet = $transaction->wallet;
                    $refundAmount = abs($transaction->amount) + $transaction->fee;

                    $wallet->balance += $refundAmount;
                    $wallet->save();

                    $transaction->markAsFailed($data['failure_reason'] ?? 'Payout failed');
                });

                Log::info('Withdrawal failed and refunded', [
                    'transaction_id' => $transaction->id,
                    'payout_id' => $payoutId
                ]);
                break;

            default:
                Log::info('SHAP payout status update', [
                    'transaction_id' => $transaction->id,
                    'status' => $status
                ]);
        }
    }
}
