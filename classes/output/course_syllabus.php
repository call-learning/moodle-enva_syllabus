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

use context_user;
use core_course\external\course_summary_exporter;
use core_customfield\category;
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
 * @copyright   2022 CALL Learning - Laurent David <laurent@call-learning>
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class course_syllabus implements renderable, \templatable {

    /**
     * Display the course in FULL syllabus mode
     */
    const DISPLAY_FULL = 0;

    /**
     * Display the course in simplified version syllabus mode
     */
    const DISPLAY_SIMPLIFIED = 1;

    /**
     * @var array TEACHER_ROLES_NAME
     */
    const TEACHER_ROLES_NAME = ['editingteacher', 'teacher'];

    /**
     * @var array RESPONSABLE_ROLES_NAME
     */
    const RESPONSABLE_ROLES_NAME = ['responsablecourse'];

    /**
     * Cfield definition
     */
    const CF_HEADER_DEFINITION = [
        ['type' => 'categorysum', 'languagestring' => 'syllabuspage:student_grand_total_hours', 'class' => 'highlighted-top',
            'positionafter' => true,
            'fields' => [
                ['type' => 'categorysum', 'languagestring' => 'syllabuspage:student_total_hours', 'class' => 'highlighted-top',
                    'fields' => [
                        ['type' => 'cf', 'fieldname' => 'uc_heures_cm_etudiant'],
                        ['type' => 'cf', 'fieldname' => 'uc_heures_td_etudiant'],
                        ['type' => 'cf', 'fieldname' => 'uc_heures_tp_etudiant'],
                        ['type' => 'cf', 'fieldname' => 'uc_heures_tpa_etudiant'],
                        ['type' => 'cf', 'fieldname' => 'uc_heures_tc_etudiant'],
                        ['type' => 'cf', 'fieldname' => 'uc_heures_fmp_etudiant'],
                    ]
                ],
                ['type' => 'categorysum', 'languagestring' => 'syllabuspage:student_total_hours_he', 'class' => 'highlighted-top',
                    'fields' => [
                        ['type' => 'cf', 'fieldname' => 'uc_heures_he_aas_etudiant'],

                        ['type' => 'cf', 'fieldname' => 'uc_heures_he_tpers_etudiant'],
                    ]
                ],
            ]
        ],
        ['type' => 'cf', 'fieldname' => 'uc_ects',
            'languagestring' => 'syllabuspage:student_ects', 'icon' => 'ects', 'class' => 'highlighted-top']
    ];

    /**
     * @var int $courseid course id
     */
    protected $courseid = 0;

    /**
     * @var int $mode display mode
     */
    protected $mode = 0;

    /**
     * Constructor
     *
     * @param int $courseid
     * @param int $mode
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
        global $DB, $CFG;
        $currentlang = current_language();
        $contextdata = new stdClass();
        $course = $DB->get_record('course', ['id' => $this->courseid]);
        $context = \context_course::instance($this->courseid);
        $csexporter = new course_summary_exporter($course, array('context' => $context));
        $contextdata->coursedata = $csexporter->export($output);

        // Get custom field info.
        $handler = \core_customfield\handler::get_handler('core_course', 'course');
        $cfdata = $handler->get_instance_data($this->courseid, true);
        foreach ($cfdata as $cfdatacontroller) {
            $customfields[$cfdatacontroller->get_field()->get('shortname')] = $cfdatacontroller->export_value();
        }
        $contextdata->teachers = [];
        $managers = $this->get_teacher_for_course($this->courseid, self::RESPONSABLE_ROLES_NAME);
        $canviewuseridentity = has_capability('moodle/site:viewuseridentity', $context);
        if ($canviewuseridentity) {
            $identityfields = array_flip(explode(',', $CFG->showuseridentity));
        } else {
            $identityfields = array();
        }
        if ($managers) {
            $managernames = [];
            foreach ($managers as $manager) {
                $manageroutput = fullname($manager);

                if (isset($identityfields['email']) && $manager->email) {
                    $email = obfuscate_mailto($manager->email, '');
                    $manageroutput .= " ($email)";
                }
                $managernames[] = $manageroutput;
            }
            $contextdata->managers = join('<span>, </span>', $managernames);
        }
        $contextdata->headerdata = $this->get_header_data(self::CF_HEADER_DEFINITION, $customfields);

        $contextdata->teachers = [];
        $teachers = $this->get_teacher_for_course($this->courseid);
        foreach ($teachers as $teacheruser) {
            $teacher = new stdClass();
            $teacher->userpicture = '';
            $teacher->userfullname = fullname($teacheruser);
            $teacher->useremail = '';
            if (isset($identityfields['email']) && !empty($teacheruser->email)) {
                $teacher->useremail = ' ' . obfuscate_mailto($teacheruser->email, '');
            }
            if (user_can_view_profile($teacheruser, $course)) {
                $teacher->userpicture = $output->user_picture($teacheruser, array('class' => 'userpicture'));
            }
            $contextdata->teachers[] = $teacher;
        }
        $contextdata->summary = $customfields['uc_summary_' . $currentlang] ?? '';
        $matrixid = $customfields['uc_matrix'] ?? get_config('local_envasyllabus', 'defaultmatrixid');
        $contextdata->competencies = (object) [
            'graph' => empty($customfields['uc_nombre']) ? '' :
                $this->get_graph_for_course($customfields['uc_nombre'], $matrixid, $output),
            'description' => $this->get_cf_displayable_info('uc_competences', $cfdata, $output)
        ];
        $contextdata->prerequisites = $this->get_cf_displayable_info('uc_prerequis', $cfdata, $output);
        $contextdata->programme = $this->get_cf_displayable_info('uc_programme', $cfdata, $output);
        $contextdata->vaq = $this->get_cf_displayable_info('uc_validation', $cfdata, $output);
        return $contextdata;
    }

    /**
     * Get users matching the teacher role.
     *
     * @param int $courseid
     * @param array $rolesname
     * @return array
     */
    protected function get_teacher_for_course(int $courseid, array $rolesname = self::TEACHER_ROLES_NAME): array {
        global $DB;
        [$where, $params] = $DB->get_in_or_equal($rolesname);
        $teacherroles = $DB->get_fieldset_select('role', 'id', 'shortname ' . $where, $params);
        if (!empty($teacherroles)) {
            return get_role_users($teacherroles, \context_course::instance($courseid));
        } else {
            return [];
        }
    }

    /**
     * Get header data for course summary
     *
     * @param array $fieldinfolist
     * @param array $customfields
     * @return array
     * @throws \coding_exception
     */
    protected function get_header_data(array $fieldinfolist, array $customfields): array {
        $headerdata = [];
        foreach ($fieldinfolist as $fieldinfo) {
            if (!empty($fieldinfo['type'])) {
                if (empty($fieldinfo['languagestring'])) {
                    $fielddesc = get_string('syllabuspage:' . $fieldinfo['fieldname'], 'local_envasyllabus');
                } else {
                    $fielddesc = get_string($fieldinfo['languagestring'], 'local_envasyllabus');
                }
                $headerinfo = $this->create_header_data(
                    $fieldinfo['class'] ?? '',
                    $fielddesc,
                    $fieldinfo['icon'] ?? '');
                switch ($fieldinfo['type']) {
                    case 'cf':
                        $fieldname = $fieldinfo['fieldname'];
                        $headerinfo->value = empty($customfields[$fieldname]) ? '-' : $customfields[$fieldname];
                        $headerdata[] = $headerinfo;
                        break;
                    case 'categorysum':
                        $subheaders = $this->get_header_data($fieldinfo['fields'], $customfields);
                        $headerinfo->value = $this->get_header_sum($fieldinfo['fields'], $customfields);
                        if (!empty($fieldinfo['positionafter'])) {
                            array_push($headerdata, ...$subheaders);
                            $headerdata[] = $headerinfo;
                        } else {
                            $headerdata[] = $headerinfo;
                            array_push($headerdata, ...$subheaders);
                        }
                }

            }
        }
        return $headerdata;
    }

    /**
     * Create a header data object
     *
     * @param string $class
     * @param string $title
     * @param string $icon
     * @return stdClass
     */
    protected function create_header_data(string $class, string $title, string $icon): stdClass {
        $headerinfo = new stdClass();
        $headerinfo->class = $class;
        $headerinfo->title = $title;
        $headerinfo->value = '';
        $headerinfo->icon = $icon;
        return $headerinfo;
    }

    /**
     * Get header data for course summary
     *
     * @param array $fieldinfolist
     * @param array $customfields
     * @return int
     * @throws \coding_exception
     */
    protected function get_header_sum(array $fieldinfolist, array $customfields): int {
        $total = 0;
        foreach ($fieldinfolist as $fieldinfo) {
            if (!empty($fieldinfo['type'])) {
                switch ($fieldinfo['type']) {
                    case 'cf':
                        $fieldname = $fieldinfo['fieldname'];
                        $total += empty($customfields[$fieldname]) ? 0 : $customfields[$fieldname];
                        break;
                    case 'categorysum':
                        $total += $this->get_header_sum($fieldinfo['fields'], $customfields);
                        break;
                }

            }
        }
        return $total;
    }

    /**
     * Get graph for course
     *
     * @param string $uename
     * @param int $matrixid
     * @param \renderer_base $output
     * @return string
     * @throws \coding_exception
     * @throws \dml_exception
     */
    protected function get_graph_for_course(string $uename, int $matrixid, \renderer_base $output) {
        $matrix = new matrix($matrixid);
        $matrix->load_data();
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

        $progressoverview = new \local_competvetsuivi\renderable\uevscompetency_summary(
            $matrix,
            $ue->id,
            $currentcomp
        );
        $text = \html_writer::div($output->render($progressoverview), "container-fluid w-75");
        return $text;
    }

    /**
     * Get field value
     *
     * @param string $cfname
     * @param array $cfdata
     * @param \renderer_base $output
     * @return mixed
     */
    protected function get_cf_displayable_info(string $cfname, array $cfdata, \renderer_base $output) {
        $cffieldvalue = '';
        foreach ($cfdata as $cfdatacontroller) {
            if ($cfdatacontroller->get_field()->get('shortname') == $cfname) {
                $cffieldvalue = $cfdatacontroller->export_value($output);
            }
        }
        return $cffieldvalue;
    }
}
