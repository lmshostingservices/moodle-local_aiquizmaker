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
 * Upgrade steps for AI Quiz Maker.
 *
 * @package    local_aiquizmaker
 * @copyright  2025 Essay Grader AI
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

/**
 * Run the AI Quiz Maker upgrade steps.
 *
 * @param int $oldversion The version the site is upgrading from.
 * @return bool Always true; a failed step throws instead.
 */
function xmldb_local_aiquizmaker_upgrade($oldversion) {
    if ($oldversion < 2026072300) {
        upgrade_plugin_savepoint(true, 2026072300, 'local', 'aiquizmaker');
    }

    // FIX-AIQM-NONEDITING-TEACHER (v3.16.94): local/aiquizmaker:use no longer lists the
    // 'teacher' archetype. Existing grants are deliberately left in place -- a site may have
    // made that choice on purpose -- so there is no capability rewrite here. The tool's entry
    // points now require an authoring capability in the context they act on, which takes
    // effect regardless of role data. Savepoint only.
    if ($oldversion < 2026082603) {
        upgrade_plugin_savepoint(true, 2026082603, 'local', 'aiquizmaker');
    }

    return true;
}
