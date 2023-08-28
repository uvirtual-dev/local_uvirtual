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

defined('MOODLE_INTERNAL') || die();

require_once($CFG->dirroot . '/user/lib.php');
require_once($CFG->dirroot . '/blocks/grade_overview/classes/course_info.php');
require_once($CFG->dirroot . '/grade/querylib.php');
require_once($CFG->dirroot . '/lib/grade/grade_item.php');
require_once($CFG->dirroot . '/lib/grade/constants.php');



use core_user;

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
        $curl = new curl();

        $libraryws = get_config('block_grade_overview', 'historicows');
        $args = [
            'email' => $this->user->email
        ];
        $minimungrade = get_config('block_grade_overview', 'historicomingrade');
        $userhistorico = json_decode($curl->post($libraryws, $args), true);

        if (!isset($userhistorico['msg'])) {
            foreach ($userhistorico as $number => $courseinfo) {
                $userhistorico[$number]['number'] = (int)$number + 1;
                $userhistorico[$number]['notapproved'] = (int)$courseinfo['grade'] >= (int)$minimungrade ? '' : 'notapproved';
            }
        }
        return $userhistorico;
    }

    public function get_mods($gradable = false, $pretty = false, $active = false, $courseid = false) {
        $activities = [];
        $courses = !empty($courseid) ? [get_course($courseid)] : enrol_get_all_users_courses($this->user->id,true);
        foreach ($courses as $course) {
            $coursedata = \course_info::get_course_activities($course->id, $active);

            if (empty($activities)) {
                $activities = $coursedata['activities'];
            } else {
                array_merge($activities, $coursedata['activities']);
            }
        }

        $gradableatvs = [];
        foreach ($activities as $index => $activity) {
            $gradeitem = \grade_get_grade_items_for_activity((object)$activity, true);
            if (!empty($gradeitem) && $gradable) {
                $gradableatvs[] = $activity;
            }
            if (empty($gradeitem) && !$gradable) {
                $gradableatvs[] = $activity;
            }
        }
        $activitiesinfo = [];
        foreach ($gradableatvs as $atv) {

            $gradeitem = \grade_user_management::get_user_mod_grade($this->user->id, $atv['instance'], $atv['type'], $atv['courseid']);

            $gradeplit =  !empty($gradeitem->str_long_grade) ? explode('/', $gradeitem->str_long_grade) : [0,0];
            $maxgrade =  number_format((float)$gradeplit[1], 2, '.', '');
            $gradeuser = number_format((float)$gradeitem->grade, 2, '.', '');

            $status = '';
            if ($gradable) {
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
                if ($atv['viewed']) {
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
        $sum = 0;
        $prom = 0;
        foreach ($historico as $coursesinfo) {
            $sum += $coursesinfo['grade'];
        }
        if(isset($historico)){
            $prom = $sum / count($historico);
        }
        

        return $prom;
    }

    public function get_complete_courses() {

        $courses = enrol_get_all_users_courses($this->user->id,true, 'fullname, startdate, enddate, shortname');
        $finishedcourses = [];
        $number = 0;
        foreach ($courses as $course) {
            $number++;
            $current['number'] = $number;
            $current['shortname'] = $course->shortname;
            $current['fullname'] = $course->fullname;
            $current['startdate'] = date('d M Y', $course->startdate);
            $current['enddate'] = date('d M Y', $course->enddate);;
            $finishedcourses[] = $current;
        }
        return $finishedcourses;
    }

    public function get_forums_user_unread_info($courseid, $active = false) {
        $unread = 0;
        $currenttime = time();
        $modsinfo = get_fast_modinfo($courseid);
        foreach ($modsinfo->cms as $cm) {
            $dates = \course_info::get_activity_dates($cm);
            if ($cm->modname != 'forum' || $active && ($currenttime > $dates->enddate)) {
                continue;
            }
            $unread +=  forum_tp_count_forum_unread_posts($cm, $cm->get_course());
        }
        return $unread;
    }
}