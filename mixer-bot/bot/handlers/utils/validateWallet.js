const validator = require('wallet-address-validator');
/**
 * 
 * @param {String} addr Crypto Currency Wallet
 * @returns {Boolean} 
 */
module.exports = (addr) => validator.validate(addr, 'BTC')
