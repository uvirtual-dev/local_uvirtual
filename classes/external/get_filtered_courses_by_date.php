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
 * @copyright 2023 Miguel Velasquez (m.a.velasquez@uvirtual.org)
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
require_once($CFG->dirroot . "/local/uvirtual/lib.php");

class get_filtered_courses_by_date extends external_api {

    /**
     * Returns description of method parameters
     *
     * @return external_function_parameters
     * @since Moodle 2.2
     */
    public static function execute_parameters() {
        return new external_function_parameters(
            [
                'typeCourse' => new external_value(PARAM_TEXT, 'uvirtual course type', VALUE_DEFAULT, 'Regular'),
                'fieldDate' => new external_value(PARAM_TEXT, 'field date filter', VALUE_DEFAULT, 'startdate'),
                'roleIdsTeachers' =>  new external_multiple_structure(
                    new external_value(PARAM_TEXT, 'Role ids tutors', VALUE_DEFAULT, ''), 'Roles Ids', VALUE_DEFAULT, []),
                'timeFilter' => new external_value(PARAM_INT, 'Date in format timestap', VALUE_DEFAULT, 0)
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
    public static function execute($typeCourse, $fieldDate, $roleIdsTeachers, $timeFilter) {
        global $DB;
        $params = [
            'typeCourse'  => $typeCourse,
            'roleIdsTeachers' => $roleIdsTeachers,
            'timeFilter' => $timeFilter,
            'fieldDate' => $fieldDate,
        ];
        $params = self::validate_parameters(self::execute_parameters(), $params);
        $typeCourse = $params['typeCourse'];
        $roleidstutors = $params['roleIdsTeachers'];
        $timeFilter = $params['timeFilter'];
        $fieldDate = $params['fieldDate'];
        $warnings = array();
        $typeCourse = empty($typeCourse) ? 'all' : $typeCourse;
        
        if (empty($timeFilter) || $timeFilter == '') {
            $currenttime = time();
            $timeFilter = $currenttime;
        }
        $timeFilterDate = date('Y-m-d', $timeFilter);

        $timesql = "DATE(FROM_UNIXTIME({$fieldDate})) = '{$timeFilterDate}'";
        $sql = "SELECT id, fullname as name, shortname, startdate, enddate 
                         FROM {course} 
                        WHERE $timesql
                              AND visible = 1";
        
        $courses = $DB->get_records_sql($sql);

        $coursesinfo = [];
        if ($typeCourse == 'all') {
            $coursesinfo = $courses;
        } else {
            $coursesinfo = self::filter_courses_by_type($courses, $typeCourse);
        }
        $teachersfields = 'u.id, u.firstname as firstName, u.lastname as lastName, u.email';

        foreach ($coursesinfo as $courseid => $courseinfo) {

            $teachers = array_values(course_info::get_course_tutor($courseinfo->id, $teachersfields, $roleidstutors));           

            $newArrayTeacher = [];
            foreach ($teachers as $teacher) {
            $pictureUrl = local_uvirtual_get_picture_profile_for_template($teacher); 
            $newArrayTeacher[] =[
                "id"=> $teacher->id,
                "firstname"=> $teacher->firstname,
                "lastname"=> $teacher->lastname,
                "email"=> $teacher->email,
                "imgprofile"=> $pictureUrl,
            ]; 
            }
            $coursesinfo[$courseid]->teachers = $newArrayTeacher;

        }

        return $coursesinfo;
    }

    /**
     * Returns description of method result value
     *
     * @return external_multiple_structure
     * @since Moodle 2.2
     */
    public static function execute_returns() {
        return new external_multiple_structure(
            new external_single_structure(
                [
                    'id' => new external_value(PARAM_INT, 'Course ID'),
                    'name' => new external_value(PARAM_TEXT, 'Course name'),
                    'shortname' => new external_value(PARAM_TEXT, 'Course shortname'),
                    'startdate' => new external_value(PARAM_INT, 'Course start date'),
                    'enddate' => new external_value(PARAM_INT, 'Course end date'),
                    'teachers' => new external_multiple_structure(
                        new external_single_structure(
                            [
                                'id' => new external_value(PARAM_INT, 'Teacher ID'),
                                'firstname' => new external_value(PARAM_TEXT, 'First name'),
                                'lastname' => new external_value(PARAM_TEXT, 'Last name'),
                                'email' => new external_value(PARAM_TEXT, 'Email'),
                                'imgprofile' => new external_value(PARAM_TEXT, 'Profile image URL'),
                            ]
                        )
                    ),
                ]
            )
        );    }

    /**
     * Filter courses by type.
     * 
     * @param array $courses
     * @param string $type
     * @return array
     */
    public static function filter_courses_by_type($courses, $type) {
        global $DB;
        $filtered = [];
        $customfield = $DB->get_record('customfield_field', ['shortname' => 'typecourse']);
        $configdata = json_decode($customfield->configdata, true);
        $options = explode(PHP_EOL, $configdata['options']);
        $typeindetifier = 0;
        foreach ($options as $key => $option) {
            if (is_int(strpos($option, $type))) {
               $typeindetifier = $key + 1;
               break;
            }
        }
        $handler = \core_customfield\handler::get_handler('core_course', 'course');
        foreach ($courses as $course) {
            $datas = $handler->get_instance_data($course->id);
            $add_course = false;
            foreach ($datas as $data) {
                if ($data->get_form_element_name() == 'customfield_typecourse') {
                    if (!empty($data->get_value()) && ((int)$data->get_value() == (int)$typeindetifier)) {
                        $add_course = true;
                        break;
                    }
                }
            }
            if ($add_course) {
                $filtered[$course->id] = $course;
            }
        }
        return $filtered;
    }
}
