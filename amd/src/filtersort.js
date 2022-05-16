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
 * Javascript to initialise the enva filter sorting.
 *
 * @package    local_envasyllabus
 * @copyright  2022 CALL Learning <laurent@call-learning.fr>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

import Config from 'core/config';
import jQuery from 'jquery';

/**
 * Initialise catalog
 *
 * @param filterItemId
 */
export const init = (filterItemId) => {
    const formNode = document.querySelector('#' + filterItemId + ' form');
    formNode.addEventListener("submit", (e) => {
        e.preventDefault();
        // Now we get all the current values from the form.
        let filterinfo = getFilterData(formNode, false);
        if (filterinfo) {
            const event = new CustomEvent('enva-syllabus-catalog-filter', {detail: filterinfo});
            document.dispatchEvent(event);
        }
    });
};

/**
 * Serialise array for form so to send it to filters
 *
 * @param target
 * @param ignoresesskey
 * @returns {unknown[]|boolean}
 */
const getFilterData = (target, ignoresesskey) => {
    var data = jQuery(target).serializeArray();
    // Check sesskey (if not ignore request).
    var sesskeyconfirmed = false;
    var filterarray = [];
    var sortarray = [];
    data.forEach((d) => {
        if (d.name === 'sesskey') {
            sesskeyconfirmed = d.value === Config.sesskey;
        } else {
            if (d.value) {
                const filtername = d.name.match(/^filter_(.+)/);
                if (filtername) {
                    filterarray.push({
                        'fieldsn': filtername[1],
                        'value': d.value
                    });
                }
            }
        }
    });
    const sortfield = data.find((d) => d.name === 'sort');
    if (sortfield && sortfield.value) {
        const sortfieldspec = sortfield.value.match(/^(.+)-(.+)/);
        if (sortfieldspec) {
            sortarray.push({
                'order': sortfieldspec[2],
                'field': sortfieldspec[1]
            });
        }
    }
    if (sesskeyconfirmed || ignoresesskey) {
        return {
            filters: filterarray,
            sorts: sortarray
        };
    }
    return null;
};
