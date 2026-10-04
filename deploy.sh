#!/bin/bash

set -euo pipefail

ENV_DIR="environments/prod"
ENV_SCRIPT_FILE="$ENV_DIR/.env.script"

for file in "$ENV_SCRIPT_FILE" "$ENV_DIR/.env"; do
  if [ ! -f "$file" ]; then
    echo "❌ - $file introuvable : le créer depuis $file.example."
    exit 1
  fi
done

set -o allexport
source "$ENV_SCRIPT_FILE"
set +o allexport

SITE_DIR="/var/www/$SITE_NAME"
CADDY_CONF_FILE="/etc/caddy/conf/$SITE_NAME.caddy"
MAIN_CADDYFILE="/etc/caddy/Caddyfile"
IMPORT_LINE="import conf/$SITE_NAME.caddy"
COMPOSE="sudo docker compose -f $ENV_DIR/compose.yaml"

remote() {
  ssh "$REMOTE_USER@$REMOTE_HOST" "set -euo pipefail; $1"
}

step() {
  echo ""
  echo "----------------------------------------"
  echo "🔍 - $1"
}

echo ""
echo "🚀 - Déploiement de $SITE_NAME sur $REMOTE_HOST (branche $GIT_BRANCH, port $PROJECT_PORT)"
echo "⚠️  - La base est réinitialisée avec les fixtures à chaque déploiement."

step "Étape 1 : dépôt git dans $SITE_DIR"
remote "
  if [ ! -d '$SITE_DIR/.git' ]; then
    sudo mkdir -p '$SITE_DIR'
    sudo chown '$REMOTE_USER:$REMOTE_USER' '$SITE_DIR'
    git clone -b '$GIT_BRANCH' '$REPO_URL' '$SITE_DIR'
  else
    cd '$SITE_DIR'
    git fetch origin
    git checkout '$GIT_BRANCH'
    git pull --ff-only origin '$GIT_BRANCH'
  fi
"

step "Étape 2 : fichier d'environnement"
scp -q "$ENV_DIR/.env" "$REMOTE_USER@$REMOTE_HOST:$SITE_DIR/$ENV_DIR/.env"
remote "chmod 600 '$SITE_DIR/$ENV_DIR/.env'"

step "Étape 3 : build des images app et seed"
remote "cd '$SITE_DIR' && $COMPOSE --profile seed build"

step "Étape 4 : réinitialisation de la base (migrations + fixtures)"
remote "
  cd '$SITE_DIR'
  $COMPOSE stop app
  $COMPOSE run --rm seed
"

step "Étape 5 : démarrage de l'application"
remote "cd '$SITE_DIR' && $COMPOSE up -d --wait --wait-timeout 120 --remove-orphans app"

# Le Caddy hôte sert tous les sites du serveur : reload (gracieux), jamais restart.
step "Étape 6 : configuration Caddy"
remote "
  sudo tee '$CADDY_CONF_FILE' > /dev/null <<'CADDY'
$SITE_NAME {
    reverse_proxy 127.0.0.1:$PROJECT_PORT
}
CADDY
  grep -Fxq '$IMPORT_LINE' '$MAIN_CADDYFILE' || echo '$IMPORT_LINE' | sudo tee -a '$MAIN_CADDYFILE' > /dev/null
  sudo caddy validate --config '$MAIN_CADDYFILE' --adapter caddyfile
  sudo systemctl reload caddy
"

echo ""
echo "✅ - Déployé : https://$SITE_NAME"
