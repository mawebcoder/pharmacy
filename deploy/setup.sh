#!/usr/bin/env bash
# Sets up a fresh Ubuntu 24.04 server for Hutana (roadmap D19.2), with the same versions and settings as the local
# Docker stack: PHP 8.5-FPM, MySQL 8.4 LTS, Redis 7.4, Nginx 1.28, Meilisearch 1.55.1, Supervisor (Horizon + scheduler).
# Safe to run again: it installs what is missing and re-copies the config files (run it after changing one).
#
# Run as root from a copy of the repository's deploy/ and shared config files (deploy/README.md, step 2):
#   DOMAINS="hutana.ir www.hutana.ir" CERTBOT_EMAIL=you@example.com \
#   DEPLOY_PUBLIC_KEY="ssh-ed25519 AAAA… github-actions" bash deploy/setup.sh
#
# Generated secrets (MySQL, Redis, Meilisearch, APP_KEY) go straight into /var/www/hutana/shared/.env and are
# never printed.
set -euo pipefail

[[ "$(id -u)" -eq 0 ]] || { echo "Run as root." >&2; exit 1; }
. /etc/os-release
[[ "${VERSION_ID}" == "24.04" ]] || { echo "This script is for Ubuntu 24.04 (found ${PRETTY_NAME})." >&2; exit 1; }

REPO="$(cd "$(dirname "$0")/.." && pwd)"
DOMAINS="${DOMAINS:?Set DOMAINS, e.g. DOMAINS=\"hutana.ir www.hutana.ir\"}"
CERTBOT_EMAIL="${CERTBOT_EMAIL:-}"
DEPLOY_USER="${DEPLOY_USER:-deploy}"
DEPLOY_PUBLIC_KEY="${DEPLOY_PUBLIC_KEY:-}"
# Sized for the current server (2 GB RAM, 2 cores); raise both when the server grows (e.g. 2G and 100 on 8 GB).
MYSQL_BUFFER_POOL="${MYSQL_BUFFER_POOL:-384M}"
MYSQL_MAX_CONNECTIONS="${MYSQL_MAX_CONNECTIONS:-60}"
# Swap file made when the server has none, so a busy moment slows the server down instead of freezing it.
SWAP_SIZE="${SWAP_SIZE:-2G}"

PHP_VERSION=8.5
NGINX_VERSION=1.28
REDIS_VERSION=7.4
MEILISEARCH_VERSION=v1.55.1

APP_ROOT=/var/www/hutana
SHARED="${APP_ROOT}/shared"
ENV_FILE="${SHARED}/.env"

export DEBIAN_FRONTEND=noninteractive

log() {
    printf '\n==> %s\n' "$*"
}

secret() {
    openssl rand -hex 24
}

# Sets KEY=value in the shared .env only while it is still empty.
fill_env() {
    local key="$1" value="$2"

    if grep -qE "^${key}=$" "${ENV_FILE}"; then
        sed -i "s|^${key}=$|${key}=${value}|" "${ENV_FILE}"
    fi
}

env_value() {
    grep -E "^$1=" "${ENV_FILE}" | head -n 1 | cut -d= -f2- | tr -d '"'
}

log "Swap"
if [[ -z "$(swapon --show=NAME --noheadings)" ]]; then
    fallocate -l "${SWAP_SIZE}" /swapfile
    chmod 600 /swapfile
    mkswap /swapfile > /dev/null
    swapon /swapfile
    grep -q '^/swapfile ' /etc/fstab || echo '/swapfile none swap sw 0 0' >> /etc/fstab
fi
# Use swap only for memory that sits idle (idle workers), not for what MySQL and PHP are using.
printf 'vm.swappiness = 10\n' > /etc/sysctl.d/99-hutana.conf
sysctl -q -p /etc/sysctl.d/99-hutana.conf

log "Base packages"
apt-get update -q
apt-get install -yq ca-certificates curl gnupg unzip acl ufw software-properties-common supervisor \
    certbot python3-certbot-nginx jpegoptim optipng pngquant webp gifsicle

log "Package repositories (PHP, Nginx, Redis, MySQL)"
install -d -m 0755 /etc/apt/keyrings
add-apt-repository -y ppa:ondrej/php > /dev/null

curl -fsSL https://nginx.org/keys/nginx_signing.key | gpg --dearmor --yes -o /etc/apt/keyrings/nginx.gpg
echo "deb [signed-by=/etc/apt/keyrings/nginx.gpg] http://nginx.org/packages/ubuntu noble nginx" > /etc/apt/sources.list.d/nginx.list
printf 'Package: *\nPin: origin nginx.org\nPin-Priority: 900\n' > /etc/apt/preferences.d/99nginx

