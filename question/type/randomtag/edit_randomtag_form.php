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
 * @package     qtype_randomtag
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

    /**
     * Add any question-type specific form fields.
     *
     * @param object $mform the form being built.
     */
    protected function definition_inner($mform) {
        global $DB;
        $question = $this->question;
        $opts = $DB->get_record('qtype_randomtag_options', array("questionid" => $question->id));
        $qtags = $DB->get_records('qtype_randomtag_tags', array("randomtagid" => $opts->id));
        $defaultintags = array_map(function($it) {
                return $it->tagid;
        }, array_filter($qtags, function($v, $k) {
                return $v->included;
        }, ARRAY_FILTER_USE_BOTH));
        $defaultouttags = array_map(function($it) {
                return $it->tagid;
        }, array_filter($qtags, function($v, $k) {
                return !$v->included;
        }, ARRAY_FILTER_USE_BOTH));
        $intags = optional_param_array('intags', ($defaultintags ? $defaultintags : []), PARAM_INT);
        $outtags = optional_param_array('outtags', ($defaultouttags ? $defaultouttags : []), PARAM_INT);
        $tags = $this->get_tags_used();
        $mform->addElement('select', 'intags', get_string('includetags', 'quiz'), $tags, ['class' => 'select searchoptions']);
        $mform->getElement('intags')->setMultiple(true);
        $mform->getElement('intags')->setSelected($intags);

        $mform->addElement('select', 'includetype', get_string('includetagstype', 'quiz'), array(
            "1" => get_string("includetagstypeany", "quiz"), "2" => get_string("includetagstypeall", "quiz")));
        $includetype = $opts->includetype;
        $mform->getElement('includetype')->setSelected($includetype);

        $mform->addElement('select', 'outtags', get_string('excludetags', 'quiz'), $tags, ['class' => 'select searchoptions']);
        $mform->getElement('outtags')->setMultiple(true);
        $mform->getElement('outtags')->setSelected($outtags);
    }

    /**
     * Returns a list of used question tags in the selected category and subcategories (if "includesubcategories" is checked)
     *
     * This method checks if tag instances for questions in the selected category (and if necessary subcategories) exist
     * and returns a list of said tags.
     *
     * @return array an associative array of tag id's and names
     */
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

    /**
     * Return the id of the selected category and the ids of the subcategories (if "includesubcategories" is checked)
     *
     * @return array list of category id and subcategory ids
     */
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

    /**
     * Load in existing data as form defaults. Usually new entry defaults are stored directly in
     * form definition (new entry form); this function is used to load in data where values
     * already exist and data is being edited (edit entry form).
     *
     * note: $slashed param removed
     *
     * @param stdClass $question object of default values
     */
    public function set_data($question) {
        $question->questiontext = array('text' => $question->questiontext);
        // We don't want the complex stuff in the base class to run.
        moodleform::set_data($question);
    }

    /**
     * Returns an empty array because validation is not relevant for this question type.
     *
     * @param array $data array of ("fieldname"=>value) of submitted data
     * @param array $files array of uploaded files "element_name"=>tmp_file_path
     * @return array empty array.
     */
    public function validation($data, $files) {
        // Validation of category is not relevant for this question type.

        return array();
    }

    /**
     * Returns the submitted and modified data to save the randomtag question correctly
     *
     * @return object submitted data; NULL if not valid or not submitted or cancelled
     */
    public function get_data() {
        if (!$this->is_cancelled() and $this->is_submitted() and $this->is_validated()) {
            $data = parent::get_data();
            $def = $this->category->id . ',' . $this->category->contextid;
            $cat = optional_param('cat', $def, PARAM_SEQUENCE);
            $data->intags = optional_param_array('intags', [], PARAM_INT);
            $data->outtags = optional_param_array('outtags', [], PARAM_INT);
            if (object_property_exists($data, 'includesubcategories')) {
                $data->questiontext['text'] = 1;
            } else {
                $data->questiontext['text'] = 0;
            }
            $data->category = $cat;

            return $data;
        } else {
            return null;
        }
    }

    /**
     * Returns the qtype
     *
     * @return string The question type name, should be the same as the name() method
     *      in the question type class.
     */
    public function qtype() {
        return 'randomtag';
    }
}