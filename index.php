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
 * Main page for AI Quiz Maker local plugin.
 *
 * This page provides the interface for generating essay questions.
 *
 * @package    local_aiquizmaker
 * @copyright  2025 Essay Grader AI
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../config.php');

require_login();
require_capability('local/aiquizmaker:use', context_system::instance());

// Get cmid if coming from a quiz edit page
$cmid = optional_param('cmid', 0, PARAM_INT);
$quizname = '';
$courseid = 0;

// Always set system context first - this is the plugin's native context
$PAGE->set_context(context_system::instance());

// If we have a cmid, get the quiz details (but don't change page context)
if ($cmid) {
    try {
        list($course, $cm) = get_course_and_cm_from_cmid($cmid, 'quiz');
        $quizname = $cm->name;
        $courseid = $course->id;
        // Note: We keep system context for the page, but will check module capability in ajax.php
    } catch (Exception $e) {
        // If invalid cmid, just ignore it
        $cmid = 0;
    }
}

$PAGE->set_url(new moodle_url('/local/aiquizmaker/index.php', $cmid ? ['cmid' => $cmid] : []));
$PAGE->set_title(get_string('page_title', 'local_aiquizmaker'));
$PAGE->set_pagelayout('standard');
$PAGE->set_heading(get_string('pluginname', 'local_aiquizmaker'));

$PAGE->requires->css(new moodle_url('/local/aiquizmaker/styles.css'));

// Get user's current Moodle language for multilingual AI output
$userlang = current_language();
$userlang = str_replace('_', '-', $userlang); // Convert 'en_au' -> 'en-AU'

// CRITICAL: Wrap config in extra array so Moodle passes as single object, not separate args
$PAGE->requires->js_call_amd('local_aiquizmaker/aiquizmaker', 'init', [[
    'sesskey' => sesskey(),
    'ajaxurl' => (new moodle_url('/local/aiquizmaker/ajax.php'))->out(false),
    'buyurl' => 'https://lms-labs.com/pricing',
    'cmid' => $cmid,
    'quizname' => $quizname,
    'courseid' => $courseid,
    'language' => $userlang,
]]);

echo $OUTPUT->header();

// Explicitly include aiconfig lib.php if available
$aiconfiglib = $CFG->dirroot . '/local/aiconfig/lib.php';
if (file_exists($aiconfiglib)) {
    require_once($aiconfiglib);
}

// Priority 1: Central Config (recommended for multi-plugin setups)
$siteid = '';
$apikey = '';
if (function_exists('local_aiconfig_get_siteid')) {
    $siteid = local_aiconfig_get_siteid();
}
if (function_exists('local_aiconfig_get_apikey')) {
    $apikey = local_aiconfig_get_apikey();
}

// Priority 2: Plugin settings as fallback
if (empty($siteid)) {
    $siteid = get_config('local_aiquizmaker', 'siteid');
}
if (empty($apikey)) {
    $apikey = get_config('local_aiquizmaker', 'apikey');
}

echo html_writer::start_div('aiquizmaker-container', ['id' => 'aiquizmaker-root']);

// Header section.
echo html_writer::start_div('aiquizmaker-header');
echo html_writer::start_div('aiquizmaker-brand');
echo html_writer::start_div('aiquizmaker-logo');
echo '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M14.5 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V7.5L14.5 2z"/><polyline points="14 2 14 8 20 8"/><line x1="16" y1="13" x2="8" y2="13"/><line x1="16" y1="17" x2="8" y2="17"/><line x1="10" y1="9" x2="8" y2="9"/></svg>';
echo html_writer::end_div();

echo html_writer::start_div('');
echo html_writer::tag('h1', get_string('pluginname', 'local_aiquizmaker'), ['class' => 'aiquizmaker-title']);
echo html_writer::tag('p', get_string('page_description', 'local_aiquizmaker'), ['class' => 'aiquizmaker-subtitle']);
echo html_writer::end_div();
echo html_writer::end_div(); // End brand.

// Credits badge.
echo html_writer::start_div('aiquizmaker-credits-wrapper');
echo html_writer::start_div('aiquizmaker-credits', ['id' => 'aiquizmaker-credits-badge']);
echo html_writer::start_div('aiquizmaker-credits-icon');
echo '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><path d="M16 8h-6a2 2 0 1 0 0 4h4a2 2 0 1 1 0 4H8"/><path d="M12 18V6"/></svg>';
echo html_writer::end_div();

echo html_writer::start_div('');
echo html_writer::tag('div', get_string('credits', 'local_aiquizmaker'), ['class' => 'aiquizmaker-credits-label']);
echo html_writer::tag('div', '...', ['class' => 'aiquizmaker-credits-value', 'id' => 'aiquizmaker-credits-count']);
echo html_writer::end_div();
echo html_writer::end_div(); // End credits.
echo html_writer::end_div(); // End credits wrapper.
echo html_writer::end_div(); // End header.

// Quiz target indicator - show when coming from a quiz
if ($cmid && $quizname) {
    $clearurl = (new moodle_url('/local/aiquizmaker/index.php'))->out(false);
    echo html_writer::start_div('aiquizmaker-alert aiquizmaker-alert-success aiquizmaker-quiz-target', ['style' => 'position:relative;']);
    echo '<svg class="aiquizmaker-alert-icon" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/><polyline points="22 4 12 14.01 9 11.01"/></svg>';
    echo html_writer::start_div('aiquizmaker-alert-content');
    echo html_writer::tag('p', get_string('quiz_target_title', 'local_aiquizmaker'), ['class' => 'aiquizmaker-alert-title']);
    echo html_writer::tag('p', get_string('quiz_target_message', 'local_aiquizmaker', format_string($quizname)), ['class' => 'aiquizmaker-alert-message']);
    echo html_writer::end_div();
    echo html_writer::tag('a', '&times;', [
        'href' => $clearurl,
        'title' => 'Clear quiz context',
        'style' => 'position:absolute;top:8px;right:12px;font-size:1.3rem;line-height:1;color:inherit;opacity:0.6;text-decoration:none;',
        'aria-label' => 'Clear quiz context',
    ]);
    echo html_writer::end_div();
}

// Configuration warning.
if (empty($siteid) || empty($apikey)) {
    echo html_writer::start_div('aiquizmaker-alert aiquizmaker-alert-warning');
    echo '<svg class="aiquizmaker-alert-icon" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>';
    echo html_writer::start_div('aiquizmaker-alert-content');
    echo html_writer::tag('p', get_string('not_configured', 'local_aiquizmaker'), ['class' => 'aiquizmaker-alert-title']);
    echo html_writer::tag('p', get_string('configure_message', 'local_aiquizmaker'), ['class' => 'aiquizmaker-alert-message']);
    echo html_writer::end_div();
    echo html_writer::end_div();
}

// Form.
echo html_writer::start_tag('form', ['id' => 'aiquizmaker-form', 'class' => 'aiquizmaker-form', 'novalidate' => 'novalidate']);

// Input mode toggle.
echo html_writer::start_div('aiquizmaker-section aiquizmaker-mode-toggle-section');
echo html_writer::tag('h2', get_string('section_input_mode', 'local_aiquizmaker'), ['class' => 'aiquizmaker-section-title']);
echo html_writer::tag('p', get_string('section_input_mode_help', 'local_aiquizmaker'), ['class' => 'aiquizmaker-sublabel']);
echo html_writer::start_div('aiquizmaker-mode-tabs');
echo html_writer::start_tag('button', [
    'type' => 'button',
    'id' => 'mode-tab-criteria',
    'class' => 'aiquizmaker-mode-tab aiquizmaker-mode-tab-active',
    'data-mode' => 'criteria',
]);
echo '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="8" y1="6" x2="21" y2="6"/><line x1="8" y1="12" x2="21" y2="12"/><line x1="8" y1="18" x2="21" y2="18"/><line x1="3" y1="6" x2="3.01" y2="6"/><line x1="3" y1="12" x2="3.01" y2="12"/><line x1="3" y1="18" x2="3.01" y2="18"/></svg>';
echo get_string('mode_tab_criteria', 'local_aiquizmaker');
echo html_writer::end_tag('button');
echo html_writer::start_tag('button', [
    'type' => 'button',
    'id' => 'mode-tab-ownquestions',
    'class' => 'aiquizmaker-mode-tab',
    'data-mode' => 'ownquestions',
]);
echo '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 20h9"/><path d="M16.5 3.5a2.121 2.121 0 0 1 3 3L7 19l-4 1 1-4L16.5 3.5z"/></svg>';
echo get_string('mode_tab_ownquestions', 'local_aiquizmaker');
echo html_writer::end_tag('button');
echo html_writer::end_div();
echo html_writer::end_div(); // End mode toggle section.

