/**
 * Created by andreas on 05.04.16.
 */
M.qtype_randomtag = M.qtype_randomtag || {};

M.qtype_randomtag.init = function(Y, returnurl, cmid, id) {
    var select = Y.one('select#id_cat'),
        checkbox = Y.one('input#id_includesubcategories'),
        onChange = function() {
            var catString = select.get('value');
            window.location.search = encodeURI("returnurl=" + returnurl + "&cmid=" + cmid + "&id=" + id + "&cat=" + catString + "&includesubcategories=" + (checkbox.get('checked') ? 1 : 0));
        };
    Y.on('change', onChange, select);
    Y.on('change', onChange, checkbox);
};