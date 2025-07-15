<?php

namespace App\Exceptions;

use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;

class PaymentException extends Exception
{
    protected $errorCode;
    protected $errorData;
    
    public function __construct(string $message, string $errorCode = 'PAYMENT_ERROR', array $errorData = [], int $httpCode = 400)
    {
        parent::__construct($message, $httpCode);
        $this->errorCode = $errorCode;
        $this->errorData = $errorData;
    }
    
    /**
     * Render the exception as an HTTP response
     */
    public function render(): JsonResponse
    {
        // Logger l'erreur
        Log::error("Payment Exception: {$this->errorCode}", [
            'message' => $this->getMessage(),
            'code' => $this->errorCode,
            'data' => $this->errorData,
            'trace' => $this->getTraceAsString()
        ]);
        
        return response()->json([
            'message' => $this->getMessage(),
            'error_code' => $this->errorCode,
            'error_data' => $this->errorData,
            'status' => 'error',
            'timestamp' => now()->toISOString(),
        ], $this->getCode());
    }
    
    /**
     * Payment-specific error methods
     */
    public static function invalidAmount(float $amount, float $min, float $max): self
    {
        return new self(
            "Montant invalide: {$amount} FCFA. Doit être entre {$min} et {$max} FCFA",
            'INVALID_AMOUNT',
            ['amount' => $amount, 'min' => $min, 'max' => $max],
            400
        );
    }
    
    public static function insufficientBalance(float $required, float $available): self
    {
        return new self(
            "Solde insuffisant. Requis: {$required} FCFA, Disponible: {$available} FCFA",
            'INSUFFICIENT_BALANCE',
            ['required' => $required, 'available' => $available],
            400
        );
    }
    
    public static function invalidProvider(string $provider): self
    {
        return new self(
            "Provider de paiement invalide: {$provider}",
            'INVALID_PROVIDER',
            ['provider' => $provider],
            400
        );
    }
    
    public static function paymentFailed(string $reference, string $reason): self
    {
        return new self(
            "Paiement échoué: {$reason}",
            'PAYMENT_FAILED',
            ['reference' => $reference, 'reason' => $reason],
            400
        );
    }
    
    public static function transactionNotFound(string $reference): self
    {
        return new self(
            "Transaction introuvable: {$reference}",
            'TRANSACTION_NOT_FOUND',
            ['reference' => $reference],
            404
        );
    }
    
    public static function transactionAlreadyProcessed(string $reference): self
    {
        return new self(
            "Transaction déjà traitée: {$reference}",
            'TRANSACTION_ALREADY_PROCESSED',
            ['reference' => $reference],
            400
        );
    }
    
    public static function providerError(string $provider, string $message, array $data = []): self
    {
        return new self(
            "Erreur du provider {$provider}: {$message}",
            'PROVIDER_ERROR',
            array_merge(['provider' => $provider], $data),
            502
        );
    }
    
    public static function dailyLimitExceeded(float $amount, float $limit): self
    {
        return new self(
            "Limite quotidienne dépassée. Montant: {$amount} FCFA, Limite: {$limit} FCFA",
            'DAILY_LIMIT_EXCEEDED',
            ['amount' => $amount, 'limit' => $limit],
            400
        );
    }
    
    public static function withdrawalNotAllowed(string $reason): self
    {
        return new self(
            "Retrait non autorisé: {$reason}",
            'WITHDRAWAL_NOT_ALLOWED',
            ['reason' => $reason],
            400
        );
    }
    
    public static function pendingTransactionExists(string $reference): self
    {
        return new self(
            "Une transaction en attente existe déjà: {$reference}",
            'PENDING_TRANSACTION_EXISTS',
            ['reference' => $reference],
            400
        );
    }
}