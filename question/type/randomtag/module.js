/**
 * Created by andreas on 05.04.16.
 */
M.qtype_randomtag = M.qtype_randomtag || {};

M.qtype_randomtag.init = function(Y, returnurl, cmid, id) {
    var select = Y.one('select#id_cat'),
        selectIncludeType = Y.one('select#id_includetype'),
        checkbox = Y.one('input#id_includesubcategories'),
        codeArrayParam = function(array, name) {
            var re = '';
            array.forEach(function(it) {
                re += "&" + name + "[]=" + it;
            });
            return re;
        },
        onChange = function() {
            var catString = select.get('value'),
                includetype = selectIncludeType.get('value'),
                intags = Y.all('select#id_intags option:checked').get('value'),
                outtags = Y.all('select#id_outtags option:checked').get('value'),
                intagString = codeArrayParam(intags, 'intags'),
                outtagString = codeArrayParam(outtags, 'outtags');
            window.location.search = encodeURI("returnurl=" + returnurl + "&cmid=" + cmid + "&id=" + id + "&cat=" + catString + "&includesubcategories=" + (checkbox.get('checked') ? 1 : 0) + "&includetype=" + includetype + intagString + outtagString);
        };
    Y.on('change', onChange, select);
    Y.on('change', onChange, checkbox);
};