// Paste content section (criteria mode only).
echo html_writer::start_div('aiquizmaker-section aiquizmaker-content-section', ['id' => 'aiquizmaker-content-section']);
echo html_writer::tag('h2', get_string('section_content', 'local_aiquizmaker'), ['class' => 'aiquizmaker-section-title']);
echo html_writer::tag('p', get_string('section_content_help', 'local_aiquizmaker'), ['class' => 'aiquizmaker-sublabel']);
echo html_writer::tag('textarea', '', [
    'id' => 'pasted-content-input',
    'name' => 'pasted_content',
    'class' => 'aiquizmaker-textarea aiquizmaker-content-textarea',
    'placeholder' => get_string('content_placeholder', 'local_aiquizmaker'),
    'rows' => '6',
]);
echo html_writer::start_div('aiquizmaker-content-actions');
echo html_writer::start_tag('button', [
    'type' => 'button',
    'id' => 'extract-criteria-btn',
    'class' => 'aiquizmaker-btn aiquizmaker-btn-primary',
]);
echo '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 2l3.09 6.26L22 9.27l-5 4.87 1.18 6.88L12 17.77l-6.18 3.25L7 14.14 2 9.27l6.91-1.01L12 2z"/></svg>';
echo '<span class="extract-criteria-btn-label">' . get_string('extract_criteria_btn', 'local_aiquizmaker') . '</span>';
echo html_writer::end_tag('button');
echo html_writer::start_tag('button', [
    'type' => 'button',
    'id' => 'clear-content-btn',
    'class' => 'aiquizmaker-btn aiquizmaker-btn-ghost',
]);
echo '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="3 6 5 6 21 6"/><path d="M19 6l-1 14H6L5 6"/><path d="M10 11v6"/><path d="M14 11v6"/><path d="M9 6V4h6v2"/></svg>';
echo get_string('content_clear', 'local_aiquizmaker');
echo html_writer::end_tag('button');
echo html_writer::end_div();
echo html_writer::end_div(); // End content section.

// Criteria section (criteria mode only).
echo html_writer::start_div('aiquizmaker-section', ['id' => 'aiquizmaker-criteria-section']);
echo html_writer::tag('h2', get_string('section_criteria', 'local_aiquizmaker'), ['class' => 'aiquizmaker-section-title']);

// Bulk add criteria area.
echo html_writer::start_div('aiquizmaker-bulk-add');
echo html_writer::tag('label', get_string('bulk_add_label', 'local_aiquizmaker'), ['class' => 'aiquizmaker-label']);
echo html_writer::tag('textarea', '', [
    'id' => 'bulk-criteria-input',
    'class' => 'aiquizmaker-textarea',
    'placeholder' => get_string('bulk_add_placeholder', 'local_aiquizmaker'),
    'rows' => '4',
]);
echo html_writer::start_div('aiquizmaker-bulk-add-actions');
echo html_writer::start_tag('button', [
    'type' => 'button',
    'id' => 'bulk-add-btn',
    'class' => 'aiquizmaker-btn aiquizmaker-btn-secondary',
]);
echo '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="17 8 12 3 7 8"/><line x1="12" y1="3" x2="12" y2="15"/></svg>';
echo get_string('bulk_add_button', 'local_aiquizmaker');
echo html_writer::end_tag('button');
echo html_writer::end_div();
echo html_writer::end_div();

// Apply to All row - allows setting question count for all criteria at once.
echo html_writer::start_div('aiquizmaker-apply-all');
echo html_writer::tag('label', get_string('apply_to_all_label', 'local_aiquizmaker'), [
    'class' => 'aiquizmaker-label',
    'for' => 'apply-all-count',
]);
$applyalloptions = '';
for ($i = 1; $i <= 10; $i++) {
    $label = ($i === 1) ? get_string('question_singular', 'local_aiquizmaker', $i)
                        : get_string('question_plural', 'local_aiquizmaker', $i);
    $applyalloptions .= html_writer::tag('option', $label, ['value' => $i]);
}
echo html_writer::tag('select', $applyalloptions, [
    'id' => 'apply-all-count',
    'class' => 'aiquizmaker-select',
]);
echo html_writer::start_tag('button', [
    'type' => 'button',
    'id' => 'apply-all-btn',
    'class' => 'aiquizmaker-btn aiquizmaker-btn-secondary',
]);
echo '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="17 1 21 5 17 9"/><path d="M3 11V9a4 4 0 0 1 4-4h14"/><polyline points="7 23 3 19 7 15"/><path d="M21 13v2a4 4 0 0 1-4 4H3"/></svg>';
echo get_string('apply_to_all_button', 'local_aiquizmaker');
echo html_writer::end_tag('button');
echo html_writer::end_div();

echo html_writer::start_div('', ['id' => 'criteria-container']);

// First criteria row.
echo html_writer::start_div('aiquizmaker-criteria-row', ['data-index' => '0']);
echo html_writer::start_div('aiquizmaker-criteria-inputs');
echo html_writer::empty_tag('input', [
    'type' => 'text',
    'name' => 'criteria[0][text]',
    'class' => 'aiquizmaker-input',
    'placeholder' => get_string('criteria_placeholder', 'local_aiquizmaker'),
]);

$selectoptions = '';
for ($i = 1; $i <= 10; $i++) {
    $label = ($i === 1) ? get_string('question_singular', 'local_aiquizmaker', $i)
                        : get_string('question_plural', 'local_aiquizmaker', $i);
    $selectoptions .= html_writer::tag('option', $label, ['value' => $i]);
}
echo html_writer::tag('select', $selectoptions, [
    'name' => 'criteria[0][count]',
    'class' => 'aiquizmaker-select',
]);
echo html_writer::end_div();

echo html_writer::start_tag('button', [
    'type' => 'button',
    'class' => 'aiquizmaker-btn-icon aiquizmaker-remove-criteria',
    'style' => 'visibility: hidden;',
]);
echo '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>';
echo html_writer::end_tag('button');
echo html_writer::end_div(); // End criteria row.
echo html_writer::end_div(); // End criteria container.

// Add criteria button.
echo html_writer::start_tag('button', [
    'type' => 'button',
    'id' => 'add-criteria-btn',
    'class' => 'aiquizmaker-btn aiquizmaker-btn-secondary',
]);
echo '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>';
echo get_string('add_criteria', 'local_aiquizmaker');
echo html_writer::end_tag('button');
echo html_writer::end_div(); // End criteria section.

// Own questions section (own-questions mode only, hidden by default).
echo html_writer::start_div('aiquizmaker-section', ['id' => 'own-questions-section', 'style' => 'display:none;']);
echo html_writer::tag('h2', get_string('section_ownquestions', 'local_aiquizmaker'), ['class' => 'aiquizmaker-section-title']);
echo html_writer::tag('p', get_string('section_ownquestions_help', 'local_aiquizmaker'), ['class' => 'aiquizmaker-sublabel']);

// ChatGPT Prompt Helper — collapsible.
echo html_writer::start_div('aiquizmaker-prompt-helper', ['id' => 'prompt-helper-container']);

