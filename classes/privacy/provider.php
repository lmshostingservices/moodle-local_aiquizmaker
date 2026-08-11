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
 * Privacy provider for AI Quiz Maker local plugin.
 *
 * @package    local_aiquizmaker
 * @copyright  2025 Essay Grader AI
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_aiquizmaker\privacy;

use core_privacy\local\metadata\collection;
use core_privacy\local\request\approved_contextlist;
use core_privacy\local\request\approved_userlist;
use core_privacy\local\request\contextlist;
use core_privacy\local\request\userlist;

defined('MOODLE_INTERNAL') || die();

/**
 * Privacy provider implementation for local_essaymaker.
 *
 * This plugin sends competency criteria to an external AI service for question generation.
 * It does not store any personal data locally.
 *
 * @package    local_aiquizmaker
 * @copyright  2025 Essay Grader AI
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class provider implements
    \core_privacy\local\metadata\provider,
    \core_privacy\local\request\plugin\provider,
    \core_privacy\local\request\core_userlist_provider {
    /**
     * Returns metadata about the external data this plugin sends.
     *
     * @param collection $collection The collection to add metadata to.
     * @return collection The updated collection.
     */
    public static function get_metadata(collection $collection): collection {
        $collection->add_external_location_link(
            'essaygraderai_api',
            [
                'criteria' => 'privacy:metadata:essaygraderai_api:criteria',
                'industry' => 'privacy:metadata:essaygraderai_api:industry',
                'jobtitle' => 'privacy:metadata:essaygraderai_api:jobtitle',
            ],
            'privacy:metadata:essaygraderai_api'
        );

        return $collection;
    }

    /**
     * Get the list of contexts that contain user information for the specified user.
     *
     * This plugin does not store any user data locally.
     *
     * @param int $userid The user to search.
     * @return contextlist The contextlist containing the list of contexts used.
     */
    public static function get_contexts_for_userid(int $userid): contextlist {
        return new contextlist();
    }

    /**
     * Get the list of users who have data within a context.
     *
     * This plugin does not store any user data locally.
     *
     * @param userlist $userlist The userlist containing the list of users.
     */
    public static function get_users_in_context(userlist $userlist) {
        // No local data stored.
    }

    /**
     * Export all user data for the specified approved contexts.
     *
     * This plugin does not store any user data locally.
     *
     * @param approved_contextlist $contextlist The approved contexts to export.
     */
    public static function export_user_data(approved_contextlist $contextlist) {
        // No local data stored.
    }

    /**
     * Delete all data for all users in the specified context.
     *
     * This plugin does not store any user data locally.
     *
     * @param \context $context The specific context to delete data for.
     */
    public static function delete_data_for_all_users_in_context(\context $context) {
        // No local data stored.
    }

    /**
     * Delete all user data for the specified user, in the specified contexts.
     *
     * This plugin does not store any user data locally.
     *
     * @param approved_contextlist $contextlist The approved contexts and user.
     */
    public static function delete_data_for_user(approved_contextlist $contextlist) {
        // No local data stored.
    }

    /**
     * Delete multiple users within a single context.
     *
     * This plugin does not store any user data locally.
     *
     * @param approved_userlist $userlist The approved context and user information.
     */
    public static function delete_data_for_users(approved_userlist $userlist) {
        // No local data stored.
    }
}