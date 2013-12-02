#!/bin/bash

if [ ! -z "$DEBUG" ]; then
    set -x
fi

/usr/sbin/sshd

declare -a services=(mysql php5-fpm nginx)
declare -a tries=(index.php app.php)

for file in ${tries[@]}; do
    if [ -f /var/www/web/$file ]; then
        sed -e "s/%frontcontroller%/$file/" -i /etc/nginx/sites-enabled/default
        break;
    fi
done;

for service in ${services[@]}; do
    /etc/init.d/$service start 2>&1 > /dev/null
done;

touch /var/www/app/logs/prod.log

tail -f /var/log/nginx/*.log /var/www/app/logs/*.log /var/log/php5-fpm.log