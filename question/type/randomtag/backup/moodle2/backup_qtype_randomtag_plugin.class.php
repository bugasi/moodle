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
 * @package     qtype_randomtag
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

        $randomtag = new backup_nested_element('randomtag', array('id'), array('includetype'));
        $randomtagtags = new backup_nested_element('randomtagtags');
        $randomtagtag = new backup_nested_element('randomtagtag', array('id'), array('tagid', 'included', 'rawname'));

        $randomtag->add_child($randomtagtags);
        $randomtagtags->add_child($randomtagtag);

        $randomtagtag->set_source_sql("SELECT rt.id id, t.id tagid, rt.included included, t.rawname rawname
          from {qtype_randomtag_tags} rt JOIN {tag} t on rt.tagid = t.id where rt.randomtagid = ?", array(backup::VAR_PARENTID));

        $pluginwrapper->add_child($randomtag);

        $randomtag->set_source_table('qtype_randomtag_options', array('questionid' => backup::VAR_PARENTID));
        return $plugin;
    }

}