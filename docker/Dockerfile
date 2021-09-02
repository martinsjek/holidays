FROM nginx:stable
#General bringing up to date and prerequisites
RUN apt-get update \
&& apt-get upgrade -y \
&& apt-get install -y wget lsb-release zip \
#Add up to date php PPA, and retrieve latest stable php8 with required extensions that are not bundled with it
&& wget -O /etc/apt/trusted.gpg.d/php.gpg https://packages.sury.org/php/apt.gpg \
&& echo "deb https://packages.sury.org/php/ $(lsb_release -sc) main" | tee /etc/apt/sources.list.d/php.list \
&& apt-get update && apt-get install -y \
php8.0-fpm \
php8.0-xdebug \
php8.0-xml \
php8.0-mysql \
#Add www-data group to nginx user so that php-fpm has access to nginx pool
&& usermod -aG www-data nginx \
# Installing composer and its prerequisites globally
&& php -r "copy('https://getcomposer.org/installer', 'composer-setup.php');" && php composer-setup.php --2 \
&& php -r "unlink('composer-setup.php');" && mv composer.phar /usr/local/bin/composer \
&& apt-get install -y build-essential
COPY php.ini /etc/php/8.0/fpm/php.ini
COPY startup_script.sh /root/
RUN chmod a+x /root/startup_script.sh

CMD /root/startup_script.sh
