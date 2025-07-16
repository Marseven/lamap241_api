<?php

namespace App\Services;

use App\Models\Game;
use App\Models\User;
use Illuminate\Support\Facades\Log;

class GameAIService
{
    /**
     * Niveaux de difficulté de l'IA
     */
    const DIFFICULTY_EASY = 'easy';
    const DIFFICULTY_MEDIUM = 'medium';
    const DIFFICULTY_HARD = 'hard';

    /**
     * Stratégies de jeu
     */
    const STRATEGY_AGGRESSIVE = 'aggressive';
    const STRATEGY_DEFENSIVE = 'defensive';
    const STRATEGY_BALANCED = 'balanced';

    /**
     * Temps d'attente par difficulté (simule la réflexion)
     */
    const THINKING_TIME = [
        self::DIFFICULTY_EASY => [1, 3],    // 1-3 secondes
        self::DIFFICULTY_MEDIUM => [2, 5],  // 2-5 secondes
        self::DIFFICULTY_HARD => [3, 7],    // 3-7 secondes
    ];

    /**
     * Jouer un coup pour l'IA
     */
    public function playMove(Game $game, User $botUser, string $difficulty = self::DIFFICULTY_MEDIUM): array
    {
        // Vérifier que c'est le tour du bot
        if ($game->current_player_id !== $botUser->id) {
            throw new \Exception('Ce n\'est pas le tour du bot');
        }

        // Obtenir la main du bot
        $botCards = $game->getPlayerCards($botUser->id);
        if (empty($botCards)) {
            throw new \Exception('Le bot n\'a pas de cartes');
        }

        // Analyser la situation
        $gameAnalysis = $this->analyzeGameState($game, $botUser->id);
        
        // Décider de l'action à prendre
        $decision = $this->makeDecision($game, $botCards, $gameAnalysis, $difficulty);
        
        // Simuler le temps de réflexion
        $this->simulateThinkingTime($difficulty);
        
        // Exécuter l'action
        if ($decision['action'] === 'play') {
            $success = $game->playCard($botUser->id, $decision['card']);
            
            Log::info("Bot {$botUser->pseudo} a joué {$decision['card']['value']}{$decision['card']['suit']}", [
                'game_id' => $game->id,
                'reasoning' => $decision['reasoning']
            ]);
            
            return [
                'action' => 'play',
                'card' => $decision['card'],
                'success' => $success,
                'reasoning' => $decision['reasoning']
            ];
        } else {
            $success = $game->pass($botUser->id);
            
            Log::info("Bot {$botUser->pseudo} a passé", [
                'game_id' => $game->id,
                'reasoning' => $decision['reasoning']
            ]);
            
            return [
                'action' => 'pass',
                'success' => $success,
                'reasoning' => $decision['reasoning']
            ];
        }
    }

    /**
     * Analyser l'état du jeu
     */
    private function analyzeGameState(Game $game, int $botId): array
    {
        $gameState = $game->game_state;
        $tableCards = $game->table_cards;
        
        return [
            'table_cards' => $tableCards,
            'last_card' => !empty($tableCards) ? end($tableCards) : null,
            'consecutive_passes' => $gameState['consecutive_passes'] ?? 0,
            'player_order' => $gameState['player_order'] ?? [],
            'other_players' => $this->analyzeOtherPlayers($game, $botId),
            'round_number' => $game->round_number,
            'is_endgame' => $this->isEndgame($game, $botId)
        ];
    }

    /**
     * Analyser les autres joueurs
     */
    private function analyzeOtherPlayers(Game $game, int $botId): array
    {
        $analysis = [];
        $playerOrder = $game->game_state['player_order'] ?? [];
        
        foreach ($playerOrder as $playerId) {
            if ($playerId !== $botId) {
                $playerCards = $game->getPlayerCards($playerId);
                $analysis[$playerId] = [
                    'cards_count' => count($playerCards),
                    'is_close_to_winning' => count($playerCards) <= 2,
                    'is_next_player' => $this->isNextPlayer($game, $playerId, $botId)
                ];
            }
        }
        
        return $analysis;
    }

    /**
     * Vérifier si c'est le joueur suivant
     */
    private function isNextPlayer(Game $game, int $playerId, int $botId): bool
    {
        $playerOrder = $game->game_state['player_order'] ?? [];
        $botIndex = array_search($botId, $playerOrder);
        
        if ($botIndex === false) return false;
        
        $nextIndex = ($botIndex + 1) % count($playerOrder);
        return $playerOrder[$nextIndex] === $playerId;
    }

