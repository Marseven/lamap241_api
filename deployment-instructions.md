# Instructions de Déploiement CORS

## Problème CORS Résolu

L'erreur CORS rencontrée était:
```
Access to fetch at 'https://lamap.mebodorichard.com/api/auth/profile' from origin 'https://lamap241.vercel.app' has been blocked by CORS policy: Response to preflight request doesn't pass access control check: No 'Access-Control-Allow-Origin' header is present on the requested resource.
```

## Solutions Implémentées

### 1. Configuration CORS Laravel (config/cors.php)
- Ajouté les domaines Vercel explicitement
- Configuré les patterns pour toutes les URLs Vercel
- Ajouté le support pour les variables d'environnement

### 2. Middleware CORS Personnalisé (app/Http/Middleware/CorsMiddleware.php)
- Middleware de fallback pour un contrôle plus fin
- Gestion des requêtes preflight
- Support des patterns regex pour Vercel

### 3. Registration du Middleware (bootstrap/app.php)
- Enregistré le middleware CORS en premier
- Alias configuré pour utilisation flexible

## Étapes de Déploiement

### 1. Fichiers à Uploader sur le Serveur Production
```bash
# Copier ces fichiers vers lamap.mebodorichard.com
config/cors.php
app/Http/Middleware/CorsMiddleware.php
bootstrap/app.php
```

### 2. Variables d'Environnement à Ajouter
Ajouter dans le fichier `.env` de production:
```env
FRONTEND_URL_PROD=https://lamap241.vercel.app
FRONTEND_URLS="https://lamap241.vercel.app,https://lamap241-git-main-lamap241.vercel.app,https://www.lamap241.com"
```

### 3. Commandes à Exécuter sur le Serveur
```bash
# Après upload des fichiers
php artisan config:cache
php artisan route:cache
php artisan optimize

# Optionnel: redémarrer les workers
php artisan queue:restart
```

### 4. Test de Fonctionnement
Une fois déployé, tester avec:
```bash
curl -H "Origin: https://lamap241.vercel.app" \
     -H "Access-Control-Request-Method: GET" \
     -H "Access-Control-Request-Headers: Authorization, Content-Type" \
     -X OPTIONS \
     https://lamap.mebodorichard.com/api/auth/profile
```

## Domaines Autorisés
- `https://lamap241.vercel.app` (production)
- `https://lamap241-git-main-lamap241.vercel.app` (branches)
- `https://www.lamap241.com` (domaine principal)
- Pattern: `https://lamap241*.vercel.app` (toutes les previews)

## Prochaines Étapes
1. Déployer les fichiers modifiés
2. Configurer les variables d'environnement
3. Vider le cache Laravel
4. Tester l'API depuis Vercel