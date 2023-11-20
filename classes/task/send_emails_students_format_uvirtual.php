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


defined('MOODLE_INTERNAL') || die();

class send_emails_students_format_uvirtual extends \core\task\scheduled_task {

    /**
     * Name for this task.
     *
     * @return string
     */
    public function get_name() {
        return 'Enviar correos de informes';
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
                $mensaje = ' Envio de correos de informes estudiantes comenzando...';
                mtrace($mensaje);

                foreach ($courses as $course) {
                    $students = \course_info::get_course_students($course->id, 'u.*');

                    if (!empty($students)) {
                        foreach ($students as $student) {
                            $context = format_uvirtual_get_student_report_context($course, $student);
                            $htmlemail = $OUTPUT->render_from_template('format_uvirtual/email/studentmail', $context);
                            if (!empty($htmlemail)) {
                                $htmlemail = str_replace('ú', ' ', $htmlemail);
                                $html = '<div style="text-align: left; margin: 5px auto;">';
                                $html .= $htmlemail;
                                $html .= '</div>';
                                $mailobject = block_grade_overview_get_mail_object('reports', 'student');
                                $mailobject->address = empty($mailobject->address) ? $student->email : $mailobject->address;
                                $mailobject->subject = $course->shortname;
                                $mailobject->body = $html;
                                block_grade_overview_send_email($mailobject);
                            }
                        }
                    } 
                }
                $mensaje = ' Envio de correos de informes studientes finalizando...';
                mtrace($mensaje);
            } else {
                $mensaje = ' No hay cursos validos en el rango de fecha actual.';
                mtrace($mensaje);
            }

        } else {
            $mensaje = ' No hay nada para enviar';
            mtrace($mensaje);
        }
    }
}
