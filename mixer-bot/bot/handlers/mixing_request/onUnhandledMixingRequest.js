const { Resources: { Messages: { UNHANDLED_MIXING_ERROR } } } = require('../../../config')
const onUnhandledMixingRequest = (userId) => {
  return [UNHANDLED_MIXING_ERROR];
}
module.exports = onUnhandledMixingRequest;