<?php

namespace App\Http\Requests\Game;

use App\Http\Requests\BaseRequest;

class CreateRoomRequest extends BaseRequest
{
    /**
     * Get the validation rules that apply to the request.
     */
    public function rules(): array
    {
        $rules = [
            'name' => 'required|string|min:3|max:50',
            'max_players' => 'required|integer|min:2|max:4',
            'time_limit' => 'nullable|integer|min:60|max:3600',
            'rounds_to_win' => 'required|integer|min:1|max:10',
            'allow_spectators' => 'boolean',
            'is_exhibition' => 'boolean',
        ];

        // Validation du montant de la mise uniquement si ce n'est pas une exhibition
        if (!$this->boolean('is_exhibition')) {
            $rules['bet_amount'] = 'required|numeric|min:500|max:100000';
        }

        return $rules;
    }

    /**
     * Prepare the data for validation.
     */
    protected function prepareForValidation(): void
    {
        $this->merge([
            'name' => trim($this->name),
            'is_exhibition' => $this->boolean('is_exhibition'),
            'allow_spectators' => $this->boolean('allow_spectators'),
        ]);
    }

    /**
     * Custom validation logic
     */
    public function withValidator($validator): void
    {
        parent::withValidator($validator);
        
        $validator->after(function ($validator) {
            // Vérifier que l'utilisateur a assez de fonds pour une partie payante
            if (!$this->boolean('is_exhibition') && $this->has('bet_amount')) {
                $user = auth()->user();
                $betAmount = $this->get('bet_amount');
                
                if ($user && !$user->canAfford($betAmount)) {
                    $validator->errors()->add('bet_amount', 'Solde insuffisant pour cette mise');
                }
            }
        });
    }
}