const request = require('request-promise');
const { Resources: { Variables }, BACKEND_URL, COIN_ID, PARTNER_API_TOKEN } = require('./config');
const SATOSHI_IN_BTC = 100000000;

const setVariables = (json) => {
    for (let field in json) {
        Variables[field.toUpperCase()] = json[field];
    }
    Variables.MIN_AMOUNT = Variables.MIN_AMOUNT / SATOSHI_IN_BTC;
    Variables.MAX_AMOUNT = Variables.MAX_AMOUNT / SATOSHI_IN_BTC;
    Variables.MIXER_FIX_FEE = Variables.MIXER_FIX_FEE / SATOSHI_IN_BTC;
}
const bootstrap = async () => {
    try {
        const result = await request.get(`${BACKEND_URL}/partners/info/${COIN_ID}`, {
            headers: {
                'xkey': PARTNER_API_TOKEN
            }
        })
        const json = JSON.parse(result);
        setVariables(json)
    } catch (ex) {
        process.exit(1);
    }
}
module.exports = bootstrap;
