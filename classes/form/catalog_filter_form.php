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
        'fullname'
    ];

    const SORT_ORDER = [
        'asc',
        'desc'
    ];

    protected function definition() {
        $mform = $this->_form;
        $mform->addElement('header', get_string('catalog:filter', 'local_envasyllabus'));
        foreach (self::FIELDS_FILTERS as $cfname) {
            $filtername = 'filter_' . $cfname;
            $choices = $this->get_customfield_choices($cfname);
            $mform->addElement('select', $filtername,
                get_string('cf:' . $cfname, 'local_envasyllabus'),
                $choices

            );
            $mform->setType($filtername, PARAM_ALPHAEXT);
        }
        $mform->addElement('header', get_string('catalog:sort', 'local_envasyllabus'));
        $sorttypes = [];
        foreach (self::FIELDS_SORT as $sortfield) {
            foreach (self::SORT_ORDER as $sortorder) {
                $sorttypes["{$sortfield}-{$sortorder}"] = get_string($sortfield, 'local_envasyllabus') . ' '
                    . ' - '. get_string('sortorder' . $sortorder, 'local_envasyllabus');
            }
        }
        $mform->addElement('select', 'sort',
            get_string('sort', 'local_envasyllabus'),
            $sorttypes
        );
        $mform->setType('sort', PARAM_ALPHAEXT);
        $this->add_action_buttons(null, get_string('search'));
    }

    protected function get_customfield_choices($cfsname) {
        $field = field::get_record(['shortname' => $cfsname]);
        $controller = field_controller::create($field->get('id'));
        $options = [];
        if (method_exists($controller, 'get_options_array')) {
            $options = \customfield_select\field_controller::get_options_array($controller);
        };
        $options = array_combine($options, $options);
        return $options;
    }
}