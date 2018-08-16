const request = require('request-promise');
const config = require('../../../config');
const antiDdos = require('../../../anti-brute-force').Instance;
const onSuccessMixingRequest = require('./onSuccessMixingRequest');
const onFailureMixingRequest = require('./onFailureMixingRequest');

const MixingRequest = async (userId, addrOne, addrTwo = '') => {
  antiDdos.setHit(userId);
  try {
    const result = await request.post(`${config.BACKEND_URL}/partners/orders/${config.COIN_ID}`, {
      headers: {
        'Content-Type': 'application/json',
        'xkey': config.PARTNER_API_TOKEN
      },
      body: JSON.stringify({
        forward_addr: addrOne,
        forward_addr2: addrTwo
      })
    })
    const json = JSON.parse(result);
    return onSuccessMixingRequest(userId, json);
  } catch (ex) {
    return onFailureMixingRequest(userId, ex);
  }
}

module.exports = MixingRequest;
