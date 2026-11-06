#!/bin/bash
# Runs once, when the MySQL data volume is first created.
# Tests use "<database>_testing"; parallel runs add "_test_<n>" suffixes, so the app user may create any of them.
set -euo pipefail

mysql -u root -p"${MYSQL_ROOT_PASSWORD}" <<SQL
CREATE DATABASE IF NOT EXISTS \`${MYSQL_DATABASE}_testing\` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
GRANT ALL PRIVILEGES ON \`${MYSQL_DATABASE}\_testing%\`.* TO '${MYSQL_USER}'@'%';
FLUSH PRIVILEGES;
SQL
