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
            $url = new moodle_url('/local/envasyllabus/syllabuspage.php', ['id' => $courseid]);
            $newnode = new navigation_node(
                [
                    'text' => get_string('syllabuspage:menu', 'local_envasyllabus'),
                    'action' => $url,
                    'type' => navigation_node::TYPE_SETTING,
                    'icon' => new pix_icon('t/viewdetails', ''),
                    'key' => 'envasyllabus',
                ]
            );
            $navigation->add_node($newnode);
        }
        // Now add the index.
        $url = new moodle_url('/local/envasyllabus/index.php');
        $newnode = new navigation_node(
            [
                'text' => get_string('catalog:index', 'local_envasyllabus'),
                'action' => $url,
                'type' => navigation_node::TYPE_SETTING,
                'icon' => new pix_icon('t/viewdetails', ''),
                'key' => 'catalogindex',
            ]
        );
        $navigation->add_node($newnode);
    }
}

/**
 * Insert "View Syllabus" Button in course header
 *
 * @return void
 */
function local_envasyllabus_before_standard_top_of_body_html() {
    global $PAGE;
    $context = $PAGE->context;
    if ($context->contextlevel == CONTEXT_COURSE && $context->instanceid != SITEID) {
        if (strpos(trim(strtolower($PAGE->course->shortname)), 'uc') === 0) {
            $PAGE->requires->js_call_amd('local_envasyllabus/syllabus_button', 'init', [$PAGE->course->id]);
        }
    }
}

/**
 * Specific icons for the module
 * @return string[]
 */
function local_envasyllabus_get_fontawesome_icon_map() {
    return [
        'local_envasyllabus:i/languages' => 'fa-language',
        'local_envasyllabus:i/arrowview' => 'fa-arrow-circle-o-right',
    ];
}
