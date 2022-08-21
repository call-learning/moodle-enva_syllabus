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

namespace local_envasyllabus\external;

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->libdir . '/externallib.php');
require_once($CFG->dirroot . '/course/externallib.php');

use context_system;
use core_course_external;
use external_api;
use external_description;
use external_files;
use external_format_value;
use external_function_parameters;
use external_multiple_structure;
use external_single_structure;
use external_value;
use local_envasyllabus\setup;
use local_envasyllabus\visibility;
use moodle_url;

/**
 * External services
 *
 * @package     local_envasyllabus
 * @copyright   2022 CALL Learning - Laurent David <laurent@call-learning>
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class get_filtered_courses extends external_api {
    /**
     * Small summary length
     */
    const SMALL_SUMMARY_LENGTH = 120;
    /**
     * Filter type : custom field
     */
    const TYPE_CUSTOM_FIELD = 'customfield';
    /**
     * Filter type : full text
     */
    const FULL_TEXT_SEARCH = 'fulltext';

    /**
     * Returns description of method parameters
     *
     * @return external_function_parameters
     */
    public static function execute_parameters() {
        return new external_function_parameters(
            [
                'rootcategoryid' => new external_value(PARAM_INT, 'root category id'),
                'currentlang' => new external_value(PARAM_ALPHA, 'Current language code',  VALUE_OPTIONAL, ''),
                'filters' =>
                    new external_multiple_structure(
                        new external_single_structure(
                            [
                                'type' => new external_value(PARAM_ALPHA, 'search type'),
                                'search' => new external_single_structure(
                                    [
                                        'field' => new external_value(PARAM_ALPHANUMEXT, 'field name'),
                                        'value' => new external_value(PARAM_RAW, 'field value'),
                                    ]
                                )
                            ]
                        ),
                        'Filters',
                        VALUE_OPTIONAL
                    ),
                'sort' =>
                    new \external_single_structure(
                        [
                            'field' => new external_value(PARAM_ALPHA, 'field type'),
                            'order' => new external_value(PARAM_ALPHA, 'asc or desc'),
                        ],
                        'Sort',
                        VALUE_OPTIONAL
                    ),
            ]
        );
    }

    /**
     * Get courses
     *
     * @param int $rootcategoryid
     * @param object|null $currentlang current selected language (en only supported for now)
     * @param array|null $filters It contains a list of search filters
     * @param object|null $sort sort criteria
     * @return array
     * @throws \invalid_parameter_exception
     * @throws \restricted_context_exception
     */
    public static function execute($rootcategoryid, $currentlang = '', $filters = null, $sort = null) {
        $paramstocheck = [
            'rootcategoryid' => $rootcategoryid,
            'currentlang' => $currentlang
        ];
        if ($filters) {
            $paramstocheck['filters'] = $filters;
        }
        if ($sort) {
            $paramstocheck['sort'] = $sort;
        }
        $params = self::validate_parameters(self::execute_parameters(), $paramstocheck);
        raise_memory_limit(MEMORY_EXTRA);
        self::validate_context(context_system::instance());
        // First we get all courses matching filters.
        // Then we filter by visibility...
        $category = \core_course_category::get($params['rootcategoryid']);
        // Get all courses from this category.
        $coursesid = $category->get_courses(['recursive' => true, 'idonly' => true]);
        // Now feed it back to the get_courses.
        $courses = core_course_external::get_courses_by_field(
            'ids',
            join(',', $coursesid)
        );
        $allcustomfields = \core_course\customfield\course_handler::create()->get_instances_data($coursesid, true);
        // Filter out course ID = 1.
        $courses = array_filter($courses['courses'], function($c) {
            return $c['id'] != SITEID;
        });
        // Now the filter.
        $filteredcourse = [];
        foreach ($courses as $c) {
            $cobject = (object) $c;
            $addcourse = true;
            $coursecustomfieldsmatcher = [];
            $cobject->customfields = [];
            $coursecfs = $allcustomfields[$cobject->id] ?? [];
            $coursecontext = \context_course::instance($cobject->id);
            foreach ($coursecfs as $cfdatacontroller) {
                $coursecustomfieldsmatcher[$cfdatacontroller->get_field()->get('shortname')] = $cfdatacontroller->export_value();
                $fieldshortname = $cfdatacontroller->get_field()->get('shortname');
                $canviewfield = visibility::is_customfield_visible($fieldshortname);
                if ($canviewfield) {
                    $cobject->customfields[$fieldshortname] = [
                        'type' => $cfdatacontroller->get_field()->get('type'),
                        'value' => $cfdatacontroller->export_value(),
                        'name' => $cfdatacontroller->get_field()->get('name'),
                        'shortname' => $fieldshortname
                    ];
                }
            }
            if (!empty($cobject->customfields['uc_titre_'.$currentlang])) {
                if (!empty($cobject->customfields['uc_titre_'.$currentlang]['value'])) {
                    $cobject->displayname = $cobject->customfields['uc_titre_' . $currentlang]['value'];
                }
            }
            if (!empty($params['filters'])) {
                foreach ($params['filters'] as $criterion) {
                    switch ($criterion['type']) {
                        case static::TYPE_CUSTOM_FIELD:
                            $search = $criterion['search'];
                            $searchfield = $search['field'];
                            if (!empty($coursecustomfieldsmatcher[$searchfield])) {
                                $addcourse = $addcourse && ($coursecustomfieldsmatcher[$searchfield] == $search['value']);
                            }
                            break;
                        case static::FULL_TEXT_SEARCH:
                            break;
                    }
                }
            }
            $cobject->smallsummarytext = '';
            if (!empty($cobject->summary) && !empty($cobject->summaryformat) ) {
                $cobject->smallsummarytext = html_to_text(format_text($cobject->summary, $cobject->summaryformat, [
                    'context' => $coursecontext
                ]));
                if (strlen($cobject->smallsummarytext) > static::SMALL_SUMMARY_LENGTH) {
                    $cobject->smallsummarytext =
                        substr($cobject->smallsummarytext, 0, static::SMALL_SUMMARY_LENGTH)
                        . "...";
                }

            }
            if ($addcourse) {
                $listelement = new \core_course_list_element($cobject);
                $cobject->courseimageurl = (new moodle_url('/local/envasyllabus/pix/nocourseimage.jpg'))->out();
                $overviewfiles = $listelement->get_course_overviewfiles();
                if ($overviewfiles) {
                    $file = array_shift($overviewfiles);
                    $cobject->courseimageurl = moodle_url::make_pluginfile_url($file->get_contextid(), $file->get_component(),
                        $file->get_filearea(), null, $file->get_filepath(),
                        $file->get_filename())->out(false);
                }
                $filteredcourse[] = $cobject;
            }
        }
        if ($sort) {
            uasort($filteredcourse, function($c1, $c2) use ($sort) {
                $c1value = $c1->{$sort['field']} ?? '';
                $c2value = $c2->{$sort['field']} ?? '';
                $sortfactor = $sort['order'] == 'asc' ? 1 : -1;
                if (is_string($c1value) && is_string($c2value)) {
                    return strcmp($c1value, $c2value) * $sortfactor;
                }
                if (is_int($c1value) && is_int($c2value)) {
                    return ($c1value < $c2value) ? -$sortfactor : $sortfactor;
                }
                return 0;
            });
        }
        return $filteredcourse;
    }

    /**
     * Returns description of method result value
     *
     * @return external_description|external_multiple_structure
     */
    public static function execute_returns() {
        return new external_multiple_structure(
            new external_single_structure(
                [
                    'id' => new external_value(PARAM_INT, 'course id'),
                    'fullname' => new external_value(PARAM_RAW, 'course full name'),
                    'displayname' => new external_value(PARAM_RAW, 'course display name'),
                    'visible' => new external_value(PARAM_BOOL, 'is course visible', VALUE_OPTIONAL, false),
                    'shortname' => new external_value(PARAM_RAW, 'course short name'),
                    'categoryid' => new external_value(PARAM_INT, 'category id'),
                    'categoryname' => new external_value(PARAM_RAW, 'category name'),
                    'sortorder' => new external_value(PARAM_INT, 'Sort order in the category', VALUE_OPTIONAL),
                    'summary' => new external_value(PARAM_RAW, 'summary'),
                    'summaryformat' => new external_format_value('summary'),
                    'smallsummarytext' => new external_value(PARAM_RAW, 'smallsummarytext'),
                    'summaryfiles' => new external_files('summary files in the summary field', VALUE_OPTIONAL),
                    'overviewfiles' => new external_files('additional overview files attached to this course'),
                    'contacts' => new external_multiple_structure(
                        new external_single_structure(
                            array(
                                'id' => new external_value(PARAM_INT, 'contact user id'),
                                'fullname' => new external_value(PARAM_NOTAGS, 'contact user fullname'),
                            )
                        ),
                        'contact users'
                    ),
                    'enrollmentmethods' => new external_multiple_structure(
                        new external_value(PARAM_PLUGIN, 'enrollment method'),
                        'enrollment methods list'
                    ),
                    'customfields' => new external_multiple_structure(
                        new external_single_structure(
                            array(
                                'name' => new external_value(PARAM_RAW, 'The name of the custom field'),
                                'shortname' => new external_value(PARAM_RAW,
                                    'The shortname of the custom field - to be able to build the field class in the code'),
                                'type' => new external_value(PARAM_ALPHANUMEXT,
                                    'The type of the custom field - text field, checkbox...'),
                                'value' => new external_value(PARAM_RAW, 'The value of the custom field'),
                            )
                        ), 'Custom fields', VALUE_OPTIONAL),
                    'courseimageurl' => new external_value(PARAM_URL, 'image url', VALUE_OPTIONAL)
                ]
            )
        );
    }

}
