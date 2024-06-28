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

namespace local_uvirtual;

defined('MOODLE_INTERNAL') || die();

require_once($CFG->dirroot . '/user/lib.php');
require_once('local/newusermanagement.php');

use core\notification;

class observer
{
    public static function user_created_management(\core\event\base $event)
    {
        $userid = $event->relateduserid;
        $usermanagement = new \new_user_management($userid);
        $usermanagement->send_welcome_email();
    }

    public static function message_user_blocked(\core\event\base $event)
    {
        global $USER;
    

        if ($USER->profile['student_bloq'] == 1){
            
            \core\notification::add("¡Acceso bloqueado!", \core\output\notification::NOTIFY_INFO);
            \core\notification::add("Tome contacto con pagos@usal.uvirtual.org para regularizar sus cuotas vencidas. Si ya pagó su sistema se restablecerá en un tiempo máximo de en 24 hrs hábiles", \core\output\notification::NOTIFY_INFO);
        }
    }
}