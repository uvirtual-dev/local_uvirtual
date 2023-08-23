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

require_once($CFG->dirroot . '/user/lib.php');

use core_user;

class new_user_management
{
    protected $user;

    public function __construct($userid) {
        $this->set_user($userid);
    }

    protected function set_user($userid)
    {
        $this->user = core_user::get_user($userid);
    }

    public function send_welcome_email() {
        $message = new \core\message\message();
        $message->component = 'local_uvirtual'; // Your plugin's name
        $message->name = 'newusermessage'; // Your notification name from message.php
        $message->userfrom = core_user::get_noreply_user(); // If the message is 'from' a specific user you can set them here
        $message->userto = $this->user->id;
        $message->subject = get_string('messageprovider:welcomemessage:subject', 'local_uvirtual');
        $message->fullmessageformat = FORMAT_HTML;
        $message->fullmessagehtml = get_string('messageprovider:welcomemessage', 'local_uvirtual', $this->user->firstname);
        $message->notification = 1; // Because this is a notification generated from Moodle, not a user-to-user message
        $message->contexturl = (new \moodle_url(''))->out(false); // A relevant URL for the notification
        $message->contexturlname = 'Moodle site url'; // Link title explaining where users get to for the contexturl

// Actually send the message
        $messageid = message_send($message);
    }
}