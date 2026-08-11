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
 * Hook callback for before_standard_head_html_generation.
 *
 * @package    local_aiquizmaker
 * @copyright  2025 Essay Grader AI
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_aiquizmaker\hook;

defined('MOODLE_INTERNAL') || die();

/**
 * Hook callbacks for local_essaymaker.
 */
class before_standard_head_html_generation {
    /**
     * Callback for before_standard_head_html_generation hook.
     * Injects AI Essay Maker button on quiz edit pages.
     *
     * @param \core\hook\output\before_standard_head_html_generation $hook The hook object.
     */
    public static function callback(\core\hook\output\before_standard_head_html_generation $hook): void {
        global $PAGE;

        // Only proceed on quiz edit pages.
        if ($PAGE->pagetype !== 'mod-quiz-edit') {
            return;
        }

        // Only add if user has the capability to use the plugin.
        if (!has_capability('local/aiquizmaker:use', \context_system::instance())) {
            return;
        }

        // Get the quiz ID from the URL.
        $cmid = optional_param('cmid', 0, PARAM_INT);

        // Load the quiz button JavaScript module.
        $PAGE->requires->js_call_amd('local_aiquizmaker/quizbutton', 'init', [[
            'cmid' => $cmid,
            'buttonText' => get_string('ai_essay_maker', 'local_aiquizmaker'),
            'essayMakerUrl' => (new \moodle_url('/local/aiquizmaker/index.php'))->out(false),
        ]]);
    }
}
