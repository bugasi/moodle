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
 * Add a random question functionality for a popup in quiz editing page.
 *
 * @package   mod_quiz
 * @copyright 2014 The Open University
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

var CSS = {
    LOADING:       'div.questionbytagsloading',
    RANDOMQUESTIONBYTAGSFORM: 'div.randomquestionbytagsformforpopup',
    PAGEHIDDENINPUT: 'input#rform_qpage',
    RANDOMQUESTIONBYTAGSLINKS: '.menu [data-action="addarandomquestionbytags"]',
    SEARCHOPTIONS: 'select.searchoptions'
};

var PARAMS = {
    PAGE: 'addonpage',
    HEADER: 'header',
    FORM: 'form'
};

var POPUP = function() {
    POPUP.superclass.constructor.apply(this, arguments);
};

Y.extend(POPUP, Y.Base, {
    loadingDiv: '',
    dialogue: null,
    addonpage: 0,
    create_dialogue: function () {
        var config = {
            headerContent: '',
            bodyContent: Y.one(CSS.LOADING),
            draggable : true,
            modal : true,
            centered: true,
            width: null,
            visible: false,
            postmethod: 'form',
            footerContent: null,
            extraClasses: ['mod_quiz-randomquestionbytags']
        };
        this.dialogue = new M.core.dialogue(config);
        this.dialogue.bodyNode.delegate('click', this.link_clicked, 'a[href]', this);
        this.dialogue.hide();

        this.loadingDiv = this.dialogue.bodyNode.getHTML();

        Y.later(100, this, function () {this.load_content(window.location.search);});
    },
    initializer : function() {
        if (!Y.one(CSS.LOADING)) {
            return;
        }
        this.create_dialogue();
        Y.one('body').delegate('click', this.display_dialogue, CSS.RANDOMQUESTIONBYTAGSLINKS, this);
    },

    display_dialogue : function (e) {
        e.preventDefault();
        this.dialogue.set('headerContent', e.currentTarget.getData(PARAMS.HEADER));

        this.dialogue.show();

    },

    load_content: function (queryString) {
        this.dialogue.bodyNode.append(this.loadingDiv);
        Y.io(M.cfg.wwwroot + '/mod/quiz/randombytags.ajax.php' + queryString, {
            method: 'GET',
            on: {
                success: this.load_done
            },
            context: this
        });
    },

    load_done: function (transactionid, response) {
        var result = JSON.parse(response.responseText);
        if (!result.status || result.status !== 'OK') {
            // Because IIS is useless, Moodle can't send proper HTTP response
            // codes, so we have to detect failures manually.
            this.load_failed(transactionid, response);
            return;
        }
        this.dialogue.bodyNode.setHTML(result.contents);
        this.dialogue.bodyNode.one('form').delegate('change', this.options_changed, '.searchoptions', this);
        this.dialogue.bodyNode.one('form').delegate('change', this.options_changed, '#id_includesubcategories', this);
    },

    options_changed: function() {
        this.load_content('?' + Y.IO.stringify(this.dialogue.bodyNode.one('form')));
    }
});

M.mod_quiz = M.mod_quiz || {};
M.mod_quiz.randomquestionbytags = M.mod_quiz.randomquestionbytags || {};
M.mod_quiz.randomquestionbytags.init = function() {
    return new POPUP();
};
