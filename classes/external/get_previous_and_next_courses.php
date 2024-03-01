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

global $CFG;

use external_api;
use external_function_parameters;
use external_multiple_structure;
use external_single_structure;
use external_value;

require_once($CFG->dirroot . "/local/uvirtual/lib.php");

defined('MOODLE_INTERNAL') || die;

class get_previous_and_next_courses extends external_api {

    /**
     * Returns description of method parameters
     * @return external_function_parameters
     */
    public static function execute_parameters() {
        return new external_function_parameters(
            [
                'courseid' => new external_value(PARAM_INT, 'id of course', VALUE_REQUIRED, ''),
         
            ]);
    }

     /**
     * Create groups
     * @param array $groups array of group description arrays (with keys groupname and courseid)
     * @return array of newly created groups
     */
    public static function execute($courseid) {
        $params = [
            'courseid' => $courseid
        ];
        $params = self::validate_parameters(self::execute_parameters(), $params);

        $courseid = $params['courseid'];

        $response = local_uvirtual_get_data_previous_and_next_courses($courseid);

        return $response;
    }

    /**
     * Returns description of method result value
     *
     * @return external_multiple_structure
     * @since Moodle 2.2
     */
    // public static function execute_returns() {
    //     return new external_value(PARAM_RAW, 'JSON object');
    // }
    public static function execute_returns() {
        return new external_single_structure([
            'coursePrevios' => new external_single_structure([  // Use external_single_structure para un solo objeto
                'id' => new external_value(PARAM_INT, 'ID del curso'),
                'shortname' => new external_value(PARAM_TEXT, 'Nombre corto del curso'),
                'fullname' => new external_value(PARAM_TEXT, 'Nombre completo del curso'),
                'startdate' => new external_value(PARAM_INT, 'Fecha de inicio del curso'),
                'enddate' => new external_value(PARAM_INT, 'Fecha de finalización del curso'),
            ]),
            'courseNext' => new external_single_structure([  // Use external_single_structure para un solo objeto
                'id' => new external_value(PARAM_INT, 'ID del curso'),
                'shortname' => new external_value(PARAM_TEXT, 'Nombre corto del curso'),
                'fullname' => new external_value(PARAM_TEXT, 'Nombre completo del curso'),
                'startdate' => new external_value(PARAM_INT, 'Fecha de inicio del curso'),
                'enddate' => new external_value(PARAM_INT, 'Fecha de finalización del curso'),
            ]),
        ]);
    }
}