    /**
     * Vérifier si c'est la fin de partie
     */
    private function isEndgame(Game $game, int $botId): bool
    {
        $botCards = $game->getPlayerCards($botId);
        $otherPlayers = $this->analyzeOtherPlayers($game, $botId);
        
        // Endgame si le bot a 2 cartes ou moins
        if (count($botCards) <= 2) {
            return true;
        }
        
        // Endgame si un adversaire a 2 cartes ou moins
        foreach ($otherPlayers as $player) {
            if ($player['cards_count'] <= 2) {
                return true;
            }
        }
        
        return false;
    }

    /**
     * Prendre une décision de jeu
     */
    private function makeDecision(Game $game, array $botCards, array $analysis, string $difficulty): array
    {
        $lastCard = $analysis['last_card'];
        $playableCards = $this->getPlayableCards($botCards, $lastCard);
        
        // Si aucune carte jouable, passer
        if (empty($playableCards)) {
            return [
                'action' => 'pass',
                'reasoning' => 'Aucune carte jouable disponible'
            ];
        }
        
        // Stratégie selon la difficulté
        switch ($difficulty) {
            case self::DIFFICULTY_EASY:
                return $this->easyStrategy($playableCards, $analysis);
            
            case self::DIFFICULTY_MEDIUM:
                return $this->mediumStrategy($playableCards, $analysis);
            
            case self::DIFFICULTY_HARD:
                return $this->hardStrategy($playableCards, $analysis, $game);
            
            default:
                return $this->mediumStrategy($playableCards, $analysis);
        }
    }

    /**
     * Stratégie facile: jouer la première carte disponible
     */
    private function easyStrategy(array $playableCards, array $analysis): array
    {
        $card = $playableCards[0];
        
        return [
            'action' => 'play',
            'card' => $card,
            'reasoning' => 'Stratégie facile: première carte disponible'
        ];
    }

    /**
     * Stratégie moyenne: jouer la carte la plus faible sauf en fin de partie
     */
    private function mediumStrategy(array $playableCards, array $analysis): array
    {
        // Trier les cartes par valeur
        usort($playableCards, function($a, $b) {
            return $a['value'] <=> $b['value'];
        });
        
        // En fin de partie, jouer la carte la plus forte
        if ($analysis['is_endgame']) {
            $card = end($playableCards);
            return [
                'action' => 'play',
                'card' => $card,
                'reasoning' => 'Stratégie moyenne: carte forte en fin de partie'
            ];
        }
        
        // Normalement, jouer la carte la plus faible
        $card = $playableCards[0];
        return [
            'action' => 'play',
            'card' => $card,
            'reasoning' => 'Stratégie moyenne: carte faible pour préserver les fortes'
        ];
    }

    /**
     * Stratégie difficile: analyse complète et choix optimal
     */
    private function hardStrategy(array $playableCards, array $analysis, Game $game): array
    {
        // Analyser chaque carte possible
        $cardAnalysis = [];
        
        foreach ($playableCards as $card) {
            $cardAnalysis[] = [
                'card' => $card,
                'score' => $this->evaluateCard($card, $analysis, $game),
                'blocks_opponents' => $this->cardBlocksOpponents($card, $analysis),
                'strategic_value' => $this->getStrategicValue($card, $analysis)
            ];
        }
        
        // Trier par score total
        usort($cardAnalysis, function($a, $b) {
            return $b['score'] <=> $a['score'];
        });
        
        $bestCard = $cardAnalysis[0];
        
        return [
            'action' => 'play',
            'card' => $bestCard['card'],
            'reasoning' => 'Stratégie difficile: analyse optimale (score: ' . $bestCard['score'] . ')'
        ];
    }

    /**
     * Évaluer une carte
     */
    private function evaluateCard(array $card, array $analysis, Game $game): float
    {
        $score = 0;
        
        // Score de base inversé (cartes faibles = score élevé)
        $score += (11 - $card['value']) * 2;
        
        // Bonus si on est en fin de partie
        if ($analysis['is_endgame']) {
            $score += $card['value'] * 3;
        }
        
        // Bonus si la carte bloque les adversaires
        if ($this->cardBlocksOpponents($card, $analysis)) {
            $score += 15;
        }
        
        // Malus si beaucoup de passes consécutives (jouer plus agressivement)
        if ($analysis['consecutive_passes'] >= 2) {
            $score += $card['value'] * 2;
        }
        
        return $score;
    }

    /**
     * Vérifier si une carte bloque les adversaires
     */
    private function cardBlocksOpponents(array $card, array $analysis): bool
    {
        // Si c'est une carte élevée (8, 9, 10), elle peut bloquer
        return $card['value'] >= 8;
    }

