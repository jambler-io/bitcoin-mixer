## Jambler bot

* DESCRIPTION
* DESCRIPTION
* DESCRIPTION

## Requirements

* Node 18 or later
* Git
* pm2 package (optional)

## Setup

Clone the repo and install the dependencies.

```bash
git clone REPOSITORY
cd REPOSTIORY
```

```bash
npm install
```

Optionaly
````bash
npm install pm2 -g
````
## Registration

* Register on Telegram
* Create bot on bothather
* Setup your bot token to environment variables
`````bash
TELEGRAM_API_TOKEN=YOUR_TOKEN
`````
* Setup your Jambler.io partner api token to environment variables
`````bash
PARTNER_API_TOKEN=YOUR_TOKEN
`````
## Run your bot

`````bash
npm start
`````

or if you want to use the process manager

`````bash
pm2 start index.js --name="YOUR_BOT"
`````

## Configuration

You can change the text resources used by this bot. 
To do this, you just need to go to the config.js file and edit the required resource.
