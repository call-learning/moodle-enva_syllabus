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
namespace local_envasyllabus\output;

use core_course\external\course_summary_exporter;
use core_customfield\category;
use core_customfield\category_controller;
use core_customfield\field;
use local_competvetsuivi\matrix\matrix;
use moodle_exception;
use renderable;
use renderer_base;
use stdClass;

/**
 * Course Syllabus renderable implementation
 *
 * @package     local_envasyllabus
 * @category    admin
 * @copyright   2022 CALL Learning - Laurent David <laurent@call-learning>
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class course_syllabus implements renderable, \templatable {

    /**
     * @var int $courseid course id
     */
    protected $courseid = 0;

    /**
     * @var int $mode display mode
     */
    protected $mode = 0;

    /**
     * Display the course in FULL syllabus mode
     */
    const DISPLAY_FULL = 0;

    /**
     * Display the course in simplified version syllabus mode
     */
    const DISPLAY_SIMPLIFIED = 1;

    /**
     * Constructor
     *
     * @param $courseid
     */
    public function __construct(int $courseid, int $mode = self::DISPLAY_FULL) {
        $this->courseid = $courseid;
        $this->mode = $mode;
    }

    /**
     * Export the course data for template
     *
     * @param renderer_base $output
     * @return array|stdClass|void
     */
    public function export_for_template(renderer_base $output) {
        global $DB;
        $contextdata = new stdClass();
        $course = $DB->get_record('course', ['id' => $this->courseid]);
        $context = \context_course::instance($this->courseid);
        $csexporter = new course_summary_exporter($course, array('context' => $context));
        $contextdata->coursedata = $csexporter->export($output);

        // Get custom field info.
        $handler = \core_customfield\handler::get_handler('core_course', 'course');
        $cfdata = $handler->get_instance_data($this->courseid);
        foreach ($cfdata as $cfdatacontroller) {
            $customfields[$cfdatacontroller->get_field()->get('shortname')] = $cfdatacontroller->get_value();
        }
        $manageremail = trim($customfields[self::CF_MANAGER_EMAIL_NAME]);
        if ($manageremail) {
            $manager = \core_user::get_user_by_email($manageremail);
            $contextdata->manager = new stdClass();
            $contextdata->manager->fullname = fullname($manager);
            $contextdata->manager->email = $manageremail;
        }
        $contextdata->headerdata = [];
        foreach (self::CF_HEADER_DEFINITION as $fieldname => $fieldinfo) {
            $headerinfo = new stdClass();
            $headerinfo->class = $fieldinfo['class'] ?? '';
            $headerinfo->title = get_string($fieldinfo['languagestring'], 'local_envasyllabus');
            $headerinfo->value = empty($customfields[$fieldname]) ? '-' : $customfields[$fieldname];
            $headerinfo->icon = $fieldinfo['icon'] ?? '';
            $contextdata->headerdata[] = $headerinfo;
        }
        $contextdata->teachers = [];
        [$where, $params] = $DB->get_in_or_equal(['responsablecourse', 'editingteacher']);
        $teacherroles = $DB->get_fieldset_select('role', 'id', 'archetype ' . $where, $params);
        $teachers = get_role_users($teacherroles, \context_course::instance($this->courseid));
        foreach ($teachers as $teacheruser) {
            $teacher = new stdClass();
            $teacher->userpicture = $output->user_picture($teacheruser, array('class' => 'userpicture'));

            $teacher->userfullname = fullname($teacheruser);
            $teacher->useremail = obfuscate_mailto($teacheruser->email, '');
            $contextdata->teachers = $teacher;
        }
        $contextdata->summary = format_text($contextdata->coursedata->summary, $contextdata->coursedata->summaryformat);
        return $contextdata;
    }

    protected function get_graph_for_course($uename) {
        $matrix = new matrix($matrixid);
        try {
            $ue = $matrix->get_matrix_ue_by_criteria('shortname', $uename);
        } catch (moodle_exception $e) {
            return '';
        }

        $compidparamname = \local_competvetsuivi\renderable\uevscompetency_details::PARAM_COMPID;
        $currentcompid = optional_param($compidparamname, 0, PARAM_INT);
        $currentcomp = null;
        if ($currentcompid) {
            $currentcomp = $matrix->comp[$currentcompid];
        }

        $progressoverview = new \local_competvetsuivi\renderable\uevscompetency_details(
            $matrix,
            $ue->id,
            $currentcomp
        );

        $renderer = $PAGE->get_renderer('local_competvetsuivi');
        $text = \html_writer::div($renderer->render($progressoverview), "container-fluid w-75");
    }
    /**
     * Manager email
     */
    const CF_MANAGER_EMAIL_NAME = 'uc_responsable';

    /**
     * Cfield definition
     */
    const CF_HEADER_DEFINITION = [
        'uc_departement' => ['languagestring' => 'syllabuspage:departement', 'class' => 'highlighted'],
        'uc_heures_cours_etudiant' => ['languagestring' => 'syllabuspage:student_totalh', 'class' => 'highlighted'],
        'uc_heures_td_etudiant' => ['languagestring' => 'syllabuspage:student_tdh'],
        'uc_heures_tp_etudiant' => ['languagestring' => 'syllabuspage:student_tph'],
        'uc_heures_tpa_etudiant' => ['languagestring' => 'syllabuspage:student_tpah'],
        'uc_heures_fmp_etudiant' => ['languagestring' => 'syllabuspage:student_fmph'],
        'uc_heures_tpers_etudiant' => ['languagestring' => 'syllabuspage:student_tpersh'],
        'uc_heures_aas_etudiant' => ['languagestring' => 'syllabuspage:student_aash'],
        'uc_ects' => ['languagestring' => 'syllabuspage:student_ects', 'icon' => 'ects', 'class' => 'highlighted']
    ];
}