// Toggle header.
echo html_writer::start_div('aiquizmaker-prompt-helper-header', ['id' => 'prompt-helper-toggle', 'role' => 'button', 'tabindex' => '0', 'aria-expanded' => 'false']);
echo '<svg class="aiquizmaker-prompt-helper-icon" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 8V4H8"/><rect x="8" y="2" width="8" height="4" rx="1"/><path d="M16 4h2a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2H6a2 2 0 0 1-2-2V6a2 2 0 0 1 2-2h2"/><path d="M12 11v6"/><path d="M9 14l3-3 3 3"/></svg>';
echo html_writer::tag('span', get_string('chatgpt_helper_title', 'local_aiquizmaker'), ['class' => 'aiquizmaker-prompt-helper-title']);
echo '<svg class="aiquizmaker-prompt-helper-chevron" id="prompt-helper-chevron" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="6 9 12 15 18 9"/></svg>';
echo html_writer::end_div(); // end toggle header

// Collapsible body.
echo html_writer::start_div('aiquizmaker-prompt-helper-body', ['id' => 'prompt-helper-body', 'style' => 'display:none;']);
echo html_writer::tag('p', get_string('chatgpt_helper_help', 'local_aiquizmaker'), ['class' => 'aiquizmaker-sublabel', 'style' => 'margin-bottom:12px;']);

// Row 1: Topic.
echo html_writer::start_div('aiquizmaker-field', ['style' => 'margin-bottom:10px;']);
echo html_writer::tag('label', get_string('chatgpt_topic_label', 'local_aiquizmaker'), ['class' => 'aiquizmaker-label', 'for' => 'prompt-helper-topic']);
echo html_writer::empty_tag('input', [
    'type' => 'text',
    'id' => 'prompt-helper-topic',
    'class' => 'aiquizmaker-input',
    'placeholder' => get_string('chatgpt_topic_placeholder', 'local_aiquizmaker'),
    'style' => 'width:100%;',
]);
echo html_writer::end_div();

// Row 2: Count + Level.
echo html_writer::start_div('aiquizmaker-grid aiquizmaker-grid-2', ['style' => 'margin-bottom:10px;']);

echo html_writer::start_div('aiquizmaker-field');
echo html_writer::tag('label', get_string('chatgpt_count_label', 'local_aiquizmaker'), ['class' => 'aiquizmaker-label', 'for' => 'prompt-helper-count']);
$countopts = '';
for ($n = 1; $n <= 50; $n++) {
    $countopts .= html_writer::tag('option', $n, ['value' => $n, 'selected' => ($n === 10 ? 'selected' : null)]);
}
echo html_writer::tag('select', $countopts, ['id' => 'prompt-helper-count', 'class' => 'aiquizmaker-select', 'name' => 'prompt_helper_count']);
echo html_writer::end_div();

echo html_writer::start_div('aiquizmaker-field');
echo html_writer::tag('label', get_string('chatgpt_level_label', 'local_aiquizmaker'), ['class' => 'aiquizmaker-label', 'for' => 'prompt-helper-level']);
$levels = [
    '' => 'Any / Not specified',
    'Certificate II' => 'Certificate II',
    'Certificate III' => 'Certificate III',
    'Certificate IV' => 'Certificate IV',
    'Diploma' => 'Diploma',
    'Advanced Diploma' => 'Advanced Diploma',
    'Graduate Certificate' => 'Graduate Certificate',
    'Undergraduate' => 'Undergraduate (Year 1–2)',
    'Degree' => 'Undergraduate (Year 3–4)',
    'Postgraduate' => 'Postgraduate',
];
$levelopts = '';
foreach ($levels as $val => $label) {
    $levelopts .= html_writer::tag('option', $label, ['value' => $val]);
}
echo html_writer::tag('select', $levelopts, ['id' => 'prompt-helper-level', 'class' => 'aiquizmaker-select', 'name' => 'prompt_helper_level']);
echo html_writer::end_div();

echo html_writer::end_div(); // end grid

// Row 3: Checkboxes.
echo html_writer::start_div(null, ['style' => 'margin-bottom:14px;']);
echo html_writer::start_tag('label', ['class' => 'aiquizmaker-checkbox-label', 'style' => 'display:flex;align-items:flex-start;gap:8px;margin-bottom:8px;cursor:pointer;']);
echo html_writer::empty_tag('input', ['type' => 'checkbox', 'id' => 'prompt-helper-modelresponses', 'checked' => 'checked', 'style' => 'margin-top:3px;flex-shrink:0;']);
echo html_writer::tag('span', get_string('chatgpt_modelresponses_label', 'local_aiquizmaker'), ['class' => 'aiquizmaker-label', 'style' => 'margin:0;']);
echo html_writer::end_tag('label');

echo html_writer::start_tag('label', ['class' => 'aiquizmaker-checkbox-label', 'style' => 'display:flex;align-items:center;gap:8px;cursor:pointer;']);
echo html_writer::empty_tag('input', ['type' => 'checkbox', 'id' => 'prompt-helper-headings', 'style' => 'flex-shrink:0;']);
echo html_writer::tag('span', get_string('chatgpt_headings_label', 'local_aiquizmaker'), ['class' => 'aiquizmaker-label', 'style' => 'margin:0;']);
echo html_writer::end_tag('label');
echo html_writer::end_div();

// Question types notice.
echo html_writer::tag('p',
    get_string('chatgpt_qtypes_note', 'local_aiquizmaker'),
    ['class' => 'aiquizmaker-sublabel', 'id' => 'prompt-helper-qtypes-note',
     'style' => 'margin-bottom:12px;background:var(--aiquizmaker-muted,#f0f4ff);border-radius:6px;padding:8px 10px;font-size:0.85em;']);

// Generate button.
echo html_writer::start_tag('button', ['type' => 'button', 'id' => 'prompt-helper-generate-btn', 'class' => 'aiquizmaker-btn aiquizmaker-btn-secondary', 'style' => 'margin-bottom:14px;']);
echo '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polygon points="13 2 3 14 12 14 11 22 21 10 12 10 13 2"/></svg>';
echo get_string('chatgpt_generate_btn', 'local_aiquizmaker');
echo html_writer::end_tag('button');

// Output area (hidden initially).
echo html_writer::start_div('aiquizmaker-prompt-output', ['id' => 'prompt-helper-output', 'style' => 'display:none;']);
echo html_writer::tag('textarea', '', [
    'id' => 'prompt-helper-result',
    'class' => 'aiquizmaker-textarea',
    'readonly' => 'readonly',
    'rows' => '12',
    'style' => 'font-family:monospace;font-size:0.85em;background:var(--aiquizmaker-muted,#f8f9fa);resize:vertical;',
]);
echo html_writer::start_div(null, ['style' => 'display:flex;gap:8px;margin-top:8px;align-items:center;']);
echo html_writer::start_tag('button', ['type' => 'button', 'id' => 'prompt-helper-copy-btn', 'class' => 'aiquizmaker-btn aiquizmaker-btn-primary']);
echo '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="9" y="9" width="13" height="13" rx="2"/><path d="M5 15H4a2 2 0 0 1-2-2V4a2 2 0 0 1 2-2h9a2 2 0 0 1 2 2v1"/></svg>';
echo get_string('chatgpt_copy_btn', 'local_aiquizmaker');
echo html_writer::end_tag('button');
echo html_writer::tag('span', '', ['id' => 'prompt-helper-copied-msg', 'style' => 'color:var(--aiquizmaker-success,#22c55e);font-size:0.875em;display:none;']);
echo html_writer::end_div();
echo html_writer::end_div(); // end output area

echo html_writer::end_div(); // end body
echo html_writer::end_div(); // end prompt-helper-container

