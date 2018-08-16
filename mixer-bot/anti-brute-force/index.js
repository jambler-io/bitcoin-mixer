function AntiBrute(gcTimeout = 1000 * 60 * 5) {
    const self = this;
    this.cache = {};
    this.gcTimeout = gcTimeout
    let interval = setInterval(function () {
        self.gccollect();
    }, this.gcTimeout);
    this.stopInterval = function () {
        if (interval != null) {
            clearInterval(interval);
            interval = null;
        }
    }
    this.startInterval = function () { 
    }

    const protectionTime = 1000 * 60;
    this.get = function (key) {
        const result = this.cache[key];
        return result == null ? [] : this.clear(result);
    }
    this.save = function (key, value) {
        this.cache[key] = value;
    }
    this.setHit = function (key) {
        const value = this.get(key);
        value.push(Date.now() + protectionTime);
        this.save(key, value);
    }
    this.clear = function (value) {
        const now = Date.now();
        return value.filter((obj) => obj >= now);
    }
    this.gccollect = function () {
        const keys = Object.keys(this.cache);
        for (let key of keys) {
            const byKey = this.cache[key];
            const result = this.clear(byKey);
            if (result.length == 0) {
                delete this.cache[key];
            } else {
                this.save(key, result);
            }
        }
    }
}
const Instance = new AntiBrute();
module.exports = {
    Instance,
    AntiBrute
}