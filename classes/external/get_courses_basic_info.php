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


class get_courses_basic_info extends external_api {

    /**
     * Returns description of method parameters
     *
     * @return external_function_parameters
     * @since Moodle 2.2
     */
    public static function execute_parameters() {
        return new external_function_parameters(
            [
                'typeCourse' => new external_value(PARAM_TEXT, 'uvirtual course type', VALUE_REQUIRED, ''),
                'roleIdsTeachers' =>  new external_multiple_structure(
                    new external_value(PARAM_TEXT, 'Role ids tutors', VALUE_DEFAULT, ''), 'Roles Ids', VALUE_DEFAULT, []),
                'activeCourses' => new external_value(PARAM_BOOL, 'active', VALUE_DEFAULT, 0),
                'timeFilter' => new external_value(PARAM_INT, 'Filter', VALUE_DEFAULT, 0),
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
    public static function execute($typecourse, $roleidstutors, $activecourses, $timefilter) {
        global $DB;
        $params = [
            'typeCourse'  => $typecourse,
            'roleIdsTeachers' => $roleidstutors,
            'activeCourses' => $activecourses,
            'timeFilter' => $timefilter
        ];
        $params = self::validate_parameters(self::execute_parameters(), $params);
        $typeCourse = $params['typeCourse'];
        $roleidstutors = $params['roleIdsTeachers'];
        $activecourses = $params['activeCourses'];
        $timefilter = $params['timeFilter'];

        if (empty($typeCourse)) {
            $typeCourse = empty($timefilter) ? 'all' : 'Regular';
        }
        
        $currenttime = time();
        $timesql = "timestart < $currenttime AND timeend > $currenttime";
        if (empty($activecourses) && !empty($timefilter)) {
            $timesql = "timestart >= $timefilter";
        }

        $sql = "SELECT id, shortname, startdate, enddate,  
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
            $coursesinfo[$courseid]->teachers = array_values(course_info::get_course_tutor($courseinfo->id, $teachersfields, $roleidstutors));           
            $ahora = time();
            if ($ahora > $courseinfo->startdate && $ahora < $courseinfo->enddate) {
                $format = \course_get_format($courseinfo->id);
                $formatname = $format->get_format();
                if ($formatname == 'weeks' || $formatname == 'uvirtual') {
                    $sections = $format->get_sections();
                    foreach ($sections as $section) {
                        $date = $format->get_section_dates($section);
                        if ($ahora > $date->start && $ahora < $date->end) {
                            $coursesinfo[$courseid]->currentWeek = $section->section;
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
