const { Resources: { Variables } } = require('../../../config')
const sendToMessage = (adress) => {
  return `Please send your bitcoins (min ${Variables.MIN_AMOUNT} BTC, max ${Variables.MAX_AMOUNT} BTC) at: bitcoin:${adress}<a href="http://chart.googleapis.com/chart?chs=125x125&cht=qr&chl=${adress}">.</a>\nThe address will be available ${Variables.ORDER_LIFETIME} hours (${Variables.ORDER_LIFETIME / 24} days) Cleansing time up to ${Variables.WITHDRAW_MAX_TIMEOUT} hours. Commission fee is up to ${Variables.MIXER_FEE_PCT}%, + ${Variables.MIXER_FIX_FEE} BTC`
}
module.exports = sendToMessage;