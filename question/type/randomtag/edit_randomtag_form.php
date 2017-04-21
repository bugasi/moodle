<?php
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
 * Defines the editing form for the randomtag question type.
 *
 * @package     qtype
 * @subpackage  randomtag
 * @copyright   2017 Andreas Figge (BuGaSi GmbH)
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */


defined('MOODLE_INTERNAL') || die();


/**
 * randomtag editing form definition
 *
 * @copyright   2017 Andreas Figge (BuGaSi GmbH)
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class qtype_randomtag_edit_form extends question_edit_form {
    protected $currentcat;

    protected function get_category_string($contexts, $catid) {
        $clist = [];
        foreach ($contexts as $context) {
            $clist[] = $context->id;
        }
        $clist = join($clist, ', ');
        $categories = get_categories_for_contexts($clist);
        if (array_key_exists($catid, $categories)) {
            $cat = $categories[$catid];
            return  $cat->id . ',' . $cat->contextid;
        }
        return null;
    }

    public function __construct($submiturl, $question, $category, $contexts, $formeditable) {
        global $DB;

        $cat = optional_param('cat', '', PARAM_INT);
        if ($cat) {
            $category = $DB->get_record('question_categories', ['id' => $cat]);
        }
        $question->category = $category->id;

        parent::__construct($submiturl, $question, $category, $contexts, $formeditable);
    }

    /**
     * Build the form definition.
     *
     * This adds all the form files that the default question type supports.
     * If your question type does not support all these fields, then you can
     * override this method and remove the ones you don't want with $mform->removeElement().
     */
    protected function definition() {
        global $PAGE;

        $returnurl = optional_param('returnurl', '', PARAM_LOCALURL);
        $cmid = optional_param('cmid', 0, PARAM_INT);
        $id = optional_param('id', 0, PARAM_INT);

        $PAGE->requires->js_init_call('M.qtype_randomtag.init', array('returnurl' => $returnurl, 'cmid' => $cmid , 'id' => $id));
        $mform = $this->_form;

        // Standard fields at the start of the form.
        $mform->addElement('header', 'generalheader', get_string("general", 'form'));

        $contexts = $this->contexts->having_cap('moodle/question:useall');

        $def = $this->category->id . ',' . $this->category->contextid;
        $cat = optional_param('cat', $def, PARAM_SEQUENCE);
        $mform->addElement('questioncategory', 'cat', get_string('category', 'question'),
            array('contexts' => $this->contexts->having_cap('moodle/question:useall')));

        $mform->setDefault('cat', $cat);

        $includesubcategories = optional_param('includesubcategories', $this->question->questiontext, PARAM_BOOL);
        $mform->addElement('checkbox', 'includesubcategories',
            get_string('includingsubcategories', 'qtype_randomtag'));
        $mform->setDefault('includesubcategories', $includesubcategories);

        $mform->disable_form_change_checker();
        $mform->addElement('hidden', 'qtype');

        $mform->setType('qtype', PARAM_ALPHA);

        $this->add_hidden_fields();

        $this->definition_inner($mform);

        $buttonarray = array();
        $buttonarray[] = $mform->createElement('submit', 'submitbutton', get_string('savechanges'));
        $buttonarray[] = $mform->createElement('cancel');
        $mform->addGroup($buttonarray, 'buttonar', '', array(' '), false);
        $mform->closeHeaderBefore('buttonar');
    }

    protected function definition_inner($mform) {
        global $DB;
        $question = $this->question;
        $opts = $DB->get_record('question_randomtag', array("questionid" => $question->id));

        $tags = $this->get_tags_used();
        $mform->addElement('select', 'intags', get_string('includetags', 'quiz'), $tags, ['class' => 'select searchoptions']);
        $mform->getElement('intags')->setMultiple(true);
        $intags = $opts->intags;
        $mform->getElement('intags')->setSelected(explode(',', ($intags ? $intags : '')));

        $mform->addElement('select', 'nottags', get_string('excludetags', 'quiz'), $tags, ['class' => 'select searchoptions']);
        $mform->getElement('nottags')->setMultiple(true);
        $nottags = $opts->outtags;
        $mform->getElement('nottags')->setSelected(explode(',', ($nottags ? $nottags : '')));
    }

    private function get_tags_used() {
        global $DB;
        $categories = $this->get_categories();

        list($catidtest, $params) = $DB->get_in_or_equal($categories, SQL_PARAMS_NAMED, 'cat');
        $sql = "SELECT id as value, name as display FROM {tag} WHERE id IN
                (
                 SELECT DISTINCT tagi.tagid FROM {tag_instance} tagi, {question}
                         WHERE itemtype='question' AND {question}.id=tagi.itemid AND category $catidtest
                )
                ORDER BY name";
        return $DB->get_records_sql_menu($sql, $params);
    }

    private function get_categories() {

        $categoryparam = $this->category->id;

        if ($categoryparam) {
            list($cat) = explode(',', $categoryparam);
            $includesubcategories = optional_param('includesubcategories', $this->question->questiontext, PARAM_BOOL);
            if ($includesubcategories) {
                $cats = question_categorylist($cat);
            } else {
                $cats = array($cat);
            }
            return $cats;
        }

    }

    public function set_data($question) {
        $question->questiontext = array('text' => $question->questiontext);
        // We don't want the complex stuff in the base class to run.
        moodleform::set_data($question);
    }

    public function validation($fromform, $files) {
        // Validation of category is not relevant for this question type.

        return array();
    }

    public function get_data() {
        if (!$this->is_cancelled() and $this->is_submitted() and $this->is_validated()) {
            $data = parent::get_data();
            $def = $this->category->id . ',' . $this->category->contextid;
            $cat = optional_param('cat', $def, PARAM_SEQUENCE);
            $intags = optional_param_array('intags', [], PARAM_INT);
            $nottags = optional_param_array('nottags', [], PARAM_INT);
            $includesubcategories = optional_param('includesubcategories', $this->question->questiontext, PARAM_BOOL);
            $data->category = $cat;
            $data->intags = $intags;
            $data->nottags = $nottags;
            $data->questiontext['text'] = $includesubcategories;
            return $data;
        } else {
            return null;
        }
    }


    public function qtype() {
        return 'randomtag';
    }
}