echo html_writer::tag('p', get_string('chatgpt_paste_label', 'local_aiquizmaker'), ['class' => 'aiquizmaker-sublabel', 'style' => 'margin-top:16px;margin-bottom:6px;font-weight:500;']);
echo html_writer::tag('textarea', '', [
    'id' => 'own-questions-input',
    'name' => 'own_questions',
    'class' => 'aiquizmaker-textarea aiquizmaker-content-textarea',
    'placeholder' => get_string('ownquestions_placeholder', 'local_aiquizmaker'),
    'rows' => '10',
]);
echo html_writer::tag('p', get_string('ownquestions_hint', 'local_aiquizmaker'), ['class' => 'aiquizmaker-sublabel', 'style' => 'margin-top:6px;']);
echo html_writer::end_div(); // End own questions section.

// Context section (collapsible with toggle).
echo html_writer::start_div('aiquizmaker-section aiquizmaker-context-section');
echo html_writer::start_div('aiquizmaker-context-header');
echo html_writer::start_tag('label', ['class' => 'aiquizmaker-toggle-label']);
echo html_writer::empty_tag('input', [
    'type' => 'checkbox',
    'id' => 'workplace-context-toggle',
    'name' => 'workplace_context_enabled',
    'class' => 'aiquizmaker-toggle-checkbox',
]);
echo html_writer::start_tag('span', ['class' => 'aiquizmaker-toggle-switch']);
echo html_writer::end_tag('span');
echo html_writer::tag('span', get_string('add_workplace_context', 'local_aiquizmaker'), ['class' => 'aiquizmaker-toggle-text']);
echo html_writer::end_tag('label');
echo html_writer::tag('p', get_string('workplace_context_help', 'local_aiquizmaker'), ['class' => 'aiquizmaker-sublabel']);
echo html_writer::end_div();
echo html_writer::start_div('aiquizmaker-context-fields', ['id' => 'workplace-context-fields', 'style' => 'display: none;']);
echo html_writer::start_div('aiquizmaker-grid');

// Country field.
echo html_writer::start_div('aiquizmaker-field');
echo html_writer::tag('label', get_string('country', 'local_aiquizmaker'), ['class' => 'aiquizmaker-label']);
$countryoptions = html_writer::tag('option', get_string('select_country', 'local_aiquizmaker'), ['value' => '']);
$countryoptions .= html_writer::tag('option', get_string('country_australia', 'local_aiquizmaker'),
    ['value' => 'Australia', 'selected' => 'selected']);
$countryoptions .= html_writer::tag('option', get_string('country_new_zealand', 'local_aiquizmaker'),
    ['value' => 'New Zealand']);
$countryoptions .= html_writer::tag('option', get_string('country_uk', 'local_aiquizmaker'),
    ['value' => 'United Kingdom']);
$countryoptions .= html_writer::tag('option', get_string('country_us', 'local_aiquizmaker'),
    ['value' => 'United States']);
$countryoptions .= html_writer::tag('option', get_string('country_canada', 'local_aiquizmaker'),
    ['value' => 'Canada']);
$countryoptions .= html_writer::tag('option', get_string('country_singapore', 'local_aiquizmaker'),
    ['value' => 'Singapore']);
echo html_writer::tag('select', $countryoptions, [
    'name' => 'country',
    'id' => 'country-select',
    'class' => 'aiquizmaker-select',
]);
echo html_writer::end_div();

// State field.
echo html_writer::start_div('aiquizmaker-field');
echo html_writer::tag('label', get_string('state', 'local_aiquizmaker'), ['class' => 'aiquizmaker-label']);
$stateoptions = html_writer::tag('option', get_string('select_state', 'local_aiquizmaker'), ['value' => '']);
$stateoptions .= html_writer::tag('option', get_string('state_wa', 'local_aiquizmaker'),
    ['value' => 'Western Australia']);
$stateoptions .= html_writer::tag('option', get_string('state_qld', 'local_aiquizmaker'),
    ['value' => 'Queensland']);
$stateoptions .= html_writer::tag('option', get_string('state_nsw', 'local_aiquizmaker'),
    ['value' => 'New South Wales']);
$stateoptions .= html_writer::tag('option', get_string('state_vic', 'local_aiquizmaker'),
    ['value' => 'Victoria']);
$stateoptions .= html_writer::tag('option', get_string('state_sa', 'local_aiquizmaker'),
    ['value' => 'South Australia']);
$stateoptions .= html_writer::tag('option', get_string('state_tas', 'local_aiquizmaker'),
    ['value' => 'Tasmania']);
$stateoptions .= html_writer::tag('option', get_string('state_nt', 'local_aiquizmaker'),
    ['value' => 'Northern Territory']);
$stateoptions .= html_writer::tag('option', get_string('state_act', 'local_aiquizmaker'),
    ['value' => 'Australian Capital Territory']);
echo html_writer::tag('select', $stateoptions, [
    'name' => 'state',
    'id' => 'state-select',
    'class' => 'aiquizmaker-select',
]);
echo html_writer::end_div();

// Industry field.
echo html_writer::start_div('aiquizmaker-field');
echo html_writer::tag('label', get_string('industry', 'local_aiquizmaker'), ['class' => 'aiquizmaker-label']);
$industryoptions = html_writer::tag('option', get_string('select_industry', 'local_aiquizmaker'), ['value' => '']);
echo html_writer::tag('select', $industryoptions, [
    'name' => 'industry',
    'id' => 'industry-select',
    'class' => 'aiquizmaker-select',
]);
echo html_writer::end_div();

// Industry details field.
echo html_writer::start_div('aiquizmaker-field aiquizmaker-field-full');
echo html_writer::tag('label', get_string('industry_details', 'local_aiquizmaker'), ['class' => 'aiquizmaker-label']);
echo html_writer::empty_tag('input', [
    'type' => 'text',
    'name' => 'industry_details',
    'class' => 'aiquizmaker-input',
    'placeholder' => get_string('industry_details_placeholder', 'local_aiquizmaker'),
]);
echo html_writer::end_div();

// Job level field - multi-select checkboxes (populated dynamically based on industry).
echo html_writer::start_div('aiquizmaker-field aiquizmaker-field-full');
echo html_writer::tag('label', get_string('job_level', 'local_aiquizmaker'), ['class' => 'aiquizmaker-label']);
echo html_writer::tag('p', get_string('job_level_multi_help', 'local_aiquizmaker'), ['class' => 'aiquizmaker-sublabel']);
echo html_writer::start_div('aiquizmaker-checkbox-grid aiquizmaker-level-checkboxes', ['id' => 'job-level-checkboxes']);
// Default levels — JS re-renders this when industry changes.
$defaultlevels = ['Entry Level', 'Intermediate', 'Senior', 'Supervisor', 'Manager', 'Executive'];
foreach ($defaultlevels as $level) {
    echo html_writer::start_div('aiquizmaker-checkbox-item');
    echo html_writer::start_tag('label', ['class' => 'aiquizmaker-checkbox-label']);
    echo html_writer::empty_tag('input', [
        'type' => 'checkbox',
        'name' => 'job_level[]',
        'value' => $level,
        'class' => 'aiquizmaker-checkbox',
    ]);
    echo html_writer::tag('span', $level, ['class' => 'aiquizmaker-checkbox-text']);
    echo html_writer::end_tag('label');
    echo html_writer::end_div();
}
echo html_writer::end_div(); // End job-level-checkboxes.
echo html_writer::end_div();

// Job title field - searchable multi-select checkbox panel + custom input.
echo html_writer::start_div('aiquizmaker-field aiquizmaker-field-full');
echo html_writer::tag('label', get_string('job_title', 'local_aiquizmaker'), ['class' => 'aiquizmaker-label']);
echo html_writer::tag('p', get_string('job_title_multi_help', 'local_aiquizmaker'), ['class' => 'aiquizmaker-sublabel']);
// The panel (search + checkbox list) is shown when an industry is selected.
echo html_writer::start_div('aiquizmaker-job-title-panel', ['id' => 'job-title-panel', 'style' => 'display: none;']);
// Search bar + Select All / Clear All actions row.
echo html_writer::start_div('aiquizmaker-job-title-toolbar');
echo html_writer::empty_tag('input', [
    'type' => 'text',
    'id' => 'job-title-search',
    'class' => 'aiquizmaker-input aiquizmaker-job-title-search',
    'placeholder' => get_string('job_title_search_placeholder', 'local_aiquizmaker'),
]);
echo html_writer::start_div('aiquizmaker-job-title-actions');
echo html_writer::tag('button', get_string('job_title_select_all', 'local_aiquizmaker'),
    ['type' => 'button', 'id' => 'job-title-select-all', 'class' => 'aiquizmaker-btn aiquizmaker-btn-ghost aiquizmaker-btn-sm']);
