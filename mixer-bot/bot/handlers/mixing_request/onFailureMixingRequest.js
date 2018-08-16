const onUnhandledMixingRequest = require('./onUnhandledMixingRequest');
const isJson = require('is-json');
const onFailureMixingRequest = (userId, ex) => {
  if (isJson(ex.error)) {
    const errrObject = JSON.parse(ex.error);
    const errorText = errrObject.error_message;
    return [errorText]
  }
  return onUnhandledMixingRequest();
}
module.exports = onFailureMixingRequest;