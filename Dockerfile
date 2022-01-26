FROM ubuntu:20.04
RUN apt-get upgrade
RUN apt-get update
ARG DEBIAN_FRONTEND=noninteractive
RUN apt install php7.4 php-gd php-intl php-mbstring php-memcached php-mysql php-zip gettext aspell apache2 yaz libyaz4-dev php-dev php-pear wdiff pngcheck unzip dos2unix -y
RUN apt install curl -y
RUN curl https://jpgraph.net/download/download.php?p=49 --output jpgraph-4.3.4.tar.gz && tar -xzf jpgraph-4.3.4.tar.gz && mv jpgraph-4.3.4 /var/www/ && ln -s /var/www/jpgraph-4.3.4/ /var/www/jpgraph
RUN pecl install yaz && echo "extension=yaz.so" >> /etc/php/7.4/apache2/php.ini
RUN curl http://aoineko.free.fr/wikihiero.zip --output wikihiero.zip && unzip -d /var/www/wikihiero wikihiero.zip
COPY ./SETUP/wikihiero-0.2.13.patch /var/www
RUN dos2unix /var/www/wikihiero/wikihiero.php && patch /var/www/wikihiero/wikihiero.php -p1 < /var/www/wikihiero-0.2.13.patch && unix2dos /var/www/wikihiero/wikihiero.php
RUN php -r "copy('https://getcomposer.org/installer', 'composer-setup.php');"
RUN php -r "if (hash_file('sha384', 'composer-setup.php') === '756890a4488ce9024fc62c56153228907f1545c228516cbf63f885e036d37e9a59d27d63f46af1d4d07ee0f76181c7d3') { echo 'Installer verified'; } else { echo 'Installer corrupt'; unlink('composer-setup.php'); } echo PHP_EOL;"
RUN php composer-setup.php --install-dir=/bin --filename=composer
RUN php -r "unlink('composer-setup.php');"
COPY ./SETUP/apache2.conf.example /etc/apache2/sites-available/dproofreaders.conf
RUN a2ensite dproofreaders
RUN curl https://download.phpbb.com/pub/release/3.3/3.3.4/phpBB-3.3.4.zip --output phpBB-3.3.4.zip && unzip -d /var/www/html phpBB-3.3.4.zip
RUN chgrp -R www-data /var/www/html/phpBB3/
COPY ./SETUP/phpbb3-functions.php.patch /var/www
RUN patch /var/www/html/phpBB3/includes/functions.php -p0 < /var/www/phpbb3-functions.php.patch
CMD /var/www/html/c/SETUP/docker-entrypoint.sh
