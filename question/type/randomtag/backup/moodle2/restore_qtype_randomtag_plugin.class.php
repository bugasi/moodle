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
 * @package     moodlecore
 * @subpackage  backup-moodle2
 * @copyright   2017 Andreas Figge (BuGaSi GmbH)
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */


defined('MOODLE_INTERNAL') || die();


/**
 * restore plugin class that provides the necessary information
 * needed to restore one randomtag qtype plugin
 *
 * @copyright   2017 Andreas Figge (BuGaSi GmbH)
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class restore_qtype_randomtag_plugin extends restore_qtype_plugin {

    /**
     * Define the plugin structure.
     *
     * @return array  Array of {@link restore_path_elements}.
     */
    protected function define_question_plugin_structure() {
        $paths = array();

        $elename = 'randomtag';
        $elepath = $this->get_pathfor('/randomtag');

        $paths[] = new restore_path_element($elename, $elepath);
        $paths[] = new restore_path_element('usedtag', $this->get_pathfor('/randomtag/usedtags/usedtag'));
        $paths[] = new restore_path_element('excludedtag', $this->get_pathfor('/randomtag/excludedtags/excludedtag'));
        return $paths;
    }

    public function process_randomtag($data) {
        global $DB;
        $data = (object)$data;
        $newquestionid   = $this->get_new_parentid('question');

        $data->questionid = $newquestionid;
        $DB->insert_record('question_randomtag', $data);
    }

    public function process_usedtag($d) {
        $data = (object)$d;
        if($data && object_property_exists($data, 'rawname') && $data->rawname) {
            if (core_tag_tag::is_enabled('core_question', 'question')) {
                $collectionid = core_tag_area::get_collection('core_question', 'question');
                $tags = core_tag_tag::create_if_missing($collectionid, array($data->rawname));
                $tag = array_shift($tags);
                if(!$this->get_mappingid('usedtag', $data->id)) {
                    $this->set_mapping('usedtag', $data->id, $tag->id);
                }
            }
        }
    }

    public function process_excludedtag($d) {
        $data = (object)$d;
        if($data && object_property_exists($data, 'rawname') && $data->rawname) {
            if (core_tag_tag::is_enabled('core_question', 'question')) {
                $collectionid = core_tag_area::get_collection('core_question', 'question');
                $tags = core_tag_tag::create_if_missing($collectionid, array($data->rawname));
                $tag = array_shift($tags);
                if(!$this->get_mappingid('excludedtag', $data->id)) {
                    $this->set_mapping('excludedtag', $data->id, $tag->id);
                }
            }
        }
    }

    /**
     * Given one question_states record, return the answer
     * recoded pointing to all the restored stuff for randomtag questions
     *
     * answer format is randomtagxx-yy, with xx being question->id and
     * yy the actual response to the question. We'll delegate the recode
     * to the corresponding qtype
     *
     * also, some old states can contain, simply, one question->id,
     * support them, just in case
     */
    public function recode_legacy_state_answer($state) {
        global $DB;

        $answer = $state->answer;
        $result = '';
        // Randomxx-yy answer format.
        if (preg_match('~^randomtag([0-9]+)-(.*)$~', $answer, $matches)) {
            $questionid = $matches[1];
            $subanswer  = $matches[2];
            $newquestionid = $this->get_mappingid('question', $questionid);
            $questionqtype = $DB->get_field('question', 'qtype', array('id' => $newquestionid));
            // Delegate subanswer recode to proper qtype, faking one question_states record.
            $substate = new stdClass();
            $substate->question = $newquestionid;
            $substate->answer = $subanswer;
            $newanswer = $this->step->restore_recode_legacy_answer($substate, $questionqtype);
            $result = 'randomtag' . $newquestionid . '-' . $newanswer;

            // Simple question id format.
        } else {
            $newquestionid = $this->get_mappingid('question', $answer);
            $result = $newquestionid;
        }
        return $result;
    }

    /**
     * After restoring, make sure questiontext is set properly.
     */
    public function after_execute_question() {
        global $DB;
        $data = $DB->get_record_sql("
            SELECT qrt.id, qrt.intags, qrt.outtags
            FROM {question_randomtag} qrt
            JOIN {backup_ids_temp} bi ON bi.newitemid = qrt.questionid
            WHERE bi.backupid = ?
        ", array($this->get_restoreid()));
        if($data) {
            if($data->intags) {
                $oldtags = explode(",", $data->intags);
                $newtags = [];
                foreach ($oldtags as $oldtag) {
                    $newtags[] = $this->get_mappingid('usedtag', $oldtag);
                }
                $data->intags = implode(",", $newtags);
            }
            if($data->outtags) {
                $newtags = [];
                $oldtags = explode(',', $data->outtags);
                foreach($oldtags as $t) {
                    $newtags[] = $this->get_mappingid('excludedtag', $t);
                }
                $data->outtags = implode(",", $newtags);
            }
            $DB->update_record('question_randomtag', $data);
        }
        // Update any blank randomtag questiontexts to 0.
        $sql = "UPDATE {question}
                   SET questiontext = '0'
                 WHERE qtype = 'randomtag'
                   AND " . $DB->sql_compare_text('questiontext') . " = ?
                   AND id IN (SELECT bi.newitemid
                                FROM {backup_ids_temp} bi
                               WHERE bi.backupid = ?
                                 AND bi.itemname = 'question_created')";

        $DB->execute($sql, array('', $this->get_restoreid()));
    }

}