    /**
     * Obtenir la valeur stratégique d'une carte
     */
    private function getStrategicValue(array $card, array $analysis): float
    {
        $value = 0;
        
        // Cartes moyennes ont plus de valeur stratégique
        if ($card['value'] >= 5 && $card['value'] <= 7) {
            $value += 5;
        }
        
        // En fin de partie, garder les cartes fortes
        if ($analysis['is_endgame'] && $card['value'] >= 8) {
            $value += 10;
        }
        
        return $value;
    }

    /**
     * Obtenir les cartes jouables
     */
    private function getPlayableCards(array $botCards, ?array $lastCard): array
    {
        if (!$lastCard) {
            // Si table vide, toutes les cartes sont jouables
            return $botCards;
        }
        
        $playable = [];
        
        foreach ($botCards as $card) {
            // Même couleur et valeur supérieure
            if ($card['suit'] === $lastCard['suit'] && $card['value'] > $lastCard['value']) {
                $playable[] = $card;
            }
        }
        
        return $playable;
    }

    /**
     * Simuler le temps de réflexion
     */
    private function simulateThinkingTime(string $difficulty): void
    {
        $timeRange = self::THINKING_TIME[$difficulty] ?? self::THINKING_TIME[self::DIFFICULTY_MEDIUM];
        $thinkingTime = rand($timeRange[0], $timeRange[1]);
        
        // En mode test, ne pas attendre
        if (app()->environment('testing')) {
            return;
        }
        
        sleep($thinkingTime);
    }

    /**
     * Créer un utilisateur bot
     */
    public function createBot(string $difficulty = self::DIFFICULTY_MEDIUM): User
    {
        $botNames = [
            self::DIFFICULTY_EASY => ['BotNovice', 'BotDebutant', 'BotFacile'],
            self::DIFFICULTY_MEDIUM => ['BotJoueur', 'BotMoyen', 'BotNormal'],
            self::DIFFICULTY_HARD => ['BotExpert', 'BotPro', 'BotMaitre']
        ];
        
        $names = $botNames[$difficulty] ?? $botNames[self::DIFFICULTY_MEDIUM];
        $botName = $names[array_rand($names)];
        
        $timestamp = time() . '_' . rand(1000, 9999);
        $bot = User::create([
            'name' => $botName . '_' . $timestamp,
            'pseudo' => $botName . '_' . $timestamp,
            'email' => 'bot_' . $timestamp . '@lamap241.com',
            'password' => bcrypt('bot_password'),
            'phone' => '000000000',
            'status' => 'active'
        ]);
        
        // Mise à jour des champs bot séparément
        $bot->update([
            'is_bot' => true,
            'bot_difficulty' => $difficulty,
            'last_bot_activity' => now()
        ]);
        
        return $bot;
    }

    /**
     * Vérifier si un utilisateur est un bot
     */
    public function isBot(User $user): bool
    {
        return $user->is_bot ?? false;
    }

    /**
     * Obtenir les statistiques d'un bot
     */
    public function getBotStats(User $botUser): array
    {
        if (!$this->isBot($botUser)) {
            return [];
        }
        
        return [
            'difficulty' => $botUser->bot_difficulty ?? self::DIFFICULTY_MEDIUM,
            'total_games' => $botUser->stats->games_played ?? 0,
            'win_rate' => $this->calculateBotWinRate($botUser),
            'avg_game_duration' => $this->calculateAvgGameDuration($botUser),
            'favorite_strategy' => $this->getFavoriteStrategy($botUser)
        ];
    }

    /**
     * Calculer le taux de victoire d'un bot
     */
    private function calculateBotWinRate(User $botUser): float
    {
        $stats = $botUser->stats;
        if (!$stats || $stats->games_played === 0) {
            return 0;
        }
        
        return round(($stats->games_won / $stats->games_played) * 100, 2);
    }

    /**
     * Calculer la durée moyenne des parties
     */
    private function calculateAvgGameDuration(User $botUser): int
    {
        // Logique pour calculer la durée moyenne
        // À implémenter selon les besoins
        return 0;
    }

    /**
     * Obtenir la stratégie favorite
     */
    private function getFavoriteStrategy(User $botUser): string
    {
        $difficulty = $botUser->bot_difficulty ?? self::DIFFICULTY_MEDIUM;
        
        return match($difficulty) {
            self::DIFFICULTY_EASY => self::STRATEGY_DEFENSIVE,
            self::DIFFICULTY_MEDIUM => self::STRATEGY_BALANCED,
            self::DIFFICULTY_HARD => self::STRATEGY_AGGRESSIVE,
            default => self::STRATEGY_BALANCED
        };
    }
}