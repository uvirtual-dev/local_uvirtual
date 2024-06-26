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

$tasks = array(
    array(
        'classname' => '\local_uvirtual\task\send_emails_teachers_format_uvirtual',
        'blocking' => 0,
        'minute' => '30',
        'hour' => '11,22',
        'day' => '*',
		'month' => '*',
		'dayofweek' => '*',
    ),
    array(
        'classname' => '\local_uvirtual\task\send_emails_students_format_uvirtual',
        'blocking' => 0,
        'minute' => '30',
        'hour' => '11,22',
        'day' => '*',
		'month' => '*',
		'dayofweek' => '*',
    ),
    array(
        'classname' => '\local_uvirtual\task\change_session_zoom_format_uvirtual',
        'blocking' => 0,
        'minute' => '5',
        'hour' => '*',
        'day' => '*',
		'month' => '*',
		'dayofweek' => '*',
    ),
    array(
        'classname' => '\local_uvirtual\task\send_remember_es_format_uvirtual',
        'blocking' => 0,
        'minute' => '0,30',
        'hour' => '*',
        'day' => '*',
		'month' => '*',
		'dayofweek' => '*',
    )
);