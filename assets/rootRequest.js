import basicRequest from "basic_axios/src/basic.js";
// select language.
var languageId = null;
/***
 * On surcharge la configuration.
 */
const requestAxios = {
    ...basicRequest,
    debug: true,
    languageId: languageId,
    // on ne laisse la valeur par defaut, pour permttre au domaine local de pouvoir se connecter.
};
export default requestAxios;
