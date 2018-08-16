const bot = require('../instance');
const config = require('../../config');
const cache = require('../cache');

const { Resources: { Keyboard, Commands, Variables, Messages, Markups }, PARSE_MODE } = config;

const START_COMMAND = /\/start/i
const HELP_COMMAND = /\/help/i
const CLEAR_COMMAND = /\/clear/i

/**
 * 
 * @param {Number} chatId 
 * @param {String} command 
 */
module.exports = async (chatId, command) => {
    switch (true) {
      case START_COMMAND.test(command) : {
        const message = Messages.MainMenuMessage(Variables);
        bot.sendMessage(chatId, Messages.MainMenuMessage(Variables), { parse_mode: PARSE_MODE, reply_markup: {  resize_keyboard: true, keyboard: Markups.STANDARD  } });
        break;
      }
      case HELP_COMMAND.test(command) : {
        bot.sendMessage(chatId, Commands.HELP_COMMAND, { parse_mode: PARSE_MODE });
        break;
      }
      case CLEAR_COMMAND.test(command) : {
        const userInCache = await cache.getAsync(`USER:${chatId}`);
        if(userInCache){
            await cache.setAsync(`USER:${chatId}`, {
                userId: chatId,
                firstWallet: null,
                secondeWallet: null
              })
        }
        break;
      }
      default : {
        bot.sendMessage(chatId, Commands.UNHANDLED_COMMAND, { parse_mode: PARSE_MODE });
        break;
      }
    }
}