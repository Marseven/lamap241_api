<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Transaction;
use App\Services\MobileMoneyService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class WalletController extends Controller
{
    protected $mobileMoneyService;

    public function __construct(MobileMoneyService $mobileMoneyService)
    {
        $this->mobileMoneyService = $mobileMoneyService;
    }

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
     * Deposit money avec polling.
     */
    public function deposit(Request $request)
    {
        $validated = $request->validate(
            [
                'amount' => 'required|numeric|min:500|max:1000000',
                'payment_method' => 'required|string|in:airtel,moov',
                'phone_number' => [
                    'required',
                    'string',
                    Rule::regex('/^(074|077|076|062|065|066|060)[0-9]{6}$/'),
                    function ($attribute, $value, $fail) use ($request) {

                        $operator = $this->getOperatorFromPhone($value);
                        if ($operator !== $request->payment_method) {
                            $fail('Le numéro ne correspond pas à l\'opérateur sélectionné.');
                        }
                    }
                ],
            ],
        );

        $user = $request->user();

        // Utiliser la nouvelle méthode avec polling
        $result = $this->mobileMoneyService->initiateDepositWithPolling($user, $validated);

        if ($result['success']) {
            return response()->json([
                'success' => true,
                'transaction' => $result['transaction'],
                'message' => $result['message'],
                'polling_duration' => $result['polling_duration'],
                'instructions' => 'Suivez les instructions sur votre téléphone. Le paiement sera vérifié automatiquement.',
            ]);
        }

        return response()->json([
            'success' => false,
            'message' => $result['message']
        ], 400);
    }

    /**
     * Vérifier le statut d'une transaction en cours
     */
    public function checkTransactionStatus(Request $request, $reference)
    {
        $transaction = $request->user()->transactions()
            ->where('reference', $reference)
            ->firstOrFail();

        $response = [
            'reference' => $transaction->reference,
            'status' => $transaction->status,
            'amount' => $transaction->amount,
            'created_at' => $transaction->created_at,
            'processed_at' => $transaction->processed_at,
        ];

        // Ajouter des informations spécifiques selon le statut
        switch ($transaction->status) {
            case 'pending':
                $startTime = $transaction->metadata['polling_started_at'] ?? $transaction->created_at;
                $elapsedSeconds = now()->diffInSeconds($startTime);
                $maxTime = $transaction->metadata['max_polling_time'] ?? 60;

                $response['elapsed_time'] = $elapsedSeconds;
                $response['remaining_time'] = max(0, $maxTime - $elapsedSeconds);
                $response['message'] = 'Paiement en cours de vérification...';
                break;

            case 'completed':
                $response['message'] = 'Paiement réussi ! Votre compte a été crédité.';
                break;

            case 'failed':
                $response['message'] = 'Paiement échoué. Vous pouvez réessayer.';
                $response['can_retry'] = true;
                break;
        }

        return response()->json($response);
    }

    /**
     * Withdraw money.
     */
    public function withdraw(Request $request)
    {
        $validated = $request->validate([
            'amount' => 'required|numeric|min:1000|max:500000',
            'payment_method' => 'required|string|in:airtel,moov',
            'phone_number' => [
                'required',
                'string',
                Rule::regex('/^(074|077|076|062|065|066|060)[0-9]{6}$/'),
                function ($attribute, $value, $fail) use ($request) {
                    $operator = $this->getOperatorFromPhone($value);
                    if ($operator !== $request->payment_method) {
                        $fail('Le numéro ne correspond pas à l\'opérateur sélectionné.');
                    }
                }
            ],
        ], [
            'phone_number.regex' => 'Le numéro doit être un numéro Airtel (074, 077, 076) ou Moov (062, 065, 066, 060) valide.'
        ]);

        $user = $request->user();

        // Utiliser le service Mobile Money pour SHAP
        $result = $this->mobileMoneyService->initiateWithdrawal($user, $validated);

        if ($result['success']) {
            return response()->json([
                'success' => true,
                'transaction' => $result['transaction'],
                'message' => 'Retrait en cours de traitement.',
                'amount' => $validated['amount'],
                'fee' => $result['transaction']->fee,
                'total' => abs($result['transaction']->amount) + $result['transaction']->fee,
            ]);
        }

        return response()->json([
            'success' => false,
            'message' => $result['message'] ?? 'Erreur lors du retrait'
        ], 400);
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
     * Déterminer l'opérateur depuis le numéro de téléphone
     */
    private function getOperatorFromPhone($phone)
    {
        $prefix = substr($phone, 0, 3);

        if (in_array($prefix, ['074', '077', '076'])) {
            return 'airtelmoney';
        }

        if (in_array($prefix, ['062', '065', '066', '060'])) {
            return 'moovmoney4';
        }

        return null;
    }
}
