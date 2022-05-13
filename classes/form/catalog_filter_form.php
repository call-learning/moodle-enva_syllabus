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

namespace local_envasyllabus\form;

use core_customfield\field;
use core_customfield\field_controller;

class catalog_filter_form extends \moodleform {

    const FIELDS_FILTERS = [
        'uc_annee',
        'uc_semestre',

    ];
    const FIELDS_SORT = [
        'startdate',
        'enddate',
    ];

    protected function definition() {
        $mform = $this->_form;
        $mform->addElement('header', get_string('catalog:filter', 'local_envasyllabus'));
        foreach (self::FIELDS_FILTERS as $cfname) {
            $mform->addElement('select', $cfname,
                get_string('cf:' . $cfname, 'local_envasyllabus'),
                $this->get_customfield_choices($cfname)
            );
            $mform->setType($cfname, PARAM_ALPHAEXT);
        }
        $mform->addElement('header', get_string('catalog:sort', 'local_envasyllabus'));
        foreach (self::FIELDS_SORT as $sorttype) {
            $sorttypes = [];
            foreach (['asc', 'dsc'] as $sortorder) {
                $sorttypes["$sorttype-$sortorder"] = get_string('sort' . $sorttype . strtolower($sortorder), 'local_envasyllabus');
            }
            $mform->addElement('select', $sorttype,
                get_string('sort:' . $sorttype, 'local_envasyllabus'),
                $sorttypes
            );
            $mform->setType($cfname, PARAM_ALPHAEXT);
        }
        $this->add_action_buttons(null, get_string('search'));
    }

    protected function get_customfield_choices($cfsname) {
        $field = field::get_record(['shortname' => $cfsname]);
        $controller = field_controller::create($field->get('id'));
        if (method_exists($controller, 'get_options_array')) {
            return \customfield_select\field_controller::get_options_array($controller);
        };
        return [];
    }
}