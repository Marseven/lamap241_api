<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Support\Facades\Log;

abstract class BaseRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Handle a failed validation attempt.
     */
    protected function failedValidation(Validator $validator): void
    {
        $errors = $validator->errors()->toArray();
        
        // Logger les erreurs de validation
        Log::warning('Validation failed', [
            'errors' => $errors,
            'input' => $this->all(),
            'ip' => $this->ip(),
            'user_id' => auth()->id(),
        ]);

        throw new HttpResponseException(
            response()->json([
                'message' => 'Données invalides',
                'errors' => $errors,
                'status' => 'validation_error'
            ], 422)
        );
    }

    /**
     * Get custom attributes for validator errors.
     */
    public function attributes(): array
    {
        return [
            'email' => 'adresse email',
            'phone' => 'numéro de téléphone',
            'password' => 'mot de passe',
            'pseudo' => 'pseudonyme',
            'amount' => 'montant',
            'bet_amount' => 'montant de la mise',
            'name' => 'nom',
            'code' => 'code',
        ];
    }

    /**
     * Get custom messages for validator errors.
     */
    public function messages(): array
    {
        return [
            'required' => 'Le champ :attribute est obligatoire.',
            'email' => 'Le champ :attribute doit être une adresse email valide.',
            'phone' => 'Le champ :attribute doit être un numéro de téléphone valide.',
            'min' => 'Le champ :attribute doit contenir au moins :min caractères.',
            'max' => 'Le champ :attribute ne peut pas contenir plus de :max caractères.',
            'numeric' => 'Le champ :attribute doit être un nombre.',
            'integer' => 'Le champ :attribute doit être un nombre entier.',
            'boolean' => 'Le champ :attribute doit être vrai ou faux.',
            'string' => 'Le champ :attribute doit être une chaîne de caractères.',
            'unique' => 'La valeur du champ :attribute est déjà utilisée.',
            'exists' => 'La valeur sélectionnée pour :attribute n\'est pas valide.',
            'confirmed' => 'La confirmation du champ :attribute ne correspond pas.',
            'in' => 'La valeur sélectionnée pour :attribute n\'est pas valide.',
        ];
    }

    /**
     * Sanitize input data
     */
    protected function sanitizeInput(array $data): array
    {
        $sanitized = [];
        
        foreach ($data as $key => $value) {
            if (is_string($value)) {
                // Supprimer les espaces en début et fin
                $value = trim($value);
                
                // Supprimer les caractères dangereux
                $value = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/', '', $value);
                
                // Échapper les caractères HTML
                $value = htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
                
                $sanitized[$key] = $value;
            } else {
                $sanitized[$key] = $value;
            }
        }
        
        return $sanitized;
    }

    /**
     * Validate phone number format
     */
    protected function validatePhoneNumber(?string $phone): bool
    {
        if (empty($phone)) {
            return false;
        }
        
        // Format: +221XXXXXXXXX (Sénégal) ou +224XXXXXXXXX (Guinée)
        $pattern = '/^\+22[14][0-9]{8}$/';
        
        return preg_match($pattern, $phone) === 1;
    }

    /**
     * Validate amount
     */
    protected function validateAmount($amount): bool
    {
        if (!is_numeric($amount)) {
            return false;
        }
        
        $amount = floatval($amount);
        
        // Montant minimum: 100 FCFA
        if ($amount < 100) {
            return false;
        }
        
        // Montant maximum: 1 million FCFA
        if ($amount > 1000000) {
            return false;
        }
        
        return true;
    }

    /**
     * Validate pseudo
     */
    protected function validatePseudo(?string $pseudo): bool
    {
        if (empty($pseudo)) {
            return false;
        }
        
        // Longueur entre 3 et 20 caractères
        if (strlen($pseudo) < 3 || strlen($pseudo) > 20) {
            return false;
        }
        
        // Caractères autorisés: lettres, chiffres, tirets et underscores
        $pattern = '/^[a-zA-Z0-9_-]+$/';
        
        return preg_match($pattern, $pseudo) === 1;
    }

    /**
     * Validate password strength
     */
    protected function validatePasswordStrength(?string $password): bool
    {
        if (empty($password)) {
            return false;
        }
        
        // Longueur minimum: 8 caractères
        if (strlen($password) < 8) {
            return false;
        }
        
        // Doit contenir au moins une lettre minuscule
        if (!preg_match('/[a-z]/', $password)) {
            return false;
        }
        
        // Doit contenir au moins une lettre majuscule
        if (!preg_match('/[A-Z]/', $password)) {
            return false;
        }
        
        // Doit contenir au moins un chiffre
        if (!preg_match('/[0-9]/', $password)) {
            return false;
        }
        
        return true;
    }

    /**
     * Custom validation rules
     */
    public function withValidator(Validator $validator): void
    {
        $validator->after(function ($validator) {
            $data = $this->all();
            
            // Validation personnalisée du téléphone
            if (isset($data['phone']) && !$this->validatePhoneNumber($data['phone'])) {
                $validator->errors()->add('phone', 'Le numéro de téléphone doit être au format +221XXXXXXXXX ou +224XXXXXXXXX');
            }
            
            // Validation personnalisée du montant
            if (isset($data['amount']) && !$this->validateAmount($data['amount'])) {
                $validator->errors()->add('amount', 'Le montant doit être entre 100 et 1,000,000 FCFA');
            }
            
            // Validation personnalisée du pseudo
            if (isset($data['pseudo']) && !$this->validatePseudo($data['pseudo'])) {
                $validator->errors()->add('pseudo', 'Le pseudo doit contenir entre 3 et 20 caractères alphanumériques');
            }
            
            // Validation personnalisée du mot de passe
            if (isset($data['password']) && !$this->validatePasswordStrength($data['password'])) {
                $validator->errors()->add('password', 'Le mot de passe doit contenir au moins 8 caractères avec majuscules, minuscules et chiffres');
            }
        });
    }
}