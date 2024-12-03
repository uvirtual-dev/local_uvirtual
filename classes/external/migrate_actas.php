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
            'export' => new external_value(PARAM_BOOL, 'Export acta', VALUE_DEFAULT, false),
            'import' => new external_value(PARAM_BOOL, 'Import acta', VALUE_DEFAULT, false),
            'dev' => new external_value(PARAM_BOOL, 'Get JSON', VALUE_DEFAULT, false),
            'delete' => new external_value(PARAM_BOOL, 'Delete Table', VALUE_DEFAULT, false),
        ]);
    }

    /**
     * @param $courses
     * @param $all
     * @param $export
     * @param $import
     * @param $dev
     * @param $delete
     * @return string
     * @throws dml_exception
     * @throws invalid_parameter_exception
     */
    public static function execute($courses, $all, $export, $import, $dev, $delete): string
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

        $answers = [];

        // Get courses by shortname
        if ($all) {
            $courses = $DB->get_records('course');
        } else {
            $courses = $DB->get_records_list('course', 'shortname', $arr_courses);
        }

        $html = '<tr style="background-color: #FFFFFF">
    <td width="34" height="17">1</td>
    <td width="180" style="text-align: left;">Aldana Hernandez</td>
    <td width="180" style="text-align: left;">Sayra Guinette</td>
    <td width="78">82</td>
    <td width="76">8,2</td>
    <td width="90">Aprobado</td>
</tr>
<tr style="background-color: #FFFFFF">
    <td width="34" height="17">2</td>
    <td width="180" style="text-align: left;">Altuna Ramirez</td>
    <td width="180" style="text-align: left;">Mishelle Nicolle</td>
    <td width="78">88</td>
    <td width="76">8,8</td>
    <td width="90">Aprobado</td>
</tr>
<tr style="background-color: #FFFFFF">
    <td width="34" height="17">3</td>
    <td width="180" style="text-align: left;">Arrese Orellana</td>
    <td width="180" style="text-align: left;">Luis Humberto</td>
    <td width="78">73</td>
    <td width="76">7,3</td>
    <td width="90">Aprobado</td>
</tr>
<tr style="background-color: #FFFFFF">
    <td width="34" height="17">4</td>
    <td width="180" style="text-align: left;">Bernal Navarrete</td>
    <td width="180" style="text-align: left;">María José</td>
    <td width="78">90</td>
    <td width="76">9,0</td>
    <td width="90">Aprobado</td>
</tr>
<tr style="background-color: #FFC0CA">
    <td width="34" height="17">5</td>
    <td width="180" style="text-align: left;">Diaz de Leon Martinez</td>
    <td width="180" style="text-align: left;">Carlos</td>
    <td width="78">0</td>
    <td width="76">0,0</td>
    <td width="90">Sin participación</td>
</tr>
<tr style="background-color: #FFFFFF">
    <td width="34" height="17">6</td>
    <td width="180" style="text-align: left;">Dominguez Perez</td>
    <td width="180" style="text-align: left;">Dulce Maria</td>
    <td width="78">86</td>
    <td width="76">8,6</td>
    <td width="90">Aprobado</td>
</tr>
<tr style="background-color: #FFFFFF">
    <td width="34" height="17">7</td>
    <td width="180" style="text-align: left;">Dueñas</td>
    <td width="180" style="text-align: left;">Oscar Alejandro</td>
    <td width="78">85</td>
    <td width="76">8,5</td>
    <td width="90">Aprobado</td>
</tr>
<tr style="background-color: #FFC0CA">
    <td width="34" height="17">8</td>
    <td width="180" style="text-align: left;">Goycoochea</td>
    <td width="180" style="text-align: left;">Héctor</td>
    <td width="78">25</td>
    <td width="76">2,5</td>
    <td width="90">No Aprobado</td>
</tr>
<tr style="background-color: #FFFFFF">
    <td width="34" height="17">9</td>
    <td width="180" style="text-align: left;">Marin Marin</td>
    <td width="180" style="text-align: left;">Margarita Maria</td>
    <td width="78">92</td>
    <td width="76">9,2</td>
    <td width="90">Aprobado</td>
</tr>
<tr style="background-color: #FFFFFF">
    <td width="34" height="17">10</td>
    <td width="180" style="text-align: left;">Medina Barrios</td>
    <td width="180" style="text-align: left;">Lucero Zamarith</td>
    <td width="78">70</td>
    <td width="76">7,0</td>
    <td width="90">Aprobado</td>
</tr>
<tr style="background-color: #FFFFFF">
    <td width="34" height="17">11</td>
    <td width="180" style="text-align: left;">Merino</td>
    <td width="180" style="text-align: left;">Anahi</td>
    <td width="78">78</td>
    <td width="76">7,8</td>
    <td width="90">Aprobado</td>
</tr>
<tr style="background-color: #FFC0CA">
    <td width="34" height="17">12</td>
    <td width="180" style="text-align: left;">Monsalve</td>
    <td width="180" style="text-align: left;">Diana Lucia</td>
    <td width="78">40</td>
    <td width="76">4,0</td>
    <td width="90">No Aprobado</td>
</tr>
<tr style="background-color: #FFFFFF">
    <td width="34" height="17">13</td>
    <td width="180" style="text-align: left;">Ordoñez Ochoa</td>
    <td width="180" style="text-align: left;">Ana Maria</td>
    <td width="78">89</td>
    <td width="76">8,9</td>
    <td width="90">Aprobado</td>
</tr>
<tr style="background-color: #FFFFFF">
    <td width="34" height="17">14</td>
    <td width="180" style="text-align: left;">Perez Puerta Puerta</td>
    <td width="180" style="text-align: left;">Natalia</td>
    <td width="78">88</td>
    <td width="76">8,8</td>
    <td width="90">Aprobado</td>
</tr>
<tr style="background-color: #FFC0CA">
    <td width="34" height="17">15</td>
    <td width="180" style="text-align: left;">Porcel Luna</td>
    <td width="180" style="text-align: left;">Willard</td>
    <td width="78">0</td>
    <td width="76">0,0</td>
    <td width="90">Sin participación</td>
</tr>
<tr style="background-color: #FFFFFF">
    <td width="34" height="17">16</td>
    <td width="180" style="text-align: left;">Sanchez Cordova</td>
    <td width="180" style="text-align: left;">Juan Antonio</td>
    <td width="78">83</td>
    <td width="76">8,3</td>
    <td width="90">Aprobado</td>
</tr>
<tr style="background-color: #FFFFFF">
    <td width="34" height="17">17</td>
    <td width="180" style="text-align: left;">Silvera</td>
    <td width="180" style="text-align: left;">Oscar</td>
    <td width="78">86</td>
    <td width="76">8,6</td>
    <td width="90">Aprobado</td>
</tr>';

        foreach ($courses as $course) {

            // SQL get actas
            $sql = "SELECT * FROM {course_actas} WHERE courseid = :courseid";

            // Get actas
            $actas = $DB->get_records_sql($sql, ['courseid' => $course->id]);

            // Iterate actas
            foreach ($actas as $acta) {

                //$acta->data = base64_encode(serialize($html));
                //$DB->update_record('course_actas', $acta);

                echo base64_decode($acta->data);

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