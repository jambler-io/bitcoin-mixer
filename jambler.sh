#!/bin/bash

# Jambler.io partner mixer installation script. v 1.0
# Include Jambler partner template (tor, clearnet). Partner telegram bot. Services
# you need clear Debian 9 VPS


if ! test `id -u` -eq 0 ; then
   echo "Please run this script with root privileges"
   exit 1
fi

# You clearnet sitename
CLEARNET_SITE_SERVERNAME=""
    echo -n "Enter you mixer domain name. Exanple: mymixer.com: "
    read CLEARNET_SITE_SERVERNAME

# install git
apt-get update
apt-get -y install apt-transport-https dirmngr curl sudo
if test `which git|wc -l` -eq 0 ; then
    apt-get -y install git
fi

    GIT_ADDRESS="https://github.com/jambler-io/bitcoin-mixer.git"
    PKG_DIRECTORY="/tmp/distribution"
    git clone ${GIT_ADDRESS} ${PKG_DIRECTORY}

# General site parmeters
SITE_SOURCE="${PKG_DIRECTORY}/html"
SITE_DIRECTORY="/var/www/html"
TOR_SITE_SERVERNAME_FILE="/var/lib/tor/hidden-services/hostname"

    # install TOR
    if test `cat /etc/apt/sources.list|grep torproject|wc -l` -eq 0 ; then
        echo "deb https://deb.torproject.org/torproject.org stretch main" >> /etc/apt/sources.list
        echo "deb-src https://deb.torproject.org/torproject.org stretch main" >> /etc/apt/sources.list
    fi
    if test `which tor|wc -l` -eq 0 ; then
        gpg --keyserver keys.gnupg.net --recv A3C4F0F979CAA22CDBA8F512EE8CBC9E886DDD89
        gpg --export A3C4F0F979CAA22CDBA8F512EE8CBC9E886DDD89 | apt-key add -
        apt-get update
        apt-get -y --allow-unauthenticated install tor
    fi
    if test `which tor|wc -l` -eq 0 ; then
        echo "Tor not installed, please install manualy and run script again"
        echo "apt-get -y install tor"
        exit 1
    fi


    #create TOR hidden services directory and copy hostname and private_key files
    mkdir -p /var/lib/tor/hidden-services

    if test `cat /etc/tor/torrc|grep "^HiddenServicePort 80 127.0.0.1:8080"|wc -l` -eq 0 ; then
        echo "HiddenServiceDir /var/lib/tor/hidden-services" >> /etc/tor/torrc
        echo "HiddenServicePort 80 127.0.0.1:8080" >> /etc/tor/torrc
    fi
    # set valid owner and modes for tor files and make symlink
    chown -R debian-tor:debian-tor /var/lib/tor/hidden-services
    chmod -R 600 /var/lib/tor/hidden-services
    chmod 700 /var/lib/tor/hidden-services
    chmod g+s /var/lib/tor/hidden-services
#    ln -s /var/lib/tor/hidden/services ${TOR_HS_DIRECTORY}

# enable and start tor
systemctl enable tor
systemctl restart tor

# General variables for clearweb site
CLEARNET_SITE_KEY_FILE="${PKG_DIRECTORY}/ssl/site.key"                     # clearweb private key file
CLEARNET_SITE_CRT_FILE="${PKG_DIRECTORY}/ssl/site.pem"                     # clearweb certificate file
CLEARNET_SITE_SSL_DIRECTORY="/etc/nginx/ssl"    # clearweb nginx ssl directory
TELEGRAM_BOT_SOURCE="${PKG_DIRECTORY}/mixer-bot"
TELEGRAM_BOT_DIRECTORY="/var/mixer-bot"  # telegram


# install nginx
if test `cat /etc/apt/sources.list|grep nginx|wc -l` -eq 0 ; then
   echo "deb http://nginx.org/packages/debian/ stretch nginx" >> /etc/apt/sources.list
   echo "deb-src http://nginx.org/packages/debian/ stretch nginx" >> /etc/apt/sources.list
fi
if test `which nginx|wc -l` -eq 0 ; then
    apt-get update
    apt-get -y --allow-unauthenticated install nginx
fi

# install php-fpm fail2ban socat supervisor Java
apt-get -y install php-fpm fail2ban supervisor openjdk-8-jdk-headless openjdk-8-jre-headless php-curl mc php-mbstring php-gd php-bcmath


# install node.js
if test `which nodejs|wc -l` -eq 0 ; then
    curl -sL https://deb.nodesource.com/setup_8.x | bash -
    apt-get install -y nodejs
fi

# Configure nginx and php parameters
if test `cat /etc/nginx/nginx.conf|grep max_ranges|grep 0|wc -l` -eq 0 ; then
    sed -i 's/http {/http {\n    max_ranges       0;\n/' /etc/nginx/nginx.conf
