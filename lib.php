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
        if ($activity['uvid'] == 'gradable_quiz') {
            $formativeAssessments[] = $activity;
        } else if ($activity['uvid'] == 'tracked_lecture') {
            $readings[] = $activity;
        } else if ($activity['uvid'] == 'video_class') {
            $videoCapsules[] = $activity;
        } else if ($activity['uvid'] == 'gradable_assign') {
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