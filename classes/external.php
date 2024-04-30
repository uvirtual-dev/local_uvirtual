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
 * @copyright 2022 Oscar Nadjar (oscar.nadjar@uvirtual.org)
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
namespace local_uvirtual;

defined('MOODLE_INTERNAL') || die;

require_once($CFG->libdir . "/externallib.php");
require_once($CFG->dirroot . '/user/profile/lib.php');
require_once($CFG->dirroot . '/local/uvirtual/lib.php');

use external_api;
use external_function_parameters;
use external_multiple_structure;
use external_single_structure;
use external_value;
use core_user;
use context_system;

class external extends external_api {

    /**
     * Returns description of method parameters
     *
     * @return external_function_parameters
     * @since Moodle 2.2
     */
    public static function create_users_parameters() {
        global $CFG;
        $userfields = [
            'createpassword' => new external_value(PARAM_BOOL, 'True if password should be created and mailed to user.',
                VALUE_OPTIONAL),
            // General.
            'username' => new external_value(\core_user::get_property_type('username'),
                'Username policy is defined in Moodle security config.'),
            'auth' => new external_value(\core_user::get_property_type('auth'), 'Auth plugins include manual, ldap, etc',
                VALUE_DEFAULT, 'manual', \core_user::get_property_null('auth')),
            'password' => new external_value(\core_user::get_property_type('password'),
                'Plain text password consisting of any characters', VALUE_OPTIONAL),
            'firstname' => new external_value(\core_user::get_property_type('firstname'), 'The first name(s) of the user'),
            'lastname' => new external_value(\core_user::get_property_type('lastname'), 'The family name of the user'),
            'email' => new external_value(\core_user::get_property_type('email'), 'A valid and unique email address'),
            'maildisplay' => new external_value(\core_user::get_property_type('maildisplay'), 'Email display', VALUE_OPTIONAL),
            'city' => new external_value(\core_user::get_property_type('city'), 'Home city of the user', VALUE_OPTIONAL),
            'country' => new external_value(\core_user::get_property_type('country'),
                'Home country code of the user, such as AU or CZ', VALUE_OPTIONAL),
            'timezone' => new external_value(\core_user::get_property_type('timezone'),
                'Timezone code such as Australia/Perth, or 99 for default', VALUE_OPTIONAL),
            'description' => new external_value(\core_user::get_property_type('description'), 'User profile description, no HTML',
                VALUE_OPTIONAL),
            // Additional names.
            'firstnamephonetic' => new external_value(\core_user::get_property_type('firstnamephonetic'),
                'The first name(s) phonetically of the user', VALUE_OPTIONAL),
            'lastnamephonetic' => new external_value(\core_user::get_property_type('lastnamephonetic'),
                'The family name phonetically of the user', VALUE_OPTIONAL),
            'middlename' => new external_value(\core_user::get_property_type('middlename'), 'The middle name of the user',
                VALUE_OPTIONAL),
            'alternatename' => new external_value(\core_user::get_property_type('alternatename'), 'The alternate name of the user',
                VALUE_OPTIONAL),
            // Interests.
            'interests' => new external_value(PARAM_TEXT, 'User interests (separated by commas)', VALUE_OPTIONAL),
            // Optional.
            'idnumber' => new external_value(\core_user::get_property_type('idnumber'),
                'An arbitrary ID code number perhaps from the institution', VALUE_DEFAULT, ''),
            'institution' => new external_value(\core_user::get_property_type('institution'), 'institution', VALUE_OPTIONAL),
            'department' => new external_value(\core_user::get_property_type('department'), 'department', VALUE_OPTIONAL),
            'phone1' => new external_value(\core_user::get_property_type('phone1'), 'Phone 1', VALUE_OPTIONAL),
            'phone2' => new external_value(\core_user::get_property_type('phone2'), 'Phone 2', VALUE_OPTIONAL),
            'address' => new external_value(\core_user::get_property_type('address'), 'Postal address', VALUE_OPTIONAL),
            // Other user preferences stored in the user table.
            'lang' => new external_value(\core_user::get_property_type('lang'), 'Language code such as "en", must exist on server',
                VALUE_DEFAULT, \core_user::get_property_default('lang'), \core_user::get_property_null('lang')),
            'calendartype' => new external_value(\core_user::get_property_type('calendartype'),
                'Calendar type such as "gregorian", must exist on server', VALUE_DEFAULT, $CFG->calendartype, VALUE_OPTIONAL),
            'theme' => new external_value(\core_user::get_property_type('theme'),
                'Theme name such as "standard", must exist on server', VALUE_OPTIONAL),
            'mailformat' => new external_value(\core_user::get_property_type('mailformat'),
                'Mail format code is 0 for plain text, 1 for HTML etc', VALUE_OPTIONAL),
            // Custom user profile fields.
            'customfields' => new external_multiple_structure(
                new external_single_structure(
                    [
                        'type'  => new external_value(PARAM_ALPHANUMEXT, 'The name of the custom field'),
                        'value' => new external_value(PARAM_RAW, 'The value of the custom field')
                    ]
                ), 'User custom fields (also known as user profil fields)', VALUE_OPTIONAL),
            // User preferences.
            'preferences' => new external_multiple_structure(
                new external_single_structure(
                    [
                        'type'  => new external_value(PARAM_RAW, 'The name of the preference'),
                        'value' => new external_value(PARAM_RAW, 'The value of the preference')
                    ]
                ), 'User preferences', VALUE_OPTIONAL),
        ];
        return new external_function_parameters(
            [
                'users' => new external_multiple_structure(
                    new external_single_structure($userfields)
                )
            ]
        );
    }

