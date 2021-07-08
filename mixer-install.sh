#!/bin/bash

# Jambler.io partner mixer installation script v1.3.0
# Applied to a clean Debian 10 VPS.
#
#  The script:
#   - downloads and configures required packages
#   - installs and confingures Partner Mixer website based on a Jambler Partner Website template
#   - installs and configures Tor mirror for Mixer website
#   - installs and configures Telegram Bot hosting based on Jambler Partner Bot template
#   - starts all services


#-----------------------
#	HELPER FUNCTIONS
#-----------------------

	LOG_FILE="/var/log/mixer-install.log"

	J_API_KEY_PLACEHOLDER="__YOUR_JAMBLER_API_KEY__"
	TELEGRAM_TOKEN_PLACEHOLDER="__YOUR_TELEGRAM_TOKEN__"

	J_API_KEY=""
	TELEGRAM_TOKEN=""
	CLEARNET_SITE_SERVERNAME=""


	log() {

	# The logging function. It can be used directly, or the output of a command
	# can be piped into it.

		if [ -n "$1" ]; then
			echo $(date +"[%Y-%m-%d %H:%M:%S]") $1 >> $LOG_FILE
		else
			# This is needed to correctly capture multi-line output
			# redirected from background tasks.
			OIFS=$IFS
			IFS=$'\n'
			while read -r; do
				echo $(date +"[%Y-%m-%d %H:%M:%S]") $REPLY >> $LOG_FILE
			done
			IFS=$OIFS
		fi
	}

	printAndLog() {

	# A simple helper to write the same thing in both the log and the terminal.

		log "$@"
		echo "$@"
	}

	waitAnimation=(
		'[==    ]'
		'[ ==   ]'
		'[  ==  ]'
		'[   == ]'
		'[    ==]'
		'[   == ]'
		'[  ==  ]'
		'[ ==   ]'
		)

	animationDelay=0.2

	waitForTask() {

	# A wrapper for commands that executes them in the background and
	# redirects their output to the log file while showing a status message
	# and an animation in the terminal.

		task=$1
		waitMessage=$2

		log "----------------------------------------------------------------------"
		log "Executing '${task}' in the background."
		log "----------------------------------------------------------------------"

		# Start the task in background but take note of its process id
		${task} 2>&1 | log &
		PID=$!

		# While the task is running, keep showing the wait message w/ animation
		currFrame=0

		while [[ $PID -ne 0 ]] && [[ -d "/proc/${PID}" ]]; do
			echo -ne "\033[2K\r"
			echo -n $waitMessage "${waitAnimation[currFrame]}"

			((currFrame++))
			if [[ $currFrame -ge ${#waitAnimation[@]} ]]; then
				currFrame=0
			fi

			sleep $animationDelay
		done

		# Clear the animation frame before returning
		echo -ne "\033[2K\r"
			echo $waitMessage

		log "Done waiting for background task."
	}


	askForUserInfo() {

	# Make the user enter their domain name, the Jambler API key and
	# the Telegram API token

		log "Getting the domain name of the clearnet website from the user."
		echo -n "Enter your mixer domain name (example: mymixer.com): "
		read CLEARNET_SITE_SERVERNAME
		log "User entered: ${CLEARNET_SITE_SERVERNAME}"

		log "Getting the Jambler API key from the user."
		echo -n "Enter your Jambler API key: "
		read J_API_KEY
		log "User entered: ${J_API_KEY}"

		log "Getting the Telegram token from the user."
		echo -n "Enter your Telegram token: "
		read TELEGRAM_TOKEN
		log "User entered: ${TELEGRAM_TOKEN}"
	}

	verifyUserInfo() {

	# Display previously entered info for the user to double-check.

		log "Verifying info entered by the user."

		REPLY=""

		while [[ ${REPLY,,} != "y" ]] && [[ ${REPLY,,} != "n" ]]; do
			echo
			echo "Please double-check the information you've entered:"
			echo
			echo "	Domain name: ${CLEARNET_SITE_SERVERNAME}"
			echo "	Jambler API key: ${J_API_KEY}"
			echo "	Telegram token: ${TELEGRAM_TOKEN}"
			echo
			echo -n "Is everything correct? (Y to proceed, N to start over) "
			read REPLY
		done

		if [[ ${REPLY,,} == "y" ]]; then
			log "User confirmed all info is correct."
			return 0
		else
			log "User chose to go back and re-enter the info."
			return 1
		fi
	}

	obtainUserInfo() {

	# Obtain the domain name, the Jambler API key and Telegram API token
	# from the user.

		log "Obtaining required information from the user."
		askForUserInfo

		while ! verifyUserInfo; do
			askForUserInfo
		done

		echo
	}


#--------------------
#	SCRIPT START
#--------------------

# Make sure we are root

	if ! test `id -u` -eq 0 ; then
	   echo "Please run this script with root privileges."
	   exit 1
	fi

# Clear log entries from before (if any)

	> $LOG_FILE

# Greet the user

	clear
	log "Script $(basename $0) started."
	echo
	echo "Welcome to Jambler.io partner mixer installation script."
	echo

# Collect user info

	obtainUserInfo

# Announce the start of automated segment

	printAndLog "-----------------------------------------------------"
	printAndLog "Staring automated deployment."
	printAndLog "This may take anywhere between 2 minutes and an hour,"
	printAndLog "depending on disk speed and internet connection."
	printAndLog "-----------------------------------------------------"
	printAndLog " "

# Update package list

	waitForTask "apt update" "Updating the list of available packages..."

# Make sure wget is installed

	waitForTask "apt -y install wget" "Making sure wget is installed..."

# Install git

	waitForTask "apt -y install apt-transport-https dirmngr curl sudo" \
				"Installing prerequisites for git..."

	if test `which git|wc -l` -eq 0 ; then
		log "Installing git."
	    waitForTask "apt -y install git" "Installing git..."
	else
		printAndLog "Git is already installed."
	fi

# Clone the partner mixer repo

	GIT_ADDRESS="https://github.com/jambler-io/bitcoin-mixer.git"
	PKG_DIRECTORY="/tmp/distribution"
	waitForTask "git clone ${GIT_ADDRESS} ${PKG_DIRECTORY}" \
				"Cloning the partner mixer repository..."

# General site parmeters

	SITE_SOURCE="${PKG_DIRECTORY}/html"
	SITE_DIRECTORY="/var/www/html"
	TOR_SITE_SERVERNAME_FILE="/var/lib/tor/hidden-services/hostname"

# Install Tor

	printAndLog "Preparing to install Tor..."

	if test `cat /etc/apt/sources.list|grep torproject|wc -l` -eq 0 ; then
		log "Adding Tor Project repositories."
	    echo "deb https://deb.torproject.org/torproject.org stretch main" >> /etc/apt/sources.list
	    echo "deb-src https://deb.torproject.org/torproject.org stretch main" >> /etc/apt/sources.list
	fi

	if test `which tor|wc -l` -eq 0 ; then
		log "Adding PGP key..."
        gpg --keyserver keys.gnupg.net --recv A3C4F0F979CAA22CDBA8F512EE8CBC9E886DDD89 2>&1 | log
        gpg --export A3C4F0F979CAA22CDBA8F512EE8CBC9E886DDD89 | apt-key add - 2>&1 | log

        waitForTask "apt -y --allow-unauthenticated install tor" \
        			"Installing Tor..."
	else
		printAndLog "Tor is already installed."
    fi

# Make sure Tor was installed successfully before continuing

    if test `which tor|wc -l` -eq 0 ; then
    	echo
    	echo "ERROR: Tor not installed!"
    	echo "You may be able to find more details in ${LOG_FILE}"
        echo "You can try installing Tor maually and then running this script again."
        echo "Use the command: apt -y install tor"
        echo
        exit 1
    fi

# Create Tpr hidden services directory

    #mkdir -p /var/lib/tor/hidden-services
    mkdir -p /var/lib/tor/
	chown -R debian-tor:debian-tor /var/lib/tor

    if test `cat /etc/tor/torrc|grep "^HiddenServicePort 80 127.0.0.1:8080"|wc -l` -eq 0 ; then
        echo "HiddenServiceDir /var/lib/tor/hidden-services" >> /etc/tor/torrc
        echo "HiddenServicePort 80 127.0.0.1:8080" >> /etc/tor/torrc
    fi

# Start Tor

	printAndLog "Starting Tor..."
	service tor stop 2>&1 | log
	service tor start 2>&1 | log

# General variables for the clearnet website

	CLEARNET_SITE_KEY_FILE="${PKG_DIRECTORY}/ssl/site.key"     # Clearnet private key file
	CLEARNET_SITE_CRT_FILE="${PKG_DIRECTORY}/ssl/site.pem"     # Clearnet certificate file
	CLEARNET_SITE_SSL_DIRECTORY="/etc/nginx/ssl"    		   # Clearnet Nginx ssl directory
	TELEGRAM_BOT_SOURCE="${PKG_DIRECTORY}/mixer-bot"           #
	TELEGRAM_BOT_DIRECTORY="/var/mixer-bot"  				   # Telegram

# Install Nginx

	if test `cat /etc/apt/sources.list|grep nginx|wc -l` -eq 0 ; then
		printAndLog "Adding repositories for Nginx."
		echo "deb http://nginx.org/packages/debian/ stretch nginx" >> /etc/apt/sources.list
		echo "deb-src http://nginx.org/packages/debian/ stretch nginx" >> /etc/apt/sources.list
	fi

	if test `which nginx|wc -l` -eq 0 ; then

		# We've seen some hosters include Apache in their VPS templates out of the box.
		# If Apache is there, it will not let nginx get installed, so attempt to delete it.
		waitForTask "apt -y remove apache2" \
					"Checking and removing Apache..."

		waitForTask "apt -y --allow-unauthenticated install nginx" \
					"Installing Nginx..."
	else
		printAndLog "Nginx is already installed."
	fi

# Install php-fpm, fail2ban, supervisor, Java, etc.

	waitForTask "apt -y install php-fpm php-curl php-mbstring php-gd php-bcmath" \
				"Installing PHP..."

	waitForTask "apt -y install default-jre-headless" \
				"Installing Java..."

	waitForTask "apt -y install fail2ban supervisor" \
				"Installing additional packages..."

# Install Node.js

	if test `which nodejs | wc -l` -eq 0 ; then

		wget -qO /tmp/node.sh https://deb.nodesource.com/setup_lts.x 2>&1 | log
		chmod +x /tmp/node.sh 2>&1 | log

		waitForTask "/tmp/node.sh" "Preparing to install Node.js..."

		rm -f /tmp/node.sh 2>&1 | log

		waitForTask "apt install -y nodejs" \
	    			"Installing Node.js..."
	else
		printAndLog "Node.js is already installed."
	fi

# Install npm

	if test `which npm | wc -l` -eq 0 ; then

		waitForTask "apt install -y npm" \
	    			"Installing npm..."
	else
		printAndLog "npm is already installed."
	fi

# Get PHP version

PHP_VER=`php --version`
PHP_VER=${PHP_VER:4:3}

# Configure Nginx and PHP parameters

printAndLog "Configuring PHP and Nginx..."

	if test `cat /etc/nginx/nginx.conf|grep max_ranges|grep 0|wc -l` -eq 0 ; then
	    sed -i 's/http {/http {\n    max_ranges       0;\n/' /etc/nginx/nginx.conf 2>&1 | log
	fi
	if test `cat /etc/php/${PHP_VER}/fpm/php.ini|grep short_open_tag|grep -i on|wc -l` -eq 0 ; then
	    sed -i 's/^short_open_tag.*/short_open_tag = On/' /etc/php/${PHP_VER}/fpm/php.ini 2>&1 | log
	fi

# Create site directory and write Nginx configuration

	mkdir -p ${SITE_DIRECTORY} 2>&1 | log
	mkdir -p ${CLEARNET_SITE_SSL_DIRECTORY} 2>&1 | log
	cp -rf ${SITE_SOURCE}/* ${SITE_DIRECTORY}/ 2>&1 | log
	#cp -f ${CLEARNET_SITE_KEY_FILE} ${CLEARNET_SITE_SSL_DIRECTORY}/site.key 2>&1 | log
	#cp -f ${CLEARNET_SITE_CRT_FILE} ${CLEARNET_SITE_SSL_DIRECTORY}/site.pem 2>&1 | log
	chown -R www-data:www-data ${SITE_DIRECTORY} 2>&1 | log
	chown -R www-data:www-data ${CLEARNET_SITE_SSL_DIRECTORY} 2>&1 | log
	sed -i 's/user  nginx/user  www-data/' /etc/nginx/nginx.conf 2>&1 | log

# Insert user's API key into the website

	sed -i -e "s/${J_API_KEY_PLACEHOLDER}/${J_API_KEY}/g" ${SITE_DIRECTORY}/index.php 2>&1 | log
	
# Read the Tor address from hostname file

	if [[ -e $TOR_SITE_SERVERNAME_FILE ]]; then
		TOR_SITE_SERVERNAME=`cat ${TOR_SITE_SERVERNAME_FILE}`
	else
		printAndLog "Tor hostname file not found! Attempting to restart Tor."
		service tor stop 2>&1 | log
		service tor start 2>&1 | log
		sleep 5
	fi

	if [[ -e $TOR_SITE_SERVERNAME_FILE ]]; then
		TOR_SITE_SERVERNAME=`cat ${TOR_SITE_SERVERNAME_FILE}`
	fi
	
# Make sure the Tor address was obtained

	if [[ -z $TOR_SITE_SERVERNAME ]]; then
		log "Still could not obtain the Tor server name. Using a placeholder instead."
		TOR_SITE_SERVERNAME="UNDEFINED"
	fi

# Create cache directory for Nginx

	mkdir -p /opt/nginx/cache 2>&1 | log
	chown -R www-data:www-data /opt/nginx/cache 2>&1 | log

# Delete the default contents of sites-enabled/ which would overrule conf.d/

	rm -f /etc/nginx/sites-enabled/* 2>&1 | log

# Write the Tor site config file

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
        fastcgi_pass unix:/var/run/php/php${PHP_VER}-fpm.sock;
        fastcgi_index index.php;
        fastcgi_param SCRIPT_FILENAME \$document_root\$fastcgi_script_name;
        include fastcgi_params;
    }
}" > /etc/nginx/conf.d/tor_site.conf

# Write the clearnet site config file

	echo "server {
server_name ${CLEARNET_SITE_SERVERNAME};
##########################################
# Line below should be commented in case SSL certificate is applied
    listen 80;
#
##########################################
#    SSL block: lines below should be uncommented in case SSL cerificate is applied
##########################################
#
#    ssl on;
#    listen 443 ssl;
#    ssl_protocols TLSv1.1 TLSv1.2;
#    ssl_ciphers \"EECDH+ECDSA+AESGCM:EECDH+aRSA+AESGCM:EECDH+ECDSA+SHA384:EECDH+ECDSA+SHA256:EECDH+aRSA+SHA384:EECDH+aRSA+SHA256:EECDH+aRSA+RC4:EECDH:EDH+aRSA:DES-CBC3-SHA:!DES:!RC4:!aNULL:!eNULL:!LOW:!MD5:!EXP:!PSK:!SRP:!DSS:!CAMELLIA:!SEED\";
#    ssl_session_cache shared:SSL:10m;
#    ssl_certificate ${CLEARNET_SITE_SSL_DIRECTORY}/site.pem;
#    ssl_certificate_key ${CLEARNET_SITE_SSL_DIRECTORY}/site.key;
#    ssl_session_timeout  5m;
#    ssl_prefer_server_ciphers   on;
#
##########################################

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
        fastcgi_pass unix:/var/run/php/php${PHP_VER}-fpm.sock;
        fastcgi_index index.php;
        fastcgi_param SCRIPT_FILENAME \$document_root\$fastcgi_script_name;
        include fastcgi_params;
    }
}" > /etc/nginx/conf.d/www_site.conf

# Compile the Telegram bot

	mkdir -p ${TELEGRAM_BOT_DIRECTORY} 2>&1 | log
	cp -R ${TELEGRAM_BOT_SOURCE}/* ${TELEGRAM_BOT_DIRECTORY}/ 2>&1 | log
	cd ${TELEGRAM_BOT_DIRECTORY}/ 2>&1 | log
	waitForTask "npm i -g npm" "Compiling the Telegram bot..."

# Insert user's API key and Telegram token into the bot config file

	sed -i -e "s/${J_API_KEY_PLACEHOLDER}/${J_API_KEY}/g" ${TELEGRAM_BOT_DIRECTORY}/config.js 2>&1 | log
	sed -i -e "s/${TELEGRAM_TOKEN_PLACEHOLDER}/${TELEGRAM_TOKEN}/g" ${TELEGRAM_BOT_DIRECTORY}/config.js 2>&1 | log

# Start the bot

	cd ${TELEGRAM_BOT_DIRECTORY}/
	waitForTask "npm install" "Preparing the Telegram bot..."
	nohup npm start > /dev/null &
	disown -h

# Create autorun script for the bot
echo "[program:jambler_tbot]
directory = /var/mixer-bot
command = npm start --production
autostart=true
autorestart=true
stderr_logfile=/var/log/telegram-bot.log" > /etc/supervisor/conf.d/jambler_tbot.conf


# Enable and start all the services
	
	printAndLog "Enabling and starting all the services..."

	service supervisor start

	systemctl enable fail2ban 2>&1 | log
	systemctl enable php${PHP_VER}-fpm 2>&1 | log
	systemctl enable nginx 2>&1 | log

	log "Issuing the commands to restart fail2ban, PHP and Nginx."

	systemctl restart fail2ban 2>&1 | log
	systemctl restart php${PHP_VER}-fpm 2>&1 | log
	systemctl restart nginx 2>&1 | log


	echo
	printAndLog "All finished."
	echo

	if [[ "$TOR_SITE_SERVERNAME" == "UNDEFINED" ]]; then
		printAndLog "Something went wrong and we could not get the Tor hostname."
		printAndLog "This means your mixer will not be available through Tor."
		printAndLog "To fix this, you can try the following:"
		printAndLog "	Issue the commands: 'service tor stop', then 'service tor start'."
		printAndLog "	Next, check if this file exists: ${TOR_SITE_SERVERNAME_FILE}."
		printAndLog "	If it does, copy the hostname from it and paste it"
		printAndLog "	into /etc/nginx/conf.d/tor_site.conf as the server_name parameter."
	else
		printAndLog "Your mixer is available through Tor at ${TOR_SITE_SERVERNAME}"
		printAndLog "(you can view this address anytime in ${TOR_SITE_SERVERNAME_FILE})."
	fi
	
	echo
	printAndLog "You can view a detailed log of the installation in ${LOG_FILE}"
	echo
	printAndLog "If something is not working for you, let us know: https://jambler.io/contact-us.php"
	echo
