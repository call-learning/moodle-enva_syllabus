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
 * Common lib
 *
 * @package     local_envasyllabus
 * @category    string
 * @copyright   2022 CALL Learning - Laurent David <laurent@call-learning>
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/**
 * Add navigation for course
 *
 * @param global_navigation $navigation
 * @throws coding_exception
 * @throws moodle_exception
 */
function local_envasyllabus_extend_navigation(global_navigation $navigation) {
    global $CFG, $PAGE;
    $context = $PAGE->context;
    if ($CFG->enableenvasyllabus) {
        if ($context->contextlevel == CONTEXT_COURSE && $context->instanceid != SITEID) {
            $courseid = $context->instanceid;
            $node = $navigation->find($courseid, navigation_node::TYPE_COURSE);
            $url = new moodle_url('/local/envasyllabus/syllabuspage.php', array('id' => $courseid));
            $newnode = new navigation_node(
                [
                    'text' => get_string('syllabuspage:menu', 'local_envasyllabus'),
                    'action' => $url,
                    'type' => navigation_node::TYPE_SETTING,
                    'icon' => new pix_icon('t/viewdetails', ''),
                    'key' => 'envasyllabus'
                ]
            );
            $allkeys = $node->get_children_key_list();
            $node->add_node($newnode, $allkeys[0]);
        }
    }
}