    /**
     * Create one or more users.
     *
     * @throws invalid_parameter_exception
     * @param array $users An array of users to create.
     * @return array An array of arrays
     * @since Moodle 2.2
     */
    public static function create_users($users) {
        global $CFG, $DB;
        require_once($CFG->dirroot."/lib/weblib.php");
        require_once($CFG->dirroot."/user/lib.php");
        require_once($CFG->dirroot."/user/editlib.php");
        require_once($CFG->dirroot."/user/profile/lib.php"); // Required for customfields related function.

        // Ensure the current user is allowed to run this function.
        $context = \context_system::instance();
        self::validate_context($context);
        require_capability('moodle/user:create', $context);

        // Do basic automatic PARAM checks on incoming data, using params description.
        // If any problems are found then exceptions are thrown with helpful error messages.
        $params = self::validate_parameters(self::create_users_parameters(), array('users' => $users));

        $availableauths  = \core_component::get_plugin_list('auth');
        unset($availableauths['mnet']);       // These would need mnethostid too.
        unset($availableauths['webservice']); // We do not want new webservice users for now.

        $availablethemes = \core_component::get_plugin_list('theme');
        $availablelangs  = get_string_manager()->get_list_of_translations();

        $transaction = $DB->start_delegated_transaction();

        $userids = array();
        $creationfailusernames = array();
        foreach ($params['users'] as $user) {
            try {
                // Make sure that the username, firstname and lastname are not blank.
                foreach (array('username', 'firstname', 'lastname') as $fieldname) {
                    if (trim($user[$fieldname]) === '') {
                        throw new \invalid_parameter_exception('The field '.$fieldname.' cannot be blank');
                    }
                }

                // Make sure that the username doesn't already exist.
                if ($DB->record_exists('user', array('username' => $user['username'], 'mnethostid' => $CFG->mnet_localhost_id))) {
                    throw new \invalid_parameter_exception('Username already exists: '.$user['username']);
                }

                // Make sure auth is valid.
                if (empty($availableauths[$user['auth']])) {
                    throw new \invalid_parameter_exception('Invalid authentication type: '.$user['auth']);
                }

                // Make sure lang is valid.
                if (empty($availablelangs[$user['lang']])) {
                    throw new \invalid_parameter_exception('Invalid language code: '.$user['lang']);
                }

                // Make sure lang is valid.
                if (!empty($user['theme']) && empty($availablethemes[$user['theme']])) { // Theme is VALUE_OPTIONAL,
                    // so no default value
                    // We need to test if the client sent it
                    // => !empty($user['theme']).
                    throw new \invalid_parameter_exception('Invalid theme: '.$user['theme']);
                }

                // Make sure we have a password or have to create one.
                $authplugin = get_auth_plugin($user['auth']);
                if ($authplugin->is_internal() && empty($user['password']) && empty($user['createpassword'])) {
                    throw new \invalid_parameter_exception('Invalid password: you must provide a password, or set createpassword.');
                }

                $user['confirmed'] = true;
                $user['mnethostid'] = $CFG->mnet_localhost_id;

                // Start of user info validation.
                // Make sure we validate current user info as handled by current GUI. See user/editadvanced_form.php func validation().
                if (!validate_email($user['email'])) {
                    throw new \invalid_parameter_exception('Email address is invalid: '.$user['email']);
                } else if (empty($CFG->allowaccountssameemail)) {
                    // Make a case-insensitive query for the given email address.
                    $select = $DB->sql_equal('email', ':email', false) . ' AND mnethostid = :mnethostid';
                    $params = array(
                        'email' => $user['email'],
                        'mnethostid' => $user['mnethostid']
                    );
                    // If there are other user(s) that already have the same email, throw an error.
                    if ($DB->record_exists_select('user', $select, $params)) {
                        throw new \invalid_parameter_exception('Email address already exists: '.$user['email']);
                    }
                }
                // End of user info validation.

                $createpassword = !empty($user['createpassword']);
                unset($user['createpassword']);
                $updatepassword = false;
                if ($authplugin->is_internal()) {
                    if ($createpassword) {
                        $user['password'] = '';
                    } else {
                        $updatepassword = true;
                    }
                } else {
                    $user['password'] = AUTH_PASSWORD_NOT_CACHED;
                }

                // Create the user data now!
                $user['id'] = user_create_user($user, $updatepassword, false);

                $userobject = (object)$user;

                // Set user interests.
                if (!empty($user['interests'])) {
                    $trimmedinterests = array_map('trim', explode(',', $user['interests']));
                    $interests = array_filter($trimmedinterests, function($value) {
                        return !empty($value);
                    });
                    useredit_update_interests($userobject, $interests);
                }

                // Custom fields.
                if (!empty($user['customfields'])) {
                    foreach ($user['customfields'] as $customfield) {
                        // Profile_save_data() saves profile file it's expecting a user with the correct id,
                        // and custom field to be named profile_field_"shortname".
                        $user["profile_field_".$customfield['type']] = $customfield['value'];
                    }
                    profile_save_data((object) $user);
                }

                if ($createpassword) {
                    setnew_password_and_mail($userobject);
                    unset_user_preference('create_password', $userobject);
                    set_user_preference('auth_forcepasswordchange', 1, $userobject);
                }

                // Trigger event.
                \core\event\user_created::create_from_userid($user['id'])->trigger();

                // Preferences.
                if (!empty($user['preferences'])) {
                    $userpref = (object)$user;
                    foreach ($user['preferences'] as $preference) {
                        $userpref->{'preference_'.$preference['type']} = $preference['value'];
                    }
                    useredit_update_user_preference($userpref);
                }

                $userids[] = array('id' => $user['id'], 'username' => $user['username']);
            } catch (\Exception $e) {
                $creationfailusernames[] = 'Username: ' . $user['username'] . ' - ' .$e->debuginfo;
            }
        }

        $transaction->allow_commit();
        return ['usersid' => $userids, 'failedusers' => $creationfailusernames];
    }

