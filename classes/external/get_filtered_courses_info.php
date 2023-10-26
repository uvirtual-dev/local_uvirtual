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

/**
 * This file contains external functions for Urvirtual .
 *
 * @package   local_uvirtual
 * @copyright 2022 Oscar Nadjar (oscar.nadjar@uvirtual.org)
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_uvirtual\external;

global $CFG;

use external_api;
use external_function_parameters;
use external_multiple_structure;
use external_single_structure;
use external_value;
use course_info;

defined('MOODLE_INTERNAL') || die;
require_once($CFG->libdir . "/externallib.php");
require_once($CFG->dirroot . "/blocks/grade_overview/classes/course_info.php");
require_once($CFG->dirroot . "/course/format/lib.php");


class get_filtered_courses_info extends external_api {

    /**
     * Returns description of method parameters
     *
     * @return external_function_parameters
     * @since Moodle 2.2
     */
    public static function execute_parameters() {
        return new external_function_parameters(
            [
                'filter' => new external_value(PARAM_TEXT, 'Course id', VALUE_REQUIRED, ''),
                'roleIdsTeachers' =>  new external_multiple_structure(
                    new external_value(PARAM_TEXT, 'Role ids tutors', VALUE_DEFAULT, ''), 'Roles Ids', VALUE_DEFAULT, []),
                'roleIdsOthers' =>  new external_multiple_structure(
                    new external_value(PARAM_TEXT, 'Role ids others', VALUE_DEFAULT, ''), 'Roles Ids', VALUE_DEFAULT, [])
            ]
        );
    }

    /**
     * Return the categories tree.
     *
     * @throws invalid_parameter_exception
     * @param array $coursetype Course type to filter
     * @param array $studentid Student id to filter
     * @return array An array of arrays
     * @since Moodle 2.2
     */
    public static function execute($filter, $roleidstutors, $roleidsothers) {
        global $DB;
        $params = [
            'filter'  => $filter,
            'roleIdsTeachers' => $roleidstutors,
            'roleIdsOthers' => $roleidsothers
        ];
        $params = self::validate_parameters(self::execute_parameters(), $params);
        $filter = $params['filter'];
        $roleidstutors = $params['roleIdsTeachers'];
        $roleidsothers = $params['roleIdsOthers'];

        $sql = "SELECT id, fullname as name, shortname as shortName, startdate as startDate, enddate as endDate
                  FROM {course}
                 WHERE fullname LIKE '%$filter%'
                    OR shortname LIKE '%$filter%'
                 ORDER BY startdate DESC";

        $coursesinfo = $DB->get_records_sql($sql);

        $teachersfields = 'u.id, u.firstname as firstName, u.lastname as lastName, u.email';
        $othersfields = 'u.id, u.firstname as firstName, u.lastname as lastName, u.email, r.shortname as rol';

        foreach ($coursesinfo as $courseinfo) {
            $courseinfo->teachers = array_values(course_info::get_course_tutor($courseinfo->id, $teachersfields, $roleidstutors));
            $courseinfo->others = array_values(course_info::get_course_tutor($courseinfo->id, $othersfields, $roleidsothers));
            $courseinfo->status = ($coursesinfo->startDate < time()) && ($coursesinfo->endDate > time());
            $ahora = time();
            if ($ahora > $courseinfo->startdate && $ahora < $courseinfo->enddate) {
                $format = \course_get_format($coursesinfo->id);
                $formatname = $format->get_format();
                if ($formatname == 'weeks' || $formatname == 'uvirtual') {
                    $sections = $format->get_sections();
                    foreach ($sections as $section) {
                        $date = $format->get_section_dates($section, $coursesinfo->id);
                        if ($ahora > $date->start && $ahora < $date->end) {
                            $courseinfo->currentWeek = $section->section;
                        }
                    }
                }
            }
        }


        return json_encode(array_values($coursesinfo));
    }

    /**
     * Returns description of method result value
     *
     * @return external_multiple_structure
     * @since Moodle 2.2
     */
    public static function execute_returns() {
        return new external_value(PARAM_TEXT, 'JSON object', VALUE_OPTIONAL);
    }
}
