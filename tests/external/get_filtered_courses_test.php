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

use external_api;

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->dirroot . '/webservice/tests/helpers.php');

/**
 * Tests for the get_filtered_courses class.
 *
 * @package     local_envasyllabus
 * @copyright   2022 CALL Learning - Laurent David <laurent@call-learning>
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers \local_envasyllabus\get_filtered_courses
 */
class get_filtered_courses_test extends \externallib_advanced_testcase {
    /**
     * Max categories
     */
    const MAX_CAT = 5;
    /**
     * Course definition
     */
    const COURSES_DEF = [
        [
            'visible' => 1,
            'category' => 'CAT1',
            'fullname' => 'UC0211 - Anatomie de l\'encolure et du tronc',
            'shortname' => 'UC0211',
            'customfields' => [
                'uc_nombre' => 'UC0211',
                'uc_titre_en' => 'Neck and Trunk Anatomy',
                'uc_acronyme' => 'ANAT ET',
                'uc_annee' => '1',
                'uc_semestre' => '1',
            ]
        ],
        [
            'visible' => 1,
            'category' => 'CAT1',
            'fullname' => 'UC0101 - Fundamentals of Veterinary Medicine',
            'shortname' => 'UC0101',
            'customfields' => [
                'uc_nombre' => 'UC0101',
                'uc_titre_en' => 'Basics of Veterinary Medicine',
                'uc_acronyme' => 'BVM',
                'uc_annee' => '1',
                'uc_semestre' => '1',
            ]
        ],
        [
            'visible' => 1,
            'category' => 'CAT1',
            'fullname' => 'UC0102 - Animal Nutrition and Diet',
            'shortname' => 'UC0102',
            'customfields' => [
                'uc_nombre' => 'UC0102',
                'uc_titre_en' => 'Animal Nutrition and Diet',
                'uc_acronyme' => 'AND',
                'uc_annee' => '1',
                'uc_semestre' => '2',
            ]
        ],
        [
            'visible' => 1,
            'category' => 'CAT1',
            'fullname' => 'UC0103 - Introduction to Veterinary Epidemiology',
            'shortname' => 'UC0103',
            'customfields' => [
                'uc_nombre' => 'UC0103',
                'uc_titre_en' => 'Introduction to Veterinary Epidemiology',
                'uc_acronyme' => 'IVE',
                'uc_annee' => '1',
                'uc_semestre' => '1',
            ]
        ],
        [
            'visible' => 1,
            'category' => 'CAT2',
            'fullname' => 'UC0201 - Small Animal Surgery',
            'shortname' => 'UC0201',
            'customfields' => [
                'uc_nombre' => 'UC0201',
                'uc_titre_en' => 'Small Animal Surgery',
                'uc_acronyme' => 'SAS',
                'uc_annee' => '2',
                'uc_semestre' => '1',
            ]
        ],
        [
            'visible' => 1,
            'category' => 'CAT3',
            'fullname' => 'UC0202 - Large Animal Medicine',
            'shortname' => 'UC0202',
            'customfields' => [
                'uc_nombre' => 'UC0202',
                'uc_titre_en' => 'Large Animal Medicine',
                'uc_acronyme' => 'LAM',
                'uc_annee' => '2',
                'uc_semestre' => '2',
            ]
        ],
        [
            'visible' => 1,
            'category' => 'CAT3',
            'fullname' => 'UC0203 - Veterinary Pharmacology',
            'shortname' => 'UC0203',
            'customfields' => [
                'uc_nombre' => 'UC0203',
                'uc_titre_en' => 'Veterinary Pharmacology',
                'uc_acronyme' => 'VPH',
                'uc_annee' => '2',
                'uc_semestre' => '1',
            ]
        ],
        [
            'visible' => 1,
            'category' => 'CAT3',
            'fullname' => 'UC0104 - Veterinary Microbiology',
            'shortname' => 'UC0104',
            'customfields' => [
                'uc_nombre' => 'UC0104',
                'uc_titre_en' => 'Veterinary Microbiology',
                'uc_acronyme' => 'VMIC',
                'uc_annee' => '1',
                'uc_semestre' => '2',
            ]
        ],
        [
            'visible' => 1,
            'category' => 'CAT1',
            'fullname' => 'UC0105 - Animal Behavior and Welfare',
            'shortname' => 'UC0105',
            'customfields' => [
                'uc_nombre' => 'UC0105',
                'uc_titre_en' => 'Animal Behavior and Welfare',
                'uc_acronyme' => 'ABW',
                'uc_annee' => '1',
                'uc_semestre' => '2',
            ]
        ],
        [
            'visible' => 0,
            'category' => 'CAT3',
            'fullname' => 'UC0204 - Veterinary Pathology',
            'shortname' => 'UC0204',
            'customfields' => [
                'uc_nombre' => 'UC0204',
                'uc_titre_en' => 'Veterinary Pathology',
                'uc_acronyme' => 'VPATH',
                'uc_annee' => '1',
                'uc_semestre' => '1',
            ]
        ]
    ];
    /**
     * @var array $categories all categories
     */
    protected $categories = [];
    /**
     * @var array $courses course lst
     */
    protected $courses = [];

