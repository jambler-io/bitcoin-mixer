const bot = require('./instance');
const config = require('../config');
const { onCommand, onText } = require('./handlers');

bot.on('text',async (msg) => {
  if(msg.entities && msg.entities.length && msg.entities[0].type == 'bot_command' ){
    const commandTextStart = msg.entities[0].offset;
    const commandTextEnd = msg.entities[0].length + commandTextStart;
    const command = msg.text.slice(commandTextStart, commandTextEnd);
    await onCommand(msg.from.id, command);
  } else if(msg.text != null) {
    await onText(msg.from.id, msg.text);
  }
})
module.exports = bot;