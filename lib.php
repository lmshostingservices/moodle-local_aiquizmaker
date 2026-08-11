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
 * Library functions for AI Quiz Maker local plugin.
 *
 * @package    local_aiquizmaker
 * @copyright  2025 Essay Grader AI
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

// FIX-AIQM-REDECL (v3.16.88): Guard both navigation hooks with function_exists()
// so that sites that still have the old local_essaymaker plugin installed alongside
// local_aiquizmaker do not crash with a fatal "Cannot redeclare" error.
// The old local_essaymaker/lib.php (distributed during the rename transition)
// contained these same function names — having both plugins on disk caused PHP to
// try to declare local_aiquizmaker_extend_navigation() twice, throwing a 500.
// Wrapping with function_exists() makes the load order irrelevant; whichever
// plugin is loaded first wins, and the second declaration is silently skipped.
// Both functions are identical in behaviour so functionality is unaffected.

/**
 * Add navigation node to the site navigation.
 *
 * This function adds the AI Quiz Maker link to Moodle's navigation menu
 * for users who have the required capability.
 *
 * @param navigation_node $navigation The navigation node to extend.
 * @param stdClass $course The course object (can be null for site context).
 * @param context $context The current context.
 */
if (!function_exists('local_aiquizmaker_extend_navigation')) {
function local_aiquizmaker_extend_navigation(navigation_node $navigation, $course = null, $context = null) {
    global $PAGE;

    // Only add if user has the capability to use the plugin.
    if (!has_capability('local/aiquizmaker:use', context_system::instance())) {
        return;
    }

    // Add link to site navigation.
    $navigation->add(
        get_string('pluginname', 'local_aiquizmaker'),
        new moodle_url('/local/aiquizmaker/index.php'),
        navigation_node::TYPE_CUSTOM,
        null,
        'local_aiquizmaker',
        new pix_icon('i/edit', '')
    );
}
}

/**
 * Extend the settings navigation for quiz module.
 *
 * This adds an "AI Quiz Maker" link to the quiz settings menu (gear icon)
 * when viewing or editing a quiz.
 *
 * @param settings_navigation $settingsnav The settings navigation object.
 * @param context $context The context of the module.
 */
if (!function_exists('local_aiquizmaker_extend_settings_navigation')) {
function local_aiquizmaker_extend_settings_navigation(settings_navigation $settingsnav, context $context) {
    // Only work with module context (quiz).
    if (!$context instanceof context_module) {
        return;
    }

    // Get the course module.
    $cm = get_coursemodule_from_id('quiz', $context->instanceid, 0, false, IGNORE_MISSING);
    if (!$cm) {
        return;
    }

    // Check user has capability to use the plugin.
    if (!has_capability('local/aiquizmaker:use', context_system::instance())) {
        return;
    }

    // Find the module settings node.
    $modulesettings = $settingsnav->find('modulesettings', navigation_node::TYPE_SETTING);
    if (!$modulesettings) {
        return;
    }

    // Add AI Quiz Maker link to the quiz settings menu.
    $url = new moodle_url('/local/aiquizmaker/index.php', ['cmid' => $cm->id]);
    $modulesettings->add(
        get_string('pluginname', 'local_aiquizmaker'),
        $url,
        navigation_node::TYPE_SETTING,
        null,
        'local_aiquizmaker_settings',
        new pix_icon('i/edit', '')
    );
}
}

// Note: Both hook callbacks have been migrated to the new Moodle 4.5+ hook system.
// See: classes/hook/before_standard_head_html_generation.php
// See: classes/hook/before_footer_html_generation.php
// See: db/hooks.php