curl -fsSL https://packages.redis.io/gpg | gpg --dearmor --yes -o /etc/apt/keyrings/redis.gpg
echo "deb [signed-by=/etc/apt/keyrings/redis.gpg] https://packages.redis.io/deb noble main" > /etc/apt/sources.list.d/redis.list

# Oracle's MySQL signing key, from the Ubuntu keyserver: Oracle extends its expiry there, while the copy on
# repo.mysql.com (RPM-GPG-KEY-mysql-2023) has expired.
curl -fsSL "https://keyserver.ubuntu.com/pks/lookup?op=get&search=0xB7B3B788A8D3785C" | gpg --dearmor --yes -o /etc/apt/keyrings/mysql.gpg
echo "deb [signed-by=/etc/apt/keyrings/mysql.gpg] http://repo.mysql.com/apt/ubuntu noble mysql-8.4-lts" > /etc/apt/sources.list.d/mysql.list

apt-get update -q

log "PHP ${PHP_VERSION}"
apt-get install -yq "php${PHP_VERSION}-fpm" "php${PHP_VERSION}-cli" \
    "php${PHP_VERSION}"-{bcmath,curl,gd,imagick,intl,mbstring,mysql,redis,xml,zip}
install -m 0644 "${REPO}/docker/php/conf.d/app.ini" "/etc/php/${PHP_VERSION}/fpm/conf.d/98-hutana.ini"
install -m 0644 "${REPO}/docker/php/conf.d/app.ini" "/etc/php/${PHP_VERSION}/cli/conf.d/98-hutana.ini"
install -m 0644 "${REPO}/deploy/php/production.ini" "/etc/php/${PHP_VERSION}/fpm/conf.d/99-hutana-production.ini"
install -m 0644 "${REPO}/deploy/php/production.ini" "/etc/php/${PHP_VERSION}/cli/conf.d/99-hutana-production.ini"
install -m 0644 "${REPO}/deploy/php/hutana-fpm.conf" "/etc/php/${PHP_VERSION}/fpm/pool.d/hutana.conf"
rm -f "/etc/php/${PHP_VERSION}/fpm/pool.d/www.conf"
# Files PHP-FPM writes in storage stay writable by the deploy user (and the other way round).
install -d "/etc/systemd/system/php${PHP_VERSION}-fpm.service.d"
printf '[Service]\nUMask=0002\n' > "/etc/systemd/system/php${PHP_VERSION}-fpm.service.d/umask.conf"

log "Nginx ${NGINX_VERSION}"
apt-get install -yq "nginx=${NGINX_VERSION}.*"
install -d /etc/nginx/sites-available /etc/nginx/sites-enabled
grep -q 'sites-enabled' /etc/nginx/nginx.conf || sed -i '/include \/etc\/nginx\/conf.d\/\*.conf;/a\    include /etc/nginx/sites-enabled/*;' /etc/nginx/nginx.conf
sed -i 's/^user .*/user www-data;/' /etc/nginx/nginx.conf
rm -f /etc/nginx/conf.d/default.conf
# Certbot's HTTPS lines are kept: the site file is only written the first time.
if [[ ! -f /etc/nginx/sites-available/hutana.conf ]]; then
    sed "s/server_name .*;/server_name ${DOMAINS};/" "${REPO}/deploy/nginx/hutana.conf" > /etc/nginx/sites-available/hutana.conf
fi
ln -sfn /etc/nginx/sites-available/hutana.conf /etc/nginx/sites-enabled/hutana.conf
# Unknown hosts (the server IP) get no answer; needs the hutana.ir certificate, so a first run without it skips this and
# a run after certbot installs it.
if [[ -f /etc/letsencrypt/live/hutana.ir/fullchain.pem ]]; then
    install -m 644 "${REPO}/deploy/nginx/00-catch-all.conf" /etc/nginx/sites-available/00-catch-all.conf
    ln -sfn /etc/nginx/sites-available/00-catch-all.conf /etc/nginx/sites-enabled/00-catch-all.conf
    rm -f /etc/nginx/sites-enabled/default
fi

log "MySQL 8.4"
apt-get install -yq mysql-server
sed -e "s/^innodb_buffer_pool_size = .*/innodb_buffer_pool_size = ${MYSQL_BUFFER_POOL}/" \
    -e "s/^max_connections = .*/max_connections = ${MYSQL_MAX_CONNECTIONS}/" \
    "${REPO}/docker/mysql/conf.d/my.cnf" > /etc/mysql/mysql.conf.d/zz-hutana.cnf
