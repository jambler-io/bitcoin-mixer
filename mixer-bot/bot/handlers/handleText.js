const bot = require('../instance');
const cache = require('../cache');
const config = require('../../config');
const onMixing = require('./mixing_request/onMixing');
const handlePlainText = require('./handlePlainText');
const { Resources: { Pages, Keyboard, Messages, Commands, Variables }, MAX_DDOS_HITS, PARSE_MODE } = config;
const antiBrute = require('../../anti-brute-force').Instance;

const standardKeyboard = [
  [{ text: Keyboard.MIX_MY_COINS }, { text: Keyboard.FAQ }],
  [{ text: Keyboard.FEE }, { text: Keyboard.CONTACT_US }]
];

const START_MIXING_REGEXP = new RegExp(config.Resources.Keyboard.START_MIXING, 'i');
const MIX_MY_COINS_REGEXP = new RegExp(config.Resources.Keyboard.MIX_MY_COINS, 'i');
const FAQ_REGEXP = new RegExp(config.Resources.Keyboard.FAQ, 'i');
const FEE_REGEXP = new RegExp(config.Resources.Keyboard.FEE, 'i');
const CONTACT_US_REGEXP = new RegExp(config.Resources.Keyboard.CONTACT_US, 'i');
const CANCEL_MIXING_REGEXP = new RegExp(config.Resources.Keyboard.CANCEL_MIXING, 'i');

module.exports = async (chatId, text) => {
  switch (true) {
    /* BUTTON HANDLERS */
    case START_MIXING_REGEXP.test(text): {
      const hits = antiBrute.get(chatId).length;
      if (hits <= MAX_DDOS_HITS) {
        const user = await cache.getAsync(`USER:${chatId}`);
        if (user == null) {
          bot.sendMessage(chatId, Messages.MainMenuMessage(Variables), { parse_mode: PARSE_MODE, reply_markup: { resize_keyboard: true, keyboard: standardKeyboard } })
        } else {
          if (user.firstWallet != null) {
            await onMixing(chatId, user.firstWallet, user.secondeWallet);
          }
        }

      } else {
        bot.sendMessage(chatId, Messages.DDOS_PROTECTION_PAGE, { parse_mode: PARSE_MODE, reply_markup: { resize_keyboard: true, keyboard: standardKeyboard } })
      }
      break;
    }
    case MIX_MY_COINS_REGEXP.test(text): {
      const hits = antiBrute.get(chatId).length;
      if (hits <= MAX_DDOS_HITS) {
        await cache.setAsync(`USER:${chatId}`, {
          userId: chatId,
          firstWallet: null,
          secondeWallet: null
        })
        bot.sendMessage(chatId, Messages.ENTER_FIRST_BTC_WALLET, { parse_mode: PARSE_MODE });
      } else {
        bot.sendMessage(chatId, Messages.DDOS_PROTECTION_PAGE, { parse_mode: PARSE_MODE, reply_markup: { resize_keyboard: true, keyboard: standardKeyboard } })
      }
      break;
    }
    case CANCEL_MIXING_REGEXP.test(text): {
      await cache.delAsync(`USER:${chatId}`);
      bot.sendMessage(chatId, Messages.MainMenuMessage(Variables), { parse_mode: PARSE_MODE, reply_markup: { resize_keyboard: true, keyboard: standardKeyboard } })
      break;
    }
    case FAQ_REGEXP.test(text): {
      bot.sendMessage(chatId, Pages.FAQ_PAGE, { parse_mode: PARSE_MODE });
      break;
    }
    case FEE_REGEXP.test(text): {
      bot.sendMessage(chatId, Messages.FeeMenuMessage(Variables), { parse_mode: PARSE_MODE });
      break;
    }
    case CONTACT_US_REGEXP.test(text): {
      bot.sendMessage(chatId, Pages.CONTACT_US_PAGE, { parse_mode: PARSE_MODE });
      break;
    }
    /* CUSTOM BOT LOGINC */
    default: {
      await handlePlainText(chatId, text)
      break;
    }
  }
}

