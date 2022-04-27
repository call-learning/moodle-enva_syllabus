<?php
// This file is part of Moodle - https://moodle.org/
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
// along with Moodle.  If not, see <https://www.gnu.org/licenses/>.

/**
 * Plugin administration pages are defined here.
 *
 * @package     local_envasyllabus
 * @category    admin
 * @copyright   2022 CALL Learning - Laurent David <laurent@call-learning>
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
require_once(__DIR__ . '/../../config.php');
global $CFG, $DB, $PAGE;
// Get submitted parameters.
$courseid = required_param('id', PARAM_INT);
if (!$course = $DB->get_record('course', array('id' => $courseid))) {
    throw new moodle_exception('invalidcourse', 'local_envasyllabus');
}

// Check login.
require_login($courseid, false);

$title = get_string('syllabuspage:title', 'local_envasyllabus');
global $OUTPUT;
$PAGE->set_title($title);
$PAGE->set_url(new moodle_url('/local/enva_syllabus/syllabuspage.php'));
$PAGE->set_heading($title);
$PAGE->set_pagelayout('general');
$csyllabus = new \local_envasyllabus\output\course_syllabus($courseid);
$renderer = $PAGE->get_renderer('local_envasyllabus');
echo $OUTPUT->header();
echo $renderer->render($csyllabus);
echo $OUTPUT->footer();