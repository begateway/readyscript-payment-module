FROM trafex/alpine-nginx-php7:latest

USER root

RUN apk --no-cache add php7-simplexml php7-zip postfix awall su-exec

ARG readyscript_version

ADD src/readyscript$readyscript_version-shop-middle.zip /readyscript-shop-middle.zip
ADD scripts/start.sh /start.sh
COPY config/supervisord.conf /etc/supervisor/conf.d/supervisord.conf

VOLUME ["/var/www/html"]

CMD ["/bin/sh", "/start.sh"]
