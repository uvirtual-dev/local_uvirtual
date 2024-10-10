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

use course_info;
use external_api;
use external_function_parameters;
use external_multiple_structure;
use external_value;
use context_system;
use coding_exception;
use dml_exception;
use invalid_parameter_exception;
use user_picture;

defined('MOODLE_INTERNAL') || die;
require_once($CFG->libdir . "/externallib.php");
require_once($CFG->dirroot . "/grade/querylib.php");
require_once($CFG->libdir . '/gradelib.php');
require_once($CFG->dirroot . "/course/format/uvirtual/lib.php");

class get_user_info extends external_api
{
    /**
     * Returns description of method parameters
     *
     * @return external_function_parameters
     * @since Moodle 2.2
     */
    public static function execute_parameters(): external_function_parameters
    {
        return new external_function_parameters([
            'filter' => new external_value(PARAM_TEXT, 'Filter', VALUE_DEFAULT, ''),
            'getCourses' => new external_value(PARAM_INT, 'Get courses', VALUE_DEFAULT, 0),
            'roleIdStudents' => new external_multiple_structure(
                new external_value(PARAM_INT, 'Role ids tutors', VALUE_DEFAULT, 0), 'Roles Ids', VALUE_DEFAULT, []),
            'roleIdTeachers' => new external_multiple_structure(
                new external_value(PARAM_INT, 'Role ids others', VALUE_DEFAULT, 0), 'Roles Ids', VALUE_DEFAULT, [])
        ]);
    }

    /**
     * Execute the service
     *
     * @param $filter
     * @param $getcourses
     * @param $roleidstudents
     * @param $roleidteachers
     * @return string
     * @throws coding_exception
     * @throws dml_exception
     * @throws invalid_parameter_exception
     */
    public static function execute($filter, $getcourses, $roleidstudents, $roleidteachers): string
    {
        global $DB;

        // Set params
        $params = [
            'filter' => $filter,
            'getCourses' => $getcourses,
            'roleIdStudents' => $roleidstudents,
            'roleIdTeachers' => $roleidteachers,
        ];

        // Validate parameters
        $params = self::validate_parameters(self::execute_parameters(), $params);

        $filter = !empty($params['filter']) ? $params['filter'] : '';
        $getCourses = !empty($params['getCourses']) ? $params['getCourses'] : false;
        $roleIdStudents = !empty($params['roleIdStudents']) ? $params['roleIdStudents'] : [];
        $roleIdTeachers = !empty($params['roleIdTeachers']) ? $params['roleIdTeachers'] : [];

        // Get users
        $sql = "SELECT id, firstname as firstName, lastname as lastName, email
                  FROM {user}
                 WHERE CONCAT(firstname, ' ', lastname, ' ', email) LIKE '%$filter%'";

        // Get users
        $users = $DB->get_records_sql($sql);

        // If no users found, search by words
        if (empty($users)) {
            foreach (explode(' ', $filter) as $word) {
                $sql .= " OR CONCAT(firstname, ' ', lastname, ' ', email) LIKE '%$word%'";
            }
            $users = $DB->get_records_sql($sql);
        }

        // Context level
        $contextlvl = CONTEXT_COURSE;

        // Get role id students
        if (!empty($roleIdStudents)) {
            [$insql, $inparams] = $DB->get_in_or_equal($roleIdStudents);
        } else {
            $insql = '<> ?';
            $inparams = [0];
        }

        // If getCourses is true, get courses
        if ($getCourses) {

            // Get courses
            $fields = 'c.id, c.shortname as shortName, c.fullname as fullName, c.startdate as startDate, c.enddate as endDate';

            // SQL query
            $sql = "SELECT $fields
                      FROM {course} c
                      JOIN {context} ctx ON ctx.instanceid = c.id
                      JOIN {role_assignments} ra ON ra.contextid = ctx.id
                     WHERE ctx.contextlevel = $contextlvl
                       AND ra.userid = ? AND ra.roleid $insql
                       ORDER BY c.startdate DESC";

            // Iterate users
            foreach ($users as $index => $user) {

                // Get courses
                $courses = $DB->get_records_sql($sql, array_merge([$user->id], $inparams));

                // Iterate courses
                foreach ($courses as $id => $course) {

                    $courses[$id]->grade = grade_get_course_grade($user->id, $course->id)->grade;
                    $courses[$id]->currentWeek = format_uvirtual_get_course_current_week($course)[0];
                    $userlastacces = $DB->get_record('user_lastaccess', ['userid' => $user->id, 'courseid' => $course->id]);
                    $courses[$id]->lastAccess = $userlastacces->timeaccess;
                    $courses[$id]->userBlock = local_uvirtual_get_role_by_course_and_user($user->id, $course->id);
                    $teacherfields = 'u.id, u.firstname as firstName, u.lastname as lastName, u.email';

                    // Get teachers
                    $teachers = array_values(course_info::get_course_tutor($course->id, $teacherfields, $roleIdTeachers));

                    // Get teacher image
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
     * @return external_value
     * @since Moodle 2.2
     */
    public static function execute_returns(): external_value
    {
        return new external_value(PARAM_TEXT, 'JSON object', VALUE_OPTIONAL);
    }

    /**
     * @param $userid
     * @return string
     * @throws coding_exception
     * @throws dml_exception
     */
    public static function get_user_picture($userid): string
    {
        global $PAGE;

        // Set context
        if (empty($PAGE->context)) {
            $syscontext = context_system::instance();
            $PAGE->set_context($syscontext);
        }

        // Get user picture
        $users = user_get_users_by_id([$userid]);

        // Get first user
        $user = reset($users);

        // Get user picture
        $user_picture = new user_picture($user);

        // Return user picture
        return $user_picture->get_url($PAGE)->out(false);
    }
}
