#!/bin/bash
set -e

rm -f .env
cp .env.example .env

sed -ri "s~^APP_ENV=.*$~APP_ENV=${APP_ENV:-production}~" .env
sed -ri "s~^APP_KEY=.*$~APP_KEY=$APP_KEY~" .env
sed -ri "s~^APP_DEBUG=.*$~APP_DEBUG=${APP_DEBUG:-false}~" .env
sed -ri "s~^APP_URL=.*$~APP_URL=$APP_URL~" .env

sed -ri "s~^BEEKEEPER_API_BASE_URL=.*$~BEEKEEPER_API_BASE_URL=${BEEKEEPER_API_BASE_URL}~" .env
sed -ri "s~^BEEKEEPER_BOT_TOKEN=.*$~BEEKEEPER_BOT_TOKEN=${BEEKEEPER_BOT_TOKEN}~" .env
sed -ri "s~^BEEKEEPER_WEBHOOK_ID_MESSAGE=.*$~BEEKEEPER_WEBHOOK_ID_MESSAGE=${BEEKEEPER_WEBHOOK_ID_MESSAGE}~" .env

sed -ri "s~^DB_HOST=.*$~DB_HOST=${DB_HOST:-localhost}~" .env
sed -ri "s~^DB_DATABASE=.*$~DB_DATABASE=${DB_DATABASE:-bot}~" .env
sed -ri "s~^DB_USERNAME=.*$~DB_USERNAME=$DB_USERNAME~" .env
sed -ri "s~^DB_PASSWORD=.*$~DB_PASSWORD=$DB_PASSWORD~" .env

sed -ri "s~^SENTRY_LARAVEL_DSN=.*$~SENTRY_LARAVEL_DSN=${SENTRY_LARAVEL_DSN:-null}~" .env
sed -ri "s~^SENTRY_USER_FEEDBACK_URL=.*$~SENTRY_USER_FEEDBACK_URL=$SENTRY_USER_FEEDBACK_URL~" .env

docker-compose run --entrypoint "composer install --no-dev" app

# Travis CI uses OpenSSL 1.0.2g  1 Mar 2016. Files encrypted with newer versions of OpenSSL are not decryptable by
# the Travis CI version, error message is "bad decrypt". So to encrypt a file, use the following:
# docker run --rm -v $(pwd):/app -w /app frapsoft/openssl aes-256-cbc -k "<password>" -in <input_file> -out <output_file>
openssl aes-256-cbc -k "$ID_RSA_PASSWORD" -in .travis/id_rsa_travis.enc -out .travis/id_rsa -d
eval "$(ssh-agent -s)"
chmod 600 .travis/id_rsa
ssh-add .travis/id_rsa

echo "Uploading files to the server..."
# Add the host fingerprint to the known hosts
echo "|1|dV4qsau1GbVSd7SouuBbZX/7lY0=|TrxRD/WiO0lwzNYp9i3QXTEMi5Q= ssh-ed25519 AAAAC3NzaC1lZDI1NTE5AAAAIKlHpAA/T87DCjPTHb2o5nLuxfPDhj00cZB2lBlNjbbb" >> ~/.ssh/known_hosts
rsync -avz -e ssh --exclude=/. --include=/.env --exclude=/resources/bot.yml --exclude=/tests --exclude=/storage/logs/ --exclude=/storage/app/ ./ "${SSH_USERNAME}@${SSH_HOST}:${SSH_DIRECTORY}"

echo "All files uploaded to the server."

ssh -l "$SSH_USERNAME" -T "$SSH_HOST" <<EOF
  cd $SSH_DIRECTORY
  php artisan storage:link
  php artisan migrate --force
EOF
