# syntax=docker/dockerfile:1.7

FROM nginx:1.28-alpine

RUN rm -f /etc/nginx/conf.d/default.conf

COPY conf.d/app.conf /etc/nginx/conf.d/app.conf

EXPOSE 80

HEALTHCHECK --interval=10s --timeout=3s --start-period=5s --retries=3 \
    CMD wget -q -O /dev/null http://127.0.0.1/up || exit 1
