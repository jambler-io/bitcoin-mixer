const cache = require('../../cache');
const MixingRequest = require('./mixingRequest');
const antiBrute = require('../../../anti-brute-force').Instance;

const onMixing = async (userId, firstAddr, secondAddr) => {
  antiBrute.setHit(userId);
  await MixingRequest(userId,firstAddr,secondAddr);
  await cache.delAsync(`USER:${userId}`);
}
module.exports = onMixing;