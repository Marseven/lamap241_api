<?php

namespace App\Http\Requests\Wallet;

use App\Http\Requests\BaseRequest;

class DepositRequest extends BaseRequest
{
    /**
     * Get the validation rules that apply to the request.
     */
    public function rules(): array
    {
        return [
            'amount' => 'required|numeric|min:500|max:100000',
            'provider' => 'required|string|in:orange,moov,wave,free',
            'phone' => 'required|string|max:20',
        ];
    }

    /**
     * Prepare the data for validation.
     */
    protected function prepareForValidation(): void
    {
        $this->merge([
            'phone' => trim($this->phone),
            'provider' => strtolower(trim($this->provider)),
        ]);
    }

    /**
     * Custom validation logic
     */
    public function withValidator($validator): void
    {
        parent::withValidator($validator);
        
        $validator->after(function ($validator) {
            $amount = $this->get('amount');
            $provider = $this->get('provider');
            
            // Vérifier les limites par provider
            if ($provider && $amount) {
                $limits = $this->getProviderLimits($provider);
                
                if ($amount < $limits['min']) {
                    $validator->errors()->add('amount', "Le montant minimum pour {$provider} est {$limits['min']} FCFA");
                }
                
                if ($amount > $limits['max']) {
                    $validator->errors()->add('amount', "Le montant maximum pour {$provider} est {$limits['max']} FCFA");
                }
            }
        });
    }

    /**
     * Get provider limits
     */
    private function getProviderLimits(string $provider): array
    {
        $limits = [
            'orange' => ['min' => 500, 'max' => 50000],
            'moov' => ['min' => 500, 'max' => 50000],
            'wave' => ['min' => 100, 'max' => 100000],
            'free' => ['min' => 500, 'max' => 25000],
        ];
        
        return $limits[$provider] ?? ['min' => 500, 'max' => 100000];
    }
}