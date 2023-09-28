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
namespace local_envasyllabus;

use core_customfield\category;
use core_customfield\category_controller;
use core_customfield\field;

/**
 * Plugin administration pages are defined here.
 *
 * @package     local_envasyllabus
 * @category    admin
 * @copyright   2022 CALL Learning - Laurent David <laurent@call-learning>
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class setup {
    /**
     * This sets up the basic parameters for this plugin.
     *
     * This function should stay idempotent in any case (several runs results in the same setup).
     *
     * @param string $fielddefpath
     * @return bool
     */
    public static function install_update(string $fielddefpath):bool {
        if (file_exists($fielddefpath)) {
            $filecontent = file_get_contents($fielddefpath);
            self::create_customfields_fromdef($filecontent);
            return true;
        }
        return false;
    }

    /**
     * Convert the setting customfield_def into an array of custom field.
     *
     * The relevant custom fields will be created or updated.
     * Structure:
     *     name;shortname;type;description;sortorder;categoryname;configdata(json)
     *
     * Example structure:
     *    uc_nombre;Numéro d'UC;text;Un numéro d’UC entre 1 et 1000 (réforme du bac);
     *    1;0;2;"{""required"":""0"",""uniquevalues"":""0"",""defaultvalue"":"""",""displaysize"":50,""maxlength"":1333,
     *    ""ispassword"":""0"",""link"":"""",""locked"":""0"",""visibility"":""2""}"
     *
     * @param string $configtext the list of values/fields to setup in csv format
     * @throws \coding_exception
     * @throws \dml_exception
     * @throws \moodle_exception
     */
    public static function create_customfields_fromdef($configtext):void {
        $configs = explode(PHP_EOL, $configtext);
        $csvheader = str_getcsv(array_shift($configs), ';');
        foreach ($configs as $csvrow) {
            $csvrowarray = str_getcsv($csvrow, ';', '"', "");
            if (count($csvrowarray) != count($csvheader)) {
                debugging("Error: the array should have the same number of columns than the row" . $csvrow);
                continue;
            }
            $field = array_combine($csvheader, $csvrowarray);
            $field = (object) $field;
            $category = category::get_record(['name' => $field->catname, 'component' => 'core_course']);
            if (!$category) {
                // Create it.
                $categoryrecord = (object) [
                    'name' => $field->catname,
                    'component' => 'core_course',
                    'area' => 'course',
                    'itemid' => '0',
                    'sortorder' => category::count_records() + 1,
                    'contextid' => \context_system::instance()->id,
                ];
                $category = category_controller::create(0, $categoryrecord);
                $category->save();
            }
            $categorycontroller = category_controller::create($category->get('id'));
            if ($rfield = field::get_record(['categoryid' => $category->get('id'), 'shortname' => $field->shortname])) {
                unset($field->catname);
                foreach ($field as $fname => $fvalue) {
                    $fvalue = trim($fvalue, '"');
                    $rfield->set($fname, $fvalue);
                }
                $rfield->set('descriptionformat', FORMAT_HTML);
                $rfield->set('categoryid', $category->get('id'));
                $rfield->save();
            } else {
                $rfield = \core_customfield\field_controller::create(0, (object) [
                    'name' => $field->name,
                    'shortname' => $field->shortname,
                    'type' => $field->type,
                    'description' => $field->description,
                    'sortorder' => $field->sortorder,
                    'configdata' => $field->configdata,
                ],
                    $categorycontroller);
                $rfield->save();
            }
        }
    }
}
