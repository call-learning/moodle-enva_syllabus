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
 * Plugin upgrade steps are defined here.
 *
 * @package     local_envasyllabus
 * @category    upgrade
 * @copyright   2022 CALL Learning - Laurent David <laurent@call-learning>
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

use local_envasyllabus\setup;

/**
 * Execute local_envasyllabus upgrade from the given old version.
 *
 * @param int $oldversion
 * @return bool
 */
function xmldb_local_envasyllabus_upgrade($oldversion) {
    global $DB, $CFG;
    // For further information please read {@link https://docs.moodle.org/dev/Upgrade_API}.
    //
    // You will also have to create the db/install.xml file by using the XMLDB Editor.
    // Documentation for the XMLDB Editor can be found at {@link https://docs.moodle.org/dev/XMLDB_editor}.

    if ($oldversion < 2022020210) {
        setup::install_update($CFG->dirroot . '/local/envasyllabus/tests/fixtures/customfields_defs.txt');
        upgrade_plugin_savepoint(true, 2022020210, 'local', 'envasyllabus');
    }

    if ($oldversion < 2022081310) {
        setup::install_update($CFG->dirroot . '/local/envasyllabus/tests/fixtures/customfields_defs.txt');
        upgrade_plugin_savepoint(true, 2022081310, 'local', 'envasyllabus');
    }
    return true;
}
