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
 * Settings for AI Quiz Maker local plugin.
 * 
 * Note: Site ID and API Key are managed via AI Grader Central Config (local_aiconfig).
 * These fallback settings are only used if Central Config is not installed.
 *
 * @package    local_aiquizmaker
 * @copyright  2025 Essay Grader AI
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

if ($hassiteconfig) {
    $settings = new admin_settingpage('local_aiquizmaker', get_string('pluginname', 'local_aiquizmaker'));
    $ADMIN->add('localplugins', $settings);

    // Check if central config is available
    $centralconfigurl = new moodle_url('/admin/settings.php', ['section' => 'local_aiconfig']);
    $centralconfiginstalled = file_exists($CFG->dirroot . '/local/aiconfig/version.php');
    
    if ($centralconfiginstalled) {
        $settings->add(new admin_setting_heading(
            'local_aiquizmaker/centralconfig_notice',
            '',
            '<div style="padding: 12px; background: #ecfdf5; border: 1px solid #10b981; border-radius: 8px; margin-bottom: 16px;">' .
            '<strong style="color: #047857;">AI Grader Central Config is installed.</strong><br>' .
            'Site ID and API Key are managed centrally. ' .
            '<a href="' . $centralconfigurl->out() . '">Configure Central Settings</a>' .
            '</div>'
        ));
    } else {
        $settings->add(new admin_setting_heading(
            'local_aiquizmaker/centralconfig_notice',
            '',
            '<div style="padding: 12px; background: #fef3c7; border: 1px solid #f59e0b; border-radius: 8px; margin-bottom: 16px;">' .
            '<strong style="color: #b45309;">Recommended: Install AI Grader Central Config</strong><br>' .
            'Configure Site ID and API Key once for all AI Grader plugins.' .
            '</div>'
        ));
    }

    // Site ID setting (fallback).
    $settings->add(new admin_setting_configtext(
        'local_aiquizmaker/siteid',
        get_string('siteid', 'local_aiquizmaker'),
        get_string('siteid_desc', 'local_aiquizmaker') . ($centralconfiginstalled ? ' (Fallback - Central Config takes priority)' : ''),
        '',
        PARAM_TEXT
    ));

    // API Key setting (fallback).
    $settings->add(new admin_setting_configpasswordunmask(
        'local_aiquizmaker/apikey',
        get_string('apikey', 'local_aiquizmaker'),
        get_string('apikey_desc', 'local_aiquizmaker') . ($centralconfiginstalled ? ' (Fallback - Central Config takes priority)' : ''),
        ''
    ));
}
