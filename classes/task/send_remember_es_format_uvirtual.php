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
require_once($CFG->dirroot . '/blocks/grade_overview/lib.php');
require_once($CFG->dirroot . '/blocks/grade_overview/classes/course_info.php');
require_once($CFG->dirroot . '/course/format/uvirtual/lib.php');


defined('MOODLE_INTERNAL') || die();

class send_remember_es_format_uvirtual extends \core\task\scheduled_task {

    /**
     * Name for this task.
     *
     * @return string
     */
    public function get_name() {
        return 'Enviar correos de recordatorio encuentro síncrono';
    }

    /**
     * Run task for loading keycloak userids into user profile.
     */
    public function execute() {
        global $DB, $OUTPUT;

        
        $blockconfig = get_config('block_grade_overview');

        $ahora = time();
        $calculodias = 60 * 60 * 24 * 7;
        $stardate = $ahora;
        $enddate = $ahora - $calculodias;


        $send = $blockconfig->enableemailreport;


        if (!empty($send) || true) {
            $params = [
                'startdate' => $stardate,
                'enddate'   => $enddate,
                'visible'   => 1,
                'courseid'  => 1
            ];
            if (!empty($testcourse)) {
                $sqltestcourse = "AND c.id IN (".$testcourse.")";
            }

            $sql = "SELECT c.*
                    FROM {course} c
                    WHERE c.startdate <= :startdate
                    AND c.enddate >= :enddate
                    AND c.visible = :visible
                    AND c.id != :courseid
                    AND c.format = 'uvirtual'
                    $sqltestcourse";
            $courses = $DB->get_records_sql($sql, $params);
            if (!empty($courses)) {
                $mensaje = ' Envio de correos de recordatorio a estudiantes comenzando...';
                mtrace($mensaje);

                foreach ($courses as $course) {
                    if(!format_uvirtual_check_date($course->id)){
                        $mensaje = ' No esta dentro de los horarios';
                        mtrace($mensaje);
                        continue;
                    }
                    $issecondcall = format_uvirtual_get_course_metadata($course->id, 'Otros campos', 'typecourse', '6' );
                    $isrecursos = format_uvirtual_get_course_metadata($course->id, 'Otros campos', 'typecourse', '5' );
                    if ($issecondcall || $isrecursos ) {
                        continue;
                    }
                    $students = \course_info::get_course_students($course->id, 'u.*');
                    
                    if (!empty($students)) {
                        foreach ($students as $student) {

                            $context = format_uvirtual_get_next_encuentro_sincrono($course->id, $student);
                            $htmlemail = $OUTPUT->render_from_template('format_uvirtual/email/remember_studentmail', $context);

                            if (!empty($htmlemail)) {
                                $htmlemail = str_replace('ú', ' ', $htmlemail);
                                $html = '<div style="text-align: left; margin: 5px auto;">';
                                $html .= $htmlemail;
                                $html .= '</div>';
                                $mailobject = block_grade_overview_get_mail_object('reports', 'student');
                                $mailobject->address = empty($mailobject->address) ? $student->email : $mailobject->address;
                                $mailobject->subject = "Recordatorio de encuentro síncrono - ".$course->shortname;
                                $mailobject->body = $html;
                                block_grade_overview_send_email($mailobject);
                                $mensaje = ' Se envía mail -> ' . $course->shortname . " -> " . $student->email;
                                mtrace($mensaje);
                            }
                        }
                    } 
                }
                $mensaje = ' Envio de recordatorios finalizando...';
                mtrace($mensaje);
            } else {
                $mensaje = ' No hay encuentros sincronos.';
                mtrace($mensaje);
            }

        } else {
            $mensaje = ' No hay nada para enviar';
            mtrace($mensaje);
        }
    }
}
