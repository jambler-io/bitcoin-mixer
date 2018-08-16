const Bot = require('node-telegram-bot-api');
const config = require('../config');

const bot = new Bot(config.TELEGRAM_BOT_API_TOKEN, { polling: true });

module.exports = bot;