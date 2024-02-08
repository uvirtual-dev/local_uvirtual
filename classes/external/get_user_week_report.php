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
require_once($CFG->libdir . '/gradelib.php');
require_once($CFG->dirroot . "/grade/querylib.php");
require_once($CFG->dirroot . "/local/uvirtual/lib.php");

require_once($CFG->dirroot . "/blocks/grade_overview/classes/course_info.php");
require_once($CFG->dirroot . "/blocks/grade_overview/classes/grade_management.php");
require_once($CFG->dirroot . "/course/format/uvirtual/lib.php");

class get_user_week_report extends external_api {

    /**
     * Returns description of method parameters
     *
     * @return external_function_parameters
     * @since Moodle 2.2
     */
    public static function execute_parameters() {
        return new external_function_parameters(
            [
                'studentId' => new external_value(PARAM_INT, 'Student ID', VALUE_REQUIRED),
                'courseId' => new external_value(PARAM_INT, 'Course ID', VALUE_REQUIRED),
                'week' => new external_value(PARAM_INT, 'Week number', VALUE_DEFAULT, 0),
                'roleIdsStudents' =>  new external_multiple_structure(
                    new external_value(PARAM_INT, 'Role ids tutors', VALUE_DEFAULT, 0), 'Roles Ids', VALUE_DEFAULT, []),
                'roleIdsTeachers' =>  new external_multiple_structure(
                    new external_value(PARAM_INT, 'Role ids others', VALUE_DEFAULT, 0), 'Roles Ids', VALUE_DEFAULT, [])
            ]
        );
    }

    /**
     * Return the categories tree.
     *
     * @throws invalid_parameter_exception
     * @param int $coursetype Course type to filter
     * @param int $studentid Student id to filter
     * @param int $week Student id to filter
     * @param array $roleidstudents
     * @param array $roleidteachers
     * @return array An array of arrays
     * @since Moodle 2.2
     */
    public static function execute($studentid, $courseid, $week, $roleidstudents, $roleidteachers) {
        global $DB;
        $params = [
            'courseId'  => $courseid,
            'studentId'  => $studentid,
            'week' => 0,
            'roleIdsStudents' => $roleidstudents,
            'roleIdsTeachers' => $roleidteachers
        ];
        $params = self::validate_parameters(self::execute_parameters(), $params);
        $courseid = $params['courseId'];
        $studentid = $params['studentId'];
        $week = $params['week'];
        $roleidstudents = $params['roleIdsStudents'];
        $roleidteachers = $params['roleIdsTeachers'];

        $response = [];
        $course = $DB->get_record('course', ['id' => $courseid], '*', MUST_EXIST);
        $student = $DB->get_record('user', ['id' => $studentid], '*', MUST_EXIST);

        $response['course'] = [
            'courseId' => (int)$course->id,    
            'courseName' => $course->fullname,
            'shortName' => $course->shortname,
            'startDate' => $course->startdate,
            'endDate' => $course->enddate
        ];
        $response['student'] = [
            'studentId' => (int)$student->id,    
            'email' => $student->email,
            'firstName' => $student->firstname,
            'lastName' => $student->lastname
        ];
        $response['currentWeek'] = format_uvirtual_get_course_current_week($course)[0];
        $teachersfields = 'u.id, u.firstname as firstName, u.lastname as lastName, u.email';
        $response['teachers'] = array_values(\course_info::get_course_tutor($course->id, $teachersfields, $roleidteachers));
        foreach ($response['teachers'] as $key => $teacher) {
            $response['teachers'][$key]->img = self::get_user_picture($teacher->id);

        }
        $activities = [];
        $activities = \course_info::get_course_activities($course->id, false, true, false)['activities'];
        $contpend = \course_info::get_course_activities($course->id, false, false, true)['activities'];
        $activities = format_uvirtual_get_context_for_mod($activities, false, true, false, $studentid);
        $contpend = format_uvirtual_get_context_for_mod($contpend, false, false, false, $studentid);
        $activitycontext = array_merge($activities, $contpend);
        [$sections, $finalgrade] = format_uvirtual_get_sections_context($activitycontext, $course, false);
        $weeks = [];

        $totalgrade = 0;
      
        foreach ($sections as $section) {
            // mtrace(print_r($section), true);
            $week = ['week' => $section['num'], 'startDate' => $section['unixstart'], 'endDate' => $section['unixend']];
            $week['gradeWeek'] = 0.00;
            $activies = local_uvirtual_get_activities_by_uvid($section['activities']);

            $activies->startDate = strval($section['unixstart']);
            $activies->startEnd = strval($section['unixend']);
            $weeks[] = $activies;
            $totalgrade += $week['gradeWeek'];
        }
        $response['weeks'] = $weeks;
        $totalGrade = (float)grade_get_course_grade($studentid, $courseid)->grade;
        $response['totalGrade'] = number_format($totalGrade, 2, '.', '');

        return json_encode($response);
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
        global $PAGE;
        if (empty($PAGE->context)) {
            $syscontext = \context_system::instance();
            $PAGE->set_context($syscontext);
        }
        $users = \user_get_users_by_id([$userid]);
        $user = reset($users);
        $user_picture = new \user_picture($user);
        $picurl = $user_picture->get_url($PAGE)->out(false);
        return $picurl;
    }
}
