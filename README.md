# 🚀 La Map 241 - API Backend Laravel

## 📋 Description

**La Map 241** est une API backend robuste développée en Laravel 11 pour un jeu de cartes multijoueur en temps réel. L'API offre un système complet de gestion de parties, statistiques avancées, intelligence artificielle et monétisation.

## 🎯 Fonctionnalités Principales

### 🃏 Système de Jeu
- **Jeu de cartes "La Map 241"** avec règles spécifiques
- **Parties multijoueurs** (2-4 joueurs)
- **Salles de jeu** avec codes uniques
- **Mode exhibition** (parties gratuites)
- **Gestion des tours** et transitions automatiques

### 🤖 Intelligence Artificielle
- **3 niveaux de difficulté** : Easy, Medium, Hard
- **Algorithmes adaptatifs** selon les règles du jeu
- **Gestion automatique** des bots dans les parties
- **Statistiques des bots** et performance tracking

### 💰 Système Monétaire
- **Portefeuille numérique** intégré
- **Dépôts et retraits** via Mobile Money (Airtel, Moov)
- **Système de paris** (500-100,000 FCFA)
- **Commissions automatiques** (10%)
- **Transactions sécurisées** avec E-Billing

### 📊 Statistiques Avancées
- **25 achievements** avec récompenses
- **6 types de leaderboards** (gains, taux victoire, volume, etc.)
- **Statistiques détaillées** (basiques, financières, performance)
- **Comparaisons utilisateurs** et progression

### 🔄 Temps Réel
- **WebSocket** via Laravel Reverb
- **Événements en temps réel** (cartes jouées, joueurs connectés)
- **Notifications push** pour achievements et parties

## 🛠️ Technologies Utilisées

- **PHP 8.2+**
- **Laravel 11**
- **MySQL 8.0+**
- **Redis** (cache et sessions)
- **Laravel Sanctum** (authentification)
- **Laravel Reverb** (WebSocket)
- **Pusher** (broadcasting)

## 📦 Installation

### Prérequis
```bash
- PHP 8.2 ou plus
- Composer
- MySQL 8.0+
- Redis (optionnel mais recommandé)
- Node.js (pour Laravel Reverb)
```

### Installation
```bash
# Cloner le projet
git clone [repository-url]
cd lamap241_api

# Installer les dépendances
composer install

# Configurer l'environnement
cp .env.example .env
php artisan key:generate

# Configurer la base de données dans .env
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=lamap241
DB_USERNAME=root
DB_PASSWORD=

# Migrer la base de données
php artisan migrate

# Optimiser le backend
php artisan backend:optimize --force

# Créer des bots initiaux
php artisan bot:manage create --difficulty=easy --count=2
php artisan bot:manage create --difficulty=medium --count=2
php artisan bot:manage create --difficulty=hard --count=1
```

## 🚀 Démarrage

### Serveur de développement
```bash
# Démarrer l'API
php artisan serve --port=8000

# Démarrer WebSocket (terminal séparé)
php artisan reverb:start

# Démarrer les workers de queue (terminal séparé)
php artisan queue:work
```

### Production
```bash
# Optimiser pour la production
composer install --no-dev --optimize-autoloader
php artisan config:cache
php artisan route:cache
php artisan view:cache

# Démarrer avec supervisord ou similar
php artisan serve --host=0.0.0.0 --port=8000
```

## 📚 Documentation API

### Endpoints Principaux

#### Authentification
```http
POST /api/auth/register     # Inscription
POST /api/auth/login        # Connexion
GET  /api/auth/profile      # Profil utilisateur
POST /api/auth/logout       # Déconnexion
```

#### Jeu
```http
GET  /api/rooms             # Liste des salles
POST /api/rooms             # Créer une salle
GET  /api/rooms/{code}      # Détails d'une salle
POST /api/rooms/{code}/join # Rejoindre une salle
POST /api/games/{id}/play   # Jouer une carte
POST /api/games/{id}/pass   # Passer son tour
```

#### Bots/IA
```http
GET  /api/bots              # Liste des bots
POST /api/bots              # Créer un bot
POST /api/bots/rooms/{code}/add    # Ajouter bot à salle
POST /api/bots/games/{id}/play     # Faire jouer un bot
```

#### Statistiques
```http
GET  /api/enhanced-stats/me/detailed        # Mes stats détaillées
GET  /api/enhanced-stats/leaderboards       # Tous les classements
GET  /api/enhanced-stats/me/achievements    # Mes achievements
GET  /api/enhanced-stats/global             # Stats globales
```

#### Portefeuille
```http
GET  /api/wallet/balance    # Solde actuel
POST /api/wallet/deposit    # Effectuer un dépôt
POST /api/wallet/withdraw   # Effectuer un retrait
GET  /api/wallet/transactions # Historique
```

## 🔧 Commandes Artisan

