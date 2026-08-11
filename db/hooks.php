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
 * Hook callbacks for local_essaymaker.
 *
 * @package    local_aiquizmaker
 * @copyright  2025 Essay Grader AI
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$callbacks = [
    // NOTE: Only the footer hook is registered here. A before_standard_head_html_generation
    // hook was previously also registered and performed the identical js_call_amd() call,
    // causing the "AI Quiz" button to be injected twice on the quiz edit page.
    // The footer hook is correct: AMD modules and the DOM are fully initialised by the time
    // before_footer_html_generation fires. The head hook class is retained but unused.
    [
        'hook' => \core\hook\output\before_footer_html_generation::class,
        'callback' => \local_aiquizmaker\hook\before_footer_html_generation::class . '::callback',
        'priority' => 500,
    ],
];
