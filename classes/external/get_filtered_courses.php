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

use cache;
use context_course;
use context_system;
use core_course_category;
use Exception;
use external_api;
use external_description;
use external_function_parameters;
use external_multiple_structure;
use external_single_structure;
use external_value;
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
     * Get courses
     *
     * @param int $rootcategoryid
     * @param object|null $currentlang current selected language (en only supported for now)
     * @param array|null $filters It contains a list of search filters
     * @param array $sort sort criteria
     * @return array
     * @throws \invalid_parameter_exception
     * @throws \restricted_context_exception
     */
    public static function execute($rootcategoryid, $currentlang = 'fr', $filters = null, $sort = []) {
        $paramstocheck = [
            'rootcategoryid' => $rootcategoryid,
            'currentlang' => $currentlang
        ];
        if ($filters) {
            $paramstocheck['filters'] = $filters;
        }
        if (!empty($sort)) {
            $paramstocheck['sort'] = $sort;
        }
        $params = self::validate_parameters(self::execute_parameters(), $paramstocheck);
        raise_memory_limit(MEMORY_HUGE);
        self::validate_context(context_system::instance());
        $courses = self::get_courses($params['rootcategoryid']);
        // Remove courses that are not visible to the current user.
        foreach ($courses as $id => $course) {
            $context = context_course::instance($course->id);
            $canupdatecourse = has_capability('moodle/course:update', $context);
            $canviewhiddencourses = has_capability('moodle/course:viewhiddencourses', $context);
            // Check if the course is visible in the site for the user.
            if ($course->visible || $canviewhiddencourses || $canupdatecourse) {
                continue;
            }
            // Now, check if we have access to the course, unless it was already checked.
            try {
                self::validate_context($context);
                continue;
            } catch (Exception $e) {
                // User can not access the course, check if they can see the public information about the course and return it.
                if (core_course_category::can_view_course_info($course)) {
                    continue;
                }
            }
            unset($courses[$id]); // We cannot view the course so, let's remove it.
        }
        // Now the filter.
        $currentlang = $params['currentlang'];
        $filteredcourse = self::filter_courses($courses, $params['filters'], $currentlang);

        // Compute small summary and title depending on current lang.
        foreach ($filteredcourse as $course) {
            $course->displayname = $course->fullname;
            if (!empty($course->customfields['uc_titre_' . $currentlang])) {
                if (!empty($course->customfields['uc_titre_' . $currentlang]['value'])) {
                    $course->displayname = html_to_text($course->customfields['uc_titre_' . $currentlang]['value']);
                }
            }
            $course->smallsummarytext = '';

            if (!empty($course->customfields['uc_summary_' . $currentlang])) {
                $course->smallsummarytext = html_to_text($course->customfields['uc_summary_' . $currentlang]['value']);

                if (strlen($course->smallsummarytext) > static::SMALL_SUMMARY_LENGTH) {
                    $course->smallsummarytext =
                        substr($course->smallsummarytext, 0, static::SMALL_SUMMARY_LENGTH)
                        . "...";
                }
                // Sometimes truncation leads to utf8 related issues.
                $course->smallsummarytext = clean_param($course->smallsummarytext, PARAM_RAW);
            }
        }

        self::sort_courses($filteredcourse, $sort);
        return $filteredcourse;
    }

    /**
     * Returns description of method parameters
     *
     * @return external_function_parameters
     */
    public static function execute_parameters() {
        return new external_function_parameters(
            [
                'rootcategoryid' => new external_value(PARAM_INT, 'root category id'),
                'currentlang' => new external_value(PARAM_ALPHA, 'Current language code', VALUE_DEFAULT, 'fr'),
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
                        VALUE_DEFAULT,
                        []
                    ),
                'sort' =>
                    new external_single_structure(
                        [
                            'field' => new external_value(PARAM_ALPHANUMEXT, 'field type'),
                            'order' => new external_value(PARAM_ALPHA, 'asc or desc'),
                        ],
                        'Sort',
                        VALUE_OPTIONAL
                    ),
            ]
        );
    }

    /**
     * Get all courses
     *
     * @param int $rootcategoryid
     * @return array array of courses
     * @throws \invalid_parameter_exception
     * @throws \moodle_exception
     */
    protected static function get_courses(int $rootcategoryid): array {
        $cache = cache::make('local_envasyllabus', 'filteredcourses');
        if ($courses = $cache->get($rootcategoryid)) {
            return $courses;
        }
        $category = \core_course_category::get($rootcategoryid);
        // Get all courses from this category.
        $categorycourses = $category->get_courses(['recursive' => true, 'coursecontacts' => true]);
        // Filter out course ID = 1.
        if (!empty($categorycourses[SITEID])) {
            unset($categorycourses[SITEID]);
        }
        $courses = [];
        foreach ($categorycourses as $cid => $courselistelement) {
            $course = (object) iterator_to_array($courselistelement->getIterator(), true);
            $course->contextid = $courselistelement->get_context()->id;
            $course->categoryid = $course->category;
            unset($course->category);
            $course->categoryname = static::get_category_name_for_id($course->categoryid);
            $course->courseimageurl = (new moodle_url('/local/envasyllabus/pix/nocourseimage.jpg'))->out();
            $overviewfiles = $courselistelement->get_course_overviewfiles();
            if ($overviewfiles) {
                $file = array_shift($overviewfiles);
                $course->courseimageurl = moodle_url::make_pluginfile_url($file->get_contextid(), $file->get_component(),
                    $file->get_filearea(), null, $file->get_filepath(),
                    $file->get_filename())->out(false);
            }
            $course->managers = array_map(function($manager) {
                return [
                    'id' => $manager->id,
                    'fullname' => $manager->fullname
                ];
            }, $course->managers);
            $courses[$cid] = $course;
        }
        self::map_customfiedls($courses);
        $cache->set($rootcategoryid, $courses);
        return $courses;
    }

    /**
     * Get category name for the given id
     *
     * @param int $categoryid
     * @return mixed|string
     * @throws \moodle_exception
     */
    protected static function get_category_name_for_id(int $categoryid) {
        static $categories = [];
        if (empty($categories[$categoryid])) {
            $category = \core_course_category::get($categoryid);
            $categories[$categoryid] = $category->get_formatted_name();
        }
        return $categories[$categoryid];
    }

    /**
     * Map custom fields
     *
     * @param array $courses
     * @return void
     */
    protected static function map_customfiedls(array &$courses): void {
        $allcustomfields = \core_course\customfield\course_handler::create()->get_instances_data(array_keys($courses), true);
        foreach ($courses as $cid => &$course) {
            $coursecfs = $allcustomfields[$cid] ?? [];
            $course->customfields = [];
            foreach ($coursecfs as $cfdatacontroller) {
                $fieldshortname = $cfdatacontroller->get_field()->get('shortname');
                $ispublicfield = visibility::is_syllabus_public_field($fieldshortname);
                if ($ispublicfield) {
                    $course->customfields[$fieldshortname] = [
                        'type' => $cfdatacontroller->get_field()->get('type'),
                        'value' => $cfdatacontroller->export_value(),
                        'name' => $cfdatacontroller->get_field()->get('name'),
                        'shortname' => $fieldshortname
                    ];
                }
            }
        }
    }

    /**
     * Filter courses through custom fields
     *
     * @param array $courses
     * @param array $filters
     * @param string $currentlang
     * @return array
     * @throws \coding_exception
     */
    protected static function filter_courses(array $courses, array $filters, string $currentlang): array {
        $filteredcourses = [];
        foreach ($courses as $cobject) {
            $addcourse = true;
            $coursecustomfieldsmatcher = [];
            foreach ($cobject->customfields as $cf) {
                $coursecustomfieldsmatcher[$cf['shortname']] = $cf['value'];
            }
            if (!empty($filters)) {
                foreach ($filters as $criterion) {
                    switch ($criterion['type']) {
                        case static::TYPE_CUSTOM_FIELD:
                            $search = $criterion['search'];
                            $searchfield = $search['field'];
                            if (!empty($search['value'])) {
                                if (empty($coursecustomfieldsmatcher[$searchfield])) {
                                    $addcourse = false;
                                }
                                $addcourse = $addcourse && ($coursecustomfieldsmatcher[$searchfield] == $search['value']);
                            }
                            break;
                        case static::FULL_TEXT_SEARCH:
                            // To Do: implement full text search.
                            break;
                    }
                }
            }
            if ($addcourse) {
                $filteredcourses[] = $cobject;
            }
        }
        return $filteredcourses;
    }

    /**
     * Sort courses list by fields
     *
     * @param array $courses passed by reference
     * @param array $sort
     * @return void
     */
    protected static function sort_courses(&$courses, $sort): void {
        if (!empty($sort)) {
            uasort($courses, function($c1, $c2) use ($sort) {
                if (strpos($sort['field'], 'customfield_') === 0) {
                    $sortfieldname = str_replace('customfield_', '', $sort['field']);
                    $c1value = $c1->customfields[$sortfieldname]["value"] ?? '';
                    $c2value = $c2->customfields[$sortfieldname]["value"] ?? '';
                } else {
                    $c1value = $c1->{$sort['field']} ?? '';
                    $c2value = $c2->{$sort['field']} ?? '';
                }
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
                    'smallsummarytext' => new external_value(PARAM_RAW, 'smallsummarytext'),
                    'managers' => new external_multiple_structure(
                        new external_single_structure(
                            array(
                                'id' => new external_value(PARAM_INT, 'contact user id'),
                                'fullname' => new external_value(PARAM_NOTAGS, 'contact user fullname'),
                            )
                        ),
                        'contact users'
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
