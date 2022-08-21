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

/**
 * Simple visibility helper for custom fields
 *
 * @package     local_envasyllabus
 * @category    admin
 * @copyright   2022 CALL Learning - Laurent David <laurent@call-learning>
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class visibility {

    /**
     * Publicly visible fields
     */
    public const PUBLIC_SYLLABUS_FIELDS = [
        'uc_nombre',
        'uc_titre',
        'uc_summary',
        'uc_summary_en',
        'uc_acronyme',
        'uc_ects',
        'uc_annee',
        'uc_semestre',
        'uc_departement',
        'uc_heures_cm_etudiant',
        'uc_heures_td_etudiant',
        'uc_heures_tp_etudiant',
        'uc_heures_tpa_etudiant',
        'uc_heures_tc_etudiant',
        'uc_heures_fmp_etudiant',
        'uc_heures_he_aas_etudiant',
        'uc_heures_he_tpers_etudiant',
        'uc_competences',
        'uc_competences_en'
    ];

    /**
     * Check if this field can be visible for current user
     *
     * @param string $shortname
     * @return bool
     */
    public static function is_customfield_visible(string $shortname): bool {
        $visible = true;
        if (isguestuser() || !isloggedin()) {
            $publicfieldlist = get_config('local_envasyllabus', 'publicfields');
            if (empty($publicfieldlist)) {
                $publicfieldlist = self::PUBLIC_SYLLABUS_FIELDS;
            } else {
                $publicfieldlist = explode(',', $publicfieldlist);
            }

            $visible = in_array($shortname, $publicfieldlist);
        }
        return $visible;
    }
}