    /**
     * Returns description of method result value
     *
     * @return external_multiple_structure
     * @since Moodle 2.2
     */
    public static function create_users_returns() {
        return new \external_single_structure(
                [
                    'usersid' => new \external_multiple_structure(
                        new \external_single_structure(
                            [
                                'id'       => new \external_value(\core_user::get_property_type('id'), 'user id'),
                                'username' => new \external_value(\core_user::get_property_type('username'), 'user name'),
                            ]
                        )
                    ),

                    'failedusers' => new \external_multiple_structure(
                        new \external_value(PARAM_TEXT, 'Error description', VALUE_OPTIONAL)
                    )
                ]
            );
    }

    /**
     * Returns description of method parameters
     *
     * @return external_function_parameters
     * @since Moodle 2.2
     */
    public static function update_users_parameters() {
        $userfields = [
            'id' => new external_value(core_user::get_property_type('id'), 'ID of the user'),
            // General.
            'username' => new external_value(core_user::get_property_type('username'),
                'Username policy is defined in Moodle security config.', VALUE_OPTIONAL, '', NULL_NOT_ALLOWED),
            'auth' => new external_value(core_user::get_property_type('auth'), 'Auth plugins include manual, ldap, etc',
                VALUE_OPTIONAL, '', NULL_NOT_ALLOWED),
            'suspended' => new external_value(core_user::get_property_type('suspended'),
                'Suspend user account, either false to enable user login or true to disable it', VALUE_OPTIONAL),
            'password' => new external_value(core_user::get_property_type('password'),
                'Plain text password consisting of any characters', VALUE_OPTIONAL, '', NULL_NOT_ALLOWED),
            'firstname' => new external_value(core_user::get_property_type('firstname'), 'The first name(s) of the user',
                VALUE_OPTIONAL, '', NULL_NOT_ALLOWED),
            'lastname' => new external_value(core_user::get_property_type('lastname'), 'The family name of the user',
                VALUE_OPTIONAL),
            'email' => new external_value(core_user::get_property_type('email'), 'A valid and unique email address', VALUE_OPTIONAL,
                '', NULL_NOT_ALLOWED),
            'maildisplay' => new external_value(core_user::get_property_type('maildisplay'), 'Email display', VALUE_OPTIONAL),
            'city' => new external_value(core_user::get_property_type('city'), 'Home city of the user', VALUE_OPTIONAL),
            'country' => new external_value(core_user::get_property_type('country'),
                'Home country code of the user, such as AU or CZ', VALUE_OPTIONAL),
            'timezone' => new external_value(core_user::get_property_type('timezone'),
                'Timezone code such as Australia/Perth, or 99 for default', VALUE_OPTIONAL),
            'description' => new external_value(core_user::get_property_type('description'), 'User profile description, no HTML',
                VALUE_OPTIONAL),
            // User picture.
            'userpicture' => new external_value(PARAM_INT,
                'The itemid where the new user picture has been uploaded to, 0 to delete', VALUE_OPTIONAL),
            // Additional names.
            'firstnamephonetic' => new external_value(core_user::get_property_type('firstnamephonetic'),
                'The first name(s) phonetically of the user', VALUE_OPTIONAL),
            'lastnamephonetic' => new external_value(core_user::get_property_type('lastnamephonetic'),
                'The family name phonetically of the user', VALUE_OPTIONAL),
            'middlename' => new external_value(core_user::get_property_type('middlename'), 'The middle name of the user',
                VALUE_OPTIONAL),
            'alternatename' => new external_value(core_user::get_property_type('alternatename'), 'The alternate name of the user',
                VALUE_OPTIONAL),
            // Interests.
            'interests' => new external_value(PARAM_TEXT, 'User interests (separated by commas)', VALUE_OPTIONAL),
            // Optional.
            'idnumber' => new external_value(core_user::get_property_type('idnumber'),
                'An arbitrary ID code number perhaps from the institution', VALUE_OPTIONAL),
            'institution' => new external_value(core_user::get_property_type('institution'), 'Institution', VALUE_OPTIONAL),
            'department' => new external_value(core_user::get_property_type('department'), 'Department', VALUE_OPTIONAL),
            'phone1' => new external_value(core_user::get_property_type('phone1'), 'Phone', VALUE_OPTIONAL),
            'phone2' => new external_value(core_user::get_property_type('phone2'), 'Mobile phone', VALUE_OPTIONAL),
            'address' => new external_value(core_user::get_property_type('address'), 'Postal address', VALUE_OPTIONAL),
            // Other user preferences stored in the user table.
            'lang' => new external_value(core_user::get_property_type('lang'), 'Language code such as "en", must exist on server',
                VALUE_OPTIONAL, '', NULL_NOT_ALLOWED),
            'calendartype' => new external_value(core_user::get_property_type('calendartype'),
                'Calendar type such as "gregorian", must exist on server', VALUE_OPTIONAL, '', NULL_NOT_ALLOWED),
            'theme' => new external_value(core_user::get_property_type('theme'),
                'Theme name such as "standard", must exist on server', VALUE_OPTIONAL),
            'mailformat' => new external_value(core_user::get_property_type('mailformat'),
                'Mail format code is 0 for plain text, 1 for HTML etc', VALUE_OPTIONAL),
            // Custom user profile fields.
            'customfields' => new external_multiple_structure(
                new external_single_structure(
                    [
                        'type'  => new external_value(PARAM_ALPHANUMEXT, 'The name of the custom field'),
                        'value' => new external_value(PARAM_RAW, 'The value of the custom field')
                    ]
                ), 'User custom fields (also known as user profil fields)', VALUE_OPTIONAL),
            // User preferences.
            'preferences' => new external_multiple_structure(
                new external_single_structure(
                    [
                        'type'  => new external_value(PARAM_RAW, 'The name of the preference'),
                        'value' => new external_value(PARAM_RAW, 'The value of the preference')
                    ]
                ), 'User preferences', VALUE_OPTIONAL),
        ];
        return new external_function_parameters(
            [
                'users' => new external_multiple_structure(
                    new external_single_structure($userfields)
                )
            ]
        );
    }

