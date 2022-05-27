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

use local_envasyllabus\output\catalog;

defined('MOODLE_INTERNAL') || die();

if ($hassiteconfig) {
    // phpcs:ignore Generic.CodeAnalysis.EmptyStatement.DetectedIf
    // General settings.
    $pagedesc = get_string('generalsettings', 'local_envasyllabus');
    $generalsettingspage = new admin_settingpage('envasyllabusgeneral',
        $pagedesc,
        'moodle/site:config',
        empty($CFG->enableenvasyllabus));

    $ADMIN->add('localplugins', $generalsettingspage);
    if ($ADMIN->fulltree) {
        if (!during_initial_install()) {
            $categories = array_map(function ($cat) {
                return $cat->get_formatted_name();
            },
                core_course_category::get_all()
            );
            $settingname = get_string('rootcategoryid', 'local_envasyllabus');
            $settingdescription = get_string('rootcategoryid_desc', 'local_envasyllabus');
            $rootcategoryid = new admin_setting_configselect(
                'local_envasyllabus/rootcategoryid',
                $settingname,
                $settingdescription,
                catalog::DEFAULT_COURSE_CATEGORY,
                $categories
            );
            $generalsettingspage->add($rootcategoryid);
        }
    }
    $optionalsubsystems = $ADMIN->locate('optionalsubsystems');
    $optionalsubsystems->add(new admin_setting_configcheckbox('enableenvasyllabus',
            new lang_string('enableenvasyllabus', 'local_envasyllabus'),
            new lang_string('enableenvasyllabus_help', 'local_envasyllabus'),
            1)
    );
}
