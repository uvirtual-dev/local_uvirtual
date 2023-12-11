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

use external_api;
use external_function_parameters;
use external_multiple_structure;
use external_single_structure;
use external_value;

defined('MOODLE_INTERNAL') || die;
require_once($CFG->libdir . "/externallib.php");

class get_user_info extends external_api {

    /**
     * Returns description of method parameters
     *
     * @return external_function_parameters
     * @since Moodle 2.2
     */
    public static function execute_parameters() {
        return new external_function_parameters(
            [
                'filter' => new external_value(PARAM_TEXT, 'Filter', VALUE_DEFAULT, ''),
                'getCourses' => new external_value(PARAM_BOOL, 'Get courses', VALUE_DEFAULT, false),
                'roleIdStudents' => new external_value(PARAM_INT, 'Student role id', VALUE_DEFAULT, 0),
                'roleIdTeachers' => new external_value(PARAM_INT, 'Teacher role id', VALUE_DEFAULT, 0),
            ]
        );
    }

    /**
     * Return the categories tree.
     *
     * @throws invalid_parameter_exception
     * @param string $filter
     * @param bool $getcourses
     * @param int $roleidstudents
     * @param int $roleidteachers
     * @return array An array of arrays
     * @since Moodle 2.2
     */
    public static function execute($filter, $getcourses, $roleidstudents, $roleidteachers) {
        global $DB;
        $params = [
            'filter'  => $filter,
            'getCourses' => $getcourses,
            'roleIdStudents' => $roleidstudents,
            'roleIdTeachers' => $roleidteachers,
        ];
        $params = self::validate_parameters(self::execute_parameters(), $params);
        $filter = !empty($params['filter']) ? $params['filter'] : '';
        $getCourses = !empty($params['getCourses']) ? $params['getCourses'] : false;
        $roleIdStudents = !empty($params['roleIdStudents']) ? $params['roleIdStudents'] : [];
        $roleIdTeachers = !empty($params['roleIdTeachers']) ? $params['roleIdTeachers'] : [];


        $sql = "SELECT id, firstname as firstName, lastname as lastName, email
                  FROM {user}
                 WHERE CONCAT(firstname, ' ', lastname, ' ', email) LIKE '%$filter%'";


        $users = $DB->get_records_sql($sql);
        if (empty($users)) {
            foreach (explode(' ', $filter) as $word) {
                $sql .=  " OR CONCAT(firstname, ' ', lastname, ' ', email) LIKE '%$word%'";
            }
            $users = $DB->get_records_sql($sql);
        }

        if ($getCourses) {
            $fields = 'c.id, c.shortname as shortName, c.fullname as fullName, c.startdate as startDate, c.enddate as endDate';
            $sql = "SELECT $fields
                      FROM {course} c
                      JOIN {context} ctx ON ctx.instanceid = c.id
                      JOIN {role_assignments} ra ON ra.contextid = ctx.id
                     WHERE ra.userid = ? AND ra.roleid = ?";

            foreach ($users as $index => $user) {
                $courses = $DB->get_records_sql($sql, [$user->id, $roleIdStudents]);
                foreach ($courses as $id => $course) {
                    $courses[$id]->grade = grade_get_course_grade($user->id, $course->id);
                    $courses[$id]->currentWeek = format_uvirtual_get_course_current_week($course);
                    $teacherfields = 'u.id, u.firstname as firstName, u.lastname as lastName, u.email';
                    $teachers = \course_info::get_course_tutor($course->id, $teacherfields, $roleIdTeachers);
                    $courses[$id]->teachers = $teachers;
                }
                $users[$index]->courses = array_values($courses);
            }
        }


        return json_encode(array_values($users));
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
