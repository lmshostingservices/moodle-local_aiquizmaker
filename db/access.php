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
 * Capability definitions for AI Quiz Maker local plugin.
 *
 * @package    local_aiquizmaker
 * @copyright  2025 Essay Grader AI
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$capabilities = [
    // Capability to use the AI Quiz Maker.
    // FIX-AIQM-NONEDITING-TEACHER (v3.16.94): 'teacher' removed from the archetypes.
    //
    // This capability gates a question-AUTHORING tool: it writes into the question bank and
    // adds questions to a quiz. The non-editing teacher archetype is a marking role -- it
    // holds no moodle/question:add and no mod/quiz:manage -- so granting it here handed a
    // course tab to people who could open the tool but could not have used anything it
    // produced.
    //
    // Removing the archetype only changes roles created (or archetype-reset) after this
    // upgrade; a site that has already granted it keeps the grant. That is deliberate, and it
    // is why the tool's entry points now ALSO require an authoring capability in the real
    // context -- see lib.php, the footer hook and index.php. Both halves are needed: the
    // archetype stops new sites handing it out, the context check stops existing ones showing
    // a tab that cannot do anything.
    'local/aiquizmaker:use' => [
        'captype' => 'write',
        'contextlevel' => CONTEXT_SYSTEM,
        'archetypes' => [
            'editingteacher' => CAP_ALLOW,
            'manager' => CAP_ALLOW,
        ],
    ],
];