# Sized for 2 GB: MySQL 8.4's defaults (performance schema, 4000 open tables, a 64 MB log buffer, temp tables in up
# to 1 GB of RAM) grew mysqld past 600 MB on the first 1 GB server while migrations ran. No binary log: there is no
# replica, and every DDL statement waited on it.
cat >> /etc/mysql/mysql.conf.d/zz-hutana.cnf <<'CNF'

[mysqld]
performance_schema = OFF
innodb_log_buffer_size = 16M
table_open_cache = 800
table_definition_cache = 800
temptable_max_ram = 64M
tmp_table_size = 32M
max_heap_table_size = 32M
skip-log-bin
CNF

log "Redis ${REDIS_VERSION}"
apt-get install -yq "redis-server=6:${REDIS_VERSION}.*" "redis-tools=6:${REDIS_VERSION}.*"
apt-mark hold redis-server redis-tools > /dev/null

log "Meilisearch ${MEILISEARCH_VERSION}"
if [[ "$(/usr/local/bin/meilisearch --version 2>/dev/null || true)" != *"${MEILISEARCH_VERSION#v}"* ]]; then
    curl -fsSL -o /usr/local/bin/meilisearch "https://github.com/meilisearch/meilisearch/releases/download/${MEILISEARCH_VERSION}/meilisearch-linux-amd64"
    chmod 755 /usr/local/bin/meilisearch
fi
id meilisearch > /dev/null 2>&1 || useradd --system --home /var/lib/meilisearch --shell /usr/sbin/nologin meilisearch
# Not the data folder: Meilisearch creates it itself and refuses to start on an empty one it did not create.
install -d -o meilisearch -g meilisearch /var/lib/meilisearch /var/lib/meilisearch/dumps
install -m 0644 "${REPO}/deploy/meilisearch/meilisearch.service" /etc/systemd/system/meilisearch.service

log "Deploy user and folders"
id "${DEPLOY_USER}" > /dev/null 2>&1 || useradd --create-home --shell /bin/bash "${DEPLOY_USER}"
usermod -aG www-data "${DEPLOY_USER}"
if [[ -n "${DEPLOY_PUBLIC_KEY}" ]]; then
    install -d -m 700 -o "${DEPLOY_USER}" -g "${DEPLOY_USER}" "/home/${DEPLOY_USER}/.ssh"
    touch "/home/${DEPLOY_USER}/.ssh/authorized_keys"
    grep -qxF "${DEPLOY_PUBLIC_KEY}" "/home/${DEPLOY_USER}/.ssh/authorized_keys" || echo "${DEPLOY_PUBLIC_KEY}" >> "/home/${DEPLOY_USER}/.ssh/authorized_keys"
    chown "${DEPLOY_USER}:${DEPLOY_USER}" "/home/${DEPLOY_USER}/.ssh/authorized_keys"
    chmod 600 "/home/${DEPLOY_USER}/.ssh/authorized_keys"
fi
# The release script may only reload PHP-FPM, start, stop or restart the app's Supervisor programs, and stop or start
# Meilisearch (no longer stopped by release.sh; the commands stay allowed).
cat > /etc/sudoers.d/hutana-deploy <<EOF
${DEPLOY_USER} ALL=(root) NOPASSWD: /usr/bin/systemctl reload php${PHP_VERSION}-fpm, /usr/bin/supervisorctl start hutana\:hutana-horizon, /usr/bin/supervisorctl restart hutana\:hutana-scheduler, /usr/bin/supervisorctl stop hutana\:hutana-horizon hutana\:hutana-scheduler, /usr/bin/supervisorctl start hutana\:hutana-horizon hutana\:hutana-scheduler, /usr/bin/systemctl stop meilisearch, /usr/bin/systemctl start meilisearch
EOF
chmod 440 /etc/sudoers.d/hutana-deploy
visudo -cf /etc/sudoers.d/hutana-deploy > /dev/null

install -d -m 2775 -o "${DEPLOY_USER}" -g www-data "${APP_ROOT}" "${APP_ROOT}/releases" "${APP_ROOT}/incoming" "${SHARED}"
for dir in app/public app/private framework/cache/data framework/sessions framework/views framework/testing logs; do
    install -d -m 2775 -o "${DEPLOY_USER}" -g www-data "${SHARED}/storage/${dir}"
done
# Whoever writes in storage (PHP-FPM, Horizon, the deploy user), the others can still change it.
setfacl -R -m "u:${DEPLOY_USER}:rwX,g:www-data:rwX" "${SHARED}/storage"
setfacl -R -d -m "u:${DEPLOY_USER}:rwX,g:www-data:rwX" "${SHARED}/storage"

