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
namespace local_uvirtual\task;

require_once($CFG->dirroot . '/course/format/uvirtual/lib.php');


defined('MOODLE_INTERNAL') || die();

class change_session_zoom_format_uvirtual extends \core\task\scheduled_task {

    /**
     * Name for this task.
     *
     * @return string
     */
    public function get_name() {
        return 'Cambiar sesion de zoom de acuerdo a campos de video conferencia';
    }

    /**
     * Run task for loading keycloak userids into user profile.
     */
    public function execute() {

        global $DB;
    $sql = "SELECT id, fullname as name, shortname as shortName, startdate as startDate, enddate as endDate FROM {course}";

    $courses = $DB->get_records_sql($sql);
    $ahora = time();
    foreach($courses as $course){
        $format = \course_get_format($course->id);
        $formatname = $format->get_format();
        $itemId = 1;
        $vcs = format_uvirtual_get_dates_vcs($course->id);
        if ($formatname == 'uvirtual' && $ahora > $course->startdate && $ahora < $course->enddate) {
            $dbman = $DB->get_manager();
            
            $instanceId = $DB->get_field('course_modules', 'instance', ['course' => $course->id, 'idnumber' => $itemId]);
            if($dbman->table_exists('zoom')){
                $zoomsession = $DB->get_record('zoom', ['id' => $instanceId]);
                $week = strtotime('+7 days' , $zoomsession->start_time );
                foreach($vcs as $vc){
                    
                    if($zoomsession->start_time < $vc['startsession'] && $week > $vc['startsession']){
                        $zoomsession->start_time = $vc['startsession'];
                        $zoomsession->end_date_time = $vc['endsession'];

                        $DB->update_record('zoom', $zoomsession);
                        
                        
                        
                    }
                }
                
            }
        }
    }
        
        
        
    }
}