echo html_writer::tag('button', get_string('job_title_clear_all', 'local_aiquizmaker'),
    ['type' => 'button', 'id' => 'job-title-clear-all', 'class' => 'aiquizmaker-btn aiquizmaker-btn-ghost aiquizmaker-btn-sm']);
echo html_writer::end_div();
echo html_writer::end_div(); // End toolbar.
// Scrollable checkbox list — JS populates this.
echo html_writer::start_div('aiquizmaker-job-title-list', ['id' => 'job-title-checkboxes']);
echo html_writer::end_div();
echo html_writer::end_div(); // End job-title-panel.
// Custom text input for roles not in the predefined list.
echo html_writer::empty_tag('input', [
    'type' => 'text',
    'id' => 'job-title-custom',
    'class' => 'aiquizmaker-input',
    'placeholder' => get_string('job_title_custom_placeholder', 'local_aiquizmaker'),
]);
echo html_writer::end_div();

echo html_writer::end_div(); // End grid.
echo html_writer::end_div(); // End context-fields.
echo html_writer::end_div(); // End context section.

// Education Settings section.
echo html_writer::start_div('aiquizmaker-section');
echo html_writer::tag('h2', get_string('section_education', 'local_aiquizmaker'), ['class' => 'aiquizmaker-section-title']);

// Education type with info cards.
echo html_writer::start_div('aiquizmaker-education-type-wrapper');

// Education type dropdown.
echo html_writer::start_div('aiquizmaker-field aiquizmaker-field-full');
echo html_writer::tag('label', get_string('education_type', 'local_aiquizmaker'), ['class' => 'aiquizmaker-label']);
$educationtypeoptions = html_writer::tag('option', get_string('select_education_type', 'local_aiquizmaker'), ['value' => '']);
$educationtypeoptions .= html_writer::tag('option', get_string('education_vet', 'local_aiquizmaker'), 
    ['value' => 'vet', 'selected' => 'selected']);
$educationtypeoptions .= html_writer::tag('option', get_string('education_academic', 'local_aiquizmaker'), 
    ['value' => 'academic']);
echo html_writer::tag('select', $educationtypeoptions, [
    'name' => 'education_type',
    'id' => 'education-type-select',
    'class' => 'aiquizmaker-select',
]);
echo html_writer::end_div();

// Info cards for education types.
echo html_writer::start_div('aiquizmaker-education-info');

// VET info card (visible by default).
echo html_writer::start_div('aiquizmaker-info-card aiquizmaker-info-vet', ['id' => 'vet-info-card']);
echo html_writer::start_div('aiquizmaker-info-card-header');
echo '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M14.7 6.3a1 1 0 0 0 0 1.4l1.6 1.6a1 1 0 0 0 1.4 0l3.77-3.77a6 6 0 0 1-7.94 7.94l-6.91 6.91a2.12 2.12 0 0 1-3-3l6.91-6.91a6 6 0 0 1 7.94-7.94l-3.76 3.76z"/></svg>';
echo html_writer::tag('span', get_string('vet_tooltip_title', 'local_aiquizmaker'), ['class' => 'aiquizmaker-info-card-title']);
echo html_writer::end_div();
echo html_writer::tag('p', get_string('vet_tooltip', 'local_aiquizmaker'), ['class' => 'aiquizmaker-info-card-text']);
echo html_writer::end_div();

// Academic info card (hidden by default).
echo html_writer::start_div('aiquizmaker-info-card aiquizmaker-info-academic', ['id' => 'academic-info-card', 'style' => 'display: none;']);
echo html_writer::start_div('aiquizmaker-info-card-header');
echo '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M22 10v6M2 10l10-5 10 5-10 5z"/><path d="M6 12v5c3 3 9 3 12 0v-5"/></svg>';
echo html_writer::tag('span', get_string('academic_tooltip_title', 'local_aiquizmaker'), ['class' => 'aiquizmaker-info-card-title']);
echo html_writer::end_div();
echo html_writer::tag('p', get_string('academic_tooltip', 'local_aiquizmaker'), ['class' => 'aiquizmaker-info-card-text']);
echo html_writer::end_div();

echo html_writer::end_div(); // End education info.
echo html_writer::end_div(); // End education type wrapper.

// Level selection grid.
echo html_writer::start_div('aiquizmaker-grid');

// VET level dropdown (visible by default).
echo html_writer::start_div('aiquizmaker-field', ['id' => 'vet-level-field']);
echo html_writer::tag('label', get_string('vet_level', 'local_aiquizmaker'), ['class' => 'aiquizmaker-label']);
$vetleveloptions = html_writer::tag('option', get_string('select_vet_level', 'local_aiquizmaker'), ['value' => '']);
$vetleveloptions .= html_writer::tag('option', get_string('vet_cert1', 'local_aiquizmaker'), ['value' => 'cert1']);
$vetleveloptions .= html_writer::tag('option', get_string('vet_cert2', 'local_aiquizmaker'), ['value' => 'cert2']);
$vetleveloptions .= html_writer::tag('option', get_string('vet_cert3', 'local_aiquizmaker'), 
    ['value' => 'cert3', 'selected' => 'selected']);
$vetleveloptions .= html_writer::tag('option', get_string('vet_cert4', 'local_aiquizmaker'), ['value' => 'cert4']);
$vetleveloptions .= html_writer::tag('option', get_string('vet_diploma', 'local_aiquizmaker'), ['value' => 'diploma']);
$vetleveloptions .= html_writer::tag('option', get_string('vet_adv_diploma', 'local_aiquizmaker'), ['value' => 'adv_diploma']);
echo html_writer::tag('select', $vetleveloptions, [
    'name' => 'vet_level',
    'id' => 'vet-level-select',
    'class' => 'aiquizmaker-select',
]);
echo html_writer::end_div();

// Academic level dropdown (hidden by default).
echo html_writer::start_div('aiquizmaker-field', ['id' => 'academic-level-field', 'style' => 'display: none;']);
echo html_writer::tag('label', get_string('academic_level', 'local_aiquizmaker'), ['class' => 'aiquizmaker-label']);
$academicleveloptions = html_writer::tag('option', get_string('select_academic_level', 'local_aiquizmaker'), ['value' => '']);
$academicleveloptions .= html_writer::tag('option', get_string('academic_undergraduate', 'local_aiquizmaker'), ['value' => 'undergraduate']);
$academicleveloptions .= html_writer::tag('option', get_string('academic_postgraduate', 'local_aiquizmaker'), ['value' => 'postgraduate']);
$academicleveloptions .= html_writer::tag('option', get_string('academic_masters', 'local_aiquizmaker'), ['value' => 'masters']);
$academicleveloptions .= html_writer::tag('option', get_string('academic_phd', 'local_aiquizmaker'), ['value' => 'phd']);
echo html_writer::tag('select', $academicleveloptions, [
    'name' => 'academic_level',
    'id' => 'academic-level-select',
    'class' => 'aiquizmaker-select',
]);
echo html_writer::end_div();

echo html_writer::end_div(); // End level grid.

// Moodle Quiz Question Types section.
echo html_writer::start_div('aiquizmaker-field aiquizmaker-field-full aiquizmaker-question-types', ['style' => 'margin-top: 20px;']);
echo html_writer::tag('label', get_string('moodle_question_types', 'local_aiquizmaker'), ['class' => 'aiquizmaker-label']);
echo html_writer::tag('p', get_string('select_moodle_types', 'local_aiquizmaker'), ['class' => 'aiquizmaker-sublabel']);
echo html_writer::start_div('aiquizmaker-checkbox-grid');

