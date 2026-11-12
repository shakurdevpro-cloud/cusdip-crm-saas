# CusDip — environnement Docker local

Stack de développement : Laravel 12, PHP 8.3-FPM, Nginx, MySQL 8.0 et Redis 7.

## Prérequis
- Docker Desktop démarré avec Linux containers.
- Git.
- Windows : PowerShell ou terminal VS Code.

## 1. Préparer l'environnement
À la racine du dépôt :

```powershell
Copy-Item .env.docker.example .env
```

Les mots de passe fournis sont uniquement destinés au développement local.

## 2. Construire l'image PHP

```bash
docker compose build
```

## 3. Installer Laravel 12 dans le dépôt

Les fichiers Docker occupent déjà la racine. Créer Laravel dans un dossier temporaire :

```bash
docker compose run --rm --no-deps app composer create-project laravel/laravel laravel-temp "^12.0"
```

Puis, dans PowerShell, déplacer le contenu Laravel à la racine :

```powershell
Get-ChildItem -Force .\laravel-temp | Move-Item -Destination .
Remove-Item .\laravel-temp -Recurse -Force
```

Si une collision de fichier est signalée, ne remplace pas les fichiers Docker ni `.env`. Vérifie ensuite que `artisan`, `composer.json`, `app/`, `bootstrap/`, `config/`, `database/`, `public/`, `resources/` et `routes/` sont à la racine.

## 4. Vérifier les paramètres Laravel

Dans le `.env` Laravel, conserver ces paramètres :

```dotenv
APP_NAME=CusDip
APP_ENV=local
APP_DEBUG=true
APP_URL=http://localhost:8080
DB_CONNECTION=mysql
DB_HOST=db
DB_PORT=3306
DB_DATABASE=cusdip
DB_USERNAME=cusdip
DB_PASSWORD=cusdip_local_password
SESSION_DRIVER=database
CACHE_STORE=database
QUEUE_CONNECTION=redis
REDIS_CLIENT=phpredis
REDIS_HOST=redis
REDIS_PASSWORD=null
REDIS_PORT=6379
```

Garder la clé `APP_KEY` générée par Laravel.

## 5. Démarrer et initialiser

```bash
docker compose up -d
docker compose ps
docker compose exec app php artisan key:generate
docker compose exec app php artisan migrate
```

Ne lancer `key:generate` que si `APP_KEY` est vide. Ouvrir http://localhost:8080.

## Commandes courantes

```bash
docker compose logs -f app
docker compose logs -f nginx
docker compose logs -f db
docker compose exec app php artisan about
docker compose exec app php artisan route:list
docker compose --profile workers up -d queue
docker compose down
```

Attention : `docker compose down -v` supprime les volumes et donc les données MySQL/Redis.

## Services et ports

| Service | Accès depuis Windows | Usage |
|---|---|---|
| Nginx | http://localhost:8080 | Application |
| MySQL | 127.0.0.1:3307 | Outil SQL local |
| PHP-FPM | Réseau Docker seulement | PHP |
| Redis | Réseau Docker seulement | Cache / queue |

Dans Laravel, MySQL est `db:3306`. Le port `3307` est uniquement destiné aux outils lancés sur Windows.

## Sécurité et bonnes pratiques
- Ne jamais committer `.env`.
- Ne pas exposer MySQL/Redis publiquement.
- Utiliser des secrets distincts en production.
- Les données sont conservées dans les volumes Docker.
- Ne pas utiliser `chmod -R 777` comme solution permanente.
