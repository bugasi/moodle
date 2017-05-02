// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.
/**
 * This file contains the Javascript code needed for the edit randomtag form
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