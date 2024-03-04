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
 * This file contains tranversal methods for Uvirtual managemet .
 *
 * @package   local_uvirtual
 * @copyright 2022 Oscar Nadjar (oscar.nadjar@uvirtual.org)
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

use invalid_parameter_exception;

defined('MOODLE_INTERNAL') || die();
require_once($CFG->dirroot . "/course/format/uvirtual/lib.php");

function local_uvirtual_get_users_count($year = null) {

    global $DB;

    $users = $DB->get_records('user', ['suspended' => 0, 'deleted' => 0], '', 'id, timecreated');
    $usercount = [];
    foreach ($users as $user) {
        $usercreatedyear = date('Y', $user->timecreated);
        if (!isset($usercount[$usercreatedyear])) {
            $usercount[$usercreatedyear]['total'] = 1;
            $usercount[$usercreatedyear]['year'] = $usercreatedyear;
        } else {
            $usercount[$usercreatedyear]['total']++;
        }
    }
    if (!empty($year)) {
        if (isset($usercount[$year])) {
            return $usercount[$year];
        } else {
            return ['year' => $year, 'total' => 0];
        }
    }
    $respose = [];
    $respose['totalcount'] = count($users);
    $respose['detail'] = array_values($usercount);;

    return $respose;
}

function local_uvirtual_get_activities_by_uvid($activities) {
    $modmappings = [
        'tracked_lecture' => 'readings',
        'video_class' => 'videoCapsules',
        'gradable_quiz' => 'formativeAssessments',
        'gradable_assign' => 'assignments',
    ];
    $activiesResult = new stdClass();
    $readings = [];
    $videoCapsules = [];
    $formativeAssessments = [];
    $assignment = [];
    foreach ($activities as $activity) {
        if ($activity['uvid'] == 'gradable_quiz' || ($activity['uvid'] == 'gradable_assign' && trim($activity['name']) !== 'Reto de Aprendizaje')) {
            $formativeAssessments[] = $activity;
        } else if ($activity['uvid'] == 'tracked_lecture') {
            $readings[] = $activity;
        } else if ($activity['uvid'] == 'video_class') {
            $videoCapsules[] = $activity;
        } else if ($activity['uvid'] == 'gradable_assign' && trim($activity['name']) == 'Reto de Aprendizaje') {
            $assignment[] = $activity;
        }
        if (!isset($week[$modmappings[$activity['id']]])) {
            $week[$modmappings[$activity['id']]] = [$activity];
        } else {
            $week[$modmappings[$activity['id']]][] = $activity;
        }
        $week['gradeWeek'] += (float)$activity['grade'];
    }
    $activiesResult->readings = $readings;
    $activiesResult->videoCapsules = $videoCapsules;
    $activiesResult->formativeAssessments = $formativeAssessments;
    $activiesResult->assignment = $assignment;
    $activiesResult->gradeWeek = $week['gradeWeek'];

    return $activiesResult;
}

function local_uvirtual_get_data_previous_and_next_courses($courseid) {
    global $DB;

        $currentCourse = $DB->get_record('course', ['id' => $courseid]);

      
        if (!isset($currentCourse->id)) {
            throw new invalid_parameter_exception('El curso no existe en la base de datos.');
        }
        $coursePrevious = '';
        $courseNext = '';   
        
        if ( strlen($currentCourse->shortname) == 11) {
            $sql = "SELECT * FROM {course} WHERE category = :categoryid AND enddate < :startdate ORDER BY startdate DESC LIMIT 1";
        
            $params = array('categoryid' => $currentCourse->category, 'startdate' => $currentCourse->startdate);

            $coursePrevious = $DB->get_record_sql($sql, $params);

            $sql = "SELECT * FROM {course} WHERE category = :categoryid AND startdate > :enddate ORDER BY startdate ASC LIMIT 1";
            
            $params = array('categoryid' => $currentCourse->category, 'enddate' => $currentCourse->enddate);

            $courseNext = $DB->get_record_sql($sql, $params);

           
        } else {

            $courseGroup = substr($currentCourse->shortname, -1);
            
            //Para el caso del curso previo
            $sql = "SELECT * FROM {course} WHERE category = :categoryid AND enddate < :startdate ORDER BY startdate DESC";
        
            $params = array('categoryid' => $currentCourse->category, 'startdate' => $currentCourse->startdate);
            
            $coursesPrevious = $DB->get_records_sql($sql, $params);

            foreach ($coursesPrevious as $course) {
               
                if (substr($course->shortname, -1) == $courseGroup)
                {
                    $coursePrevious = $course;
                    break ;
                 }
            }

            //Para el caso de los cursos próximos
            $sql = "SELECT * FROM {course} WHERE category = :categoryid AND startdate > :enddate ORDER BY startdate ASC";
            
            $params = array('categoryid' => $currentCourse->category, 'enddate' => $currentCourse->enddate);

            $coursesNext = $DB->get_records_sql($sql, $params);
           
            foreach ($coursesNext as $course) {
                if (substr($course->shortname, -1) == $courseGroup)
                 {
                    $courseNext = $course;
                    break ;
                 }
            }
        }
        
        $response = new stdClass();

        $coursePreviousData = new stdClass();
        $courseNextData = new stdClass();
      
        $coursePreviousData = local_uvirtual_get_data_course($coursePrevious);
        $courseNextData = local_uvirtual_get_data_course($courseNext);

        $response->coursePrevios = $coursePreviousData ;
        $response->courseNext = $courseNextData;

    return $response;

}

function local_uvirtual_get_data_course($course) {
    
    $courseData = new stdClass();
    $courseData->id = $course->id; 
    $courseData->shortname = $course->shortname; 
    $courseData->fullname = $course->fullname; 
    $courseData->startdate = $course->startdate; 
    $courseData->enddate = $course->enddate; 

    return $courseData;
}