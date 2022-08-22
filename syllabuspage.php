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
 * A syllabus page
 *
 * @package     local_envasyllabus
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
require_course_login(SITEID);

$title = get_string('syllabuspage:title', 'local_envasyllabus');
global $OUTPUT, $SESSION, $USER;
$currenturl = new moodle_url('/local/envasyllabus/syllabuspage.php', ['id' => $courseid]);
$PAGE->set_title($title);
$PAGE->set_url($currenturl);
$PAGE->set_heading($title);
$PAGE->set_context(context_course::instance($courseid));
if ($PAGE->user_allowed_editing()) {
    $editcoursebutton = new single_button(
        new moodle_url('/course/edit.php', [
            'id' => $courseid,
            'returnto' => 'url',
            'returnurl' => $currenturl->out_as_local_url(),
            'sesskey' => sesskey()
        ],
            'id_tagshdr'),
        get_string('editcoursesettings', 'moodle'),
        'get'
    );
    $buttons = $OUTPUT->render($editcoursebutton);
    $PAGE->set_button($buttons);
}
$renderer = $PAGE->get_renderer('local_envasyllabus');

$languageswitcher = new \local_envasyllabus\output\language_switcher();
$csyllabus = new \local_envasyllabus\output\course_syllabus($courseid, $languageswitcher->get_current_langcode());

$viewcoursebtn = new single_button(
    new moodle_url('/course/view.php', ['id' => $courseid]),
    get_string('viewcourse', 'local_envasyllabus')
);
$viewcatalog = new single_button(
    new moodle_url('/local/envasyllabus/index.php'),
    get_string('catalog:index', 'local_envasyllabus')
);
$additionalbuttons = $renderer->render($languageswitcher);
$additionalbuttons .= $OUTPUT->render($viewcoursebtn) . $OUTPUT->render($viewcatalog);

echo $OUTPUT->header();
echo $OUTPUT->box($additionalbuttons, 'generalbox syllabus-additional-buttons');
$languageswitcher->set_lang();
echo $renderer->render($csyllabus);
$languageswitcher->reset_lang();
echo $OUTPUT->footer();
// See local/envasyllabus/syllabuspage.php?id=801 .
