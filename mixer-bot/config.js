/* ===== TOKENS ===== */
const TELEGRAM_BOT_API_TOKEN = process.env.TELEGRAM_API_TOKEN == null ? '__you_telegram_api_key__' : process.env.TELEGRAM_API_TOKEN;
const PARTNER_API_TOKEN = process.env.PARTNER_API_TOKEN == null ? '__you_jambler_api_key__' : process.env.PARTNER_API_TOKEN;

/* ===== TECHNICAL ===== */
const BACKEND_URL = 'https://api.jambler.io';
const CACHE_TTL = 10 * 60;
const PARSE_MODE = 'html';
const COIN_ID = 'btc';
const MAX_DDOS_HITS = 2;

/* ===== PAGE RESOURCES ===== */
const FAQ_PAGE = `FAQ: jambler.io/faq.php`;
const FEE_PAGE = `<strong>FEE</strong>`;
const CONTACT_US_PAGE = `Conact Us: jambler.io/contact-us.php`
const DDOS_PROTECTION_PAGE = `DDOS_PROTECTION`;

/* ===== COMMAND TEXT RESOURCES ===== */
const START_COMMAND = `<strong>Start</strong>`
const HELP_COMMAND = `<strong>HELP</strong>`
const UNHANDLED_COMMAND = `<strong>Unhandled</strong>`

/* ===== BUTTONS TEXT ===== */
const MIX_MY_COINS = 'Mix My Coins';
const FAQ = 'FAQ';
const FEE = 'Fee';
const CONTACT_US = 'Contact Us';
const CANCEL_MIXING = 'Cancel Mixing';
const START_MIXING = 'Start Mixing'

/* ===== KEYBOARDS ===== */
const STANDARD_KEYBOARD = [
  [{ text: MIX_MY_COINS}, { text: FAQ}],
  [{ text: FEE }, { text: CONTACT_US }]
];
const MIXING_KEYBOARD = [[{ text: START_MIXING}, { text: CANCEL_MIXING }]];

/* ===== COMBINED MESSAGES ===== */
const MainMenuMessage = (Variables) => `${Variables.MIXER_NAME}\nBitcoin mixer 2.0. Get clean coins from cryptocurrency stock exchanges\nFee ${Variables.MIXER_FEE_PCT}% + ${Variables.MIXER_FIX_FEE} BTC\nMixing time ${Variables.WITHDRAW_MAX_TIMEOUT} hours.\nUnique anonymization algorithm.`;
const FeeMenuMessage = (Variables) => `Maximum fee ${Variables.MIXER_FEE_PCT}%, + ${Variables.MIXER_FIX_FEE} BTC.\nMinimum amount  ${Variables.MIN_AMOUNT} BTC, maximum amount ${Variables.MAX_AMOUNT} BTC.\nGenerated address is valid for  ${Variables.ORDER_LIFETIME} hours (${Variables.ORDER_LIFETIME / 24} days)\nMaximum mixing time ${Variables.WITHDRAW_MAX_TIMEOUT} hours\nnMixed coins will be sent to your forward addresses in some random parts\nat different time intervals. But not later than ${Variables.WITHDRAW_MAX_TIMEOUT} since a receipt of the first confirmation on the incoming transaction.`
const PlaseSendToAdress = (Variables) => `Please send your bitcoin (min ${Variables.MIN_AMOUNT} BTC, max ${Variables.MAX_AMOUNT} BTC) to:`
const AdressWillAviable = (Variables) => `\nAddress is valid for ${Variables.ORDER_LIFETIME} hours (${Variables.ORDER_LIFETIME / 24} days). Maximum mixing time ${Variables.WITHDRAW_MAX_TIMEOUT} hours. Maximum fee ${Variables.MIXER_FEE_PCT}%, + ${Variables.MIXER_FIX_FEE} BTC»`

/* ===== SIMPLE MESSAGES ===== */
const UNHANDLED_MIXING_ERROR = 'UNHANDLED_ERROR';
const ENTER_FIRST_BTC_WALLET = 'Please enter your BTC forward address below:';
const ENTER_SECOND_BTC_WALLET = 'Please enter your second BTC address or Start Mixing «Start Mixing»»';
const NOT_VALID_ADRESS = 'Your forward address is not valid BTC address. Please try another one.';
const GUARANTEE_LETTER_HEADER = '<strong>Your garantee letter</strong>';

module.exports = {
  TELEGRAM_BOT_API_TOKEN,
  PARTNER_API_TOKEN,
  BACKEND_URL,
  CACHE_TTL,
  PARSE_MODE,
  COIN_ID,
  MAX_DDOS_HITS,
  Keyboards: {
    STANDARD_KEYBOARD,
    MIXING_KEYBOARD
  },
  Resources: {
    Keyboard: {
      START_MIXING,
      CANCEL_MIXING,
      MIX_MY_COINS,
      FAQ,
      FEE,
      CONTACT_US
    },
    Markups: {
      STANDARD: STANDARD_KEYBOARD,
      MIXING: MIXING_KEYBOARD,
    },
    Pages: {
      FAQ_PAGE,
      FEE_PAGE,
      CONTACT_US_PAGE,
      
    },
    Commands: {
      START_COMMAND,
      HELP_COMMAND,
      UNHANDLED_COMMAND
    },
    Messages: {
      UNHANDLED_MIXING_ERROR,
      ENTER_FIRST_BTC_WALLET,
      ENTER_SECOND_BTC_WALLET,
      NOT_VALID_ADRESS,
      GUARANTEE_LETTER_HEADER,
      PlaseSendToAdress,
      AdressWillAviable,
      MainMenuMessage,
      FeeMenuMessage,
      DDOS_PROTECTION_PAGE
    },
    Variables: {
      MIN_AMOUNT: 1,
      MAX_AMOUNT: 2,
      ORDER_LIFETIME: 48,
      WITHDRAW_MAX_TIMEOUT: 48,
      MIXER_FEE_PCT: 10,
      MIXER_FIX_FEE: 10
    }
  }
}
