#!/bin/bash -ex

export DEBIAN_FRONTEND=noninteractive

echo LANG=en_US.UTF-8 > /etc/default/locale

if [ -d /var/lib/locales/supported.d ]; then
    rm -f /var/lib/locales/supported.d/*
    echo en_US.UTF-8 UTF-8 > /var/lib/locales/supported.d/local
fi

export LANG=en_US.UTF-8

locale-gen

apt-get install -qy --force-yes vim git curl apt-transport-https

if [ -f /tmp/apt-sources.list ]; then
    cp /tmp/apt-sources.list /etc/apt/sources.list
fi

cp /tmp/apt-dotdeb.list /etc/apt/sources.list.d/dotdeb.list
curl http://www.dotdeb.org/dotdeb.gpg | apt-key add -

cp /tmp/apt-docker.list /etc/apt/sources.list.d/docker.list
curl http://get.docker.io/gpg | apt-key add -

echo "deb http://http.debian.net/debian wheezy-backports main" > /etc/apt/sources.list.d/backports.list

curl -sL https://deb.nodesource.com/setup | bash -

apt-get update -y

apt-get -qy install -t wheezy-backports \
    php5-cli \
    php5-mysqlnd \
    php5-curl \
    php5-redis \
    mysql-client \
    realpath \
    htop \
    acl \
    nodejs \
    lxc-docker \
    linux-image-3.14-0.bpo.2-rt-amd64 \
    daemontools \
    daemontools-run

# enable ACL on /

sed -e 's/errors=remount-ro/&,acl/' -i /etc/fstab
mount -o remount /

cp /tmp/docker-default /etc/default/docker

cp /tmp/php-php.ini /etc/php5/cli/php.ini

if [ -f /tmp/grub-default ]; then
    mv /tmp/grub-default /etc/default/grub
    update-grub
fi

# install composer

curl -sS https://getcomposer.org/installer | php
mv composer.phar /usr/local/bin/composer

# docker specific stuff
docker pull stackbrew/ubuntu:saucy

# misc directories
mkdir -p /var/www/stage1 /var/log/stage1