<?php
/**
 * Script de test pour démontrer les parties d'exhibition
 */

// Simuler une requête de création de salle d'exhibition
$exhibitionRoom = [
    'name' => 'Partie amicale - Test',
    'is_exhibition' => true,
    'max_players' => 2,
    'rounds_to_win' => 3,
    'time_limit' => 300,
    'allow_spectators' => true
];

// Simuler une requête de création de salle normale
$normalRoom = [
    'name' => 'Partie officielle - Test',
    'bet_amount' => 1000,
    'is_exhibition' => false,
    'max_players' => 2,
    'rounds_to_win' => 3,
    'time_limit' => 300,
    'allow_spectators' => false
];

echo "=== TEST DES PARTIES D'EXHIBITION ===\n\n";

echo "1. Données pour créer une salle d'EXHIBITION :\n";
echo json_encode($exhibitionRoom, JSON_PRETTY_PRINT) . "\n\n";

echo "2. Données pour créer une salle NORMALE :\n";
echo json_encode($normalRoom, JSON_PRETTY_PRINT) . "\n\n";

echo "3. Différences clés :\n";
echo "   - Exhibition : is_exhibition = true, pas de bet_amount requis\n";
echo "   - Normal : is_exhibition = false, bet_amount obligatoire\n\n";

echo "4. Endpoints à utiliser :\n";
echo "   POST /api/rooms (créer une salle)\n";
echo "   POST /api/rooms/{code}/join (rejoindre une salle)\n";
echo "   POST /api/rooms/{code}/ready (marquer comme prêt)\n";
echo "   GET /api/games/{code}/state (voir l'état du jeu)\n\n";

echo "=== IMPLÉMENTATION TERMINÉE ===\n";
echo "✅ Migration ajoutée (champ is_exhibition)\n";
echo "✅ Modèle GameRoom modifié\n";
echo "✅ GameRoomController adapté\n";
echo "✅ Validation mise à jour\n";
echo "✅ Logique financière conditionnelle\n";
echo "✅ Documentation créée\n";