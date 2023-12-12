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
                'week' => new external_value(PARAM_INT, 'Week number', VALUE_DEFAULT, 0)
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
     * @return array An array of arrays
     * @since Moodle 2.2
     */
    public static function execute($studentid, $courseid, $week) {
        global $DB;
        $params = [
            'courseId'  => $courseid,
            'studentId'  => $studentid,
            'week' => 0
        ];
        $params = self::validate_parameters(self::execute_parameters(), $params);
        $courseid = $params['courseId'];
        $studentid = $params['studentId'];
        $week = $params['week'];

        $response = [];
        $course = $DB->get_record('course', ['id' => $courseid], '*', MUST_EXIST);
        $response['courseId'] = $course->id;
        $response['courseName'] = $course->fullname;
        $response['shortName'] = $course->shortname;
        $response['startDate'] = $course->startdate;
        $response['endDate'] = $course->enddate;
        $response['currentWeek'] = format_uvirtual_get_course_current_week($course);
        $teachersfields = 'u.id, u.firstname as firstName, u.lastname as lastName, u.email';
        $response['teachers'] = \course_info::get_course_tutor($course->id, $teachersfields);
        $activities = [];
        $activities = \course_info::get_course_activities($course->id, false, true, false)['activities'];
        $contpend = \course_info::get_course_activities($course->id, false, false, true)['activities'];
        $activities = array_merge($activities, $contpend);
        $activitycontext = format_uvirtual_get_context_for_mod($activities, false, false);
        [$sections, $finalgrade] = format_uvirtual_get_sections_context($activitycontext, $course, $week);
        $weeks = [];
        $modmappings = [
            'tracked_lecture' => 'readings',
            'video_class' => 'videoCapsules',
            'gradable_quiz' => 'formativeAssessments',
            'gradable_assign' => 'learningChallenge',
        ];
        $totalgrade = 0;
        foreach ($sections as $section) {
            $week = ['week' => $section['num'], 'startDate' => $section['datestart'], 'endDate' => $section['dateend']];
            $week['gradeWeek'] = 0;
            foreach ($section['activities'] as $activity) {
                if (!isset($modmappings[$activity['uvid']])) {
                    $week[$modmappings[$activity['uvid']]] = [$activity];
                } else {
                    $week[$modmappings[$activity['uvid']]][] = $activity;
                }
                if ((float)($activity['grade']) > 0) {
                    $week['gradeWeek'] += (float)$activity['grade'];
                }
            }
            $weeks[] = $week;
            $totalgrade += $week['gradeWeek'];
        }
        $response['weeks'] = $weeks;
        $response['totalGrade'] = $finalgrade;

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
}
