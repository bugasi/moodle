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
 * Defines the Moodle form used to add randomtag questions to the quiz.
 *
 * @package     mod_quiz
 * @copyright   2017 Andreas Figge (BuGaSi GmbH)
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

require_once($CFG->libdir.'/formslib.php');

/**
 * The add randomtag questions form.
 *
 * @copyright   2017 Andreas Figge (BuGaSi GmbH)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class quiz_add_random_by_tags_form extends moodleform {

    /**
     * Form definition.
     */
    protected function definition() {
        global $CFG, $DB;
        $tags = $this->get_tags_used();

        $mform =& $this->_form;
        $mform->setDisableShortforms();

        $contexts = $this->_customdata['contexts'];
        $usedtags = isset($this->_customdata['intags']) ? $this->_customdata['intags'] : [];
        $outtags = isset($this->_customdata['outtags']) ? $this->_customdata['outtags'] : [];
        $includetype = isset($this->_customdata['includetype']) ? $this->_customdata['includetype'] : 1;
        $usablecontexts = $contexts->having_cap('moodle/question:useall');

        $mform->addElement('header', 'categoryheader', '');
        $mform->addElement('select', 'intags', get_string('includetags', 'quiz'), $tags, ['class' => 'select']);
        $mform->getElement('intags')->setMultiple(true);
        $mform->getElement('intags')->setSelected($usedtags);

        $mform->addElement('select', 'includetype', get_string('includetagstype', 'quiz'), array(
            "1" => get_string("includetagstypeany", "quiz"), "2" => get_string("includetagstypeall", "quiz")));
        $mform->getElement('includetype')->setSelected($includetype);

        $mform->addElement('select', 'outtags', get_string('excludetags', 'quiz'), $tags, ['class' => 'select']);
        $mform->getElement('outtags')->setMultiple(true);
        $mform->getElement('outtags')->setSelected($outtags);

        $mform->addElement('questioncategory', 'category', get_string('category'),
            array('contexts' => $usablecontexts, 'top' => false), ['class' => 'select searchoptions']);
        $mform->setDefault('category', $this->_customdata['cat']);

        $mform->addElement('checkbox', 'includesubcategories', '', get_string('recurse', 'quiz'));
        $mform->setDefault('includesubcategories',
            isset($this->_customdata['includesubcategories']) ? $this->_customdata['includesubcategories'] : 0);

        $mform->addElement('select', 'numbertoadd', get_string('randomnumber', 'quiz'),
            $this->get_number_of_questions_to_add_choices());
        $mform->setDefault('numbertoadd', isset($this->_customdata['numbertoadd']) ? $this->_customdata['numbertoadd'] : 1);

        $mform->addElement('submit', 'existingcategory', get_string('addrandomquestion', 'quiz'));

        // Cancel button.
        $mform->addElement('cancel');
        $mform->closeHeaderBefore('cancel');

        $mform->addElement('hidden', 'addonpage', 0, 'id="rform_qpage"');
        $mform->setType('addonpage', PARAM_SEQUENCE);
        $mform->addElement('hidden', 'cmid', 0);
        $mform->setType('cmid', PARAM_INT);
        $mform->addElement('hidden', 'returnurl', 0);
        $mform->setType('returnurl', PARAM_LOCALURL);
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
        $sql = "SELECT id value, name display FROM {tag} WHERE id IN
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
        $cmid = optional_param('cmid', 0, PARAM_INT);
        $categoryparam = optional_param('category', '', PARAM_TEXT);
        $courseid = optional_param('courseid', 0, PARAM_INT);

        if ($cmid) {
            list($thispageurl, $contexts, $cmid, $cm, $quiz, $pagevars) = question_edit_setup('editq', '/mod/quiz/edit.php', true);
            if ($pagevars['cat']) {
                $categoryparam = $pagevars['cat'];
            }
        }

        if ($categoryparam) {
            list($cat) = explode(',', $categoryparam);
            if (isset($this->_customdata['includesubcategories']) && $this->_customdata['includesubcategories']) {
                $cats = question_categorylist($cat);
            } else {
                $cats = array($cat);
            }
            return $cats;
        } else if ($cmid) {
            list($module, $cm) = get_module_from_cmid($cmid);
            $courseid = $cm->course;
            require_login($courseid, false, $cm);
            $thiscontext = context_module::instance($cmid);
        } else {
            $module = null;
            $cm = null;
            if ($courseid) {
                $thiscontext = context_course::instance($courseid);
            } else {
                $thiscontext = null;
            }
        }

        $cats = get_categories_for_contexts($thiscontext->id);
        return array_keys($cats);
    }


    /**
     * Returns a list of integers to be used as options in the "numbertoadd" select
     *
     * @return array list of integers
     */
    private function get_number_of_questions_to_add_choices() {
        $maxrand = 100;
        $randomcount = array();
        for ($i = 1; $i <= min(10, $maxrand); $i++) {
            $randomcount[$i] = $i;
        }
        for ($i = 20; $i <= min(100, $maxrand); $i += 10) {
            $randomcount[$i] = $i;
        }
        return $randomcount;
    }

}