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
 * Question type class for the randomtag question type.
 *
 * @package     qtype_randomtag
 * @copyright   2017 Andreas Figge (BuGaSi GmbH)
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */


defined('MOODLE_INTERNAL') || die();


/**
 * The randomtag question type.
 *
 * This question type does not have a question definition class, nor any
 * renderers. When you load a question of this type, it actually loads a
 * question chosen randomly from a particular category and a set of tags
 * in the question bank.
 *
 * @copyright  2017 Andreas Figge (BuGaSi GmbH)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class qtype_randomtag extends question_type {
    /** @var string comma-separated list of qytpe names not to select, can be used in SQL. */
    protected $excludedqtypes = null;

    /** @var string comma-separated list of manually graded qytpe names, can be used in SQL. */
    protected $manualqtypes = null;

    /**
     * The name this question should appear as in the create new question
     * dropdown.
     *
     * @return mixed the desired string, or false to hide this question type in the menu.
     */
    public function menu_name() {
        // Don't include this question type in the 'add new question' menu.
        return false;
    }

    /**
     * @return bool true if this question type sometimes requires manual grading.
     */
    public function is_manual_graded() {
        return true;
    }

    /**
     * @return bool true if this question type can be used by the random question type.
     */
    public function is_usable_by_random() {
        return false;
    }

    /**
     * @param object $question a question of this type.
     * @param string $otherquestionsinuse comma-separate list of other question ids in this attempt.
     * @return bool true if a particular instance of this question requires manual grading.
     */
    public function is_question_manual_graded($question, $otherquestionsinuse) {
        global $DB;
        // We take our best shot at working whether a particular question is manually
        // graded follows: We look to see if any of the questions that this random
        // question might select if of a manually graded type. If a category contains
        // a mixture of manual and non-manual questions, and if all the attempts so
        // far selected non-manual ones, this will give the wrong answer, but we
        // don't care. Even so, this is an expensive calculation!
        $this->init_qtype_lists();
        if (!$this->manualqtypes) {
            return false;
        }
        if ($question->questiontext) {
            $categorylist = question_categorylist($question->category);
        } else {
            $categorylist = array($question->category);
        }
        list($qcsql, $qcparams) = $DB->get_in_or_equal($categorylist);
        // TODO use in_or_equal for $otherquestionsinuse and $this->manualqtypes.
        return $DB->record_exists_select('question',
            "category {$qcsql}
                     AND parent = 0
                     AND hidden = 0
                     AND id NOT IN ($otherquestionsinuse)
                     AND qtype IN ($this->manualqtypes)", $qcparams);
    }

    /**
     * This method needs to be called before the ->excludedqtypes and
     *      ->manualqtypes fields can be used.
     */
    protected function init_qtype_lists() {
        if (!is_null($this->excludedqtypes)) {
            return; // Already done.
        }
        $excludedqtypes = array();
        $manualqtypes = array();
        foreach (question_bank::get_all_qtypes() as $qtype) {
            $quotedname = "'" . $qtype->name() . "'";
            if (!$qtype->is_usable_by_random()) {
                $excludedqtypes[] = $quotedname;
            } else if ($qtype->is_manual_graded()) {
                $manualqtypes[] = $quotedname;
            }
        }
        $this->excludedqtypes = implode(',', $excludedqtypes);
        $this->manualqtypes = implode(',', $manualqtypes);
    }

    /**
     * Loads the question type specific options for the question.
     *
     * This function loads any question type specific options for the
     * question from the database into the question object. This information
     * is placed in the $question->options field. A question type is
     * free, however, to decide on a internal structure of the options field.
     * @return bool            Indicates success or failure.
     * @param object $question The question object for the question. This object
     *                         should be updated to include the question type
     *                         specific information (it is passed by reference).
     */
    public function get_question_options($question) {
        return true;
    }

    /**
     * Randomtag questions always get a question name that is Random (included tags).
     * This function is a centralised place to calculate that.
     * @param object $question the question.
     * @return string the name this question should have.
     */
    public function question_name($question) {
        GLOBAL $DB;
        $tags = '';
        if (object_property_exists($question, 'intags') && !empty($question->intags)) {
            list($catidtest, $params) = $DB->get_in_or_equal($question->intags, SQL_PARAMS_NAMED, 'p');
            $sql = "SELECT name from {tag} WHERE id $catidtest";
            $res = $DB->get_records_sql($sql, $params);
            $tags = implode(',', array_keys($res));
        } else if (object_property_exists($question, 'qtags') && !empty($question->qtags)) {
            list($catidtest, $params) = $DB->get_in_or_equal($question->qtags, SQL_PARAMS_NAMED, 'p');
            $sql = "SELECT name from {tag} WHERE id $catidtest";
            $res = $DB->get_records_sql($sql, $params);
            $tags = implode(',', array_keys($res));
        }

        // TODO might include excluded tags and or category name to question name.
        return get_string('randomtagqname', 'qtype_randomtag', shorten_text($tags ? $tags : '---none---', 100));
    }

    /**
     * Sets the name of randomly selected question to indicate that it was selected
     * by the randomtag question type.
     *
     * @param stdClass $question
     * @param string $randomtagname
     */
    protected function set_selected_question_name($question, $randomtagname) {
        $a = new stdClass();
        $a->randomtagname = $randomtagname;
        $a->questionname = $question->name;
        $question->name = get_string('selectedby', 'qtype_randomtag', $a);
    }

    /**
     * Overrides the parent function save_question to set a custom question text
     *
     *
     * @param object $question the question object which should be updated.
     * @param object $form the object containing the information to save, as if
     *      from the question editing form.
     * @return object On success, return the new question object. On failure,
     *       return an object as follows. If the error object has an errors field,
     *       display that as an error message. Otherwise, the editing form will be
     *       redisplayed with validation errors, from validation_errors field, which
     *       is itself an object, shown next to the form fields. (I don't think this
     *       is accurate any more.)
     */
    public function save_question($question, $form) {
        $form->name = '';

        // In case someone set the question text to true/false in the old style, set it properly.
        if ($form->questiontext['text']) {
            $form->questiontext['text'] = '1';
        } else {
            $form->questiontext['text'] = '0';
        }
        $form->tags = array();
        // Name is not a required field for randomtag questions, but
        // parent::save_question Assumes that it is.
        return parent::save_question($question, $form);
    }

    /**
     * Saves question-type specific options
     *
     * @return object $result->error or $result->notice
     * @param object $question  This holds the information from the editing form,
     *      it is not a standard question object.
     */
    public function save_question_options($question) {
        global $DB;

        // No options, as such, but we set the parent field to the question's
        // own id. Setting the parent field has the effect of hiding this
        // question in various places.
        $updateobject = new stdClass();
        $updateobject->id = $question->id;
        $updateobject->parent = $question->id;

        $updateobject->name = $this->question_name($question);

        $options = $DB->get_record('qtype_randomtag_options', array('questionid' => $question->id));

        if ($options) {
            $options->includetype = $question->includetype;
            $DB->delete_records('qtype_randomtag_tags', array("randomtagid" => $options->id));
            $tags = [];
            $intags = object_property_exists($question, 'intags') ? $question->intags : [];
            foreach ($intags as $intag) {
                $t = new stdClass();
                $t->randomtagid = $options->id;
                $t->tagid = $intag;
                $t->included = true;
                $tags[] = $t;
            }
            $outtags = object_property_exists($question, 'outtags') ? $question->outtags : [];

            foreach ($outtags as $outtag) {
                $t = new stdClass();
                $t->randomtagid = $options->id;
                $t->tagid = $outtag;
                $t->included = false;
                $tags[] = $t;
            }
            if (!empty($tags)) {
                $DB->insert_records('qtype_randomtag_tags', $tags);
            }
            $DB->update_record('qtype_randomtag_options', $options);
        } else {
            $options = new stdClass();
            $options->questionid = $question->id;
            $options->includetype = $question->includetype;
            $id = $DB->insert_record('qtype_randomtag_options', $options, true);
            $tags = [];
            foreach ($question->qtags as $intag) {
                $t = new stdClass();
                $t->randomtagid = $id;
                $t->tagid = $intag;
                $t->included = true;
                $tags[] = $t;
            }
            foreach ($question->qouttags as $outtag) {
                $t = new stdClass();
                $t->randomtagid = $id;
                $t->tagid = $outtag;
                $t->included = false;
                $tags[] = $t;
            }
            if (!empty($tags)) {
                $DB->insert_records('qtype_randomtag_tags', $tags);
            }
        }
        return $DB->update_record('question', $updateobject);
    }

    /**
     * Get all the usable questions from a particular question category.
     *
     * @param int $categoryid the id of a question category.
     * @param bool $subcategories whether to include questions from subcategories.
     * @param int $qid id of the randomtag question
     * @return array questionid => questionid.
     */
    public function get_available_questions_from_category($categoryid, $subcategories, $qid = 0) {
        global $DB;

        $this->init_qtype_lists();
        if ($subcategories) {
            $categoryids = question_categorylist($categoryid);
        } else {
            $categoryids = array($categoryid);
        }

        $extraconditions = 'qtype NOT IN (' . $this->excludedqtypes . ')';
        $extraparams = [];

        if ($qid) {
            $opt = $DB->get_record('qtype_randomtag_options', ['questionid' => $qid]);
            if ($opt) {
                $qtags = $DB->get_records('qtype_randomtag_tags', array("id" => $opt->id));

                $intags = array_map(function($it) {
                    return $it->tagid;
                }, array_filter($qtags, function($v, $k) {
                    return $v->included;
                }, ARRAY_FILTER_USE_BOTH));

                $outtags = array_map(function($it) {
                    return $it->tagid;
                }, array_filter($qtags, function($v, $k) {
                    return !$v->included;
                }, ARRAY_FILTER_USE_BOTH));

                if (!empty($intags)) {
                    if (!empty($extraconditions)) {
                        $extraconditions .= " AND ";
                    }
                    list($where, $params) = $DB->get_in_or_equal($intags, SQL_PARAMS_NAMED, 'tag');
                    if ($opt->includetype == 1) {
                        $extraconditions .= "(SELECT COUNT(*) as tagcount FROM {tag_instance} ti " .
                            "WHERE itemid={question}.id AND tagid $where)>0";
                    } else {
                        $extraconditions .= "(SELECT COUNT(*) as tagcount FROM {tag_instance} ti " .
                            "WHERE itemid={question}.id AND tagid $where)=". count($intags);
                    }

                    $extraparams = $extraparams + $params;
                }
                if (!empty($outtags)) {
                    if (!empty($extraconditions)) {
                        $extraconditions .= " AND ";
                    }
                    list($where, $params) = $DB->get_in_or_equal($outtags, SQL_PARAMS_NAMED, 'tagn');
                    $extraconditions .= "(SELECT COUNT(*) as tagcount FROM {tag_instance} ti " .
                        "WHERE itemid={question}.id AND tagid $where)=0";
                    $extraparams = $extraparams + $params;
                }
            }
        }

        $questionids = question_bank::get_finder()->get_questions_from_categories(
            $categoryids, $extraconditions, $extraparams);

        return $questionids;
    }

    /**
     * Create an appropriate question_definition for the question of this type
     * using data loaded from the database.
     * @param object $questiondata the question data loaded from the database.
     * @return question_definition the corresponding question_definition.
     */
    public function make_question($questiondata) {
        return $this->choose_other_question($questiondata, array());
    }

    /**
     * Load the definition of another question picked randomly by this question.
     * @param object       $questiondata the data defining a randomtag question.
     * @param array        $excludedquestions of question ids. We will no pick any question whose id is in this list.
     * @param bool         $allowshuffle      if false, then any shuffle option on the selected question is disabled.
     * @param null|integer $forcequestionid   if not null then force the picking of question with id $forcequestionid.
     * @throws coding_exception
     * @return question_definition|null the definition of the question that was
     *      selected, or null if no suitable question could be found.
     */
    public function choose_other_question($questiondata, $excludedquestions, $allowshuffle = true, $forcequestionid = null) {
        $available = $this->get_available_questions_from_category($questiondata->category,
            !empty($questiondata->questiontext), $questiondata->id);
        shuffle($available);

        if ($forcequestionid !== null) {
            $forcedquestionkey = array_search($forcequestionid, $available);
            if ($forcedquestionkey !== false) {
                unset($available[$forcedquestionkey]);
                array_unshift($available, $forcequestionid);
            } else {
                throw new coding_exception('thisquestionidisnotavailable', $forcequestionid);
            }
        }

        foreach ($available as $questionid) {
            if (in_array($questionid, $excludedquestions)) {
                continue;
            }

            $question = question_bank::load_question($questionid, $allowshuffle);
            $this->set_selected_question_name($question, $questiondata->name);
            return $question;
        }

        return null;
    }

    /**
     * @param object $question
     * @return number|null either a fraction estimating what the student would
     * score by guessing, or null, if it is not possible to estimate.
     */
    public function get_randomtag_guess_score($question) {
        return null;
    }

    /**
     * Deletes the question-type specific data when a question is deleted.
     * @param int $questionid the question being deleted.
     * @param int $contextid the context this quesiotn belongs to.
     */
    public function delete_question($questionid, $contextid) {
        global $DB;
        $options = $DB->get_record('qtype_randomtag_options', array('questionid' => $questionid));
        if ($options) {
            $DB->delete_records('qtype_randomtag_tags', array('randomtagid' => $options->id));
        }
        $DB->delete_records('qtype_randomtag_options', ['questionid' => $questionid]);
        parent::delete_question($questionid, $contextid);
    }


}