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

use moodle_url;
use renderable;
use renderer_base;
use templatable;

/**
 * Language switcher widget class
 *
 * @package     local_envasyllabus
 * @category    admin
 * @copyright   2022 CALL Learning - Laurent David <laurent@call-learning>
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class language_switcher implements renderable, templatable {

    /**
     * Parameter name.
     */
    const LANG_PARAMETER_NAME = 'curlang';

    /**
     * @var moodle_url $currenturl
     */
    private $currenturl;

    /**
     * @var string $currentlang
     */
    private $currentlang;

    /**
     * Constructor
     */
    public function __construct() {
        global $FULLME;
        $this->currentlang = optional_param(self::LANG_PARAMETER_NAME, '', PARAM_LANG);
        $currenturl = new moodle_url($FULLME);
        $currenturl->remove_params([self::LANG_PARAMETER_NAME]);
        $this->currenturl = $currenturl;
    }

    /**
     * Export for template
     *
     * @param renderer_base $output
     * @return array|\stdClass
     */
    public function export_for_template(renderer_base $output) {
        $enlangurl = new moodle_url($this->currenturl);
        $enlangurl->param(self::LANG_PARAMETER_NAME, 'en');
        $pixicon = new \pix_icon('i/languages', get_string('syllabus:lang:label', 'local_envasyllabus'), 'local_envasyllabus');
        $pixiconout = $output->render($pixicon);
        $singleselect = new \single_select($this->currenturl, self::LANG_PARAMETER_NAME, [
            'fra' => get_string('syllabus:lang:system', 'local_envasyllabus'),
            'en' => get_string('syllabus:lang:english', 'local_envasyllabus')
        ], $this->currentlang, null
        );
        $singleselect->set_label($pixiconout);
        return $singleselect->export_for_template($output);
    }

    /**
     * Set current language
     *
     * @return void
     */
    public function set_lang() {
        global $SESSION;
        $this->previouslang = $SESSION->lang;
        if ($this->currentlang) {
            $SESSION->lang = $this->currentlang;
        } else {
            unset($SESSION->lang);
        }
    }

    /**
     * Reset the current language
     *
     * @return void
     */
    public function reset_lang() {
        global $SESSION;
        unset($SESSION->lang);
        if (!empty($this->previouslang)) {
            $SESSION->lang = $this->previouslang;
        }
    }

    /**
     * Get current lang code to target the right customfields
     *
     * @return string
     */
    public function get_current_langcode(): string {
        return $this->currentlang == 'fra' ? '' : $this->currentlang;
    }
}