    /**
     * Update users
     *
     * @param array $users
     * @return null
     * @since Moodle 2.2
     */
    public static function update_users($users) {
        global $CFG, $DB, $USER;
        require_once($CFG->dirroot."/user/lib.php");
        require_once($CFG->dirroot."/user/profile/lib.php"); // Required for customfields related function.
        require_once($CFG->dirroot.'/user/editlib.php');

        // Ensure the current user is allowed to run this function.
        $context = context_system::instance();
        require_capability('moodle/user:update', $context);
        self::validate_context($context);

        $params = self::validate_parameters(self::update_users_parameters(), array('users' => $users));

        $filemanageroptions = array('maxbytes' => $CFG->maxbytes,
            'subdirs'        => 0,
            'maxfiles'       => 1,
            'accepted_types' => 'optimised_image');

        $transaction = $DB->start_delegated_transaction();

        foreach ($params['users'] as $user) {
            // First check the user exists.
            if (!$existinguser = core_user::get_user($user['id'])) {
                continue;
            }
            // Check if we are trying to update an admin.
            if ($existinguser->id != $USER->id and is_siteadmin($existinguser) and !is_siteadmin($USER)) {
                continue;
            }
            // Other checks (deleted, remote or guest users).
            if ($existinguser->deleted or is_mnet_remote_user($existinguser) or isguestuser($existinguser->id)) {
                continue;
            }
            // Check duplicated emails.
            if (isset($user['email']) && $user['email'] !== $existinguser->email) {
                if (!validate_email($user['email'])) {
                    continue;
                } else if (empty($CFG->allowaccountssameemail)) {
                    // Make a case-insensitive query for the given email address and make sure to exclude the user being updated.
                    $select = $DB->sql_equal('email', ':email', false) . ' AND mnethostid = :mnethostid AND id <> :userid';
                    $params = array(
                        'email' => $user['email'],
                        'mnethostid' => $CFG->mnet_localhost_id,
                        'userid' => $user['id']
                    );
                    // Skip if there are other user(s) that already have the same email.
                    if ($DB->record_exists_select('user', $select, $params)) {
                        continue;
                    }
                }
            }

            user_update_user($user, true, false);

            $userobject = (object)$user;

            // Update user picture if it was specified for this user.
            if (empty($CFG->disableuserimages) && isset($user['userpicture'])) {
                $userobject->deletepicture = null;

                if ($user['userpicture'] == 0) {
                    $userobject->deletepicture = true;
                } else {
                    $userobject->imagefile = $user['userpicture'];
                }

                core_user::update_picture($userobject, $filemanageroptions);
            }

            // Update user interests.
            if (!empty($user['interests'])) {
                $trimmedinterests = array_map('trim', explode(',', $user['interests']));
                $interests = array_filter($trimmedinterests, function($value) {
                    return !empty($value);
                });
                useredit_update_interests($userobject, $interests);
            }

            // Update user custom fields.
            if (!empty($user['customfields'])) {

                foreach ($user['customfields'] as $customfield) {
                    // Profile_save_data() saves profile file it's expecting a user with the correct id,
                    // and custom field to be named profile_field_"shortname".
                    $user["profile_field_".$customfield['type']] = $customfield['value'];
                }
                profile_save_data((object) $user);
            }

            // Trigger event.
            \core\event\user_updated::create_from_userid($user['id'])->trigger();

            // Preferences.
            if (!empty($user['preferences'])) {
                $userpref = clone($existinguser);
                foreach ($user['preferences'] as $preference) {
                    $userpref->{'preference_'.$preference['type']} = $preference['value'];
                }
                useredit_update_user_preference($userpref);
            }
            if (isset($user['suspended']) and $user['suspended']) {
                \core\session\manager::kill_user_sessions($user['id']);
            }
        }

        $transaction->allow_commit();

        return get_string('successupdate', 'local_uvirtual');
    }

