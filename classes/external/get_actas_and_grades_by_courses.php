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

use dml_exception;
use external_api;
use external_function_parameters;
use external_value;
use invalid_parameter_exception;

defined('MOODLE_INTERNAL') || die;

require_once($CFG->libdir . "/externallib.php");
require_once($CFG->dirroot . '/local/uvirtual/lib.php');

class get_actas_and_grades_by_courses extends external_api
{
    /**
     * @return external_function_parameters
     */
    public static function execute_parameters(): external_function_parameters
    {
        return new external_function_parameters([
            'courses' => new external_value(PARAM_TEXT, 'Courses SHORTNAMES', VALUE_DEFAULT, ''),
            'dev' => new external_value(PARAM_BOOL, 'Get JSON', VALUE_DEFAULT, false),
        ]);
    }

    /**
     * @param $courses
     * @param $dev
     * @return string
     * @throws dml_exception
     * @throws invalid_parameter_exception
     */
    public static function execute($courses, $dev): string
    {
        global $DB;

        // Set params
        $params = [
            'courses' => $courses,
        ];

        // Validate parameters
        self::validate_parameters(self::execute_parameters(), $params);

        // Get vlaues
        $arr_courses = explode(',', $courses);

        // Get courses by shortname
        $courses = $DB->get_records_list('course', 'shortname', $arr_courses);

        $answers = [];

        // Iterate courses
        foreach ($courses as $course) {

            // Get students
            $students = local_uvirtual_get_students_by_course($course);

            // Add to answers
            $answers[] = [
                'course_id' => $course->id,
                'shortname' => $course->shortname,
                'fullname' => $course->fullname,
                'actas' => local_uvirtual_get_last_course_actas_by_course(
                    $course,
                    $students
                ),
            ];
        }

        // Check if dev
        if (!empty($dev) && $dev === true) {
            echo json_encode($answers);
            return json_encode([]);
        } else {
            return json_encode(array_values($answers));
        }
    }

    /**
     * Returns information for the actas and grades by course
     *
     * @return external_value
     * @since Moodle 2.2
     */
    public static function execute_returns(): external_value
    {
        return new external_value(PARAM_TEXT, 'JSON object', VALUE_OPTIONAL);
    }

}