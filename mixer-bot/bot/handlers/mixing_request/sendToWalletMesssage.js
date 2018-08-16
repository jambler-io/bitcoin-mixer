const { Resources: { Variables } } = require('../../../config')
const sendToMessage = (adress) => {
  return `«Пожалуйста отправьте ваши bitcoin (мин ${Variables.MIN_AMOUNT} BTC, max ${Variables.MAX_AMOUNT} BTC) на: bitcoin:${adress}<a href="http://chart.googleapis.com/chart?chs=125x125&cht=qr&chl=${adress}">.</a>\nАдрес будет доступен ${Variables.ORDER_LIFETIME} часов (${Variables.ORDER_LIFETIME / 24} дней) Время очистки до ${Variables.WITHDRAW_MAX_TIMEOUT} часов Комиссия до ${Variables.MIXER_FEE_PCT}%, + ${Variables.MIXER_FIX_FEE} BTC»`
}
module.exports = sendToMessage;