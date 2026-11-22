# Imagem de produção: PHP 8.3 + Apache servindo a aplicação em /Contabil
FROM php:8.3-apache

RUN docker-php-ext-install pdo pdo_mysql \
    && a2enmod rewrite headers \
    && { \
        echo 'display_errors=Off'; \
        echo 'log_errors=On'; \
        echo 'error_log=/dev/stderr'; \
        echo 'date.timezone=America/Sao_Paulo'; \
        echo 'session.cookie_httponly=1'; \
    } > /usr/local/etc/php/conf.d/app.ini

# Os links da aplicação usam o prefixo /Contabil, então ela fica nessa subpasta
COPY . /var/www/html/Contabil/
RUN rm -rf /var/www/html/Contabil/.git /var/www/html/Contabil/database \
           /var/www/html/Contabil/tests /var/www/html/Contabil/node_modules \
    && printf '<?php header("Location: /Contabil/"); exit;\n' > /var/www/html/index.php \
    && chown -R www-data:www-data /var/www/html

# Plataformas como Render/Railway informam a porta pela variável PORT
ENV PORT=80
CMD sed -i "s/^Listen .*/Listen ${PORT}/" /etc/apache2/ports.conf \
    && sed -i "s/<VirtualHost \*:[0-9]*>/<VirtualHost *:${PORT}>/" /etc/apache2/sites-available/000-default.conf \
    && apache2-foreground
