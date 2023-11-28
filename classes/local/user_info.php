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

use core_reportbuilder\local\filters\number;

defined('MOODLE_INTERNAL') || die();

require_once($CFG->dirroot . '/user/lib.php');
require_once($CFG->dirroot . '/blocks/grade_overview/classes/course_info.php');
require_once($CFG->dirroot . '/grade/querylib.php');
require_once($CFG->dirroot . '/lib/grade/grade_item.php');
require_once($CFG->dirroot . '/lib/grade/constants.php');

class user_info
{
    protected $user;

    public function __construct($userid) {
        $this->set_user($userid);
    }

    protected function set_user($userid)
    {
        $this->user = core_user::get_user($userid);
    }

    public function get_user_historico() {
        if (empty($this->user)) {
            return [];
        }

        $historicows = get_config('block_grade_overview', 'historicows');
        $args = [
            'email' => $this->user->email
        ];
        $minimungrade = get_config('block_grade_overview', 'historicomingrade');

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $historicows);
        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $args);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        $response = curl_exec($ch);
        curl_close($ch);

        $userhistorico = json_decode($response, true);

        if (!isset($userhistorico['msg'])) {
            foreach ($userhistorico as $number => $courseinfo) {
                $userhistorico[$number]['number'] = (int)$number + 1;
                $userhistorico[$number]['notapproved'] = (int)$courseinfo['grade'] >= (int)$minimungrade ? '' : 'notapproved';
            }
        } else {
            $userhistorico = [];
        }
        return $userhistorico;
    }

    public function get_mods($gradable = false, $pretty = false, $active = false, $courseid = false, $contpend = false) {
        if (empty($this->user)) {
            return [];
        }
        $activities = [];
        $courses = !empty($courseid) ? [get_course($courseid)] : enrol_get_all_users_courses($this->user->id,true);
        foreach ($courses as $course) {
            $coursedata = \course_info::get_course_activities($course->id, $active, $gradable, $contpend);

            if (empty($activities)) {
                $activities = $coursedata['activities'];
            } else {
                array_merge($activities, $coursedata['activities']);
            }
        }
 
        $activitiesinfo = [];
        foreach ($activities as $atv) {
            $gradeitem = \grade_get_grade_items_for_activity((object)$atv, true);
            $gradeitem = !empty($gradeitem) ?
                \grade_user_management::get_user_mod_grade(
                    $this->user->id, $atv['instance'], $atv['type'], $atv['courseid']) : false;
            $split = !empty($gradeitem->str_long_grade) ? explode('/', $gradeitem->str_long_grade) : [0,0];
            $gradeplit =  count($split) > 1 ? $split: [0,0];
            $maxgrade =  number_format((float)$gradeplit[1], 2, '.', '');
            $gradeuser = number_format((float)$gradeplit[0], 2, '.', '');

            $status = '';
            if ($gradeitem && $gradable) {
                if (!empty($gradeitem->dategraded)) {
                    if (((float)$gradeuser > (((float)$maxgrade)/2))) {
                        $status = 'approved';
                    } else {
                        $status = 'notapproved';
                    }
                } else if (!empty($gradeitem->datesubmitted)) {
                    $status = 'subm';
                } else {
                    $status = 'notsubm';
                }
            } else {
                if ($atv['viewed'] || !empty($gradeitem->dategraded)) {
                    $status = 'viewed';
                } else {
                    $status = 'notviewed';
                }
            }

            $ahora = time();
            if($ahora < $atv['expected'] || !$gradable)
            $activitiesinfo[] = [
                'id' => $atv['id'],
                'name' => $atv['name'],
                'type' => $pretty ? get_string($atv['type'], 'local_uvirtual') : $atv['type'],
                'expected' => $pretty ? date('d M Y', $atv['expected']) : $atv['expected'],
                'startdate' => $pretty ? date('d M Y', $atv['startdate']) : $atv['startdate'],
                'grade' => $gradeuser,
                'objetive' => $maxgrade,
                'url' => $atv['url'],
                'status' => $status,
                'statustext' => get_string($status, 'local_uvirtual')
            ];
        }
        return $activitiesinfo;
    }

    public function get_historico_prom($historico) {
        if (empty($historico) || !is_array($historico)) {
            return [];
        }
        $sum = 0;
        $prom = 0;
        foreach ($historico as $coursesinfo) {
            $sum = $coursesinfo['grade'];
        }
        if(isset($historico)){
            $prom = $sum / count($historico);
        }

        return number_format($prom, 2, '.');
    }

    public function get_complete_courses() {

        if (empty($this->user)) {
            return [];
        }
        $courses = enrol_get_all_users_courses($this->user->id,true, 'fullname, startdate, enddate, shortname');
        $finishedcourses = [];
        $number = 0;
        foreach ($courses as $course) {
            if ($course->enddate < time()) {
                $number++;
                $current['number'] = $number;
                $current['shortname'] = $course->shortname;
                $current['fullname'] = $course->fullname;
                $current['startdate'] = $course->startdate > 0 ? date('d M Y', $course->enddate) : 'Sin fecha';
                $current['enddate'] = $course->enddate > 0 ? date('d M Y', $course->enddate) : 'Sin fecha';
                $finishedcourses[] = $current;
            }
        }
        return $finishedcourses;
    }

    public static function get_activity_uvid($cmid) {
        global $DB;

        $uvidrecord = $DB->get_record('course_modules_custom_fields', ['cmid' => $cmid]);

        return $uvidrecord->uvmodid;
    }
}