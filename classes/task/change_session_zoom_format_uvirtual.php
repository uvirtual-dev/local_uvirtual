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

        local_uvirtual_update_vc_task();
        
        
        
    }
}
