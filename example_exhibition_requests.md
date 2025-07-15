# Exemples de requêtes pour les parties d'exhibition

## 1. Créer une salle d'exhibition (sans pari)

```bash
curl -X POST http://localhost:8000/api/rooms \
  -H "Content-Type: application/json" \
  -H "Authorization: Bearer YOUR_TOKEN" \
  -d '{
    "name": "Partie amicale",
    "is_exhibition": true,
    "max_players": 2,
    "rounds_to_win": 3,
    "time_limit": 300,
    "allow_spectators": true
  }'
```

## 2. Créer une salle normale (avec pari)

```bash
curl -X POST http://localhost:8000/api/rooms \
  -H "Content-Type: application/json" \
  -H "Authorization: Bearer YOUR_TOKEN" \
  -d '{
    "name": "Partie officielle",
    "bet_amount": 1000,
    "is_exhibition": false,
    "max_players": 2,
    "rounds_to_win": 3,
    "time_limit": 300,
    "allow_spectators": false
  }'
```

## 3. Rejoindre une salle d'exhibition

```bash
curl -X POST http://localhost:8000/api/rooms/ABC123/join \
  -H "Content-Type: application/json" \
  -H "Authorization: Bearer YOUR_TOKEN"
```

## 4. Marquer comme prêt

```bash
curl -X POST http://localhost:8000/api/rooms/ABC123/ready \
  -H "Content-Type: application/json" \
  -H "Authorization: Bearer YOUR_TOKEN"
```

## Différences entre parties d'exhibition et parties normales

### Parties d'exhibition :
- `is_exhibition: true`
- `bet_amount: 0` (automatiquement fixé)
- `pot_amount: 0` (pas de cagnotte)
- `commission_amount: 0` (pas de commission)
- Pas de vérification de solde
- Pas de transactions financières
- Les stats sont quand même mises à jour

### Parties normales :
- `is_exhibition: false` (par défaut)
- `bet_amount` obligatoire (min: 500, max: 100000)
- Vérification du solde utilisateur
- Transactions financières
- Cagnotte et commission calculées
- Gains distribués au gagnant