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

use core_reportbuilder\local\aggregation\count;
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


class get_user_course_info extends external_api {

    /**
     * Returns description of method parameters
     *
     * @return external_function_parameters
     * @since Moodle 2.2
     */
    public static function execute_parameters() {
        return new external_function_parameters(
            [
                'studentId' => new external_value(PARAM_TEXT, 'Student ID', VALUE_DEFAULT, ''),
                'courseId' => new external_value(PARAM_TEXT, 'Course ID', VALUE_DEFAULT, '')
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
    public static function execute($studentid, $courseid) {
        global $DB;
        $params = [
            'courseId'  => $courseid,
            'studentId'  => $studentid
        ];
        $params = self::validate_parameters(self::execute_parameters(), $params);
        $courseid = $params['courseId'];
        $studentid = $params['studentId'];
        $coursedata = \course_info::get_course_activities($courseid, false, true);
        $activities = $coursedata['activities'];

        $gradableatvs = [];

        foreach ($activities as $index => $activity) {
            $gradeitem = grade_get_grade_items_for_activity((object)$activity, true);
            if (!empty($gradeitem)) {
                $gradableatvs[] = $activity;
            }
        }
        $format = course_get_format($courseid);
        $sections = [];
        $semana = 0;
        $fullgrade = 0.00;
        $ahora = time();
        $currentweeks = 0.;
        foreach ($gradableatvs as $atv) {
            $section = $atv['section'];
            $formatname = $format->get_format();
            if ($formatname == 'weeks' || $formatname == 'uvirtual') {
                $sectiondate = $format->get_section_dates($section, $courseid);
                if (!isset($sections[$atv['section']])) {
                    $semana++;
                    if($sectiondate->start <= $ahora) {
                        $currentweeks++;
                    }
                    $sections[$atv['section']] =
                        [
                            'numberWeek' => $semana,
                            'dateStart' => $sectiondate->start,
                            'dateEnd' => $sectiondate->end,
                        ];
                }
            }
            
            $gradeitem = \grade_user_management::get_user_mod_grade($studentid, $atv['instance'], $atv['type'], $courseid);

            $gradeplit =  !empty($gradeitem->str_long_grade) ? explode('/', $gradeitem->str_long_grade) : [0,0];
            $maxgrade =  number_format((float)$gradeplit[1], 2, '.', '');
            $gradeuser = number_format((float)$gradeitem->grade, 2, '.', '');

            $sections[$atv['section']]['activities'][] = [
                'id' => $atv['id'],
                'name' => $atv['name'],
                'type' => $atv['type'],
                'grade' => $gradeuser,
                'objetive' => $maxgrade,
                'status' => !empty($gradeitem->datesubmitted) || !empty($gradeitem->dategraded)
           ];
           $fullgrade += (float)$gradeuser;
        }
        $response = [];

        $category = \grade_category::fetch(array('courseid' => $courseid, 'fullname' => 'Videoconferencias'));
        $vdgrade = 0;
        if (!empty($category)) {
            $gradeitems = \grade_item::fetch_all(array('courseid' => $courseid, 'categoryid' => $category->id));
            foreach ($gradeitems as $gradeitem) {
                $vdgrade += $gradeitem->get_grade($studentid, true)->finalgrade;
            }
        }
        $user = reset(user_get_users_by_id([$studentid]));
        $response['student'] = [
            'firstname' => $user->firstname,
            'lastname' => $user->lastname,
            'email' => $user->email
        ];
        $totalgrade = number_format($fullgrade + (float)$vdgrade, 2, '.', '');

        $response['totals'] = [
            'totalCourseStudent' => number_format($fullgrade, 2, '.', ''),
            'totalCourseTotal' => $currentweeks * (100 / $semana),
            'videoconferencesStudent' => $vdgrade,
            'videoconferencesTotal' => 5,
            'finalGradeStudent' => ($totalgrade) <= 100 ? $totalgrade: 100,
            'finalGradeCourse' => 100
        ];
        $response['weeks'] = array_values($sections);
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
