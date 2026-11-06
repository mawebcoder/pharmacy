# syntax=docker/dockerfile:1.7

FROM mysql:8.4

COPY conf.d/my.cnf /etc/mysql/conf.d/zz-app.cnf

EXPOSE 3306

HEALTHCHECK --interval=10s --timeout=5s --start-period=30s --retries=5 \
    CMD mysqladmin ping -h 127.0.0.1 -u root -p"$MYSQL_ROOT_PASSWORD" --silent || exit 1
