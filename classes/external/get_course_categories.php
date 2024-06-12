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

class get_course_categories extends external_api {

    /**
     * Returns description of method parameters
     *
     * @return external_function_parameters
     * @since Moodle 2.2
     */
    public static function execute_parameters() {
        return new external_function_parameters(
            [
                'typeCourse' => new external_value(PARAM_TEXT, 'Course type', VALUE_DEFAULT, ''),
                'userId' => new external_value(PARAM_INT, 'Student id', VALUE_DEFAULT, 0),
                'activeCourse' =>  new external_value(PARAM_BOOL, 'Course is active', VALUE_DEFAULT, false),
                'roleIds' =>  new external_multiple_structure(
                    new external_value(PARAM_TEXT, 'Role id', VALUE_DEFAULT, ''), 'Roles Ids', VALUE_DEFAULT, []),
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
    public static function execute($coursetype, $userid, $activecourse, $roleids) {
        $params = [
            'typeCourse' => $coursetype,
            'userId'  => $userid,
            'activeCourse'  => $activecourse,
            'roleIds'  => $roleids
        ];
        self::validate_parameters(self::execute_parameters(), $params);

        $categories = \core_course_category::get_all();

        $rootCategories = [];
        foreach ($categories as $category) {
            if ($category->parent == 0) {
                $categorydata = self::transform_category($category, $categories, $coursetype, $userid, $activecourse, $roleids);
                if (!empty($categorydata)) {
                    $rootCategories[] = $categorydata;
                }
            }
        }
        usort($rootCategories, function ($a, $b)
        {
            if ($a == $b) {
                return 0;
            }
            return ($a > $b) ? -1 : 1;
        });

        return json_encode(array_values($rootCategories));
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

    public static function get_courses_in_category($category, $recursive, $type, $active = false) {
        global $DB;
        $courses = array();
        $customfield = $DB->get_record('customfield_field', ['shortname' => 'typecourse']);
        $configdata = json_decode($customfield->configdata, true);
        $options = explode(PHP_EOL, $configdata['options']);
        $typeindetifier = null;
        foreach ($options as $key => $option) {
            if (is_int(strpos($option, $type))) {
               $typeindetifier = $key + 1;
                break;
            }
        }
        $handler = \core_customfield\handler::get_handler('core_course', 'course');
        foreach ($category->get_courses(['recursive' => $recursive]) as $course) {
            $add_course = false;
            $datas = $handler->get_instance_data($course->id);
            if ($active) {
                $now = time();
                $timediference = $course->startdate - $now;
                $currentactive = $now > $course->startdate && $now < $course->enddate;
                //$futureactive = ($timediference > 0) && ($timediference < DAYSECS*90);
                if (!($currentactive)) {
                    continue;
                }
            }
            foreach ($datas as $data) {
                if ($data->get_form_element_name() == 'customfield_typecourse') {
                    if (!empty($data->get_value()) && ((int)$data->get_value() == (int)$typeindetifier)) {
                        $add_course = true;
                    }
                }
            }

            if (empty($type) || $add_course) {
                $courses[] = array(
                    'id' => $course->id,
                    'shortName' => $course->shortname,
                    'name' => $course->fullname
                );
            }
        }
        return $courses;
    }

    public static function get_subcategories($category, $allCategories, $typecourse, $userId, $active = false, $roles = []) {
        $subCategories = array();
        foreach ($allCategories as $cat) {
            if ($cat->parent == $category->id) {
                $subCategory = self::transform_category($cat, $allCategories, $typecourse, $userId, $active, $roles);
                if (!empty($subCategory)) {
                    $subCategories[] = $subCategory;
                }
            }
        }
        return $subCategories;
    }

    public static function transform_category($category, $allCategories, $typecourse, $userId, $active = false, $roles = []) {
        // Get courses of current category.
        $courses = self::get_courses_in_category($category, false, $typecourse, $active);
        if (!empty($userId)) {
            // This filters the courses on which the user is enrolled on and the role,
            $courses = array_filter($courses, function ($course) use ($userId, $roles) {
                $isenrolled =  is_enrolled(\context_course::instance($course['id']), $userId);
                if ($isenrolled && !empty($roles)) {
                    $userroles = enrol_get_course_users_roles($course['id'])[$userId];
                    foreach ($roles as $role) {
                        if (isset($userroles[$role])) {
                            return true;
                        }
                    }
                    return false;
                }
                return $isenrolled;
            });
        }

        $subCategories = self::get_subcategories($category, $allCategories, $typecourse, $userId, $active, $roles);

        $notempty = !empty($subCategories) || !empty($courses);
        $category_info = [
            'id' => $category->id,
            'name' => $category->name,
            'courses' => array_values($courses),
            'subCategories' => array_values($subCategories),
            'time' => $category->timemodified
        ];

        return !empty($notempty) ? $category_info : [];
    }
}