// Essay (Written Response).
echo html_writer::start_div('aiquizmaker-checkbox-item aiquizmaker-qtype-item');
echo html_writer::start_tag('label', ['class' => 'aiquizmaker-checkbox-label']);
echo html_writer::empty_tag('input', [
    'type' => 'checkbox',
    'name' => 'moodle_question_types[]',
    'value' => 'essay',
    'class' => 'aiquizmaker-checkbox aiquizmaker-qtype-check',
    'id' => 'qtype-essay',
    'checked' => 'checked',
]);
echo html_writer::tag('span', get_string('mtype_essay', 'local_aiquizmaker'), ['class' => 'aiquizmaker-checkbox-text']);
echo html_writer::end_tag('label');
echo html_writer::tag('span', get_string('mtype_essay_tip', 'local_aiquizmaker'), ['class' => 'aiquizmaker-checkbox-tip']);
echo html_writer::end_div();

// Multiple Choice.
echo html_writer::start_div('aiquizmaker-checkbox-item aiquizmaker-qtype-item');
echo html_writer::start_tag('label', ['class' => 'aiquizmaker-checkbox-label']);
echo html_writer::empty_tag('input', [
    'type' => 'checkbox',
    'name' => 'moodle_question_types[]',
    'value' => 'multichoice',
    'class' => 'aiquizmaker-checkbox aiquizmaker-qtype-check',
    'id' => 'qtype-multichoice',
]);
echo html_writer::tag('span', get_string('mtype_multichoice', 'local_aiquizmaker'), ['class' => 'aiquizmaker-checkbox-text']);
echo html_writer::end_tag('label');
echo html_writer::tag('span', get_string('mtype_multichoice_tip', 'local_aiquizmaker'), ['class' => 'aiquizmaker-checkbox-tip']);
echo html_writer::end_div();

// True / False.
echo html_writer::start_div('aiquizmaker-checkbox-item aiquizmaker-qtype-item');
echo html_writer::start_tag('label', ['class' => 'aiquizmaker-checkbox-label']);
echo html_writer::empty_tag('input', [
    'type' => 'checkbox',
    'name' => 'moodle_question_types[]',
    'value' => 'truefalse',
    'class' => 'aiquizmaker-checkbox aiquizmaker-qtype-check',
    'id' => 'qtype-truefalse',
]);
echo html_writer::tag('span', get_string('mtype_truefalse', 'local_aiquizmaker'), ['class' => 'aiquizmaker-checkbox-text']);
echo html_writer::end_tag('label');
echo html_writer::tag('span', get_string('mtype_truefalse_tip', 'local_aiquizmaker'), ['class' => 'aiquizmaker-checkbox-tip']);
echo html_writer::end_div();

// Matching.
echo html_writer::start_div('aiquizmaker-checkbox-item aiquizmaker-qtype-item');
echo html_writer::start_tag('label', ['class' => 'aiquizmaker-checkbox-label']);
echo html_writer::empty_tag('input', [
    'type' => 'checkbox',
    'name' => 'moodle_question_types[]',
    'value' => 'matching',
    'class' => 'aiquizmaker-checkbox aiquizmaker-qtype-check',
    'id' => 'qtype-matching',
]);
echo html_writer::tag('span', get_string('mtype_matching', 'local_aiquizmaker'), ['class' => 'aiquizmaker-checkbox-text']);
echo html_writer::end_tag('label');
echo html_writer::tag('span', get_string('mtype_matching_tip', 'local_aiquizmaker'), ['class' => 'aiquizmaker-checkbox-tip']);
echo html_writer::end_div();

// Select Missing Words (gapselect).
echo html_writer::start_div('aiquizmaker-checkbox-item aiquizmaker-qtype-item');
echo html_writer::start_tag('label', ['class' => 'aiquizmaker-checkbox-label']);
echo html_writer::empty_tag('input', [
    'type' => 'checkbox',
    'name' => 'moodle_question_types[]',
    'value' => 'gapselect',
    'class' => 'aiquizmaker-checkbox aiquizmaker-qtype-check',
    'id' => 'qtype-gapselect',
]);
echo html_writer::tag('span', get_string('mtype_gapselect', 'local_aiquizmaker'), ['class' => 'aiquizmaker-checkbox-text']);
echo html_writer::end_tag('label');
echo html_writer::tag('span', get_string('mtype_gapselect_tip', 'local_aiquizmaker'), ['class' => 'aiquizmaker-checkbox-tip']);
echo html_writer::end_div();

// Missing Word / Fill in the Blank (shortanswer).
echo html_writer::start_div('aiquizmaker-checkbox-item aiquizmaker-qtype-item');
echo html_writer::start_tag('label', ['class' => 'aiquizmaker-checkbox-label']);
echo html_writer::empty_tag('input', [
    'type' => 'checkbox',
    'name' => 'moodle_question_types[]',
    'value' => 'shortanswer',
    'class' => 'aiquizmaker-checkbox aiquizmaker-qtype-check',
    'id' => 'qtype-shortanswer',
]);
echo html_writer::tag('span', get_string('mtype_shortanswer', 'local_aiquizmaker'), ['class' => 'aiquizmaker-checkbox-text']);
echo html_writer::end_tag('label');
echo html_writer::tag('span', get_string('mtype_shortanswer_tip', 'local_aiquizmaker'), ['class' => 'aiquizmaker-checkbox-tip']);
echo html_writer::end_div();

echo html_writer::end_div(); // End question types checkbox grid.
echo html_writer::end_div(); // End question types field.

// Question formats section (for Essay-type questions).
echo html_writer::start_div('aiquizmaker-field aiquizmaker-field-full aiquizmaker-question-formats aiquizmaker-essay-formats');
echo html_writer::tag('label', get_string('question_formats', 'local_aiquizmaker'), ['class' => 'aiquizmaker-label']);
echo html_writer::tag('p', get_string('select_formats', 'local_aiquizmaker'), ['class' => 'aiquizmaker-sublabel']);

echo html_writer::start_div('aiquizmaker-checkbox-grid');

// Long Essay.
echo html_writer::start_div('aiquizmaker-checkbox-item');
echo html_writer::start_tag('label', ['class' => 'aiquizmaker-checkbox-label']);
echo html_writer::empty_tag('input', [
    'type' => 'checkbox',
    'name' => 'question_formats[]',
    'value' => 'long_essay',
    'class' => 'aiquizmaker-checkbox',
]);
echo html_writer::tag('span', get_string('format_long_essay', 'local_aiquizmaker'), ['class' => 'aiquizmaker-checkbox-text']);
echo html_writer::end_tag('label');
echo html_writer::tag('span', get_string('format_long_essay_tip', 'local_aiquizmaker'), ['class' => 'aiquizmaker-checkbox-tip']);
echo html_writer::end_div();

// Short Essay.
echo html_writer::start_div('aiquizmaker-checkbox-item');
echo html_writer::start_tag('label', ['class' => 'aiquizmaker-checkbox-label']);
echo html_writer::empty_tag('input', [
    'type' => 'checkbox',
    'name' => 'question_formats[]',
    'value' => 'short_essay',
    'class' => 'aiquizmaker-checkbox',
    'checked' => 'checked',
]);
echo html_writer::tag('span', get_string('format_short_essay', 'local_aiquizmaker'), ['class' => 'aiquizmaker-checkbox-text']);
echo html_writer::end_tag('label');
echo html_writer::tag('span', get_string('format_short_essay_tip', 'local_aiquizmaker'), ['class' => 'aiquizmaker-checkbox-tip']);
echo html_writer::end_div();

