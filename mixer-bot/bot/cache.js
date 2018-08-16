const NodeCache = require("node-cache");
const config = require('../config');
const cache = new NodeCache({
  stdTTL: config.CACHE_TTL
});

/**
 * set ttl for object by key
 * @param {string} key 
 * @param {number} ttl 
 */
module.exports.ttlAsync = (key, ttl) => {
  return new Promise((resolve, reject) => {
    cache.ttl(key, ttl, (err, data) => {
      if (err) {
        reject(err)
      } else {
        resolve(data);
      }
    })
  })
}
/**
 * set object by key with standard ttl
 * @param {string} key 
 * @param {object} value 
 */
module.exports.setAsync = (key, value) => {
  return new Promise((resolve, reject) => {
    cache.set(key, value, (err, data) => {
      if (err) {
        reject(err);
      } else {
        resolve(data);
      }
    })
  })
}
/**
 * get object by key
 * @param {string} key 
 */
module.exports.getAsync = (key) => {
  return new Promise((resolve, reject) => {
    cache.get(key, (err, data) => {
      if (err) {
        reject(err);
      } else {
        resolve(data);
      }
    })
  })
}
/**
 * del object by key
 * @param {string} key 
 */
module.exports.delAsync = (key) => {
  return new Promise((resolve, reject) => {
    cache.del(key, (err, data) => {
      if (err) {
        reject(err);
      } else {
        resolve(data);
      }
    })
  })
}
module.exports.cache = cache;