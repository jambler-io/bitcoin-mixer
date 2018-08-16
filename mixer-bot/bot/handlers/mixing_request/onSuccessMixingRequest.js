const bot = require('../../instance');
const QRCode = require('qr-image');
const { Resources: { Variables, Messages, Markups }, PARSE_MODE } = require('../../../config')
const sendToWalletMessage = require('./sendToWalletMesssage');
const stream = require('stream');
const onSuccessMixingRequest = async (userId,body) => {
  const messages = [];
  const { address, guarantee } = body;
  const messageOptions = { parse_mode: PARSE_MODE, disable_web_page_preview: false }
  await bot.sendMessage(userId, Messages.GUARANTEE_LETTER_HEADER, messageOptions);
  await bot.sendMessage(userId, guarantee, messageOptions);
  await bot.sendMessage(userId, Messages.PlaseSendToAdress(Variables), messageOptions);
  await bot.sendMessage(userId, `<pre>${address}</pre>`, messageOptions);
  await bot.sendMessage(userId, Messages.AdressWillAviable(Variables), messageOptions);
  const qr = QRCode.imageSync(address, { type: 'png' }) 
  await bot.sendPhoto(userId, qr, { ...messageOptions, reply_markup: {  resize_keyboard: true, keyboard: Markups.STANDARD }});
  messages.push(sendToWalletMessage(address));
  return messages;
}

module.exports = onSuccessMixingRequest;