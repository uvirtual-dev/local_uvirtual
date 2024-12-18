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

defined('MOODLE_INTERNAL') || die;

$functions = [
    'local_uvirtual_create_users' => [
        'classname' => 'local_uvirtual\external',
        'methodname' => 'create_users',
        'ajax' => true,
        'description' => '',
        'type' => 'write',
    ],
    'local_uvirtual_update_users' => [
        'classname' => 'local_uvirtual\external',
        'methodname' => 'update_users',
        'ajax' => true,
        'description' => '',
        'type' => 'write',
    ],

    'local_uvirtual_enrol_users' => [
        'classname' => 'local_uvirtual\external',
        'methodname' => 'enrol_users',
        'ajax' => true,
        'description' => '',
        'type' => 'write',
    ],

    'local_uvirtual_unenrol_users' => [
        'classname' => 'local_uvirtual\external',
        'methodname' => 'unenrol_users',
        'ajax' => true,
        'description' => '',
        'type' => 'write',
    ],

    'local_uvirtual_get_users_count' => [
        'classname' => 'local_uvirtual\external',
        'methodname' => 'get_users_count',
        'ajax' => true,
        'description' => '',
        'type' => 'read',
    ],
    'local_uvirtual_get_course_categories' => [
        'classname' => 'local_uvirtual\external\get_course_categories',
        'methodname' => 'execute',
        'ajax' => true,
        'description' => '',
        'type' => 'read'
    ],
    'local_uvirtual_get_user_info' => [
        'classname' => 'local_uvirtual\external\get_user_info',
        'methodname' => 'execute',
        'ajax' => true,
        'description' => '',
        'type' => 'read'
    ],
    'local_uvirtual_get_course_info' => [
        'classname' => 'local_uvirtual\external\get_course_info',
        'methodname' => 'execute',
        'ajax' => true,
        'description' => '',
        'type' => 'read'
    ],
    'local_uvirtual_get_filtered_courses_info' => [
        'classname' => 'local_uvirtual\external\get_filtered_courses_info',
        'methodname' => 'execute',
        'ajax' => true,
        'description' => '',
        'type' => 'read'
    ],
    'local_uvirtual_get_user_course_info' => [
        'classname' => 'local_uvirtual\external\get_user_course_info',
        'methodname' => 'execute',
        'ajax' => true,
        'description' => '',
        'type' => 'read'
    ],
    'local_uvirtual_get_courses_count_info' => [
        'classname' => 'local_uvirtual\external\get_courses_count_info',
        'methodname' => 'execute',
        'ajax' => true,
        'description' => '',
        'type' => 'read'
    ],
    'local_uvirtual_get_courses_basic_info' => [
        'classname' => 'local_uvirtual\external\get_courses_basic_info',
        'methodname' => 'execute',
        'ajax' => true,
        'description' => '',
        'type' => 'read'
    ],
    'local_uvirtual_get_user_week_report' => [
        'classname' => 'local_uvirtual\external\get_user_week_report',
        'methodname' => 'execute',
        'ajax' => true,
        'description' => '',
        'type' => 'read'
    ],
    'local_uvirtual_get_previous_and_next_courses' => [
        'classname' => 'local_uvirtual\external\get_previous_and_next_courses',
        'methodname' => 'execute',
        'ajax' => true,
        'description' => 'Permite obtener el curso previo y siguiente de un curso en específico y que se encuentren en la misma categoría.',
        'type' => 'read'
    ],
    'local_uvirtual_set_user_preferences' => [
        'classname' => 'local_uvirtual\external\set_user_preferences',
        'methodname' => 'execute',
        'ajax' => true,
        'description' => 'Configura las preferencias de usuario',
        'type' => 'read'
    ],
    'local_uvirtual_block_user' => [
        'classname' => 'local_uvirtual\external',
        'methodname' => 'block_user',
        'ajax' => true,
        'description' => '',
        'type' => 'write',
    ],
    'local_uvirtual_unblock_user' => [
        'classname' => 'local_uvirtual\external',
        'methodname' => 'unblock_user',
        'ajax' => true,
        'description' => '',
        'type' => 'write',
    ],
    'local_uvirtual_get_filtered_courses_by_date' => [
        'classname' => 'local_uvirtual\external\get_filtered_courses_by_date',
        'methodname' => 'execute',
        'ajax' => true,
        'description' => 'Obtiene los cursos filtrados por fecha y typo de curso, permite enviar el campo de consulta (enddate, startdate) y el valor de dicho campo en formato timestap',
        'type' => 'read'
    ],
    'local_uvirtual_get_actas_and_grades_by_courses' => [
        'classname' => 'local_uvirtual\external\get_actas_and_grades_by_courses',
        'methodname' => 'execute',
        'ajax' => true,
        'description' => 'Obtiene la última acta creada por el profesor y contrasta con el libro de calificaciones',
        'type' => 'read'
    ],
    'local_uvirtual_migrate_actas' => [
        'classname' => 'local_uvirtual\external\migrate_actas',
        'methodname' => 'execute',
        'ajax' => false,
        'description' => 'Migrar todas las actas a la versión actual',
        'type' => 'read'
    ],
];

// We define the services to install as pre-build services. A pre-build service is not editable by administrator.
$services = [
    'Uvirtual manage users ws' =>
        [
            'functions' => [
                'local_uvirtual_create_users',
                'local_uvirtual_update_users',
                'local_uvirtual_enrol_users',
                'local_uvirtual_unenrol_users',
                'local_uvirtual_get_course_categories',
                'local_uvirtual_get_user_info',
                'local_uvirtual_get_course_info',
                'local_uvirtual_get_filtered_courses_info',
                'local_uvirtual_get_user_course_info',
                'local_uvirtual_get_courses_count_info',
                'local_uvirtual_get_courses_basic_info',
                'local_uvirtual_get_user_week_report',
                'local_uvirtual_get_previous_and_next_courses',
                'local_uvirtual_set_user_preferences',
                'local_uvirtual_block_user',
                'local_uvirtual_unblock_user',
                'local_uvirtual_get_filtered_courses_by_date',
                'local_uvirtual_get_actas_and_grades_by_courses'
            ],
            'restrictedusers' => 0,
            'enabled' => 1,
        ]
];