log "Production .env and secrets"
if [[ ! -f "${ENV_FILE}" ]]; then
    install -m 0640 -o "${DEPLOY_USER}" -g www-data "${REPO}/deploy/.env.production.example" "${ENV_FILE}"
    sed -i "s|^APP_URL=.*|APP_URL=https://${DOMAINS%% *}|" "${ENV_FILE}"
fi
fill_env APP_KEY "base64:$(openssl rand -base64 32)"
fill_env DB_PASSWORD "$(secret)"
fill_env REDIS_PASSWORD "$(secret)"
fill_env MEILISEARCH_KEY "$(secret)"

# Redis and Meilisearch take their passwords from the .env, so the three always agree.
install -m 0640 -o redis -g redis "${REPO}/deploy/redis/hutana.conf" /etc/redis/hutana.conf
sed -i "s|^requirepass .*|requirepass $(env_value REDIS_PASSWORD)|" /etc/redis/hutana.conf
grep -q '^include /etc/redis/hutana.conf' /etc/redis/redis.conf || echo 'include /etc/redis/hutana.conf' >> /etc/redis/redis.conf
printf 'MEILI_MASTER_KEY=%s\n' "$(env_value MEILISEARCH_KEY)" > /etc/meilisearch.env
chmod 600 /etc/meilisearch.env

log "Database"
systemctl enable --now mysql > /dev/null
db_name="$(env_value DB_DATABASE)"
db_user="$(env_value DB_USERNAME)"
mysql --protocol=socket -uroot <<SQL
CREATE DATABASE IF NOT EXISTS \`${db_name}\` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE USER IF NOT EXISTS '${db_user}'@'localhost' IDENTIFIED BY '$(env_value DB_PASSWORD)';
ALTER USER '${db_user}'@'localhost' IDENTIFIED BY '$(env_value DB_PASSWORD)';
GRANT ALL PRIVILEGES ON \`${db_name}\`.* TO '${db_user}'@'localhost';
-- The app connects over TCP to 127.0.0.1 (DB_HOST), which MySQL does not count as "localhost" (and skip-name-resolve
-- in my.cnf stops it resolving one to the other).
CREATE USER IF NOT EXISTS '${db_user}'@'127.0.0.1' IDENTIFIED BY '$(env_value DB_PASSWORD)';
ALTER USER '${db_user}'@'127.0.0.1' IDENTIFIED BY '$(env_value DB_PASSWORD)';
GRANT ALL PRIVILEGES ON \`${db_name}\`.* TO '${db_user}'@'127.0.0.1';
FLUSH PRIVILEGES;
SQL

log "Supervisor (Horizon + scheduler)"
install -m 0644 "${REPO}/deploy/supervisor/hutana.conf" /etc/supervisor/conf.d/hutana.conf

log "Starting the services"
systemctl daemon-reload
systemctl enable --now redis-server meilisearch "php${PHP_VERSION}-fpm" nginx supervisor > /dev/null
systemctl restart mysql redis-server meilisearch "php${PHP_VERSION}-fpm"
nginx -t && systemctl reload nginx
supervisorctl reread > /dev/null && supervisorctl update > /dev/null

log "Firewall"
ufw allow OpenSSH > /dev/null
ufw allow 'Nginx Full' > /dev/null 2>&1 || { ufw allow 80/tcp > /dev/null; ufw allow 443/tcp > /dev/null; }
ufw --force enable > /dev/null

if [[ -n "${CERTBOT_EMAIL}" ]]; then
    log "HTTPS certificates (the domains must already point at this server)"
    domain_args=()
    for domain in ${DOMAINS}; do
        domain_args+=(-d "${domain}")
    done
    certbot --nginx --non-interactive --agree-tos --redirect -m "${CERTBOT_EMAIL}" "${domain_args[@]}"
fi

log "Done"
cat <<EOF
Versions: $(php -r 'echo "PHP ".PHP_VERSION;') · $(nginx -v 2>&1 | cut -d/ -f2 | sed 's/^/Nginx /') · $(mysqld --version | awk '{print "MySQL "$3}') · $(redis-server --version | awk '{print "Redis "$3}' | tr -d 'v=') · Meilisearch $(/usr/local/bin/meilisearch --version | awk '{print $2}')

Next (deploy/README.md):
  1. Fill in ${ENV_FILE}: SUPERADMIN_PASSWORD, SMS, Tipax, Zarinpal and mail settings.
  2. Push to main and approve the "production" deploy in GitHub Actions.
EOF
