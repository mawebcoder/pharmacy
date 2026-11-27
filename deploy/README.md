# Production server and deploys

Production runs natively on one Ubuntu 24.04 server (roadmap D19), with the same versions and settings as the local
Docker stack: PHP 8.5-FPM, MySQL 8.4 LTS, Redis 7.4, Nginx 1.28, Meilisearch 1.55.1, Supervisor (Horizon and the
scheduler). CI runs every check inside `docker-compose.yml`, so local, CI and the server behave the same.

| Branch | What runs |
|---|---|
| `dev`, feature branches | CI only: checks and the production build; on `dev` the build is kept as the release |
| `main` | Nothing runs on a push: it records what is live. The **Deploy to production** button ships the newest `dev` commit whose CI passed (no checks or build again; commits still running or red stay out), then merges that commit into `main` |

## Files

| File | Used how |
|---|---|
| `setup.sh` | Run by hand on the server: installs the pinned versions and copies the configs below. Safe to run again after changing a config. |
| `release.sh` | Copied and run by CI on every deploy; also `rollback`. |
| `nginx/hutana.conf` | → `/etc/nginx/sites-available/hutana.conf` (written once; certbot adds HTTPS to it) |
| `php/production.ini` | → `/etc/php/8.5/{fpm,cli}/conf.d/99-hutana-production.ini` |
| `../docker/php/conf.d/app.ini` | → `/etc/php/8.5/{fpm,cli}/conf.d/98-hutana.ini` (shared with local) |
| `php/hutana-fpm.conf` | → `/etc/php/8.5/fpm/pool.d/hutana.conf` |
| `../docker/mysql/conf.d/my.cnf` | → `/etc/mysql/mysql.conf.d/zz-hutana.cnf` (shared with local; larger buffer pool) |
| `redis/hutana.conf` | → `/etc/redis/hutana.conf` (password from the app's `.env`) |
| `supervisor/hutana.conf` | → `/etc/supervisor/conf.d/hutana.conf` |
| `meilisearch/meilisearch.service` | → `/etc/systemd/system/meilisearch.service` (key from the app's `.env`) |
| `.env.production.example` | → `/var/www/hutana/shared/.env` (first run only) |

Server layout: `/var/www/hutana/releases/<commit>/` (the last 5), `current` → the live release, `shared/.env`,
`shared/storage/`.

## First-time setup

1. **DNS:** point `hutana.ir` and `www.hutana.ir` (and `admin.hutana.ir` if `ADMIN_DOMAIN` is used) at the server.
2. **Copy the setup files** from your machine:
   ```bash
   rsync -aR deploy docker/php/conf.d/app.ini docker/mysql/conf.d/my.cnf root@SERVER:/root/hutana-setup/
   ```
3. **Make the deploy key** (on your machine; the private half goes to GitHub, never anywhere else):
   ```bash
   ssh-keygen -t ed25519 -N '' -C github-actions-deploy -f hutana-deploy
   ```
4. **Run the setup** on the server as root:
   ```bash
   cd /root/hutana-setup && DOMAINS="hutana.ir www.hutana.ir" CERTBOT_EMAIL=you@example.com \
     DEPLOY_PUBLIC_KEY="$(cat hutana-deploy.pub)" bash deploy/setup.sh
   ```
   (copy `hutana-deploy.pub` to the server first, or paste its content). `MYSQL_BUFFER_POOL` (default `128M`) and
   `MYSQL_MAX_CONNECTIONS` (default `40`) size MySQL for a 1 GB server; `SWAP_SIZE` (default `2G`) is the swap file
   made when the server has none. The script writes the database, Redis and Meilisearch passwords and `APP_KEY` into
   `/var/www/hutana/shared/.env` itself; it never prints them.
5. **Fill in the rest of** `/var/www/hutana/shared/.env`: `SUPERADMIN_PASSWORD` (required: the first deploy creates
   the super admin), `SUPERADMIN_EMAIL`, SMS, Tipax, Zarinpal (`ZARINPAL_SANDBOX=false` when the merchant is live)
   and mail.
6. **GitHub** (repository → Settings):
   - Environments → New environment `production` (no required reviewers: the Deploy to production button is the approval).
     Secrets in it:
     - `DEPLOY_HOST`: the server's address
     - `DEPLOY_USER`: `deploy`
     - `DEPLOY_SSH_KEY`: the content of the private key `hutana-deploy`
     - `DEPLOY_KNOWN_HOSTS`: the output of `ssh-keyscan -t ed25519 SERVER`
   - Variables: `APP_URL` = `https://hutana.ir` (health check), and `DEPLOY_PORT` only if SSH is not on 22.
7. **Deploy:** push `dev`, wait until its CI is green, then Actions → Deploy to production → Run workflow. It uploads
   the release `dev`'s CI built for its newest green commit, migrates, seeds and switches over; it rolls back by itself if `/up` does not answer.

## Everyday

- **Deploy:** after `dev`'s CI is green, Actions → Deploy to production → Run workflow. It ships the newest `dev`
  commit whose CI passed and merges that commit into `main`. If `main` has changes `dev` lacks, it refuses: merge `main` into `dev`, push,
  wait for green, then run it again. A release's folder and rollback name is the `dev` commit.
- **During a deploy** Horizon and the scheduler are stopped from the start until the switch, and Meilisearch while
  the migrations run: a 1 GB server cannot hold them next to the migrations. A failed deploy starts them again.
- **Roll back:** Actions → Rollback → Run workflow (empty = the previous release, or a kept commit). Migrations are
  not undone, so keep each migration compatible with the release before it.
- **Changed a server config** (Nginx, PHP, MySQL, Redis, Supervisor, Meilisearch): copy the files again (step 2) and
  re-run `setup.sh` with the same variables.
- **Logs:** `/var/www/hutana/shared/storage/logs/` (Laravel, Horizon, scheduler), `journalctl -u php8.5-fpm`,
  `/var/log/nginx/`.
- **Horizon:** `/horizon` in the browser (super admins with 2FA); `supervisorctl status hutana:*` on the server. Two
  workers, one process each (`config/horizon.php`): `sms`, `default`, `pricing`, `search` in that order, and the slow
  `media`, `catalog-import`, `price-import`.
