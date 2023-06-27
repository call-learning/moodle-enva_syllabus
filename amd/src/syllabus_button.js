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
 * Add view syllabus button in the course summary
 *
 * @package    local_envasyllabus
 * @copyright  2023 CALL Learning <laurent@call-learning.fr>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
import Templates from "core/templates";
import {exception as displayException} from 'core/notification';
import Config from 'core/config';
/**
 * Init
 *
 * @param {number} courseId
 */
export const init = (courseId) => {
    const summaryTag = document.querySelector('.content .summary');
    const url = Config.wwwroot + '/local/envasyllabus/syllabuspage.php?id=' + courseId;
    Templates.render('local_envasyllabus/syllabus_button', {
        url: url
    }).then((html, js) => {
        Templates.appendNodeContents(summaryTag, html, js);
    }).catch(displayException);
};
