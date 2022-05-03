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
 * A list of all syllabus
 *
 * @package     local_envasyllabus
 * @copyright   2022 CALL Learning - Laurent David <laurent@call-learning>
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
require_once(__DIR__ . '/../../config.php');
global $CFG, $DB, $PAGE;

// Check login.
require_login(true);

$title = get_string('courses:index', 'local_envasyllabus');
global $OUTPUT;
$PAGE->set_title($title);
$PAGE->set_url(new moodle_url('/local/enva_syllabus/index.php'));
$PAGE->set_heading($title);
$PAGE->set_pagelayout('general');
$catalog = new \local_envasyllabus\output\catalog();
$renderer = $PAGE->get_renderer('local_envasyllabus');
echo $OUTPUT->header();
echo $renderer->render($catalog);
echo $OUTPUT->footer();