// Extended Response.
echo html_writer::start_div('aiquizmaker-checkbox-item');
echo html_writer::start_tag('label', ['class' => 'aiquizmaker-checkbox-label']);
echo html_writer::empty_tag('input', [
    'type' => 'checkbox',
    'name' => 'question_formats[]',
    'value' => 'extended_response',
    'class' => 'aiquizmaker-checkbox',
]);
echo html_writer::tag('span', get_string('format_extended_response', 'local_aiquizmaker'), ['class' => 'aiquizmaker-checkbox-text']);
echo html_writer::end_tag('label');
echo html_writer::tag('span', get_string('format_extended_response_tip', 'local_aiquizmaker'), ['class' => 'aiquizmaker-checkbox-tip']);
echo html_writer::end_div();

// Short Answer.
echo html_writer::start_div('aiquizmaker-checkbox-item');
echo html_writer::start_tag('label', ['class' => 'aiquizmaker-checkbox-label']);
echo html_writer::empty_tag('input', [
    'type' => 'checkbox',
    'name' => 'question_formats[]',
    'value' => 'short_answer',
    'class' => 'aiquizmaker-checkbox',
    'checked' => 'checked',
]);
echo html_writer::tag('span', get_string('format_short_answer', 'local_aiquizmaker'), ['class' => 'aiquizmaker-checkbox-text']);
echo html_writer::end_tag('label');
echo html_writer::tag('span', get_string('format_short_answer_tip', 'local_aiquizmaker'), ['class' => 'aiquizmaker-checkbox-tip']);
echo html_writer::end_div();

// Definition/Explanation.
echo html_writer::start_div('aiquizmaker-checkbox-item');
echo html_writer::start_tag('label', ['class' => 'aiquizmaker-checkbox-label']);
echo html_writer::empty_tag('input', [
    'type' => 'checkbox',
    'name' => 'question_formats[]',
    'value' => 'definition',
    'class' => 'aiquizmaker-checkbox',
]);
echo html_writer::tag('span', get_string('format_definition', 'local_aiquizmaker'), ['class' => 'aiquizmaker-checkbox-text']);
echo html_writer::end_tag('label');
echo html_writer::tag('span', get_string('format_definition_tip', 'local_aiquizmaker'), ['class' => 'aiquizmaker-checkbox-tip']);
echo html_writer::end_div();

// List/Identify Items.
echo html_writer::start_div('aiquizmaker-checkbox-item');
echo html_writer::start_tag('label', ['class' => 'aiquizmaker-checkbox-label']);
echo html_writer::empty_tag('input', [
    'type' => 'checkbox',
    'name' => 'question_formats[]',
    'value' => 'list',
    'class' => 'aiquizmaker-checkbox',
]);
echo html_writer::tag('span', get_string('format_list', 'local_aiquizmaker'), ['class' => 'aiquizmaker-checkbox-text']);
echo html_writer::end_tag('label');
echo html_writer::tag('span', get_string('format_list_tip', 'local_aiquizmaker'), ['class' => 'aiquizmaker-checkbox-tip']);
echo html_writer::end_div();

// Scenario-Based Response.
echo html_writer::start_div('aiquizmaker-checkbox-item');
echo html_writer::start_tag('label', ['class' => 'aiquizmaker-checkbox-label']);
echo html_writer::empty_tag('input', [
    'type' => 'checkbox',
    'name' => 'question_formats[]',
    'value' => 'scenario',
    'class' => 'aiquizmaker-checkbox',
    'checked' => 'checked',
]);
echo html_writer::tag('span', get_string('format_scenario', 'local_aiquizmaker'), ['class' => 'aiquizmaker-checkbox-text']);
echo html_writer::end_tag('label');
echo html_writer::tag('span', get_string('format_scenario_tip', 'local_aiquizmaker'), ['class' => 'aiquizmaker-checkbox-tip']);
echo html_writer::end_div();

echo html_writer::end_div(); // End checkbox grid.
echo html_writer::end_div(); // End question formats field.

// Self-marking question styles section (visible when non-essay types are selected).
echo html_writer::start_div('aiquizmaker-field aiquizmaker-field-full aiquizmaker-question-formats aiquizmaker-selfmarking-styles', ['style' => 'display:none']);
echo html_writer::tag('label', get_string('selfmarking_styles', 'local_aiquizmaker'), ['class' => 'aiquizmaker-label']);
echo html_writer::tag('p', get_string('select_selfmarking_styles', 'local_aiquizmaker'), ['class' => 'aiquizmaker-sublabel']);

echo html_writer::start_div('aiquizmaker-checkbox-grid');

// Workplace Scenario.
echo html_writer::start_div('aiquizmaker-checkbox-item');
echo html_writer::start_tag('label', ['class' => 'aiquizmaker-checkbox-label']);
echo html_writer::empty_tag('input', [
    'type' => 'checkbox',
    'name' => 'selfmarking_styles[]',
    'value' => 'scenario',
    'class' => 'aiquizmaker-checkbox',
    'checked' => 'checked',
]);
echo html_writer::tag('span', get_string('style_scenario', 'local_aiquizmaker'), ['class' => 'aiquizmaker-checkbox-text']);
echo html_writer::end_tag('label');
echo html_writer::tag('span', get_string('style_scenario_tip', 'local_aiquizmaker'), ['class' => 'aiquizmaker-checkbox-tip']);
echo html_writer::end_div();

// Knowledge Check.
echo html_writer::start_div('aiquizmaker-checkbox-item');
echo html_writer::start_tag('label', ['class' => 'aiquizmaker-checkbox-label']);
echo html_writer::empty_tag('input', [
    'type' => 'checkbox',
    'name' => 'selfmarking_styles[]',
    'value' => 'knowledge_check',
    'class' => 'aiquizmaker-checkbox',
    'checked' => 'checked',
]);
echo html_writer::tag('span', get_string('style_knowledge_check', 'local_aiquizmaker'), ['class' => 'aiquizmaker-checkbox-text']);
echo html_writer::end_tag('label');
echo html_writer::tag('span', get_string('style_knowledge_check_tip', 'local_aiquizmaker'), ['class' => 'aiquizmaker-checkbox-tip']);
echo html_writer::end_div();

// Procedure / Steps.
echo html_writer::start_div('aiquizmaker-checkbox-item');
echo html_writer::start_tag('label', ['class' => 'aiquizmaker-checkbox-label']);
echo html_writer::empty_tag('input', [
    'type' => 'checkbox',
    'name' => 'selfmarking_styles[]',
    'value' => 'procedure',
    'class' => 'aiquizmaker-checkbox',
]);
echo html_writer::tag('span', get_string('style_procedure', 'local_aiquizmaker'), ['class' => 'aiquizmaker-checkbox-text']);
echo html_writer::end_tag('label');
echo html_writer::tag('span', get_string('style_procedure_tip', 'local_aiquizmaker'), ['class' => 'aiquizmaker-checkbox-tip']);
echo html_writer::end_div();

// Terminology / Definitions.
echo html_writer::start_div('aiquizmaker-checkbox-item');
echo html_writer::start_tag('label', ['class' => 'aiquizmaker-checkbox-label']);
echo html_writer::empty_tag('input', [
    'type' => 'checkbox',
    'name' => 'selfmarking_styles[]',
    'value' => 'terminology',
    'class' => 'aiquizmaker-checkbox',
]);
echo html_writer::tag('span', get_string('style_terminology', 'local_aiquizmaker'), ['class' => 'aiquizmaker-checkbox-text']);
echo html_writer::end_tag('label');
echo html_writer::tag('span', get_string('style_terminology_tip', 'local_aiquizmaker'), ['class' => 'aiquizmaker-checkbox-tip']);
echo html_writer::end_div();

// Identification / Listing.
echo html_writer::start_div('aiquizmaker-checkbox-item');
echo html_writer::start_tag('label', ['class' => 'aiquizmaker-checkbox-label']);
echo html_writer::empty_tag('input', [
    'type' => 'checkbox',
    'name' => 'selfmarking_styles[]',
    'value' => 'identification',
    'class' => 'aiquizmaker-checkbox',
]);
echo html_writer::tag('span', get_string('style_identification', 'local_aiquizmaker'), ['class' => 'aiquizmaker-checkbox-text']);
echo html_writer::end_tag('label');
echo html_writer::tag('span', get_string('style_identification_tip', 'local_aiquizmaker'), ['class' => 'aiquizmaker-checkbox-tip']);
echo html_writer::end_div();

