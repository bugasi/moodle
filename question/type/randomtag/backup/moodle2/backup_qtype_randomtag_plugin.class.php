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
 * Provides the information to backup randomtag questions
 *
 * @copyright   2017 Andreas Figge (BuGaSi GmbH)
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class backup_qtype_randomtag_plugin extends backup_qtype_plugin {

    /**
     * Returns the qtype information to attach to question element
     */
    protected function define_question_plugin_structure() {

        $plugin = $this->get_plugin_element(null, '../../qtype', 'randomtag');

        $pluginwrapper = new backup_nested_element($this->get_recommended_name());

        $plugin->add_child($pluginwrapper);

        $randomtag = new backup_nested_element('randomtag', array('id'), array('intags', 'outtags'));
        $tags = new backup_nested_element('usedtags');
        $tag = new backup_nested_element('usedtag', array('id'), array('name', 'rawname'));
        $outtags = new backup_nested_element('excludedtags');
        $outtag = new backup_nested_element('excludedtag', array('id'), array('name', 'rawname'));
        $randomtag->add_child($tags);
        $tags->add_child($tag);
        $randomtag->add_child($outtags);
        $outtags->add_child($outtag);

        $tag->set_source_sql("SELECT t.id, t.name, t.rawname FROM mdl_tag AS t 
         JOIN mdl_question_randomtag AS r ON FIND_IN_SET(t.id, r.intags) WHERE r.id = ?;", array(backup::VAR_PARENTID));

        $outtag->set_source_sql("SELECT t.id, t.name, t.rawname FROM mdl_tag AS t 
         JOIN mdl_question_randomtag AS r ON FIND_IN_SET(t.id, r.outtags) WHERE r.id = ?;", array(backup::VAR_PARENTID));

        $pluginwrapper->add_child($randomtag);

        $randomtag->set_source_table('question_randomtag', array('questionid' => backup::VAR_PARENTID));
        return $plugin;
    }

}