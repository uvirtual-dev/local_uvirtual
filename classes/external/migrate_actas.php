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
 * @copyright 2024 Miguel Velasquez (m.a.velasquez@uvirtual.org)
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

class migrate_actas extends external_api
{
    /**
     * @return external_function_parameters
     */
    public static function execute_parameters(): external_function_parameters
    {
        return new external_function_parameters([
            'courses' => new external_value(PARAM_TEXT, 'Courses SHORTNAMES', VALUE_DEFAULT, ''),
            'all' => new external_value(PARAM_BOOL, 'All courses', VALUE_DEFAULT, false),
            'dev' => new external_value(PARAM_BOOL, 'Get JSON', VALUE_DEFAULT, false),
            'delete' => new external_value(PARAM_BOOL, 'Delete Table', VALUE_DEFAULT, false),
        ]);
    }

    /**
     * @param $courses
     * @param $all
     * @param $dev
     * @param $delete
     * @return string
     * @throws dml_exception
     * @throws invalid_parameter_exception
     */
    public static function execute($courses, $all, $dev, $delete): string
    {
        global $DB;

        // Set params
        $params = [
            'courses' => $courses,
        ];

        // Validate parameters
        self::validate_parameters(self::execute_parameters(), $params);

        // Get values
        $arr_courses = explode(',', $courses);

        // Get courses by shortname
        if ($all) {
            $courses = $DB->get_records('course');
        } else {
            $courses = $DB->get_records_list('course', 'shortname', $arr_courses);
        }

        // Check if delete
        if ((!empty($dev) && $dev === true) && (!empty($delete) && $delete === true)) {
            $DB->execute("UPDATE {course_actas} SET information = NULL WHERE information IS NOT NULL");
        }

        $answers = [];

        // Iterate courses
        foreach ($courses as $course) {

            // SQL get actas
            $sql = "SELECT * FROM {course_actas} WHERE courseid = :courseid AND information IS NULL";

            // Get actas
            $actas = $DB->get_records_sql($sql, ['courseid' => $course->id]);

            // Iterate actas
            foreach ($actas as $acta) {

                // Validate if information is null
                if ($acta->information === null) {

                    // Get information
                    $informations = local_uvirtual_parse_html_table(base64_decode($acta->data));

                    $insert = [];
                    $validate = true;

                    // Iterate information
                    foreach ($informations as $information) {

                        // SQL to get user
                        $sql = "SELECT id, lastname, firstname FROM {user} WHERE lastname LIKE :lastname AND deleted = 0";

                        // Get user
                        $users = $DB->get_records_sql($sql, ['lastname' => '%' . $information[1] . '%']);

                        // Check if user exists
                        switch (true) {
                            case count($users) == 0:
                                $validate = false;
                                break;
                            case count($users) == 1:
                                // Get user
                                $users = array_shift($users);
                                $insert[] = [
                                    'id' => $users->id,
                                    'grade10' => (int)$information[3],
                                    'grade100' => $information[4],
                                    'status' => $information[5],
                                ];
                                break;
                            case count($users) > 1:
                                // Find user
                                $user_result = array_filter($users, function ($user) use ($information) {
                                    return strpos($user->firstname, $information[2]) !== false;
                                });

                                // Check if user exists
                                if (!empty($user_result)) {
                                    // Get user
                                    $user_result = array_shift($user_result);
                                    $insert[] = [
                                        'id' => $user_result->id,
                                        'grade10' => (int)$information[3],
                                        'grade100' => $information[4],
                                        'status' => $information[5],
                                    ];
                                } else {
                                    $validate = false;
                                }
                                break;
                        }
                    }

                    // Check if validate
                    if ($validate) {

                        // Update acta
                        $acta->information = json_encode($insert);
                        $DB->update_record('course_actas', $acta);

                        $answers[] = [
                            'course' => $course->id,
                            'shortname' => $course->shortname,
                            'acta_id' => $acta->id,
                            'insert' => true,
                        ];
                    } else {
                        $answers[] = [
                            'course' => $course->id,
                            'shortname' => $course->shortname,
                            'acta_id' => $acta->id,
                            'insert' => false,
                        ];
                    }
                }
            }
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