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
require_once($CFG->dirroot . '/blocks/grade_overview/classes/course_info.php');
require_once($CFG->dirroot . '/blocks/grade_overview/lib.php');

defined('MOODLE_INTERNAL') || die();

class send_emails_teachers_format_uvirtual extends \core\task\scheduled_task {

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
        $calculodias = 60 * 60 * 24 * 21;
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
                $mensaje = ' Envio de correos de informes profesores comenzando...';
                mtrace($mensaje);

                foreach ($courses as $course) {
                    $teacherid = array_keys(get_archetype_roles('teacher'));
                    $editingteacherid = array_keys(get_archetype_roles('editingteacher'));
                    $teacherroleids = array_merge($editingteacherid, $teacherid);
                    $teachers = \course_info::get_course_tutor($course->id, 'u.*', array_values($teacherroleids));
                    $istfm = format_uvirtual_get_course_metadata($course->id , 'Otros campos', 'typecourse', '4' );

                    if (!empty($teachers)) {
                        foreach ($teachers as $teacher) {
                            $context = format_uvirtual_get_teacher_pendientes_context($course, $teacher);
                            if($istfm){
                                if (!$context['assign_tfm']  ) {
                                    continue;
                                }
                            } else {
                                if (!$context['displayconsultas'] && !$context['displayretos'] ) {
                                    continue;
                                }
                            }
                            
                            $htmlemail = $OUTPUT->render_from_template('format_uvirtual/email/teachers_email', $context);
                            if (!empty($htmlemail)) {
                                $htmlemail = str_replace('ú', ' ', $htmlemail);
                                $html = '<div style="text-align: left; margin: 5px auto;">';
                                $html .= $htmlemail;
                                $html .= '</div>';
                                $mailobject = block_grade_overview_get_mail_object('reports', 'teacher');
                                $mailobject->address = empty($mailobject->address) ? $teacher->email : $mailobject->address;
                                $mailobject->subject = 'Informe de Actividades Pendientes - ' . $course->shortname;
                                $mailobject->body = $html;
                                block_grade_overview_send_email($mailobject);
                            }
                        }
                    } 
                }
                $mensaje = ' Envio de correos de informes profesores finalizando...';
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