    /**
     * Setup for test
     */
    public function setUp(): void {
        parent::setUp();
        $this->resetAfterTest();
        $this->create_courses_categories();
    }

    /**
     * Create courses and categories
     *
     * @return void
     */
    public function create_courses_categories() {
        $generator = $this->getDataGenerator();
        for ($catindex = 1; $catindex < self::MAX_CAT; $catindex++) {
            $catdef = ['idnumber' => 'CAT' . $catindex];
            if ($catindex > 1 && ($catindex % 2)) {
                $catdef['parent'] = $this->categories['CAT1']->id;
            }
            $category = $this->getDataGenerator()->create_category($catdef);
            $this->categories['CAT' . $catindex] = $category;
        }
        $this->courses = [];
        foreach (self::COURSES_DEF as $cdef) {
            $cdef['category'] = empty($cdef['category']) ? $this->categories['CAT1']->id : $this->categories[$cdef['category']]->id;
            $customfields = $cdef['customfields'];
            $cdef['customfields'] = [];
            foreach ($customfields as $key => $value) {
                $cdef['customfields'][] = [
                    'shortname' => $key,
                    'value' => $value
                ];
            }
            $course = $generator->create_course(
                $cdef
            );
            $this->courses[] = $course;
        }
    }

    /**
     * Test execute API CALL with no instance
     */
    public function test_execute_no_courses() {
        $this->resetAfterTest();
        $this->expectException('require_login_exception');
        $courses = $this->get_filtered_courses(0);
    }

    /**
     * Helper
     *
     * @param mixed ...$params
     * @return mixed
     */
    protected function get_filtered_courses(...$params) {
        $courses = get_filtered_courses::execute(...$params);
        return external_api::clean_returnvalue(get_filtered_courses::execute_returns(), $courses);
    }

    /**
     * Test execute API CALL when login as admin and a simple user
     */
    public function test_get_all_courses() {
        $this->resetAfterTest();
        $this->setAdminUser();
        $courses = $this->get_filtered_courses($this->categories['CAT1']->id);
        $this->assertCount(9, $courses);
        $user = $this->getDataGenerator()->create_user();
        $this->setUser($user);
        $courses = $this->get_filtered_courses($this->categories['CAT1']->id);
        $this->assertCount(8, $courses);
    }

    /**
     * Test execute API CALL to get filtered courses by year
     *
     * @dataProvider filter_dataprovider
     */
    public function test_get_filtered_courses($filters, $expected) {
        $this->resetAfterTest();
        foreach ($expected as $usertype => $expectedcount) {
            if ($usertype == 'admin') {
                $this->setAdminUser();

            } else {
                $user = $this->getDataGenerator()->create_user();
                $this->setUser($user);
            }
            $courses = $this->get_filtered_courses($this->categories['CAT1']->id, 'fr', $filters);
            $this->assertCount($expectedcount, $courses);
        }
    }

    /**
     * Filter data provider
     *
     * @return array[]
     */
    public function filter_dataprovider() {
        return [
            'filter by year A1' => [
                'filters' => [
                    [
                        'type' => 'customfield',
                        'search' =>
                            [
                                'field' => 'uc_annee',
                                'value' => 'A1',
                            ],
                    ],
                ],
                'expected' => [
                    'admin' => 7,
                    'user' => 6
                ]
            ],
            'filter by year A2' => [
                'filters' => [
                    [
                        'type' => 'customfield',
                        'search' =>
                            [
                                'field' => 'uc_annee',
                                'value' => 'A2',
                            ],
                    ],
                ],
                'expected' => [
                    'admin' => 2,
                    'user' => 2
                ]
            ],
        ];
    }
}
