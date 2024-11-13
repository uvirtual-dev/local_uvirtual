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
use external_value;
use course_info;
use dml_exception;
use invalid_parameter_exception;
use moodle_exception;
use Exception;

defined('MOODLE_INTERNAL') || die;

require_once($CFG->libdir . "/externallib.php");
require_once($CFG->dirroot . "/blocks/grade_overview/classes/course_info.php");
require_once($CFG->dirroot . "/course/format/lib.php");
require_once($CFG->dirroot . "/local/uvirtual/lib.php");

class get_courses_basic_info extends external_api
{
    /**
     * Returns description of method parameters
     *
     * @return external_function_parameters
     * @since Moodle 2.2
     */
    public static function execute_parameters(): external_function_parameters
    {
        return new external_function_parameters(
            [
                'typeCourse' => new external_value(PARAM_TEXT, 'uvirtual course type', VALUE_DEFAULT, ''),
                'roleIdsTeachers' => new external_multiple_structure(
                    new external_value(PARAM_TEXT, 'Role ids tutors', VALUE_DEFAULT, ''), 'Roles Ids', VALUE_DEFAULT, []),
                'roleIdsStudents' => new external_multiple_structure(
                    new external_value(PARAM_TEXT, 'Role ids students', VALUE_DEFAULT, ''), 'Students Ids', VALUE_DEFAULT, []),
                'activeCourses' => new external_value(PARAM_BOOL, 'active', VALUE_DEFAULT, 0),
                'pastCourses' => new external_value(PARAM_BOOL, 'past', VALUE_DEFAULT, 0),
                'timeFilter' => new external_value(PARAM_INT, 'Filter', VALUE_DEFAULT, 0),
                'nextCourses' => new external_value(PARAM_BOOL, 'Filters courses starting 3 month on the future', VALUE_DEFAULT, 0)
            ]
        );
    }

    /**
     * Return the categories tree.
     *
     * @param $typecourse
     * @param $roleidstutors
     * @param $roleidsstudents
     * @param $activecourses
     * @param $pastcourses
     * @param $timefilter
     * @param $nextcourses
     * @return string
     * @throws dml_exception
     * @throws invalid_parameter_exception
     * @throws moodle_exception
     * @since Moodle 2.2
     */
    public static function execute($typecourse, $roleidstutors, $roleidsstudents, $activecourses, $pastcourses, $timefilter, $nextcourses): string
    {
        global $DB, $CFG;

        $params = [
            'typeCourse' => $typecourse,
            'roleIdsTeachers' => $roleidstutors,
            'roleIdsStudents' => $roleidsstudents,
            'activeCourses' => $activecourses,
            'pastCourses' => $pastcourses,
            'timeFilter' => $timefilter,
            'nextCourses' => $nextcourses
        ];

        $params = self::validate_parameters(self::execute_parameters(), $params);

        $typeCourse = $params['typeCourse'];
        $roleidstutors = $params['roleIdsTeachers'];
        $roleidsstudents = $params['roleIdsStudents'];
        $activecourses = $params['activeCourses'];
        $pastcourses = $params['pastCourses'];
        $timefilter = $params['timeFilter'];
        $nextcourses = $params['nextCourses'];

        if (empty($typeCourse)) {
            $typeCourse = empty($timefilter) ? 'all' : 'Regular';
        }

        $currenttime = time();
        $timesql = "startdate < $currenttime AND enddate > $currenttime";

        if (empty($activecourses) && !empty($pastcourses)) {
            $timestart = $currenttime - DAYSECS * 90;
            $timesql = "startdate >= $timestart AND enddate <= $currenttime";
        }

        if (empty($activecourses) && !empty($timefilter)) {
            $timesql = "startdate >= $timefilter";
        }

        if (empty($activecourses) && empty($timefilter) && !empty($nextcourses)) {
            $timeend = $currenttime + DAYSECS * 90;
            $timesql = "startdate >= $currenttime AND enddate <= $timeend";
        }

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
            // $coursesinfo[$courseid]->teachers = array_values(course_info::get_course_tutor($courseinfo->id, $teachersfields, $roleidstutors));           
            $teachers = array_values(course_info::get_course_tutor($courseinfo->id, $teachersfields, $roleidstutors));
            $newArrayTeacher = [];
            foreach ($teachers as $teacher) {
                $pictureUrl = local_uvirtual_get_picture_profile_for_template($teacher);
                $newArrayTeacher[] = [
                    "id" => $teacher->id,
                    "firstname" => $teacher->firstname,
                    "lastname" => $teacher->lastname,
                    "email" => $teacher->email,
                    "imgprofile" => $pictureUrl,
                ];
            }
            $coursesinfo[$courseid]->teachers = $newArrayTeacher;

            $courseprogram = json_decode(local_uvirtual_identify_course_program($courseinfo->shortname));
            $coursesinfo[$courseid]->programid = $courseprogram->idprograma;

            $studentsfields = 'u.id, u.firstname as firstName, u.lastname as lastName, u.email, ul.timeaccess as lastAccess, gg.finalgrade as grade';
            $studentsEnrollments = array_values(course_info::get_course_students($courseid, 0, $studentsfields, $roleidsstudents));

            // Get information for actas
            $students_internal = local_uvirtual_get_students_by_course($courseinfo);
            $details = local_uvirtual_get_last_course_actas_by_course($courseinfo, $students_internal, true);

            if (!empty($details)) {
                $courseinfo->totalGradeActa = (int)$details[0]['sum10'];
                $courseinfo->createdAtActa = (int)$details[0]['created_at'];
                $acta_id = $details[0]['acta_id'];
                $courseinfo->url = $CFG->wwwroot . "/blocks/grade_overview/download.php?id=$courseid&group=0&op=d&dataformat=pdf&teacher=0&actaid=$acta_id&download=true";
            } else {
                $courseinfo->totalGradeActa = '';
                $courseinfo->createdAtActa = '';
                $courseinfo->url = '';
            }

            $statusActa = [];
            $status = [];
            $finalStatus = false;

            // Iterate students
            foreach ($studentsEnrollments as $student) {

                // Get student details
                $grade = array_filter($details, function ($detail) use ($student) {
                    return $detail['id_usuario'] == $student->id;
                });

                $grade = array_shift($grade);

                $gradeMoodle = (float)$student->grade;
                $roundGrade = number_format(round($gradeMoodle, 2), 2, '.', '');

                // Validate if student has grade
                if (empty($grade)) {
                    $statusActa[] = false;
                    $status[] = false;
                } else {
                    $statusActa[] = true;
                    $status[] = $grade['status'] ?? false;
                }
            }

            // Validate if all students have grade
            if (!in_array(false, $statusActa, true) && !in_array(false, $status, true)) {
                $finalStatus = true;
            }

            $courseinfo->statusActa = $finalStatus;
            $courseinfo->students = count($studentsEnrollments);

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
     * @return external_value
     * @since Moodle 2.2
     */
    public static function execute_returns(): external_value
    {
        return new external_value(PARAM_TEXT, 'JSON object', VALUE_OPTIONAL);
    }

    /**
     * Filter courses by type.
     *
     * @param $courses
     * @param $type
     * @return array
     * @throws dml_exception
     * @throws moodle_exception
     */
    public static function filter_courses_by_type($courses, $type): array
    {
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
