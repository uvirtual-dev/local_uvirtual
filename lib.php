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
require_once($CFG->dirroot . '/user/lib.php');
require_once($CFG->dirroot . '/mod/questionnaire/questionnaire.class.php');

function local_uvirtual_get_users_count($year = null)
{

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

function local_uvirtual_get_activities_by_uvid($activities)
{
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
        if ($activity['uvid'] == 'gradable_quiz' || ($activity['uvid'] == 'gradable_assign' && !preg_match("/Reto de Aprendizaje/i", $activity['name']))) {
            $formativeAssessments[] = $activity;
        } else if ($activity['uvid'] == 'tracked_lecture') {
            $readings[] = $activity;
        } else if ($activity['uvid'] == 'video_class') {
            $videoCapsules[] = $activity;
        } else if ($activity['uvid'] == 'gradable_assign' && preg_match("/Reto de Aprendizaje/i", $activity['name'])) {
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
    $activiesResult->gradeWeek = round($week['gradeWeek'], 2);

    return $activiesResult;
}

function local_uvirtual_get_data_previous_and_next_courses($courseid)
{
    global $DB;

    $currentCourse = $DB->get_record('course', ['id' => $courseid]);


    if (!isset($currentCourse->id)) {
        throw new invalid_parameter_exception('El curso no existe en la base de datos.');
    }
    $coursePrevious = '';
    $courseNext = '';

    $currentCategory = $DB->get_record('course_categories', ['id' => $currentCourse->category]);


    if (strlen($currentCourse->shortname) == 11) {
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

            if (substr($course->shortname, -1) == $courseGroup) {
                $coursePrevious = $course;
                break;
            }
        }

        //Para el caso de los cursos próximos
        $sql = "SELECT * FROM {course} WHERE category = :categoryid AND startdate > :enddate ORDER BY startdate ASC";

        $params = array('categoryid' => $currentCourse->category, 'enddate' => $currentCourse->enddate);

        $coursesNext = $DB->get_records_sql($sql, $params);

        foreach ($coursesNext as $course) {
            if (substr($course->shortname, -1) == $courseGroup) {
                $courseNext = $course;
                break;
            }
        }
    }

    $response = new stdClass();

    $coursePreviousData = new stdClass();
    $courseNextData = new stdClass();

    $coursePreviousData = local_uvirtual_get_data_course($coursePrevious, $currentCategory);
    $courseNextData = local_uvirtual_get_data_course($courseNext, $currentCategory);

    $response->coursePrevios = $coursePreviousData;
    $response->courseNext = $courseNextData;

    return $response;

}

function local_uvirtual_get_data_course($course, $currentCategory)
{

    $courseData = new stdClass();
    $courseData->id = $course->id;
    $courseData->shortname = $course->shortname;
    $courseData->fullname = $course->fullname;
    $courseData->startdate = $course->startdate;
    $courseData->enddate = $course->enddate;
    $courseData->categoryid = $currentCategory->id;
    $courseData->categoryname = $currentCategory->name;

    return $courseData;
}

function local_uvirtual_get_picture_profile_for_template($user)
{
    global $PAGE;

    $userpicture = new \user_picture($user);
    $userpicture->size = 1;
    $pictureUrl = $userpicture->get_url($PAGE)->out(false);

    return $pictureUrl;
}

function local_uvirtual_identify_course_program($shortname)
{

    if (empty($shortname)) {
        throw new Exception('Error en los parámetros enviados, contacte a soporte');
    }

    $pluginconfig = get_config('local_uvirtual');
    $configvalue = $pluginconfig->urlsysacad;

    if (empty($configvalue)) {
        throw new Exception('No se configuró la url base del backend del sistema académico, contacte a soporte');
    }

    $url = $configvalue . "/api/v1/prog-materias/getItem/sigla/{$shortname}";

    $ch = curl_init($url);

    curl_setopt($ch, CURLOPT_HTTPGET, true);

    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

    try {
        $result = curl_exec($ch);

        if ($result === false) {
            throw new Exception('Error en la solicitud cURL: ' . curl_error($ch));
        }

        $data = json_decode($result, true);
    } catch (Exception $e) {
        throw new Exception('Error al comunicarse con el servicio web: ' . $e->getMessage());
    } finally {
        curl_close($ch);
    }

    return json_encode($data);
}

/**
 * @param $shortname
 * @return mixed
 * @throws Exception
 */
function local_uvirtual_verify_status_grade_migration($shortname)
{
    if (empty($shortname)) {
        throw new Exception('Error en los parámetros enviados, contacte a soporte');
    }

    $urlConsult = "https://saedev.uvirtual.org/sisacadold/api/v1/clase/verifyStatusGradeMigration/$shortname";

    $ch = curl_init($urlConsult);

    curl_setopt($ch, CURLOPT_HTTPGET, true);

    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

    $result = curl_exec($ch);

    $data = json_decode($result, true);
    return $data['status'] ?? false;
}

function local_uvirtual_change_role($email, $courses, $rolename, $newrolename)
{
    global $DB, $CFG;

    require_once($CFG->dirroot . '/user/profile/lib.php');

    // Obtén el ID del rol que quieres quitar.
    $roleid = $DB->get_field('role', 'id', array('shortname' => $rolename));

    // Obtén el ID del usuario al que quieres asignar el rol.
    $userid = $DB->get_field('user', 'id', array('email' => $email));

    // Obtén el ID del rol que quieres asignar.
    $newroleid = $DB->get_field('role', 'id', array('shortname' => $newrolename));

    //Obtener el id de todos los cursos donde esta inscrito el usuario buscando por userid

    $courses = explode(',', $courses);
    list($insql, $paramsin) = $DB->get_in_or_equal($courses);

    $params = array_merge(array($userid), $paramsin);

    $sql = "SELECT c.id FROM {course} c
            INNER JOIN {context} ctx ON c.id = ctx.instanceid
            INNER JOIN {role_assignments} ra ON ctx.id = ra.contextid
            INNER JOIN {user} u ON ra.userid = u.id
            WHERE u.id = ? AND c.shortname $insql";

    $courseids = $DB->get_records_sql($sql, $params);
    $user = $DB->get_record('user', ['id' => $userid]);
    foreach ($courseids as $course) {
        $context = \context_course::instance($course->id);

        $role = $DB->get_record('role', ['id' => $roleid]);

        if (!isset($context)) {
            throw new invalid_parameter_exception('El contexto no existe en la base de datos.');
            return ['success' => false];
        }

        if (!isset($user)) {
            throw new invalid_parameter_exception('El usuario no existe en la base de datos.');
            return ['success' => false];
        }

        if (!isset($role)) {
            throw new invalid_parameter_exception('El rol no existe en la base de datos.');
            return ['success' => false];
        }

        // Quita el rol al usuario en el curso.
        role_unassign($roleid, $userid, $context->id);

        // Asigna el rol al usuario en el curso.
        role_assign($newroleid, $userid, $context->id);
    }

    profile_load_custom_fields($user);

    //suspender o activar usuario depende del rol
    if ($newrolename == 'student') {
        //$user->suspended = 0;
        // Actualizar campo personalizado de usuario (profile_field), student_bloq dependiendo del rol
        $user->profile_field_student_bloq = 0;

    } else {
        //$user->suspended = 1;
        $user->profile_field_student_bloq = 1;
    }

    profile_save_data($user);

    $DB->update_record('user', $user);

    return ['success' => true];

}

function local_uvirtual_get_response_questionnarie_by_user($userid, $courseid)
{
    global $DB;

    // Get the questionnaire module
    $module_questionnaire = $DB->get_record('modules', array('name' => 'questionnaire'));

    // Get the course and course modules
    $course = get_course($courseid);
    $course_modules = get_course_mods($courseid);

    $instance_questionnaire = "";
    $cm = null;

    // Iterate course modules
    foreach ($course_modules as $course_module) {
        $cm = $course_module;
        if ($course_module->module == $module_questionnaire->id) {
            $instance_questionnaire = $course_module->instance;
        }
    }

    // If the questionnaire is not found, return an error
    if ($instance_questionnaire == "") {
        return [];
    }

    // Get the questionnaire
    list ($course, $cm) = get_course_and_cm_from_instance($instance_questionnaire, 'questionnaire', $courseid);
    $questionnaire = new questionnaire($course, $cm, $instance_questionnaire);

    // Get the responses
    $resps = $questionnaire->get_responses($userid);

    return $resps;
}

/**
 * @param $user
 * @param $course
 * @return bool
 * @throws dml_exception
 */
function local_uvirtual_get_role_by_course_and_user($user, $course): bool
{
    global $DB;

    // Get the role of the user in the course
    $sql = "SELECT ra.roleid FROM {course} c
                INNER JOIN {context} ctx ON c.id = ctx.instanceid
                INNER JOIN {role_assignments} ra ON ctx.id = ra.contextid
                INNER JOIN {user} u ON ra.userid = u.id
                    WHERE u.id = :userid AND c.id = :courseid";

    // Get role assignments
    $roles = $DB->get_records_sql($sql, ['userid' => $user, 'courseid' => $course]);

    $answer = [];

    // Get role
    foreach ($roles as $role) {

        // Get role from DB
        $role_db = $DB->get_record('role', ['id' => $role->roleid]);

        // Check if the role is studbloq
        if ($role_db->shortname == 'studbloq') {
            return true;
        }

    }
    return false;
}

/**
 * @param $course
 * @return array
 * @throws dml_exception
 */
function local_uvirtual_get_last_course_actas_by_course_minified($course): array
{
    global $DB, $CFG;

    // Get tutor role id
    $tutor = $DB->get_field('role', 'id', ['shortname' => 'noeditingteacher']);

    // Get the teachers
    $tutor_ids = course_info::get_course_tutor($course->id, 'u.*', $tutor);

    $answers = [];

    // SQL get final grades
    $sql = "SELECT gg.userid, gg.finalgrade FROM {grade_items} as gi 
                INNER JOIN {grade_grades} as gg ON gi.id = gg.itemid 
                    WHERE gi.courseid = :courseid AND gi.itemtype = 'course'";

    // Iterate tutor
    foreach ($tutor_ids as $tutor_id) {

        // SQL get course_actas
        $sql = "SELECT * FROM {course_actas} WHERE courseid = :courseid AND userid = :userid ORDER BY created_at DESC LIMIT 1";

        // Get course_actas
        $course_actas = $DB->get_record_sql($sql, ['courseid' => $course->id, 'userid' => $tutor_id->id]);

        $acta_id = $course_actas->id;
        $course_id = $course->id;

        $answers = [
            'totalGradeActa' => (int)$course_actas->sum10,
            'createdAtActa' => (int)$course_actas->created_at,
            'url' => $CFG->wwwroot . "/blocks/grade_overview/download.php?id=$course_id&group=0&op=d&dataformat=pdf&teacher=0&actaid=$acta_id&download=true",
        ];

    }

    return $answers;
}

/**
 * @param $course
 * @param $students
 * @param bool $filter
 * @return array
 * @throws dml_exception
 */
function local_uvirtual_get_last_course_actas_by_course($course, $students, bool $filter = false): array
{
    global $DB;

    // Get tutor role id
    $tutor = $DB->get_field('role', 'id', ['shortname' => 'noeditingteacher']);

    // Get the teachers
    $tutor_ids = course_info::get_course_tutor($course->id, 'u.*', $tutor);

    $answers = [];

    // SQL get final grades
    $sql = "SELECT gg.userid, gg.finalgrade FROM {grade_items} as gi 
                INNER JOIN {grade_grades} as gg ON gi.id = gg.itemid 
                    WHERE gi.courseid = :courseid AND gi.itemtype = 'course'";

    // Get final grades
    $grades = $DB->get_records_sql($sql, ['courseid' => $course->id]);

    // Iterate tutor
    foreach ($tutor_ids as $tutor_id) {

        // SQL get course_actas
        $sql = "SELECT * FROM {course_actas} WHERE courseid = :courseid AND userid = :userid ORDER BY created_at DESC LIMIT 1";

        // Get course_actas
        $course_actas = $DB->get_record_sql($sql, ['courseid' => $course->id, 'userid' => $tutor_id->id]);

        // Get actas from JSON
        $actas = json_decode($course_actas->information, true);

        // Iterate actas
        foreach ($actas as $acta) {

            // Get grade by student
            $grade = $grades[$acta['id']]->finalgrade;
            $grade = round($grade, 0);
            $status = false;

            // Check if the grade is the same
            if ($acta['grade10'] == $grade) {
                $status = true;
            }

            // Get student by ID in students via array_filter
            $student = array_filter($students, function ($student) use ($acta) {
                return $student->id == $acta['id'];
            });

            $student = array_shift($student);

            // Check filter
            if ($filter) {
                // Return the answer
                $answers[] = [
                    'id_usuario' => (int)$acta['id'],
                    'grade10' => $acta['grade10'],
                    'grade100' => $acta['grade100'],
                    'status' => $status,
                    'grade1000' => $grade,
                    'sum10' => $course_actas->sum10,
                    'sum100' => $course_actas->sum100,
                    'created_at' => $course_actas->created_at,
                    'acta_id' => $course_actas->id,
                ];
            } else {
                // Return the answer
                $answers[] = [
                    'id_usuario' => (int)$acta['id'],
                    'student' => $student->firstname,
                    'lastname' => $student->lastname,
                    'email' => $student->email,
                    'grade10' => $acta['grade10'],
                    'grade100' => $acta['grade100'],
                    'status' => $status,
                    'grade1000' => $grade,
                    'sum10' => $course_actas->sum10,
                    'sum100' => $course_actas->sum100,
                    'created_at' => $course_actas->created_at,
                    'acta_id' => $course_actas->id,
                ];
            }
        }
    }

    return $answers;
}

/**
 * @param $html
 * @return array
 */
function local_uvirtual_parse_html_table($html): array
{
    // Create a DOMDocument object
    $dom = new DOMDocument();

    // Uploading HTML
    @$dom->loadHTML($html);

    // Create a DOMXPath object
    $xpath = new DOMXPath($dom);

    // Find all rows (tr)
    $rows = $xpath->query('//tr');

    $tableData = [];

    // Iterate rows
    foreach ($rows as $row) {
        $rowData = [];

        // Find all cells (td) in the current row
        $cells = $xpath->query('.//td', $row);

        // Iterate cells
        foreach ($cells as $cell) {
            // Get the content of the cell
            $rowData[] = utf8_decode($cell->nodeValue);
        }

        $tableData[] = $rowData;
    }

    return $tableData;
}

/**
 * @param $course
 * @return array
 * @throws dml_exception
 */
function local_uvirtual_get_students_by_course($course): array
{
    global $DB;

    // Get student role id
    $student = $DB->get_field('role', 'id', ['shortname' => 'student']);

    // Get students by course
    $students = course_info::get_course_students($course->id, '', 'u.id, u.firstname, u.lastname, u.email', $student);

    // Get studbloq role id
    $studbloq = $DB->get_field('role', 'id', ['shortname' => 'studbloq']);

    // Get students by course
    $students_bloq = course_info::get_course_students($course->id, '', 'u.id, u.firstname, u.lastname, u.email', $studbloq);

    // Merge students
    return array_merge($students, $students_bloq);
}