fi
if test `cat /etc/php/7.0/fpm/php.ini|grep short_open_tag|grep -i on|wc -l` -eq 0 ; then
    sed -i 's/^short_open_tag.*/short_open_tag = On/' /etc/php/7.0/fpm/php.ini
fi

# create site directory and write nginx configuration
mkdir -p ${SITE_DIRECTORY}
mkdir -p ${CLEARNET_SITE_SSL_DIRECTORY}
cp -rf ${SITE_SOURCE}/* ${SITE_DIRECTORY}/
#cp -f ${CLEARNET_SITE_KEY_FILE} ${CLEARNET_SITE_SSL_DIRECTORY}/site.key
#cp -f ${CLEARNET_SITE_CRT_FILE} ${CLEARNET_SITE_SSL_DIRECTORY}/site.pem
chown -R www-data:www-data ${SITE_DIRECTORY}
chown -R www-data:www-data ${CLEARNET_SITE_SSL_DIRECTORY}
sed -i 's/user  nginx/user  www-data/' /etc/nginx/nginx.conf
TOR_SITE_SERVERNAME=`cat ${TOR_SITE_SERVERNAME_FILE}`


# Create cache directory for nginx
mkdir -p /opt/nginx/cache
chown -R www-data:www-data /opt/nginx/cache

echo "server {
    listen 8080;
    server_name ${TOR_SITE_SERVERNAME};
    access_log  /var/log/nginx/tor-access-site.log;
    error_log   /var/log/nginx/tor-error-site.log;

    root  ${SITE_DIRECTORY};
    index index.php index.html;

    location / {
        try_files \$uri \$uri/ /index.php?\$query_string;
    }

    location ~* \\.php\$ {
        try_files \$uri = 404;
        fastcgi_split_path_info ^(.+\\.php)(/.+)\$;
        fastcgi_pass unix:/var/run/php/php7.0-fpm.sock;
        fastcgi_index index.php;
        fastcgi_param SCRIPT_FILENAME \$document_root\$fastcgi_script_name;
        include fastcgi_params;
    }
}" > /etc/nginx/conf.d/tor_site.conf

echo "server {
    listen 80;
#    listen 443 ssl;
    server_name ${CLEARNET_SITE_SERVERNAME};

#    ssl on;
#    ssl_protocols TLSv1.1 TLSv1.2;
#    ssl_ciphers \"EECDH+ECDSA+AESGCM:EECDH+aRSA+AESGCM:EECDH+ECDSA+SHA384:EECDH+ECDSA+SHA256:EECDH+aRSA+SHA384:EECDH+aRSA+SHA256:EECDH+aRSA+RC4:EECDH:EDH+aRSA:DES-CBC3-SHA:!DES:!RC4:!aNULL:!eNULL:!LOW:!MD5:!EXP:!PSK:!SRP:!DSS:!CAMELLIA:!SEED\";
#    ssl_session_cache shared:SSL:10m;
#    ssl_certificate ${CLEARNET_SITE_SSL_DIRECTORY}/site.pem;
#    ssl_certificate_key ${CLEARNET_SITE_SSL_DIRECTORY}/site.key;
#    ssl_session_timeout  5m;
#    ssl_prefer_server_ciphers   on;
    access_log  /var/log/nginx/www-access-site.log;
    error_log   /var/log/nginx/www-error-site.log;

    root  ${SITE_DIRECTORY};
    index index.php index.html;

    location / {
        try_files \$uri \$uri/ /index.php?\$query_string;
    }

    location ~* \\.php\$ {
        try_files \$uri = 404;
        fastcgi_split_path_info ^(.+\\.php)(/.+)\$;
        fastcgi_pass unix:/var/run/php/php7.0-fpm.sock;
        fastcgi_index index.php;
        fastcgi_param SCRIPT_FILENAME \$document_root\$fastcgi_script_name;
        include fastcgi_params;
    }
}" > /etc/nginx/conf.d/www_site.conf



# compile telegram bot
mkdir -p ${TELEGRAM_BOT_DIRECTORY}
cp -R ${TELEGRAM_BOT_SOURCE}/* ${TELEGRAM_BOT_DIRECTORY}/
cd ${TELEGRAM_BOT_DIRECTORY}/
npm i -g npm
npm install


# create autorun script
echo "[program:jambler_tbot]
command = /usr/bin/node index.js
directory = ${TELEGRAM_BOT_DIRECTORY}
user = root" > /etc/supervisor/conf.d/jambler_tbot.conf



# enable all services

systemctl enable fail2ban
systemctl enable php7.0-fpm
systemctl enable nginx
systemctl enable supervisor

# start services
systemctl restart fail2ban
systemctl restart php7.0-fpm
systemctl restart nginx
systemctl restart supervisor