echo html_writer::end_div(); // End checkbox grid.
echo html_writer::end_div(); // End selfmarking styles field.

echo html_writer::end_div(); // End education section.

// Extra AI Instructions section (collapsible).
echo html_writer::start_div('aiquizmaker-section aiquizmaker-instructions-section');
echo html_writer::start_div('aiquizmaker-section-header', ['id' => 'aiquizmaker-instructions-toggle']);
echo '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="aiquizmaker-section-icon"><circle cx="12" cy="12" r="10"/><path d="M9.09 9a3 3 0 0 1 5.83 1c0 2-3 3-3 3"/><line x1="12" y1="17" x2="12.01" y2="17"/></svg>';
echo html_writer::tag('h2', get_string('extra_instructions', 'local_aiquizmaker'), ['class' => 'aiquizmaker-section-title']);
echo '<svg class="aiquizmaker-collapse-icon" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="6 9 12 15 18 9"/></svg>';
echo html_writer::end_div(); // End section header.

echo html_writer::start_div('aiquizmaker-section-content', ['id' => 'aiquizmaker-instructions-content']);
echo html_writer::tag('p', get_string('extra_instructions_help', 'local_aiquizmaker'), ['class' => 'aiquizmaker-section-help']);

echo html_writer::start_div('aiquizmaker-instructions-form');
echo html_writer::tag('textarea', '', [
    'id' => 'aiquizmaker-extra-instructions',
    'class' => 'aiquizmaker-instructions-textarea',
    'placeholder' => get_string('extra_instructions_placeholder', 'local_aiquizmaker'),
    'rows' => '3',
]);
echo html_writer::start_div('aiquizmaker-instructions-actions');
echo html_writer::start_tag('button', [
    'id' => 'aiquizmaker-save-instructions',
    'type' => 'button',
    'class' => 'aiquizmaker-btn aiquizmaker-btn-secondary aiquizmaker-btn-sm',
]);
echo '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M19 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11l5 5v11a2 2 0 0 1-2 2z"/><polyline points="17 21 17 13 7 13 7 21"/><polyline points="7 3 7 8 15 8"/></svg>';
echo get_string('save_instructions', 'local_aiquizmaker');
echo html_writer::end_tag('button');
echo html_writer::tag('span', '', ['id' => 'aiquizmaker-instructions-status', 'class' => 'aiquizmaker-instructions-status']);
echo html_writer::end_div(); // End instructions actions.
echo html_writer::end_div(); // End instructions form.
echo html_writer::end_div(); // End section content.
echo html_writer::end_div(); // End instructions section.

// Submit button.
echo html_writer::start_div('aiquizmaker-actions');
echo html_writer::start_tag('button', [
    'type' => 'submit',
    'class' => 'aiquizmaker-btn aiquizmaker-btn-primary',
    'id' => 'generate-btn',
]);
echo '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polygon points="13 2 3 14 12 14 11 22 21 10 12 10 13 2"/></svg>';
echo html_writer::tag('span', get_string('generate', 'local_aiquizmaker'), ['id' => 'generate-btn-label']);
echo html_writer::end_tag('button');
echo html_writer::end_div();

echo html_writer::end_tag('form');

// Results section.
echo html_writer::start_div('aiquizmaker-results', ['id' => 'aiquizmaker-results', 'style' => 'display: none;']);
echo html_writer::start_div('aiquizmaker-results-header');
echo html_writer::tag('h2', get_string('questions_generated', 'local_aiquizmaker'), ['class' => 'aiquizmaker-section-title']);
echo html_writer::end_div();

echo html_writer::tag('div', '', ['id' => 'questions-list']);

// Auto-create questions section (populated by JavaScript).
echo html_writer::tag('div', '', ['id' => 'create-questions-section', 'class' => 'aiquizmaker-create-section']);

// XML export section (as fallback/alternative).
echo html_writer::start_div('aiquizmaker-xml-section');
echo html_writer::start_div('aiquizmaker-xml-header');
echo html_writer::tag('h4', get_string('xml_export_title', 'local_aiquizmaker'), ['class' => 'aiquizmaker-section-title']);
echo html_writer::start_div('aiquizmaker-xml-actions');
echo html_writer::start_tag('button', [
    'type' => 'button',
    'id' => 'download-xml-btn',
    'class' => 'aiquizmaker-btn aiquizmaker-btn-secondary',
]);
echo '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="7 10 12 15 17 10"/><line x1="12" y1="15" x2="12" y2="3"/></svg>';
echo get_string('download_xml', 'local_aiquizmaker');
echo html_writer::end_tag('button');
echo html_writer::start_tag('button', [
    'type' => 'button',
    'id' => 'copy-xml-btn',
    'class' => 'aiquizmaker-btn aiquizmaker-btn-outline',
]);
echo '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="9" y="9" width="13" height="13" rx="2" ry="2"/><path d="M5 15H4a2 2 0 0 1-2-2V4a2 2 0 0 1 2-2h9a2 2 0 0 1 2 2v1"/></svg>';
echo get_string('copy_to_clipboard', 'local_aiquizmaker');
echo html_writer::end_tag('button');
echo html_writer::end_div();
echo html_writer::end_div();
echo html_writer::tag('p', get_string('import_instructions', 'local_aiquizmaker'), ['class' => 'aiquizmaker-results-info em-text-muted']);
echo html_writer::tag('textarea', '', ['id' => 'moodle-xml', 'class' => 'aiquizmaker-xml', 'readonly' => 'readonly']);
echo html_writer::end_div(); // End XML section.

// Excel mapping download section.
echo html_writer::start_div('aiquizmaker-excel-section');
echo html_writer::start_div('aiquizmaker-excel-header');
echo html_writer::tag('h4', get_string('excel_export_title', 'local_aiquizmaker'), ['class' => 'aiquizmaker-section-title']);
echo html_writer::start_div('aiquizmaker-excel-actions');
echo html_writer::start_tag('button', [
    'type' => 'button',
    'id' => 'download-excel-btn',
    'class' => 'aiquizmaker-btn aiquizmaker-btn-secondary',
]);
echo '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/><line x1="16" y1="13" x2="8" y2="13"/><line x1="16" y1="17" x2="8" y2="17"/><polyline points="10 9 9 9 8 9"/></svg>';
echo get_string('download_excel', 'local_aiquizmaker');
echo html_writer::end_tag('button');
echo html_writer::end_div();
echo html_writer::end_div();
echo html_writer::tag('p', get_string('excel_instructions', 'local_aiquizmaker'), ['class' => 'aiquizmaker-results-info em-text-muted']);
echo html_writer::end_div(); // End Excel section.

echo html_writer::end_div(); // End results.

// Footer.
echo html_writer::start_div('aiquizmaker-footer');
$poweredby = get_string('powered_by', 'local_aiquizmaker');
$link = html_writer::link('https://lms-labs.com', 'lms-labs.com', ['target' => '_blank']);
echo html_writer::tag('span', $poweredby . ' — ' . $link, ['class' => 'aiquizmaker-powered']);
echo html_writer::end_div();

echo html_writer::end_div(); // End container.

// Loading overlay.
echo html_writer::start_div('aiquizmaker-loading-overlay', ['id' => 'aiquizmaker-loading', 'style' => 'display: none;']);
echo html_writer::start_div('aiquizmaker-loading-content');
echo html_writer::tag('div', '', ['class' => 'aiquizmaker-loading-spinner']);
echo html_writer::tag('div', get_string('generating', 'local_aiquizmaker'), ['class' => 'aiquizmaker-loading-text']);
echo html_writer::end_div();
echo html_writer::end_div();

echo $OUTPUT->footer();
