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
     * Run task for update session vc zoom.
     */
    public function execute() {

        global $DB;
    $sql = "SELECT id, fullname as name, shortname as shortName, startdate as startDate, enddate as endDate FROM {course}";
    mtrace("Comienza update vc zoom...");
    $courses = $DB->get_records_sql($sql);
    $ahora = time();
    
    
    foreach($courses as $course){
        mtrace("Se encuentra el curso " . $course->name);
        $format = \course_get_format($course->id);
        $formatname = $format->get_format();
        $itemId = 1;
        $vcs = format_uvirtual_get_dates_vcs($course->id);
        if ($formatname == 'uvirtual' && $ahora > $course->startdate && $ahora < $course->enddate) {
            $dbman = $DB->get_manager();
            mtrace("Está activo y con el formato uvirtual...");
            $instanceId = $DB->get_field('course_modules', 'instance', ['course' => $course->id, 'idnumber' => $itemId]);
            if($dbman->table_exists('zoom')){
                $zoomsession = $DB->get_record('zoom', ['id' => $instanceId]);
                $week = strtotime('+7 days' , $zoomsession->start_time );
                foreach($vcs as $vc){
                    mtrace("Entra a vcs con fecha zoom: " . $zoomsession->start_time . "  vs vc: " . $vc['startsession'] . " vs week: " . $week);
                    if($zoomsession->start_time < $vc['startsession'] && $week =< $vc['startsession']){
                        mtrace("Se validó fecha de sesion de vc");
                        $zoomsession->start_time = $vc['startsession'];
                        $zoomsession->end_date_time = $vc['endsession'];

                        $sql = $DB->update_record('zoom', $zoomsession);
                        if($sql){
                            mtrace("Se actualizó session de zoom");
                        }
                        
                        
                    }
                }
                
            }
        }
    }
        
    mtrace("Finaliza update vc zoom...");   
        
    }
}