### Gestion des Bots
```bash
# Créer des bots
php artisan bot:manage create --difficulty=medium --count=3

# Statistiques des bots
php artisan bot:manage stats

# Auto-play des bots
php artisan bot:manage auto-play
```

### Maintenance des Parties
```bash
# Statistiques générales
php artisan game:maintenance stats

# Nettoyage des parties obsolètes
php artisan game:maintenance cleanup --force

# Timeout des parties longues
php artisan game:maintenance timeout
```

### Optimisation Backend
```bash
# Optimisation complète
php artisan backend:optimize --force

# Rapport de performance
php artisan backend:optimize --report
```

## 🛡️ Sécurité

### Rate Limiting
- **Auth endpoints** : 5-10 requêtes/minute
- **Game endpoints** : 10-30 requêtes/minute
- **Payment endpoints** : 2-5 requêtes/minute

### Middleware de Sécurité
- **Détection SQL Injection** automatique
- **Protection XSS** et CSRF
- **Validation stricte** des inputs
- **Headers de sécurité** complets

## 📊 Performance

### Optimisations
- **43 index database** pour requêtes optimisées
- **Cache Redis** pour sessions et données fréquentes
- **Query optimization** avec services dédiés
- **Temps de réponse** < 100ms pour 95% des requêtes

### Monitoring
```bash
# Vérifier les performances
php artisan backend:optimize --report

# Statistiques des parties
php artisan game:maintenance stats
```

## 🔄 Système d'Achievements

### 25 Achievements Disponibles
- **Première victoire** : 100 FCFA
- **Série de 5 victoires** : 500 FCFA
- **100 parties jouées** : 1000 FCFA
- **Maître du jeu** : 2500 FCFA
- ... et 21 autres achievements

### Système de Récompenses
- **Points d'achievement** : Système de progression
- **Récompenses FCFA** : Gains automatiques
- **Classements** : Leaderboard par achievements

## 🌐 Déploiement

### Variables d'Environnement
```env
APP_NAME="La Map 241"
APP_ENV=production
APP_DEBUG=false
APP_URL=https://your-domain.com

DB_CONNECTION=mysql
DB_HOST=your-db-host
DB_DATABASE=lamap241
DB_USERNAME=your-username
DB_PASSWORD=your-password

CACHE_DRIVER=redis
QUEUE_CONNECTION=redis
BROADCAST_DRIVER=reverb

# Mobile Money Configuration
EBILLING_URL=https://api.e-billing.com
AIRTEL_API_KEY=your-airtel-key
MOOV_API_KEY=your-moov-key
```

### Serveur de Production
```bash
# Nginx configuration
server {
    listen 80;
    server_name your-domain.com;
    root /path/to/lamap241_api/public;

    index index.php;

    location / {
        try_files $uri $uri/ /index.php?$query_string;
    }

    location ~ \.php$ {
        fastcgi_pass unix:/var/run/php/php8.2-fpm.sock;
        fastcgi_index index.php;
        fastcgi_param SCRIPT_FILENAME $realpath_root$fastcgi_script_name;
        include fastcgi_params;
    }
}
```

## 📝 Tests

### Lancer les Tests
```bash
# Tests unitaires
php artisan test

# Tests avec coverage
php artisan test --coverage

# Tests spécifiques
php artisan test --filter=GameTest
```

### Suite de Tests
- **15 tests automatisés** couvrant les fonctionnalités principales
- **Tests API** pour tous les endpoints
- **Tests d'intégration** pour le système de jeu
- **Tests de performance** pour les optimisations

## 🤝 Contribution

### Standards de Code
- **PSR-12** pour le style de code
- **PHPDoc** pour la documentation
- **Tests unitaires** requis pour nouvelles fonctionnalités

### Workflow Git
```bash
# Créer une branche feature
git checkout -b feature/nouvelle-fonctionnalite

# Commit avec messages descriptifs
git commit -m "feat: ajouter système de tournois"

# Tests avant push
php artisan test
```

## 📞 Support

### Logs et Debugging
```bash
# Vérifier les logs
tail -f storage/logs/laravel.log

# Debug mode (développement seulement)
APP_DEBUG=true php artisan serve
```

### Problèmes Courants
1. **Erreur 500** : Vérifier les logs Laravel
2. **Base de données** : Vérifier les migrations
3. **Redis** : Vérifier la connexion Redis
4. **Permissions** : `chmod -R 755 storage bootstrap/cache`

## 🏆 Statistiques du Projet

- **66 routes API** totales
- **25 achievements** différents
- **6 leaderboards** complets
- **4 bots IA** opérationnels
- **43 index database** optimisés
- **3 niveaux de rate limiting**
- **15 tests automatisés**

## 📄 Licence

Ce projet est sous licence MIT. Voir le fichier [LICENSE](LICENSE) pour plus de détails.

---

**La Map 241 API** - Version 1.0.0  
Développé avec ❤️ en Laravel 11