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

namespace local_envasyllabus;

use core\hook\output\before_standard_top_of_body_html_generation;

/**
 * Class hook_callbacks
 *
 * @package    local_envasyllabus
 * @copyright  2024 Bas Brands <bas@sonsbeekmedia.nl>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class hook_callbacks {
    /**
     * Insert "View Syllabus" Button in course header.
     *
     * @param before_standard_top_of_body_html_generation $hook
     */
    public static function before_standard_top_of_body_html_generation(before_standard_top_of_body_html_generation $hook): void {
        global $PAGE;


        if (!isguestuser() && !isloggedin()) {
            return;
        }

        $context = $PAGE->context;
        if ($context->contextlevel == CONTEXT_COURSE && $context->instanceid != SITEID) {
            if (strpos(trim(strtolower($PAGE->course->shortname)), 'uc') === 0) {
                $PAGE->requires->js_call_amd('local_envasyllabus/syllabus_button', 'init', [$PAGE->course->id]);
            }
        }
    }
}
