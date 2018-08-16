const bootstrap = require('./bootstrap');
async function main(){
  await bootstrap();
  /* ===== Require bot after loading resource ===== */
  const bot = require('./bot');
}

main();