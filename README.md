## Jambler.io Partner Mixer Template
> A partner mixer template for easy deployment on a Linux server.

![](https://jambler.io/images/logo.png)

This repo has everything you will need to set up your own partner mixing service powered by Jambler. This includes the website template, the telegram bot and the rapid deployment script.

#### Telegram Bot

We have sources of a telegram bot capable of accepting cleansing orders from your customers and working with Jambler API to process them. Like the website, it needs to be running on your server to be available for interaction.

#### Automated Deployment Script

To facilitate things even more, we also have a Bash script for automated deployment on a Debian 9 server. This script installs all necessary software, deploys the partner website and the Telegram bot, and also sets up access to your website through Tor.

You can find some directions for its usage below or [on our website](https://jambler.io/howto.php#full-deployment).

### Deployment Using the Script

#### Prerequisites

If you are going to use our script for deployment, you will need:

- a virtual private (VPS) or dedicated (VDS) server with Debian 9;
- a partner account at [Jambler.io](https://jambler.io);
- the Jambler API key from your partner account;
- the Telegram API token to use with the bot (obtained from @BotFather after creating your bot).

Optional:

- a domain name to go with your mixing website, assuming you want it to be available on clearnet and not only on Tor.
- SSL certificates to use with your website (more on this below).

#### Installation

1. Using an SSH client, connect to your server as root (or connect as a regular user and execute the ```su``` command).

2. Next, download the installation script by executing ```wget https://jambler.io/src/mixer-install.sh```

3. Allow the script to be executed by running ```chmod 755 mixer-install.sh```

4. Finally, start the script by typing ```./mixer-install.sh```

5. The script will ask for the information listed above and then download and install all necessary software and resources (Nginx, Tor, Node.JS, PHP and dependencies).

#### Results

Here's what you should end up with when the script has finished working:

- All software required to run the website and the bot should be installed (Git, Nginx, Tor, Node.js, PHP and their dependencies).
- The website should be accessible at the domain name you specified (assuming you've already set up the DNS to point to your server) and at your server's IP address.
- You will be shown a Tor address at which your site will be available. You can also view it later in ```/var/lib/tor/hidden-services/hostname```
- The Telegram bot should be running and responding to commands.

#### SSL Certificates

The use of SSL certificates is optional, although it is generally a good idea to use them. Of course, you will need to have obtained them from a certification authority.

The script can install the certificates for you, but you need to do some preparations first:

- After downloading the script, open it for editing, then find and uncomment the block related to SSL certificates (roughly between lines 400 and 450 of the script).
- Comment the line ```listen 80;``` within that block to prevent access without SSL.
- Put your site.key and site.pem files into ```/tmp/distribution/ssl``` on your server.

### Contacts and Support

For any questions or concerns, please contact us one of the following ways:

- support@jambler.io
- [Telegram Group](https://t.me/jambler)
- [Bitcoin Talk](https://bitcointalk.org/index.php?topic=4667343)
- [Reddit](https://www.reddit.com/user/Jambler_io/)
