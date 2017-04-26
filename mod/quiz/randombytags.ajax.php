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
 * Fallback page of /mod/quiz/edit.php add random question dialog,

 *
 * @package   mod_quiz
 * @copyright   2017 Andreas Figge (BuGaSi GmbH)
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

define ('AJAX_SCRIPT', true);
require_once(__DIR__ . '/../../config.php');
require_once($CFG->dirroot . '/mod/quiz/locallib.php');
require_once($CFG->dirroot . '/mod/quiz/addrandombytagsform.php');
require_once($CFG->dirroot . '/question/editlib.php');
require_once($CFG->dirroot . '/question/category_class.php');

list($thispageurl, $contexts, $cmid, $cm, $quiz, $pagevars) = question_edit_setup('editq', '/mod/quiz/addrandombytags.php', true);

$course = $DB->get_record('course', array('id' => $quiz->course), '*', MUST_EXIST);
require_capability('mod/quiz:manage', $contexts->lowest());

$includesubcategories = optional_param('includesubcategories', 0, PARAM_BOOL);
$numbertoadd = optional_param('numbertoadd', 1, PARAM_INT);
$includetype = optional_param('includetype', 1, PARAM_INT);
$intags = optional_param_array('intags', [], PARAM_INT);
$outtags = optional_param_array('outtags', [], PARAM_INT);
$defaultcategoryobj = question_make_default_categories($contexts->all());
$defaultcategory = $defaultcategoryobj->id . ',' . $defaultcategoryobj->contextid;

$qcobject = new question_category_object(
    $pagevars['cpage'],
    $thispageurl,
    $contexts->having_one_edit_tab_cap('categories'),
    $defaultcategoryobj->id,
    $defaultcategory,
    null,
    $contexts->having_cap('moodle/question:add'));

$pagevars['includesubcategories'] = $includesubcategories;
$pagevars['numbertoadd'] = $numbertoadd;
$pagevars['includetype'] = $includetype;
$pagevars['intags'] = $intags;
$pagevars['outtags'] = $outtags;
$output = $PAGE->get_renderer('mod_quiz', 'edit');
$returnurl = '/mod/quiz/edit.php?cmid=' . $cmid;
$contents = $output->random_by_tags_contents($qcobject, $contexts, $pagevars, $cmid, $returnurl);

echo json_encode(array(
    'status' => 'OK',
    'contents' => $contents
));