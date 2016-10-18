/* jshint node:true */
module.exports = {
    target: {
        options: {
            mainFile: "wpr-loader.php",
            potHeaders: {
                poedit: true,
                "x-poedit-keywordslist": true
            },
            processPot: function (pot) {
                "use strict";
                pot.headers["report-msgid-bugs-to"] = "https://github.com/bemailr/wp-requirements/issues";
                pot.headers["language-team"] = "The Bemailr Team <translations@bemailr.com>";
                pot.headers["last-translator"] = pot.headers["language-team"];
                delete pot.headers["x-generator"];
                return pot;
            },
            type: "wp-plugin",
            updateTimestamp: true,
            updatePoFiles: false
        }
    }
};
