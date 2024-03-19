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
 * This file contains external functions for Urvirtual .
 *
 * @package   local_uvirtual
 * @copyright 2024 Miguel Velasquez (m.a.velasquez@uvirtual.org)
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_uvirtual\external;

global $CFG;

use external_api;
use external_function_parameters;
use external_multiple_structure;
use external_single_structure;
use external_value;
use external_warnings;
use core_user;

defined('MOODLE_INTERNAL') || die;

class set_user_preferences extends external_api {

    /**
     * Returns description of method parameters
     * @return external_function_parameters
     */
    public static function execute_parameters() {
        return new external_function_parameters(
            array(
                'preferences' => new external_multiple_structure(
                    new external_single_structure(
                        array(
                            'name' => new external_value(PARAM_RAW, 'The name of the preference'),
                            'value' => new external_value(PARAM_RAW, 'The value of the preference'),
                            'userid' => new external_value(PARAM_INT, 'Id of the user to set the preference'),
                        )
                    )
                )
            )
        );
    }

     /**
     * Create groups
     * @param array $groups array of group description arrays (with keys groupname and courseid)
     * @return array of newly created groups
     */
    public static function execute($preferences) {
        global $USER;

        $params = self::validate_parameters(self::execute_parameters(), array('preferences' => $preferences));
        $warnings = array();
        $saved = array();

        $userscache = array();
        foreach ($params['preferences'] as $pref) {
            // Check to which user set the preference.
            if (!empty($userscache[$pref['userid']])) {
                $user = $userscache[$pref['userid']];
            } else {
                try {
                    $user = core_user::get_user($pref['userid'], '*', MUST_EXIST);
                    core_user::require_active_user($user);
                    $userscache[$pref['userid']] = $user;
                } catch (Exception $e) {
                    $warnings[] = array(
                        'item' => 'user',
                        'itemid' => $pref['userid'],
                        'warningcode' => 'invaliduser',
                        'message' => $e->getMessage()
                    );
                    continue;
                }
            }

            try {
                
                
                set_user_preference($pref['name'], $pref['value'], $user->id);
                $saved[] = array(
                    'name' => $pref['name'],
                    'userid' => $user->id,
                );
                
            } catch (Exception $e) {
                $warnings[] = array(
                    'item' => 'user',
                    'itemid' => $user->id,
                    'warningcode' => 'errorsavingpreference',
                    'message' => $e->getMessage()
                );
            }
        }

        $result = array();
        $result['saved'] = $saved;
        $result['warnings'] = $warnings;
        return $result;
    }

    /**
     * Returns description of method result value
     *
     * @return external_multiple_structure
     * @since Moodle 2.2
     */
    // public static function execute_returns() {
    //     return new external_value(PARAM_RAW, 'JSON object');
    // }
    public static function execute_returns() {
        return new external_single_structure(
            array(
                'saved' => new external_multiple_structure(
                    new external_single_structure(
                        array(
                            'name' => new external_value(PARAM_RAW, 'The name of the preference'),
                            'userid' => new external_value(PARAM_INT, 'The user the preference was set for'),
                        )
                    ), 'Preferences saved'
                ),
                'warnings' => new external_warnings()
            )
        );
    }
}