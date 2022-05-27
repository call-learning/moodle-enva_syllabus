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

use context_course;
use context_helper;
use context_system;
use core_course\external\course_summary_exporter;
use core_course_external;
use external_api;
use external_description;
use external_files;
use external_format_value;
use external_function_parameters;
use external_multiple_structure;
use external_single_structure;
use external_value;
use moodle_url;

/**
 * External services
 *
 * @package     local_envasyllabus
 * @copyright   2022 CALL Learning - Laurent David <laurent@call-learning>
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class get_filtered_courses extends external_api
{
    const TYPE_CFIELD = 'coursefield';
    const FULL_TEXT_SEARCH = 'fulltext';

    /**
     * Returns description of method parameters
     *
     * @return external_function_parameters
     */
    public static function execute_parameters()
    {
        return new external_function_parameters(
            [
                'rootcategoryid' => new external_value(PARAM_INT, 'root category id'),
                'criteria' =>
                    new external_multiple_structure(
                        new \external_single_structure(
                            [
                                'type' => new external_value(PARAM_ALPHA, 'search type'),
                                'value' => new external_value(PARAM_RAW, 'seach value (json enconded)'),
                            ]
                        )
                    )
            ]
        );
    }

    /**
     * Get courses
     *
     * @param int $rootcategoryid
     * @param array $criteria It contains a list of search criteria
     * @return array
     */
    public static function execute($rootcategoryid, $criteria)
    {
        $params = self::validate_parameters(self::execute_parameters(),
            [
                'rootcategoryid' => $rootcategoryid,
                'criteria' => $criteria
            ]);
        self::validate_context(context_system::instance());
        // First we get all courses matching criteria.
        // Then we filter by visibility...
        $category = \core_course_category::get($params['rootcategoryid']);
        // Get all courses from this category.
        $coursesid = $category->get_courses(['recursive' => true, 'idonly' => true]);
        // Now feed it back to the get_courses.
        $courses = core_course_external::get_courses_by_field(
                'ids',
                join(',', $coursesid)
        );
        // Filter out course ID = 1.
        $courses = array_filter($courses['courses'], function ($c) {
            return $c['id'] != SITEID;
        });
        // Now filter by the criterias.
        $filteredcourse = [];

        foreach ($courses as $c) {
            $cobject = (object) $c;
            $addcourse = true;
            foreach ($params['criteria'] as $criterion) {
                switch ($criterion->type) {
                    case static::TYPE_CFIELD:
                        $search = json_decode($criterion->value);
                        if (!empty($cobject->{$search->field})) {
                            $addcourse = $addcourse && ($cobject->{$search->field} == $search->value);
                            $addcourse = $addcourse && ($cobject->{$search->field} == $search->value);
                        }
                        break;
                    case static::FULL_TEXT_SEARCH:

                        break;
                }
            }
            if ($addcourse) {
                $listelement  = new \core_course_list_element($cobject);
                $cobject->courseimageurl = (new moodle_url('/local/envasyllabus/pix/nocourseimage.jpg'))->out();
                $overviewfiles = $listelement->get_course_overviewfiles();
                if($overviewfiles) {
                    $file = array_shift($overviewfiles);
                    $cobject->courseimageurl = moodle_url::make_pluginfile_url($file->get_contextid(), $file->get_component(),
                        $file->get_filearea(), null, $file->get_filepath(),
                        $file->get_filename())->out(false);
                }
                $filteredcourse[] = $cobject;
            }
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
                    'shortname' => new external_value(PARAM_RAW, 'course short name'),
                    'categoryid' => new external_value(PARAM_INT, 'category id'),
                    'categoryname' => new external_value(PARAM_RAW, 'category name'),
                    'sortorder' => new external_value(PARAM_INT, 'Sort order in the category', VALUE_OPTIONAL),
                    'summary' => new external_value(PARAM_RAW, 'summary'),
                    'summaryformat' => new external_format_value('summary'),
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
