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

defined('MOODLE_INTERNAL') || die;
require_once($CFG->libdir . "/externallib.php");
require_once($CFG->dirroot . "/course/format/lib.php");


class get_courses_count_info extends external_api {

    /**
     * Returns description of method parameters
     *
     * @return external_function_parameters
     * @since Moodle 2.2
     */
    public static function execute_parameters() {
        return new external_function_parameters(
            [
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
    public static function execute() {
        global $DB;

        $sql = "SELECT id, fullname as name, shortname as shortName, startdate as startDate, enddate as endDate
                  FROM {course}
                 WHERE  (UNIX_TIMESTAMP(NOW()) > startdate 
                   AND  UNIX_TIMESTAMP(NOW()) < enddate)
                    OR  ((startdate - UNIX_TIMESTAMP(DATE_ADD(NOW(),INTERVAL +90 DAY))) > 0
                   AND  UNIX_TIMESTAMP(DATE_ADD(NOW(),INTERVAL +90 DAY)) > startdate)";

        $activecourses = $DB->get_records_sql($sql);

        // Get Course type options.
        $customfield = $DB->get_record('customfield_field', ['shortname' => 'typecourse']);
        $configdata = json_decode($customfield->configdata, true);
        $options = explode(PHP_EOL, $configdata['options']);
        $types = [];
        foreach ($options as $key => $option) {
            $types[$key]['name'] = str_replace("\r", '', $option);
            $types[$key]['numCourses'] = 0;

        }

        $handler = \core_customfield\handler::get_handler('core_course', 'course');
        foreach ($activecourses as $course) {
            $datas = $handler->get_instance_data($course->id);
            foreach ($datas as $data) {
                if ($data->get_form_element_name() == 'customfield_typecourse') {
                    if (!empty($data->get_value())) {
                        $types[$data->get_value() - 1]['numCourses']++;
                    }
                }
            }
        }



        return json_encode($types);
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