    /**
     * Returns description of method result value
     *
     * @return null
     * @since Moodle 2.2
     */
    public static function update_users_returns() {
        return new external_value(PARAM_TEXT, 'Status of the request');
    }

    /**
     * Returns description of method parameters.
     *
     * @return external_function_parameters
     * @since Moodle 2.2
     */
    public static function enrol_users_parameters() {
        return new external_function_parameters(
            array(
                'enrolments' => new external_multiple_structure(
                    new external_single_structure(
                        array(
                            'roleid' => new external_value(PARAM_INT, 'Role to assign to the user'),
                            'userid' => new external_value(PARAM_INT, 'The user that is going to be enrolled'),
                            'courseid' => new external_value(PARAM_INT, 'The course to enrol the user role in'),
                            'timestart' => new external_value(PARAM_INT, 'Timestamp when the enrolment start', VALUE_OPTIONAL),
                            'timeend' => new external_value(PARAM_INT, 'Timestamp when the enrolment end', VALUE_OPTIONAL),
                            'suspend' => new external_value(PARAM_INT, 'set to 1 to suspend the enrolment', VALUE_OPTIONAL)
                        )
                    )
                )
            )
        );
    }

    /**
     * Enrolment of users.
     *
     * Function throw an exception at the first error encountered.
     * @param array $enrolments  An array of user enrolment
     * @since Moodle 2.2
     */
    public static function enrol_users($enrolments) {
        global $DB, $CFG;

        require_once($CFG->libdir . '/enrollib.php');

        $params = self::validate_parameters(self::enrol_users_parameters(),
            array('enrolments' => $enrolments));

        $transaction = $DB->start_delegated_transaction(); // Rollback all enrolment if an error occurs
        // (except if the DB doesn't support it).

        // Retrieve the manual enrolment plugin.
        $enrol = enrol_get_plugin('manual');
        if (empty($enrol)) {
            throw new \moodle_exception('manualpluginnotinstalled', 'enrol_manual');
        }

        foreach ($params['enrolments'] as $enrolment) {
            // Ensure the current user is allowed to run this function in the enrolment context.
            $context = \context_course::instance($enrolment['courseid'], IGNORE_MISSING);
            self::validate_context($context);

            // Check that the user has the permission to manual enrol.
            require_capability('enrol/manual:enrol', $context);

            // Throw an exception if user is not able to assign the role.
            $roles = get_assignable_roles($context);
            if (!array_key_exists($enrolment['roleid'], $roles)) {
                $errorparams = new \stdClass();
                $errorparams->roleid = $enrolment['roleid'];
                $errorparams->courseid = $enrolment['courseid'];
                $errorparams->userid = $enrolment['userid'];
                throw new \moodle_exception('wsusercannotassign', 'enrol_manual', '', $errorparams);
            }

            // Check manual enrolment plugin instance is enabled/exist.
            $instance = null;
            $enrolinstances = enrol_get_instances($enrolment['courseid'], true);
            foreach ($enrolinstances as $courseenrolinstance) {
                if ($courseenrolinstance->enrol == "manual") {
                    $instance = $courseenrolinstance;
                    break;
                }
            }
            if (empty($instance)) {
                $errorparams = new \stdClass();
                $errorparams->courseid = $enrolment['courseid'];
                throw new \moodle_exception('wsnoinstance', 'enrol_manual', $errorparams);
            }

            // Check that the plugin accept enrolment (it should always the case, it's hard coded in the plugin).
            if (!$enrol->allow_enrol($instance)) {
                $errorparams = new \stdClass();
                $errorparams->roleid = $enrolment['roleid'];
                $errorparams->courseid = $enrolment['courseid'];
                $errorparams->userid = $enrolment['userid'];
                throw new \moodle_exception('wscannotenrol', 'enrol_manual', '', $errorparams);
            }

            // Finally proceed the enrolment.
            $enrolment['timestart'] = isset($enrolment['timestart']) ? $enrolment['timestart'] : 0;
            $enrolment['timeend'] = isset($enrolment['timeend']) ? $enrolment['timeend'] : 0;
            $enrolment['status'] = (isset($enrolment['suspend']) && !empty($enrolment['suspend'])) ?
                ENROL_USER_SUSPENDED : ENROL_USER_ACTIVE;

            $enrol->enrol_user($instance, $enrolment['userid'], $enrolment['roleid'],
                $enrolment['timestart'], $enrolment['timeend'], $enrolment['status']);

        }

        $transaction->allow_commit();
    }

