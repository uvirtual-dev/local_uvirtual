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
require_once($CFG->dirroot . "/grade/querylib.php");
require_once($CFG->libdir . '/gradelib.php');

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
                'getCourses' => new external_value(PARAM_INT, 'Get courses', VALUE_DEFAULT, 0),
                'roleIdStudents' =>  new external_multiple_structure(
                    new external_value(PARAM_INT, 'Role ids tutors', VALUE_DEFAULT, 0), 'Roles Ids', VALUE_DEFAULT, []),
                'roleIdTeachers' =>  new external_multiple_structure(
                    new external_value(PARAM_INT, 'Role ids others', VALUE_DEFAULT, 0), 'Roles Ids', VALUE_DEFAULT, [])
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
        $contextlvl = CONTEXT_COURSE;
        if (!empty($roleIdStudents)) {
            [$insql, $inparams] = $DB->get_in_or_equal($roleIdStudents);
        } else {
            $insql = '<> ?';
            $inparams = [0];
        }

        if ($getCourses) {
            $fields = 'c.id, c.shortname as shortName, c.fullname as fullName, c.startdate as startDate, c.enddate as endDate';
            $sql = "SELECT $fields
                      FROM {course} c
                      JOIN {context} ctx ON ctx.instanceid = c.id
                      JOIN {role_assignments} ra ON ra.contextid = ctx.id
                     WHERE ctx.contextlevel = $contextlvl
                       AND ra.userid = ? AND ra.roleid $insql
                       ORDER BY c.startdate DESC";
            foreach ($users as $index => $user) {
                $courses = $DB->get_records_sql($sql, array_merge([$user->id], $inparams));
                foreach ($courses as $id => $course) {
                    $courses[$id]->grade = grade_get_course_grade($user->id, $course->id)->grade;
                    $userlastacces = $DB->get_record('user_lastaccess', ['userid' => $user->id, 'courseid' => $course->id]);
                    $courses[$id]->lastAccess = $userlastacces->timeaccess;
                    $teacherfields = 'u.id, u.firstname as firstName, u.lastname as lastName, u.email';
                    $teachers = array_values(\course_info::get_course_tutor($course->id, $teacherfields, $roleIdTeachers));
                    foreach ($teachers as $key => $teacher) {
                        $teachers[$key]->img = self::get_user_picture($teacher->id);
                    }
                    $courses[$id]->teachers = $teachers;
                }
                $users[$index]->img = self::get_user_picture($user->id);
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

    public static function get_user_picture($userid) {
        global $PAGE, $DB;
        if (empty($PAGE->context)) {
            $syscontext = \context_system::instance();
            $PAGE->set_context($syscontext);
        }
        $users = $DB->get_records_list('user', 'id', $userid);;
        $user = reset($users);
        $user_picture = new \user_picture($user);
        $picurl = $user_picture->get_url($PAGE)->out(false);
        return $picurl;
    }
}
