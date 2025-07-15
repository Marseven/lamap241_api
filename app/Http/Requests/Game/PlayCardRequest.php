<?php

namespace App\Http\Requests\Game;

use App\Http\Requests\BaseRequest;

class PlayCardRequest extends BaseRequest
{
    /**
     * Get the validation rules that apply to the request.
     */
    public function rules(): array
    {
        return [
            'card' => 'required|array',
            'card.value' => 'required|integer|min:2|max:14',
            'card.suit' => 'required|string|in:hearts,diamonds,clubs,spades',
        ];
    }

    /**
     * Custom validation logic
     */
    public function withValidator($validator): void
    {
        parent::withValidator($validator);
        
        $validator->after(function ($validator) {
            $card = $this->get('card');
            
            if ($card) {
                // Vérifier que la carte est valide
                if (!$this->isValidCard($card)) {
                    $validator->errors()->add('card', 'Carte invalide');
                }
            }
        });
    }

    /**
     * Check if the card is valid
     */
    private function isValidCard(array $card): bool
    {
        $validSuits = ['hearts', 'diamonds', 'clubs', 'spades'];
        $validValues = range(2, 14); // 2-10, J(11), Q(12), K(13), A(14)
        
        if (!isset($card['suit']) || !isset($card['value'])) {
            return false;
        }
        
        return in_array($card['suit'], $validSuits) && in_array($card['value'], $validValues);
    }
}