    /**
     * Returns description of method result value.
     *
     * @return null
     * @since Moodle 2.2
     */
    public static function enrol_users_returns() {
        return null;
    }

    /**
     * Returns description of method parameters.
     *
     * @return external_function_parameters
     */
    public static function unenrol_users_parameters() {
        return new external_function_parameters(array(
            'enrolments' => new external_multiple_structure(
                new external_single_structure(
                    array(
                        'userid' => new external_value(PARAM_INT, 'The user that is going to be unenrolled'),
                        'courseid' => new external_value(PARAM_INT, 'The course to unenrol the user from'),
                        'roleid' => new external_value(PARAM_INT, 'The user role', VALUE_OPTIONAL),
                    )
                )
            )
        ));
    }

    /**
     * Unenrolment of users.
     *
     * @param array $enrolments an array of course user and role ids
     * @throws coding_exception
     * @throws dml_transaction_exception
     * @throws invalid_parameter_exception
     * @throws moodle_exception
     * @throws required_capability_exception
     * @throws restricted_context_exception
     */
    public static function unenrol_users($enrolments) {
        global $CFG, $DB;
        $params = self::validate_parameters(self::unenrol_users_parameters(), array('enrolments' => $enrolments));
        require_once($CFG->libdir . '/enrollib.php');
        $transaction = $DB->start_delegated_transaction(); // Rollback all enrolment if an error occurs.
        $enrol = enrol_get_plugin('manual');
        if (empty($enrol)) {
            throw new \moodle_exception('manualpluginnotinstalled', 'enrol_manual');
        }

        foreach ($params['enrolments'] as $enrolment) {
            $context = \context_course::instance($enrolment['courseid']);
            self::validate_context($context);
            require_capability('enrol/manual:unenrol', $context);
            $instance = $DB->get_record('enrol', array('courseid' => $enrolment['courseid'], 'enrol' => 'manual'));
            if (!$instance) {
                throw new \moodle_exception('wsnoinstance', 'enrol_manual', $enrolment);
            }
            $user = $DB->get_record('user', array('id' => $enrolment['userid']));
            if (!$user) {
                throw new \invalid_parameter_exception('User id not exist: '.$enrolment['userid']);
            }
            if (!$enrol->allow_unenrol($instance)) {
                throw new \moodle_exception('wscannotunenrol', 'enrol_manual', '', $enrolment);
            }
            $enrol->unenrol_user($instance, $enrolment['userid']);
        }
        $transaction->allow_commit();
    }

