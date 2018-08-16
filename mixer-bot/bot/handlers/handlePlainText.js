const { PARSE_MODE, MAX_DDOS_HITS, Resources: { Keyboard, Messages, Markups } } = require('../../config');
const bot = require('../instance');
const cache = require('../cache');
const onMixing = require('./mixing_request/onMixing');
const validateWallet = require('./utils/validateWallet');
const antiBrute = require('../../anti-brute-force').Instance;

const handlePlainText = async (userId, text) => {
  const hits = antiBrute.get(userId).length;
  if(hits <= MAX_DDOS_HITS){
    let user = await cache.getAsync(`USER:${userId}`);
  /* IF USER PRESS MIX MY COINS */
  if (user != null) {
    if (user.firstWallet == null) {
      if (validateWallet(text)) {
        user.firstWallet = text;
        await cache.setAsync(`USER:${userId}`, user);
        await bot.sendMessage(userId, Messages.ENTER_SECOND_BTC_WALLET, { parse_mode: PARSE_MODE, reply_markup: {  resize_keyboard: true, keyboard: Markups.MIXING} })
      } else {
        await bot.sendMessage(userId, Messages.NOT_VALID_ADRESS, { parse_mode: PARSE_MODE, reply_markup: {  resize_keyboard: true, keyboard: Markups.STANDARD} });
      }
    } else if (user.secondeWallet == null) {
      if (validateWallet(text)) {
        user.secondeWallet = text;
        await onMixing(userId, user.firstWallet, user.secondeWallet);
      } else {
        await bot.sendMessage(userId, Messages.NOT_VALID_ADRESS, { parse_mode: PARSE_MODE, reply_markup: {  resize_keyboard: true, keyboard: Markups.MIXING} });
      }
    }
  }
  } else {
    await bot.sendMessage(userId, Messages.DDOS_PROTECTION_PAGE, { parse_mode: PARSE_MODE, reply_markup: {  resize_keyboard: true, keyboard: Markups.STANDARD} });
  }
  
}
module.exports = handlePlainText;