    /**
     * Returns description of method result value.
     *
     * @return null
     */
    public static function unenrol_users_returns() {
        return null;
    }

    public static function get_users_count_parameters() {
        return new external_function_parameters([
            'year' => new external_value(PARAM_INT, 'Year when the user was created', VALUE_DEFAULT, 0),
        ]);
    }

    public static function get_users_count_returns() {
        return new external_value(PARAM_TEXT);
    }

    public static function get_users_count($year) {

        $params = ['year' => $year];
        $params = self::validate_parameters(self::get_users_count_parameters(), $params);

        $usercount = local_uvirtual_get_users_count($params['year']);

        $response = json_encode($usercount);
        return $response;
    }

    /**
     * Block users by email and course ID.
     *
     * @param string $email The email of the user to block.
     * @param int $courseId The ID of the course.
     * @return bool True if the user was successfully blocked, false otherwise.
     * @since Moodle 2.2
     */
    public static function block_user($email) {
        
        return local_uvirtual_change_role($email, 'student', 'studbloq');
         
    }

    /**
     * Returns description of method parameters.
     *
     * @return external_function_parameters
     * @since Moodle 2.2
     */
    public static function block_user_parameters() {
        return new external_function_parameters([
            'email' => new external_value(PARAM_EMAIL, 'The email of the user to block.'),
           
        ]);
    }

    public static function block_user_returns() {
        return new external_single_structure(
            array(
                'success' => new external_value(PARAM_BOOL, 'True if the user was successfully blocked'),
            )
        );
    }

    /**
     * Unblock users by email and course ID.
     *
     * @param string $email The email of the user to unblock.
     * @param int $courseId The ID of the course.
     * @return bool True if the user was successfully unblocked, false otherwise.
     * @since Moodle 2.2
     */
    public static function unblock_user($email) {
    
        return local_uvirtual_change_role($email, 'studbloq', 'student');
    }

    /**
     * Returns description of method parameters.
     *
     * @return external_function_parameters
     * @since Moodle 2.2
     */
    public static function unblock_user_parameters() {
        return new external_function_parameters([
            'email' => new external_value(PARAM_EMAIL, 'The email of the user to unblock.'),
            
        ]);
    }

    public static function unblock_user_returns() {
        return new external_single_structure(
            array(
                'success' => new external_value(PARAM_BOOL, 'True if the user was successfully blocked'),
            )
        );
    }

}
