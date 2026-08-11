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

// CRITICAL: Define AJAX_SCRIPT before ANYTHING else - must be before config.php
define('AJAX_SCRIPT', true);

// Set JSON header immediately
header('Content-Type: application/json; charset=utf-8');

// Disable Moodle's error display - we'll handle errors ourselves
@ini_set('display_errors', '0');

/**
 * AJAX handler for AI Quiz Maker local plugin.
 *
 * Handles AJAX requests for credit checking, industries, and question generation.
 *
 * @package    local_aiquizmaker
 * @copyright  2025 Essay Grader AI
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

try {
    require_once(__DIR__ . '/../../config.php');
require_login();
    require_once($CFG->libdir . '/filelib.php'); // Contains the curl class
    require_once($CFG->libdir . '/questionlib.php'); // For question bank operations
    require_once($CFG->dirroot . '/question/editlib.php'); // For question creation

    // Manual sesskey validation (doesn't redirect on failure)
    $sesskey = optional_param('sesskey', '', PARAM_RAW);
    if (!confirm_sesskey($sesskey)) {
        echo json_encode([
            'success' => false,
            'error' => 'Session expired. Please refresh the page.',
        ]);
        exit;
    }

    // Check user is logged in (without redirect)
    if (!isloggedin() || isguestuser()) {
        echo json_encode([
            'success' => false,
            'error' => 'Please log in to use AI Quiz Maker',
        ]);
        exit;
    }

    // Check capability - use has_capability instead of require_capability for better error handling.
    $context = context_system::instance();
    $PAGE->set_context($context);
    
    if (!has_capability('local/aiquizmaker:use', $context)) {
        echo json_encode([
            'success' => false,
            'error' => 'You do not have permission to use AI Quiz Maker',
        ]);
        exit;
    }

    // Get action parameter.
    $action = optional_param('action', '', PARAM_ALPHA);
    if (empty($action)) {
        echo json_encode([
            'success' => false,
            'error' => 'Missing action parameter',
        ]);
        exit;
    }

    // Explicitly include aiconfig lib.php if available
    $aiconfiglib = $CFG->dirroot . '/local/aiconfig/lib.php';
    if (file_exists($aiconfiglib)) {
        require_once($aiconfiglib);
    }
    
    // Priority 1: Central Config (recommended for multi-plugin setups)
    $siteid = '';
    $apikey = '';
    if (function_exists('local_aiconfig_get_siteid')) {
        $siteid = trim(local_aiconfig_get_siteid() ?? '');
    }
    if (function_exists('local_aiconfig_get_apikey')) {
        $apikey = trim(local_aiconfig_get_apikey() ?? '');
    }
    
    // Priority 2: Plugin settings as fallback
    if (empty($siteid)) {
        $siteid = trim(get_config('local_aiquizmaker', 'siteid') ?? '');
    }
    if (empty($apikey)) {
        $apikey = trim(get_config('local_aiquizmaker', 'apikey') ?? '');
    }

    /**
     * Safe HTTP request using Moodle's curl wrapper.
     * Uses STRING keys for options (proven to work in AI Grader).
     *
     * @param string $url The URL to request.
     * @param bool $post Whether to use POST method.
     * @param array|null $payload The payload for POST requests.
     * @return array Response with 'success', 'body', 'error', 'httpcode'.
     */
    // Release session lock before long-running API calls to prevent blocking other requests.
    \core\session\manager::write_close();

    function aiquizmaker_fetch($url, $post = false, $payload = null) {
        $curl = new \curl();
        // Use STRING keys (not constants) - proven to work in AI Grader
        $timeout = $post ? 120 : 30;
        $curl->setopt([
            'CURLOPT_TIMEOUT' => $timeout,
            'CURLOPT_RETURNTRANSFER' => true,
            'CURLOPT_SSL_VERIFYPEER' => true,
            'CURLOPT_SSL_VERIFYHOST' => 2,
            'CURLOPT_FOLLOWLOCATION' => true,
        ]);

        if ($post && $payload) {
            $curl->setHeader(['Content-Type: application/json', 'Accept: application/json']);
            $body = $curl->post($url, json_encode($payload));
        } else {
            $curl->setHeader(['Accept: application/json']);
            $body = $curl->get($url);
        }

        $info = $curl->get_info();
        $httpcode = isset($info['http_code']) ? $info['http_code'] : 0;
        $error = $curl->get_errno() ? $curl->error : null;

        if ($body === false || $httpcode === 0) {
            return [
                'success' => false,
                'body' => null,
                'error' => $error ? $error : 'Connection failed',
                'httpcode' => $httpcode
            ];
        }

        return [
            'success' => true,
            'body' => $body,
            'error' => null,
            'httpcode' => $httpcode
        ];
    }

    /**
     * Send JSON response and exit.
     *
     * @param array $data The response data.
     */
    function aiquizmaker_send_response(array $data): void {
        echo json_encode($data);
        exit;
    }

    /**
     * Safely encode text for HTML output, preserving apostrophes and quotes properly.
     *
     * @param string $text The text to encode.
     * @return string The safely encoded text.
     */
    function aiquizmaker_safe_html($text) {
        // First decode any existing HTML entities to avoid double-encoding
        $text = html_entity_decode($text, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        // Then encode properly, but use ENT_SUBSTITUTE to handle invalid chars
        return htmlspecialchars($text, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8', false);
    }

    /**
     * Format structured sample answer into HTML.
     *
     * @param mixed $sampleAnswer The sample answer (string or array format).
     * @param array $rubric The rubric array for reference.
     * @return string Formatted HTML for the sample answer.
     */
    function aiquizmaker_format_sample_answer($sampleAnswer, $rubric = []) {
        // If sampleAnswer is a JSON string, decode it first
        if (is_string($sampleAnswer) && !empty($sampleAnswer)) {
            $trimmed = trim($sampleAnswer);
            if (strpos($trimmed, '[') === 0 || strpos($trimmed, '{') === 0) {
                $decoded = json_decode($sampleAnswer, true);
                if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
                    $sampleAnswer = $decoded;
                }
            }
        }
        
        // Handle new structured format (array of criteria with responses)
        if (is_array($sampleAnswer) && isset($sampleAnswer[0])) {
            $html = '<div class="aiquizmaker-sample-answer">';
            foreach ($sampleAnswer as $index => $item) {
                $marks = isset($item['marks']) ? (int)$item['marks'] : 1;
                $response = isset($item['response']) ? $item['response'] : '';
                $marktext = $marks === 1 ? '1 mark' : $marks . ' marks';
                
                // Get the matching rubric description if available
                $rubricDesc = '';
                if (isset($rubric[$index]) && isset($rubric[$index]['description'])) {
                    $rubricDesc = $rubric[$index]['description'];
                }
                
                $html .= '<div style="margin-bottom: 12px; padding: 8px; background: #f8f9fa; border-left: 3px solid #0073aa; border-radius: 4px;">';
                $html .= '<p style="margin: 0 0 6px 0;"><strong style="color: #0073aa;">' . $marktext . ':</strong>';
                if ($rubricDesc) {
                    $html .= ' <em style="color: #666;">' . aiquizmaker_safe_html($rubricDesc) . '</em>';
                }
                $html .= '</p>';
                $html .= '<p style="margin: 0;">' . nl2br(aiquizmaker_safe_html($response)) . '</p>';
                $html .= '</div>';
            }
            $html .= '</div>';
            return $html;
        }
        
        // Handle legacy string format
        if (is_string($sampleAnswer) && !empty($sampleAnswer)) {
            return '<p>' . nl2br(aiquizmaker_safe_html($sampleAnswer)) . '</p>';
        }
        
        return '<p><em>No sample answer provided.</em></p>';
    }

    /**
     * Create an essay question in Moodle question bank.
     *
     * @param array $q The question data from API.
     * @param int $categoryid The question category ID.
     * @return int|false The question ID or false on failure.
     */
    function aiquizmaker_create_essay_question(array $q, int $categoryid) {
        global $DB, $USER;
        
        $rawquestion = isset($q['questionText']) ? $q['questionText'] : '';
        $rawsample = isset($q['sampleAnswer']) ? $q['sampleAnswer'] : '';
        $rawcriteria = isset($q['criteriaReference']) ? $q['criteriaReference'] : '';
        $totalmarks = isset($q['totalMarks']) ? (int)$q['totalMarks'] : 3;
        $rubric = isset($q['rubric']) ? $q['rubric'] : [];
        
        // Build rubric text for grader info
        $rubrichtml = '';
        if (isset($q['rubric'])) {
            if (is_array($q['rubric']) && isset($q['rubric'][0])) {
                // New array format (dynamic rubric)
                $rubrichtml = '<ul>';
                foreach ($q['rubric'] as $r) {
                    $marks = isset($r['marks']) ? (int)$r['marks'] : 1;
                    $desc = isset($r['description']) ? $r['description'] : '';
                    $marktext = $marks === 1 ? '1 mark' : $marks . ' marks';
                    $rubrichtml .= '<li><strong>' . $marktext . ':</strong> ' . aiquizmaker_safe_html($desc) . '</li>';
                }
                $rubrichtml .= '</ul>';
            } else {
                // Legacy format
                $rubrichtml = '<ul>';
                if (!empty($q['rubric']['hazard'])) {
                    $rubrichtml .= '<li><strong>1 mark:</strong> ' . aiquizmaker_safe_html($q['rubric']['hazard']) . '</li>';
                }
                if (!empty($q['rubric']['example'])) {
                    $rubrichtml .= '<li><strong>1 mark:</strong> ' . aiquizmaker_safe_html($q['rubric']['example']) . '</li>';
                }
                if (!empty($q['rubric']['control'])) {
                    $rubrichtml .= '<li><strong>1 mark:</strong> ' . aiquizmaker_safe_html($q['rubric']['control']) . '</li>';
                }
                $rubrichtml .= '</ul>';
            }
        }
        
        // Build question text with marking criteria embedded
        $markingtext = $totalmarks === 1 ? '1 mark total' : $totalmarks . ' marks total';
        $questionhtml = '<p>' . nl2br(aiquizmaker_safe_html($rawquestion)) . '</p>';
        $questionhtml .= '<p><strong>Marking Criteria (' . $markingtext . '):</strong></p>';
        $questionhtml .= $rubrichtml;
        
        // Question name: use sequential label 'Q1', 'Q2' etc. when a number is available
        // (avoids the double-up where both the name column and text column show the same
        // truncated sentence in the Moodle question bank). Falls back to truncated text
        // only if no number was injected by the caller.
        if (isset($q['_qnum'])) {
            $shortname = 'Q' . (int)$q['_qnum'];
        } else {
            $shortname = core_text::substr($rawquestion, 0, 80);
            if (core_text::strlen($rawquestion) > 80) {
                $shortname .= '...';
            }
        }
        
        // Build grader info with structured sample answer
        $graderinfo = '<p><strong>Criteria Reference:</strong> ' . aiquizmaker_safe_html($rawcriteria) . '</p>';
        $graderinfo .= '<p><strong>Rubric:</strong></p>' . $rubrichtml;
        $graderinfo .= '<p><strong>Sample Answer:</strong></p>';
        $graderinfo .= aiquizmaker_format_sample_answer($rawsample, $rubric);
        
        // Create the question record
        $question = new stdClass();
        $question->category = $categoryid;
        $question->parent = 0;
        $question->name = $shortname;
        $question->questiontext = $questionhtml;
        $question->questiontextformat = FORMAT_HTML;
        $question->generalfeedback = '<p><strong>Sample Answer:</strong></p>' . aiquizmaker_format_sample_answer($rawsample, $rubric);
        $question->generalfeedbackformat = FORMAT_HTML;
        $question->defaultmark = $totalmarks;
        $question->penalty = 0;
        $question->qtype = 'essay';
        $question->length = 1;
        $question->stamp = make_unique_id_code();
        $question->version = make_unique_id_code();
        $question->hidden = 0;
        $question->timecreated = time();
        $question->timemodified = time();
        $question->createdby = $USER->id;
        $question->modifiedby = $USER->id;
        
        try {
            // Insert question record
            $questionid = $DB->insert_record('question', $question);
            
            // Insert essay-specific options
            $essayoptions = new stdClass();
            $essayoptions->questionid = $questionid;
            $essayoptions->responseformat = 'editor';
            $essayoptions->responserequired = 1;
            $essayoptions->responsefieldlines = 15;
            $essayoptions->minwordlimit = null;
            $essayoptions->maxwordlimit = null;
            $essayoptions->attachments = 0;
            $essayoptions->attachmentsrequired = 0;
            $essayoptions->graderinfo = $graderinfo;
            $essayoptions->graderinfoformat = FORMAT_HTML;
            $essayoptions->responsetemplate = '';
            $essayoptions->responsetemplateformat = FORMAT_HTML;
            $essayoptions->filetypeslist = null;
            $essayoptions->maxbytes = 0;
            
            $DB->insert_record('qtype_essay_options', $essayoptions);

            aiquizmaker_create_question_bank_entry($questionid, $categoryid);

            return $questionid;
        } catch (\Throwable $e) {
            debugging('AI Quiz Maker: Failed to create question: ' . $e->getMessage(), DEBUG_DEVELOPER);
            return false;
        }
    }

    /**
     * Format structured sample answer for XML output.
     *
     * @param mixed $sampleAnswer The sample answer (string or array format).
     * @param array $rubric The rubric array for reference.
     * @return string Formatted HTML for the sample answer (for XML CDATA).
     */
    function aiquizmaker_format_sample_answer_xml($sampleAnswer, $rubric = []) {
        // If sampleAnswer is a JSON string, decode it first
        if (is_string($sampleAnswer) && !empty($sampleAnswer)) {
            $trimmed = trim($sampleAnswer);
            if (strpos($trimmed, '[') === 0 || strpos($trimmed, '{') === 0) {
                $decoded = json_decode($sampleAnswer, true);
                if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
                    $sampleAnswer = $decoded;
                }
            }
        }
        
        // Handle new structured format (array of criteria with responses)
        if (is_array($sampleAnswer) && isset($sampleAnswer[0])) {
            $html = '<div style="margin-top: 10px;">';
            foreach ($sampleAnswer as $index => $item) {
                $marks = isset($item['marks']) ? (int)$item['marks'] : 1;
                $response = isset($item['response']) ? $item['response'] : '';
                $response = str_replace(']]>', ']]]]><![CDATA[>', $response);
                $marktext = $marks === 1 ? '1 mark' : $marks . ' marks';
                
                // Get the matching rubric description if available
                $rubricDesc = '';
                if (isset($rubric[$index]) && isset($rubric[$index]['description'])) {
                    $rubricDesc = str_replace(']]>', ']]]]><![CDATA[>', $rubric[$index]['description']);
                }
                
                $html .= '<div style="margin-bottom: 12px; padding: 8px; background: #f8f9fa; border-left: 3px solid #0073aa; border-radius: 4px;">';
                $html .= '<p style="margin: 0 0 6px 0;"><strong style="color: #0073aa;">' . $marktext . ':</strong>';
                if ($rubricDesc) {
                    $html .= ' <em style="color: #666;">' . $rubricDesc . '</em>';
                }
                $html .= '</p>';
                $html .= '<p style="margin: 0;">' . nl2br($response) . '</p>';
                $html .= '</div>';
            }
            $html .= '</div>';
            return $html;
        }
        
        // Handle legacy string format
        if (is_string($sampleAnswer) && !empty($sampleAnswer)) {
            $sampleAnswer = str_replace(']]>', ']]]]><![CDATA[>', $sampleAnswer);
            return '<p>' . nl2br($sampleAnswer) . '</p>';
        }
        
        return '<p><em>No sample answer provided.</em></p>';
    }

    /**
     * Escape text for use inside CDATA sections.
     */
    function aiquizmaker_cdata(string $text): string {
        return '<![CDATA[' . str_replace(']]>', ']]]]><![CDATA[>', $text) . ']]>';
    }

    /**
     * Safely build a short question name for XML (max 80 chars, XML-encoded for use in <name><text>).
     */
    function aiquizmaker_shortname(string $text): string {
        $plain = strip_tags($text);
        $truncated = core_text::substr($plain, 0, 80);
        $short = htmlspecialchars($truncated, ENT_XML1, 'UTF-8');
        if (core_text::strlen($plain) > 80) {
            $short .= '...';
        }
        return $short;
    }

    /**
     * Safely build a short question name for DB (max 80 chars, plain text — no HTML encoding).
     * Use this for the question.name column which is a plain-text field.
     */
    function aiquizmaker_shortname_plain(string $text): string {
        $plain = strip_tags($text);
        $short = core_text::substr($plain, 0, 80);
        if (core_text::strlen($plain) > 80) {
            $short .= '...';
        }
        return $short;
    }

    /**
     * Insert question_bank_entries and question_versions records (Moodle 4.0+).
     * C1: Added timecreated, timemodified, createdby, modifiedby to bank entry.
     * C2: Cached table_exists() to avoid N schema queries in bulk operations.
     * C3: Use next version number rather than hardcoding 1.
     */
    function aiquizmaker_create_question_bank_entry(int $questionid, int $categoryid): void {
        global $DB, $USER;
        // C2: Cache table existence checks — avoids repeated schema queries in bulk operations.
        static $has_qbe = null;
        static $has_qv  = null;
        if ($has_qbe === null) {
            $has_qbe = $DB->get_manager()->table_exists('question_bank_entries');
        }
        if (!$has_qbe) {
            return;
        }
        $now = time();
        $entry = new stdClass();
        $entry->questioncategoryid = $categoryid;
        $entry->idnumber           = null;
        $entry->ownerid            = $USER->id;
        // C1: Populate audit fields so question bank shows correct owner and creation date.
        $entry->timecreated  = $now;
        $entry->timemodified = $now;
        $entry->createdby    = $USER->id;
        $entry->modifiedby   = $USER->id;
        // Some Moodle versions may not have all audit columns — fall back gracefully.
        try {
            $entryid = $DB->insert_record('question_bank_entries', $entry);
        } catch (dml_exception $e) {
            // Retry without the optional audit columns for older Moodle versions.
            $entry2 = new stdClass();
            $entry2->questioncategoryid = $categoryid;
            $entry2->idnumber           = null;
            $entry2->ownerid            = $USER->id;
            $entryid = $DB->insert_record('question_bank_entries', $entry2);
        }
        if ($has_qv === null) {
            $has_qv = $DB->get_manager()->table_exists('question_versions');
        }
        if ($has_qv) {
            $version = new stdClass();
            $version->questionbankentryid = $entryid;
            $version->questionid          = $questionid;
            // C3: Use next available version number rather than always hardcoding 1.
            if (function_exists('question_get_next_version_number')) {
                $version->version = question_get_next_version_number($entryid);
            } else {
                $existing = $DB->get_field('question_versions', 'MAX(version)', ['questionbankentryid' => $entryid]);
                $version->version = $existing ? ((int)$existing + 1) : 1;
            }
            $version->status = 'ready';
            $DB->insert_record('question_versions', $version);
        }
    }

    /**
     * Create a Multiple Choice question in the Moodle question bank.
     */
    function aiquizmaker_create_multichoice_question(array $q, int $categoryid) {
        global $DB, $USER;
        $questiontext = isset($q['questionText']) ? $q['questionText'] : '';
        $choices      = isset($q['choices']) && is_array($q['choices']) ? $q['choices'] : [];
        $explanation  = isset($q['explanation']) ? $q['explanation'] : '';
        $genfeedback  = isset($q['generalFeedback']) ? $q['generalFeedback'] : $explanation;
        $criteriaref  = isset($q['criteriaReference']) ? $q['criteriaReference'] : '';

        /* BUG-QM-CRITERIA-QTEXT-MCQ: criteriaReference was prepended to question text as
           <p><em>Criteria: ...</em></p>. Students saw the internal assessment criteria at the
           top of every MCQ question. criteriaReference is teacher/assessment metadata only —
           it appears in the AI Quiz Maker UI and Criteria Mapping export. It must never be
           part of the student-facing question body. Removed from $qhtml; stored in idnumber
           so it is preserved in Moodle's question bank (not visible to students). */
        $qhtml = '<p>' . nl2br(htmlspecialchars($questiontext)) . '</p>';

        $question = new stdClass();
        $question->category           = $categoryid;
        $question->parent             = 0;
        $question->name               = isset($q['_qnum']) ? 'Q' . (int)$q['_qnum'] : aiquizmaker_shortname_plain($questiontext);
        $question->questiontext       = $qhtml;
        $question->questiontextformat = FORMAT_HTML;
        $question->idnumber           = !empty($criteriaref) ? substr($criteriaref, 0, 100) : null;
        $question->generalfeedback    = $genfeedback ? '<p>' . nl2br(htmlspecialchars($genfeedback)) . '</p>' : '';
        $question->generalfeedbackformat = FORMAT_HTML;
        // G1: Calculate penalty dynamically based on choice count (Moodle formula: 1/(n-1)).
        $choicecount = count($choices);
        $mcpenalty = ($choicecount > 1) ? round(1.0 / ($choicecount - 1), 7) : 1.0;

        // G2: Detect multi-answer intent from choices — if more than one choice is correct, allow multiple selection.
        $correctcount = 0;
        foreach ($choices as $ch) {
            if (!empty($ch['isCorrect'])) { $correctcount++; }
        }
        $mcsingle = ($correctcount > 1) ? 0 : 1;

        $question->defaultmark        = 1;
        $question->penalty            = $mcpenalty;
        $question->qtype              = 'multichoice';
        $question->length             = 1;
        $question->stamp              = make_unique_id_code();
        $question->version            = make_unique_id_code();
        $question->hidden             = 0;
        $question->timecreated        = time();
        $question->timemodified       = time();
        $question->createdby          = $USER->id;
        $question->modifiedby         = $USER->id;
        $questionid = $DB->insert_record('question', $question);

        $mcopts = new stdClass();
        $mcopts->questionid                      = $questionid;
        $mcopts->layout                          = 0;
        $mcopts->single                          = $mcsingle;
        $mcopts->shuffleanswers                  = 1;
        $mcopts->answernumbering                 = 'abc';
        $mcopts->correctfeedback                 = 'Correct!';
        $mcopts->correctfeedbackformat           = FORMAT_HTML;
        $mcopts->partiallycorrectfeedback        = 'Partially correct.';
        $mcopts->partiallycorrectfeedbackformat  = FORMAT_HTML;
        $mcopts->incorrectfeedback               = 'Incorrect.';
        $mcopts->incorrectfeedbackformat         = FORMAT_HTML;
        // G3: Show num correct for multi-answer questions so students know how many to select.
        $mcopts->shownumcorrect                  = ($mcsingle === 0) ? 1 : 0;
        $DB->insert_record('qtype_multichoice_options', $mcopts);

        // B1: Guard for empty choices — prevent committing a question with no answer rows.
        if (empty($choices)) {
            $DB->delete_records('qtype_multichoice_options', ['questionid' => $questionid]);
            $DB->delete_records('question', ['id' => $questionid]);
            return false;
        }

        $hascorrect = ($correctcount > 0);
        // Safety: if AI returned no correct answer, mark the first choice correct.
        $firstcorrectset = false;
        foreach ($choices as $choice) {
            $ans = new stdClass();
            $ans->question      = $questionid;
            $ans->answer        = isset($choice['text']) ? clean_param($choice['text'], PARAM_TEXT) : '';
            // A1: Moodle MCQ expects FORMAT_HTML for answer (choice) text.
            $ans->answerformat  = FORMAT_HTML;
            if (!$hascorrect && !$firstcorrectset) {
                $ans->fraction     = 1.0;
                $firstcorrectset   = true;
            } else {
                $ans->fraction     = !empty($choice['isCorrect']) ? 1.0 : 0.0;
            }
            $ans->feedback      = isset($choice['feedback']) ? clean_param($choice['feedback'], PARAM_TEXT) : '';
            // A2: Moodle MCQ expects FORMAT_HTML for choice-level feedback.
            $ans->feedbackformat = FORMAT_HTML;
            $DB->insert_record('question_answers', $ans);
        }

        aiquizmaker_create_question_bank_entry($questionid, $categoryid);
        return $questionid;
    }

    /**
     * Create a True/False question in the Moodle question bank.
     */
    function aiquizmaker_create_truefalse_question(array $q, int $categoryid) {
        global $DB, $USER;
        $questiontext  = isset($q['questionText']) ? $q['questionText'] : '';
        $correctanswer = !empty($q['correctAnswer']);
        $truefeedback  = isset($q['trueAnswerFeedback']) ? $q['trueAnswerFeedback'] : '';
        $falsefeedback = isset($q['falseAnswerFeedback']) ? $q['falseAnswerFeedback'] : '';
        $explanation   = isset($q['explanation']) ? $q['explanation'] : '';
        $criteriaref   = isset($q['criteriaReference']) ? $q['criteriaReference'] : '';

        /* BUG-QM-CRITERIA-QTEXT-ALLTYPES (TrueFalse): Same root cause as BUG-QM-CRITERIA-QTEXT-MCQ.
           Criteria injection removed from question body; stored in idnumber instead. */
        $qhtml = '<p>' . nl2br(htmlspecialchars($questiontext)) . '</p>';

        $question = new stdClass();
        $question->category           = $categoryid;
        $question->parent             = 0;
        $question->name               = isset($q['_qnum']) ? 'Q' . (int)$q['_qnum'] : aiquizmaker_shortname_plain($questiontext);
        $question->questiontext       = $qhtml;
        $question->questiontextformat = FORMAT_HTML;
        $question->idnumber           = !empty($criteriaref) ? substr($criteriaref, 0, 100) : null;
        $question->generalfeedback    = $explanation ? '<p>' . nl2br(htmlspecialchars($explanation)) . '</p>' : '';
        $question->generalfeedbackformat = FORMAT_HTML;
        $question->defaultmark        = 1;
        $question->penalty            = 1;
        $question->qtype              = 'truefalse';
        $question->length             = 1;
        $question->stamp              = make_unique_id_code();
        $question->version            = make_unique_id_code();
        $question->hidden             = 0;
        $question->timecreated        = time();
        $question->timemodified       = time();
        $question->createdby          = $USER->id;
        $question->modifiedby         = $USER->id;
        $questionid = $DB->insert_record('question', $question);

        $trueans = new stdClass();
        $trueans->question      = $questionid;
        $trueans->answer        = 'true';
        $trueans->answerformat  = FORMAT_MOODLE;
        $trueans->fraction      = $correctanswer ? 1.0 : 0.0;
        $trueans->feedback      = clean_param($truefeedback, PARAM_TEXT);
        // A5: Moodle T/F stores feedback with FORMAT_MOODLE, not FORMAT_PLAIN.
        $trueans->feedbackformat = FORMAT_MOODLE;
        $trueid = $DB->insert_record('question_answers', $trueans);

        $falseans = new stdClass();
        $falseans->question      = $questionid;
        $falseans->answer        = 'false';
        $falseans->answerformat  = FORMAT_MOODLE;
        $falseans->fraction      = $correctanswer ? 0.0 : 1.0;
        $falseans->feedback      = clean_param($falsefeedback, PARAM_TEXT);
        // A5: Moodle T/F stores feedback with FORMAT_MOODLE, not FORMAT_PLAIN.
        $falseans->feedbackformat = FORMAT_MOODLE;
        $falseid = $DB->insert_record('question_answers', $falseans);

        $tfopts = new stdClass();
        $tfopts->question    = $questionid;
        $tfopts->trueanswer  = $trueid;
        $tfopts->falseanswer = $falseid;
        $DB->insert_record('question_truefalse', $tfopts);

        aiquizmaker_create_question_bank_entry($questionid, $categoryid);
        return $questionid;
    }

    /**
     * Create a Matching question in the Moodle question bank.
     */
    function aiquizmaker_create_matching_question(array $q, int $categoryid) {
        global $DB, $USER;
        $questiontext = isset($q['questionText']) ? $q['questionText'] : 'Match each term to its correct definition:';
        $matchpairs   = isset($q['matchPairs']) && is_array($q['matchPairs']) ? $q['matchPairs'] : [];
        $explanation  = isset($q['explanation']) ? $q['explanation'] : '';
        $criteriaref  = isset($q['criteriaReference']) ? $q['criteriaReference'] : '';
        // Require at least 2 pairs to create a valid matching question.
        if (count($matchpairs) < 2) {
            return false;
        }
        $totalmarks   = count($matchpairs);

        /* BUG-QM-CRITERIA-QTEXT-ALLTYPES (Matching): Same root cause as BUG-QM-CRITERIA-QTEXT-MCQ.
           Criteria injection removed from question body; stored in idnumber instead. */
        $qhtml = '<p>' . nl2br(htmlspecialchars($questiontext)) . '</p>';

        $question = new stdClass();
        $question->category           = $categoryid;
        $question->parent             = 0;
        $question->name               = isset($q['_qnum']) ? 'Q' . (int)$q['_qnum'] : aiquizmaker_shortname_plain($questiontext);
        $question->questiontext       = $qhtml;
        $question->questiontextformat = FORMAT_HTML;
        $question->idnumber           = !empty($criteriaref) ? substr($criteriaref, 0, 100) : null;
        $question->generalfeedback    = $explanation ? '<p>' . nl2br(htmlspecialchars($explanation)) . '</p>' : '';
        $question->generalfeedbackformat = FORMAT_HTML;
        $question->defaultmark        = $totalmarks;
        $question->penalty            = 0;
        $question->qtype              = 'match';
        $question->length             = 1;
        $question->stamp              = make_unique_id_code();
        $question->version            = make_unique_id_code();
        $question->hidden             = 0;
        $question->timecreated        = time();
        $question->timemodified       = time();
        $question->createdby          = $USER->id;
        $question->modifiedby         = $USER->id;
        $questionid = $DB->insert_record('question', $question);

        $matchopts = new stdClass();
        $matchopts->questionid                      = $questionid;
        $matchopts->shuffleanswers                  = 1;
        $matchopts->correctfeedback                 = 'Well done!';
        $matchopts->correctfeedbackformat           = FORMAT_HTML;
        $matchopts->partiallycorrectfeedback        = 'Parts of your response are correct.';
        $matchopts->partiallycorrectfeedbackformat  = FORMAT_HTML;
        $matchopts->incorrectfeedback               = 'That is not correct.';
        $matchopts->incorrectfeedbackformat         = FORMAT_HTML;
        $matchopts->shownumcorrect                  = 1;
        $DB->insert_record('qtype_match_options', $matchopts);

        foreach ($matchpairs as $pair) {
            $subq_text = clean_param(isset($pair['subquestion']) ? $pair['subquestion'] : '', PARAM_TEXT);
            $suba_text = clean_param(isset($pair['subanswer'])   ? $pair['subanswer']   : '', PARAM_TEXT);
            // B5: Skip pairs where either the subquestion or answer is empty — blank rows cause unlabelled dropdowns.
            if ($subq_text === '' || $suba_text === '') {
                continue;
            }
            $sub = new stdClass();
            // B7: qtype_match_subquestions FK column is 'questionid' (NOT 'question').
            // Using 'question' caused MySQL non-strict mode to store questionid=0, orphaning all
            // subquestions — Moodle then showed "This part of the question was deleted after the
            // attempt was started." Fix: use the correct column name 'questionid'.
            $sub->questionid        = $questionid;
            $sub->questiontext      = $subq_text;
            // A6: Moodle matching renderer calls format_text($sub->questiontext, $sub->questiontextformat) —
            //     FORMAT_HTML is the correct format for stored HTML-safe subquestion text.
            $sub->questiontextformat = FORMAT_HTML;
            $sub->answertext        = $suba_text;
            $DB->insert_record('qtype_match_subquestions', $sub);
        }

        aiquizmaker_create_question_bank_entry($questionid, $categoryid);
        return $questionid;
    }

    /**
     * BUG-QM-GAPSELECT-DUPES (v3.16.79) — Deduplicate the choices in a single gapselect group.
     *   Problem: For a "Select Missing Words" blank, the AI occasionally returned the same
     *   word twice within a single selectOptions sub-array (the correct answer repeated, OR
     *   a distractor repeated, OR the correct answer also appearing as one of the distractors).
     *   When this happened, Moodle would render the same word twice in the dropdown for that
     *   blank — confusing for students and incorrect for grading.
     *   Fix: this helper deduplicates a single group case-insensitively and trim-insensitively,
     *   keeping the FIRST occurrence (which preserves the correct answer at index 0 — by our
     *   convention the first choice is the correct one). Empty/whitespace-only choices are
     *   also removed.
     *   Returns the deduplicated group (array of strings, 0-indexed).
     */
    function aiquizmaker_dedupe_gapselect_group($group): array {
        if (!is_array($group)) {
            return [];
        }
        $seen = [];
        $clean = [];
        foreach ($group as $choice) {
            $text = is_string($choice) ? $choice : (string)$choice;
            $trim = trim($text);
            if ($trim === '') {
                continue;
            }
            $key = mb_strtolower($trim);
            if (isset($seen[$key])) {
                continue;
            }
            $seen[$key] = true;
            $clean[]    = $trim;
        }
        return $clean;
    }

    /**
     * Apply aiquizmaker_dedupe_gapselect_group() to every group in a selectOptions array.
     * Used at every codepath that handles AI-returned gapselect data (criteria mode,
     * generate-from-questions mode, regenerate mode, and as a final defence in the DB
     * writer and the XML writer).
     */
    function aiquizmaker_dedupe_gapselect_all(array $selectoptions): array {
        $out = [];
        foreach ($selectoptions as $group) {
            $out[] = aiquizmaker_dedupe_gapselect_group($group);
        }
        return $out;
    }

    /**
     * Create a Select Missing Words (gapselect) question in the Moodle question bank.
     * The questionText must contain [[n]] placeholders where n is a 1-based group index.
     * selectOptions is an array of groups: each group is an array of choice strings.
     * The first choice in each group is the correct answer.
     */
    function aiquizmaker_create_gapselect_question(array $q, int $categoryid) {
        global $DB, $USER;
        $questiontext = isset($q['questionText']) ? $q['questionText'] : '';
        $selectoptions = isset($q['selectOptions']) && is_array($q['selectOptions']) ? $q['selectOptions'] : [];
        $genfeedback   = isset($q['generalFeedback']) ? $q['generalFeedback'] : '';
        $criteriaref   = isset($q['criteriaReference']) ? $q['criteriaReference'] : '';

        /* BUG-QM-CRITERIA-QTEXT-ALLTYPES (GapSelect): Same root cause as BUG-QM-CRITERIA-QTEXT-MCQ.
           Criteria injection removed from question body; stored in idnumber instead. */
        // Build HTML question text — placeholders remain as [[n]] for Moodle renderer.
        $qhtml = '<p>' . nl2br(htmlspecialchars($questiontext)) . '</p>';

        /* BUG-QM-GAPSELECT-GROUPS: The external AI API may return selectOptions as a 1-based
           JSON object {"1":[...],"2":[...]} instead of a 0-indexed array [[...],[...]].
           PHP's json_decode converts numeric-string keys to PHP integer keys (1, 2 not 0, 1).
           Without normalisation, $groupnum = $groupidx + 1 gives groups 2 and 3 instead of
           1 and 2 — both [[1]] and [[2]] in the question text then reference empty groups,
           and Moodle falls back to showing the same non-empty group for every blank,
           causing all drop-downs to display identical choices.
           Fix: if keys start at 1 (not 0), re-index to 0-based so $groupidx + 1 is correct. */
        if (!empty($selectoptions) && !array_key_exists(0, $selectoptions)) {
            ksort($selectoptions);
            $selectoptions = array_values($selectoptions);
        }

        $question = new stdClass();
        // Validate that placeholder count matches group count.
        $placeholdercount = preg_match_all('/\[\[\d+\]\]/', $questiontext);
        if ($placeholdercount === false || $placeholdercount === 0 || count($selectoptions) === 0) {
            return false;
        }

        $question->category              = $categoryid;
        $question->parent                = 0;
        $question->name                  = isset($q['_qnum']) ? 'Q' . (int)$q['_qnum'] : aiquizmaker_shortname_plain($questiontext);
        $question->questiontext          = $qhtml;
        $question->questiontextformat    = FORMAT_HTML;
        $question->idnumber              = !empty($criteriaref) ? substr($criteriaref, 0, 100) : null;
        $question->generalfeedback       = $genfeedback ? '<p>' . nl2br(htmlspecialchars($genfeedback)) . '</p>' : '';
        $question->generalfeedbackformat = FORMAT_HTML;
        $question->defaultmark           = count($selectoptions);
        $question->penalty               = 0;
        $question->qtype                 = 'gapselect';
        $question->length                = 1;
        $question->stamp                 = make_unique_id_code();
        $question->version               = make_unique_id_code();
        $question->hidden                = 0;
        $question->timecreated           = time();
        $question->timemodified          = time();
        $question->createdby             = $USER->id;
        $question->modifiedby            = $USER->id;
        $questionid = $DB->insert_record('question', $question);

        // Moodle core gapselect options table is 'question_gapselect' (= 'question_' . $qtype->name()).
        // Verified against Moodle MOODLE_403_STABLE question/type/gapselect/questiontypebase.php.
        $gsopts = new stdClass();
        $gsopts->questionid                      = $questionid;
        $gsopts->shuffleanswers                  = 1;
        $gsopts->correctfeedback                 = 'Well done!';
        $gsopts->correctfeedbackformat           = FORMAT_HTML;
        $gsopts->partiallycorrectfeedback        = 'Parts of your response are correct.';
        $gsopts->partiallycorrectfeedbackformat  = FORMAT_HTML;
        $gsopts->incorrectfeedback               = 'That is not correct.';
        $gsopts->incorrectfeedbackformat         = FORMAT_HTML;
        $gsopts->shownumcorrect                  = 1;
        $DB->insert_record('question_gapselect', $gsopts);

        // Insert answer choices — each group is a set of options; group number matches [[n]] in question text.
        // FIX-QM-GAPSELECT-FEEDBACK (v3.16.62): Moodle qtype_gapselect_base stores the GROUP NUMBER
        //   in the FEEDBACK field (not fraction). Moodle's get_question_options() groups answers by
        //   $record->feedback to build the per-blank choice lists.
        //   v3.16.61 incorrectly set fraction=$groupnum and feedback='' — this meant Moodle read every
        //   choice as having feedback='' (empty string = group ''), so all choices appeared in the same
        //   group (Group A/blank 1) regardless of which blank they belonged to. Fixed in v3.16.62.
        //   Correct convention (Moodle MOODLE_403_STABLE qtype_gapselect_base.php):
        //     fraction       = 1.0 for the FIRST choice in each group (correct answer); 0 for distractors.
        //                      Moodle's is_correct_response() checks fraction > 0 — if all choices have
        //                      fraction=0, no answer is ever graded correct (BUG-QM-GAPSELECT-FRACTION).
        //     feedback       = (string) group number  e.g. '1', '2', '3' — Moodle groups by this value
        //     answerformat   = FORMAT_HTML
        //     feedbackformat = FORMAT_HTML
        // BUG-QM-GAPSELECT-FRACTION (v3.16.65): v3.16.62 set fraction=0 for ALL choices, which meant
        //   Moodle's is_correct_response() always returned false and students could never earn marks.
        //   Fixed: first choice in each group (the correct answer) now has fraction=1.0.
        // The first choice in each group is the correct answer (must be inserted first).
        $answersinserted = 0;
        // FIX-QM-GAPSELECT-JUMPBY3 (v3.16.65): Derive group numbers from the [[N]] placeholders
        // in the question text so the answer choices match the correct dropdown regardless of
        // whether the sentence uses sequential [[1]],[[2]] or any other numbering.
        preg_match_all('/\[\[(\d+)\]\]/', $questiontext, $phmatches);
        $placeholder_nums = array_map('intval', $phmatches[1] ?? []);

        // FIX-QM-GAPSELECT-COUNT-MISMATCH (v3.16.73): Validate that selectOptions count matches
        // the number of [[n]] placeholders. A mismatch means either:
        //   A. selectOptions has MORE groups than gaps — extras are silently ignored (safe to truncate).
        //   B. selectOptions has FEWER groups than gaps — some gaps would have no choices, causing
        //      Moodle to emit "Warning: Undefined array key N" on every question render/attempt.
        // Strategy: truncate extras (case A). Refuse to create (return false) for case B —
        // better to skip the question and surface it as a "skipped" item than to save broken data.
        $gapcount = count($placeholder_nums);
        $groupcount = count($selectoptions);
        if ($gapcount === 0 || $groupcount === 0) {
            return false;
        }
        if ($groupcount > $gapcount) {
            // More option groups than gaps — trim the excess (they would never be shown).
            $selectoptions = array_slice($selectoptions, 0, $gapcount);
            $groupcount    = $gapcount;
        } else if ($groupcount < $gapcount) {
            // Fewer option groups than gaps — some gaps would have no choices (Moodle warnings).
            return false;
        }

        // BUG-QM-GAPSELECT-DUPES (v3.16.79): Defence-in-depth per-blank deduplication immediately
        // before writing question_answers rows. If anything upstream missed the dedupe (older AI
        // payloads, third-party callers) we still avoid writing duplicate dropdown choices to the
        // Moodle DB. Any group that ends up with fewer than 2 distinct choices after dedupe is
        // skipped (the existing "if (count($choices) < 2) continue;" guard inside the loop below
        // will silently drop it — see comment there).
        $selectoptions = aiquizmaker_dedupe_gapselect_all($selectoptions);

        foreach ($selectoptions as $groupidx => $choices) {
            $groupnum = isset($placeholder_nums[$groupidx]) ? $placeholder_nums[$groupidx] : ($groupidx + 1);
            if (!is_array($choices) || count($choices) < 2) {
                // Skip invalid groups (must have at least one correct + one distractor).
                continue;
            }
            foreach ($choices as $choiceidx => $choicetext) {
                $answer = new stdClass();
                $answer->question       = $questionid;
                $answer->answer         = clean_param($choicetext, PARAM_TEXT);
                $answer->answerformat   = FORMAT_HTML;
                // BUG-QM-GAPSELECT-FRACTION: First choice in each group = correct answer (fraction=1.0).
                // Distractors (all subsequent choices in the group) = fraction=0.
                // Moodle's qtype_gapselect_base::is_correct_response() checks fraction > 0 to grade.
                $answer->fraction       = ($choiceidx === 0) ? 1.0 : 0.0;
                $answer->feedback       = (string) $groupnum;  // group number per Moodle convention
                $answer->feedbackformat = FORMAT_HTML;
                $DB->insert_record('question_answers', $answer);
                $answersinserted++;
            }
        }

        // Safety: if every group was skipped (all had < 2 choices), clean up orphan records and abort.
        if ($answersinserted === 0) {
            $DB->delete_records('question_gapselect', ['questionid' => $questionid]);
            $DB->delete_records('question', ['id' => $questionid]);
            return false;
        }

        aiquizmaker_create_question_bank_entry($questionid, $categoryid);
        return $questionid;
    }

    /**
     * Create a Short Answer (Missing Word / Fill in the Blank) question in the Moodle question bank.
     */
    function aiquizmaker_create_shortanswer_question(array $q, int $categoryid) {
        global $DB, $USER;
        $questiontext    = isset($q['questionText']) ? $q['questionText'] : '';
        $blanksentence   = isset($q['blankSentence']) ? $q['blankSentence'] : '';
        $acceptedanswers = isset($q['acceptedAnswers']) && is_array($q['acceptedAnswers']) ? $q['acceptedAnswers'] : [];
        $explanation     = isset($q['explanation']) ? $q['explanation'] : '';
        $genfeedback     = isset($q['generalFeedback']) ? $q['generalFeedback'] : $explanation;
        $criteriaref     = isset($q['criteriaReference']) ? $q['criteriaReference'] : '';

        /* BUG-QM-CRITERIA-QTEXT-ALLTYPES (ShortAnswer): Same root cause as BUG-QM-CRITERIA-QTEXT-MCQ.
           Criteria injection removed from question body; stored in idnumber instead. */
        $qhtml = '<p>' . nl2br(htmlspecialchars($questiontext)) . '</p>';
        if (!empty($blanksentence)) {
            $qhtml .= '<p>' . nl2br(htmlspecialchars($blanksentence)) . '</p>';
        }

        $question = new stdClass();
        $question->category           = $categoryid;
        $question->parent             = 0;
        $question->name               = isset($q['_qnum']) ? 'Q' . (int)$q['_qnum'] : aiquizmaker_shortname_plain($questiontext);
        $question->questiontext       = $qhtml;
        $question->questiontextformat = FORMAT_HTML;
        $question->generalfeedback    = $genfeedback ? '<p>' . nl2br(htmlspecialchars($genfeedback)) . '</p>' : '';
        $question->generalfeedbackformat = FORMAT_HTML;
        $question->defaultmark        = 1;
        $question->penalty            = 0;
        $question->qtype              = 'shortanswer';
        $question->length             = 1;
        $question->stamp              = make_unique_id_code();
        $question->version            = make_unique_id_code();
        $question->hidden             = 0;
        $question->timecreated        = time();
        $question->timemodified       = time();
        $question->createdby          = $USER->id;
        $question->modifiedby         = $USER->id;
        $question->idnumber           = !empty($criteriaref) ? substr($criteriaref, 0, 100) : null;
        $questionid = $DB->insert_record('question', $question);

        $saopts = new stdClass();
        $saopts->questionid = $questionid;
        $saopts->usecase    = 0; // case insensitive
        $DB->insert_record('qtype_shortanswer_options', $saopts);

        // B3: Filter empty strings — an empty accepted answer acts as a wildcard matching ANY student input.
        $acceptedanswers = array_values(array_filter(array_map(
            fn($a) => clean_param($a, PARAM_TEXT),
            $acceptedanswers
        ), fn($a) => trim($a) !== ''));

        // B2: Guard for empty acceptedAnswers — question with no answer rows never awards marks.
        if (empty($acceptedanswers)) {
            $DB->delete_records('qtype_shortanswer_options', ['questionid' => $questionid]);
            $DB->delete_records('question', ['id' => $questionid]);
            return false;
        }

        foreach ($acceptedanswers as $ans) {
            $answer = new stdClass();
            $answer->question      = $questionid;
            $answer->answer        = $ans; // already clean_param'd above
            // A3: Moodle shortanswer comparison uses FORMAT_MOODLE (0) for stored answers.
            $answer->answerformat  = FORMAT_MOODLE;
            $answer->fraction      = 1.0; // all accepted answers award full marks
            // H1: All accepted answers get 'Correct!' feedback so students see it regardless of which synonym they used.
            $answer->feedback      = 'Correct!';
            // A4: Moodle shortanswer feedback uses FORMAT_HTML.
            $answer->feedbackformat = FORMAT_HTML;
            $DB->insert_record('question_answers', $answer);
        }

        aiquizmaker_create_question_bank_entry($questionid, $categoryid);
        return $questionid;
    }

    /**
     * Create a Moodle description question in the question bank.
     * Description questions display instructional text in a quiz without any answer input.
     */
    function aiquizmaker_create_description_question(array $q, int $categoryid) {
        global $DB, $USER;
        $questiontext = isset($q['questionText']) ? clean_param($q['questionText'], PARAM_TEXT) : '';
        if (empty($questiontext)) {
            return null;
        }
        $question = new stdClass();
        $question->category              = $categoryid;
        $question->parent                = 0;
        // N1: Description questions are section headings — their name is always derived from
        //     their heading text, never prefixed with a Q number. The question bank display
        //     shows name + text; using the heading text as both makes the list clean.
        $question->name                  = aiquizmaker_shortname($questiontext);
        // D1: Render as a bold paragraph using Moodle's default paragraph margin.
        //     The previous `margin:0` collapsed the content area height so that Moodle's
        //     "Information" label (in the left column of the 2-column quiz layout) appeared
        //     vertically misaligned — floating above the content box instead of sitting next
        //     to it. Removing the margin override lets Moodle's theme apply its standard
        //     paragraph spacing, giving the content box natural height and keeping the
        //     "Information" label aligned with the text.
        //     Note: we intentionally avoid <h4> — an earlier version used it and created a
        //     double-heading effect (Moodle's own "Information" label + a second bold heading
        //     inside the box) that looked oversized. <p><strong> gives clean bold text without
        //     a second heading level.
        $escaped = htmlspecialchars($questiontext, ENT_QUOTES);
        $question->questiontext          = '<p><strong>' . $escaped . '</strong></p>';
        $question->questiontextformat    = FORMAT_HTML;
        $question->generalfeedback       = '';
        $question->generalfeedbackformat = FORMAT_HTML;
        $question->defaultmark           = 0;
        $question->penalty               = 0;
        $question->qtype                 = 'description';
        // D2: Moodle requires length=0 for description questions (they do not count toward the
        //     quiz total question count). length=1 caused Moodle to treat it as a gradable slot.
        $question->length                = 0;
        $question->stamp                 = make_unique_id_code();
        $question->version               = make_unique_id_code();
        $question->hidden                = 0;
        $question->timecreated           = time();
        $question->timemodified          = time();
        $question->createdby             = $USER->id;
        $question->modifiedby            = $USER->id;
        $questionid = $DB->insert_record('question', $question);
        aiquizmaker_create_question_bank_entry($questionid, $categoryid);
        return $questionid;
    }

    /**
     * Add a question slot to a quiz using the correct API for the running Moodle version.
     *
     * Moodle 4.0-4.3: quiz_add_quiz_question() still exists in locallib.php.
     * Moodle 4.4+:    quiz_add_quiz_question() was removed (MDL-76897).
     *                 Direct DB insert into quiz_slots + question_references is required.
     *
     * This function is called ONLY for the Moodle 4.4+ path (when quiz_add_quiz_question
     * is not available). By the time it is called, aiquizmaker_create_question_bank_entry()
     * has already written the question_bank_entries and question_versions rows.
     *
     * @param object   $DB          The global $DB object.
     * @param object   $quiz        The quiz DB record.
     * @param int      $questionid  The question id to add.
     * @param object   $modcontext  The module context.
     * @param int      $page        The page number to place the question on.
     * @throws \Exception            If the question_versions row cannot be found.
     */
    function aiquizmaker_quiz_add_slot($DB, $quiz, $questionid, $modcontext, $page) {
        // Locate the question bank entry written by aiquizmaker_create_question_bank_entry().
        $qbentryid = $DB->get_field('question_versions', 'questionbankentryid', ['questionid' => $questionid]);
        if (!$qbentryid) {
            throw new \Exception('quiz_add_slot: no question_versions row for questionid=' . $questionid);
        }

        // Next sequential slot number across the whole quiz.
        $nextslot = (int)$DB->get_field_sql(
            'SELECT COALESCE(MAX(slot), 0) FROM {quiz_slots} WHERE quizid = ?',
            [$quiz->id]
        ) + 1;

        // Default mark from the question record (defaultmark is stored as a decimal string).
        // D3: Do NOT use ?: 1 here — PHP treats 0 as falsy, so description questions (defaultmark=0)
        //     would incorrectly receive maxmark=1 in the quiz slot. Explicit null/false check instead.
        $rawmark = $DB->get_field('question', 'defaultmark', ['id' => $questionid]);
        $defaultmark = ($rawmark !== false && $rawmark !== null) ? (float)$rawmark : 1.0;

        // Insert the slot.
        $slotobj = new stdClass();
        $slotobj->quizid           = $quiz->id;
        $slotobj->page             = $page;
        $slotobj->slot             = $nextslot;
        $slotobj->requireprevious  = 0;
        $slotobj->maxmark          = $defaultmark;
        $slotid = $DB->insert_record('quiz_slots', $slotobj);

        // Insert the question reference (links slot → question bank entry).
        // version = NULL means "always use the latest published version".
        $refobj = new stdClass();
        $refobj->usingcontextid     = $modcontext->id;
        $refobj->component          = 'mod_quiz';
        $refobj->questionarea       = 'slot';
        $refobj->itemid             = $slotid;
        $refobj->questionbankentryid = $qbentryid;
        $refobj->version            = null;
        $DB->insert_record('question_references', $refobj);

        // Moodle 4.0+ requires at least one quiz_sections row.
        // The section covers the first slot; subsequent slots on later pages are within it.
        if (!$DB->record_exists('quiz_sections', ['quizid' => $quiz->id])) {
            $section = new stdClass();
            $section->quizid           = $quiz->id;
            $section->firstslot        = 1;
            $section->heading          = '';
            $section->shufflequestions = 0;
            $DB->insert_record('quiz_sections', $section);
        }
    }

    /**
     * Route question creation to the correct creator based on moodleQuestionType.
     */
    function aiquizmaker_create_question(array $q, int $categoryid) {
        $qtype = isset($q['moodleQuestionType']) ? $q['moodleQuestionType'] : 'essay';
        switch ($qtype) {
            case 'description':
                return aiquizmaker_create_description_question($q, $categoryid);
            case 'multichoice':
                return aiquizmaker_create_multichoice_question($q, $categoryid);
            case 'truefalse':
                return aiquizmaker_create_truefalse_question($q, $categoryid);
            case 'matching':
                return aiquizmaker_create_matching_question($q, $categoryid);
            case 'gapselect':
                return aiquizmaker_create_gapselect_question($q, $categoryid);
            case 'shortanswer':
                return aiquizmaker_create_shortanswer_question($q, $categoryid);
            case 'essay':
            default:
                return aiquizmaker_create_essay_question($q, $categoryid);
        }
    }

    /* ---- XML helper functions ---- */

    function aiquizmaker_xml_essay_block(array $q): string {
        $rawquestion = isset($q['questionText']) ? $q['questionText'] : '';
        $rawsample   = isset($q['sampleAnswer']) ? $q['sampleAnswer'] : '';
        $rawcriteria = isset($q['criteriaReference']) ? $q['criteriaReference'] : '';
        $totalmarks  = isset($q['totalMarks']) ? (int)$q['totalMarks'] : 3;
        $rubric      = isset($q['rubric']) ? $q['rubric'] : [];

        $questiontext = str_replace(']]>', ']]]]><![CDATA[>', $rawquestion);
        $criteriaref  = str_replace(']]>', ']]]]><![CDATA[>', $rawcriteria);

        $rubrichtml = '';
        $graderrubric = '';
        if (isset($q['rubric'])) {
            if (is_array($q['rubric']) && isset($q['rubric'][0])) {
                foreach ($q['rubric'] as $r) {
                    $marks    = isset($r['marks']) ? (int)$r['marks'] : 1;
                    $desc     = isset($r['description']) ? str_replace(']]>', ']]]]><![CDATA[>', $r['description']) : '';
                    $marktext = $marks === 1 ? '1 mark' : $marks . ' marks';
                    $rubrichtml   .= '<li><strong>' . $marktext . ':</strong> ' . $desc . '</li>' . "\n";
                    $graderrubric .= '<li>' . $marktext . ': ' . $desc . '</li>' . "\n";
                }
            } else {
                foreach (['hazard', 'example', 'control'] as $key) {
                    $val = isset($q['rubric'][$key]) ? str_replace(']]>', ']]]]><![CDATA[>', $q['rubric'][$key]) : '';
                    if ($val) {
                        $rubrichtml   .= '<li><strong>1 mark:</strong> ' . $val . '</li>' . "\n";
                        $graderrubric .= '<li>1 mark: ' . $val . '</li>' . "\n";
                    }
                }
            }
        }

        $shortname   = isset($q['_qnum']) ? 'Q' . (int)$q['_qnum'] : aiquizmaker_shortname($rawquestion);
        $markingtext = $totalmarks === 1 ? '1 mark total' : $totalmarks . ' marks total';
        $samplehtml  = aiquizmaker_format_sample_answer_xml($rawsample, $rubric);

        $xml  = '  <question type="essay">' . "\n";
        $xml .= '    <name><text>' . $shortname . '</text></name>' . "\n";
        $xml .= '    <questiontext format="html">' . "\n";
        $xml .= '      <text><![CDATA[<p>' . nl2br($questiontext) . '</p>' . "\n";
        $xml .= '<p><strong>Marking Criteria (' . $markingtext . '):</strong></p>' . "\n";
        $xml .= '<ul>' . "\n" . $rubrichtml . '</ul>]]></text>' . "\n";
        $xml .= '    </questiontext>' . "\n";
        $xml .= '    <generalfeedback format="html">' . "\n";
        $xml .= '      <text><![CDATA[<p><strong>Sample Answer:</strong></p>' . $samplehtml . ']]></text>' . "\n";
        $xml .= '    </generalfeedback>' . "\n";
        $xml .= '    <defaultgrade>' . $totalmarks . '</defaultgrade>' . "\n";
        $xml .= '    <responseformat>editor</responseformat>' . "\n";
        $xml .= '    <responserequired>1</responserequired>' . "\n";
        $xml .= '    <responsefieldlines>15</responsefieldlines>' . "\n";
        $xml .= '    <graderinfo format="html">' . "\n";
        $xml .= '      <text><![CDATA[<p><strong>Criteria Reference:</strong> ' . $criteriaref . '</p>' . "\n";
        $xml .= '<p><strong>Rubric:</strong></p><ul>' . "\n" . $graderrubric . '</ul>' . "\n";
        $xml .= '<p><strong>Sample Answer:</strong></p>' . $samplehtml . ']]></text>' . "\n";
        $xml .= '    </graderinfo>' . "\n";
        $xml .= '  </question>' . "\n";
        return $xml;
    }

    function aiquizmaker_xml_multichoice_block(array $q): string {
        $questiontext = isset($q['questionText']) ? $q['questionText'] : '';
        $choices      = isset($q['choices']) && is_array($q['choices']) ? $q['choices'] : [];
        $explanation  = isset($q['explanation']) ? $q['explanation'] : '';
        $genfeedback  = isset($q['generalFeedback']) ? $q['generalFeedback'] : $explanation;
        $criteriaref  = isset($q['criteriaReference']) ? $q['criteriaReference'] : '';

        // G1: Dynamic penalty matching DB creator logic.
        $choicecount = count($choices);
        $xmlpenalty = ($choicecount > 1) ? round(1.0 / ($choicecount - 1), 7) : 1.0;
        // G2: Multi-answer detection.
        $xmlcorrectcount = count(array_filter($choices, fn($c) => !empty($c['isCorrect'])));
        $xmlsingle = ($xmlcorrectcount > 1) ? 0 : 1;
        // G5: Fallback — if no correct answer, mark first choice correct in XML output.
        $hascorrect_xml = ($xmlcorrectcount > 0);

        $shortname = isset($q['_qnum']) ? 'Q' . (int)$q['_qnum'] : aiquizmaker_shortname($questiontext);
        /* BUG-QM-CRITERIA-QTEXT-XML (MCQ): criteriaReference must not be injected into
           XML question text — students see question text in quizzes. Criteria is teacher
           metadata only; remove injection so exported XML is clean. */
        $qtextbody = '<p>' . nl2br(htmlspecialchars($questiontext)) . '</p>';
        $xml  = '  <question type="multichoice">' . "\n";
        $xml .= '    <name><text>' . $shortname . '</text></name>' . "\n";
        $xml .= '    <questiontext format="html"><text>' . aiquizmaker_cdata($qtextbody) . '</text></questiontext>' . "\n";
        $xml .= '    <generalfeedback format="html"><text>' . aiquizmaker_cdata($genfeedback ? '<p>' . nl2br(htmlspecialchars($genfeedback)) . '</p>' : '') . '</text></generalfeedback>' . "\n";
        $xml .= '    <defaultgrade>1</defaultgrade>' . "\n";
        $xml .= '    <penalty>' . $xmlpenalty . '</penalty>' . "\n";
        $xml .= '    <single>' . $xmlsingle . '</single>' . "\n";
        $xml .= '    <shuffleanswers>1</shuffleanswers>' . "\n";
        $xml .= '    <answernumbering>abc</answernumbering>' . "\n";
        $xml .= '    <correctfeedback format="html"><text>Correct!</text></correctfeedback>' . "\n";
        $xml .= '    <partiallycorrectfeedback format="html"><text>Partially correct.</text></partiallycorrectfeedback>' . "\n";
        $xml .= '    <incorrectfeedback format="html"><text>Incorrect.</text></incorrectfeedback>' . "\n";
        // G3: shownumcorrect for multi-answer questions.
        if ($xmlsingle === 0) {
            $xml .= '    <shownumcorrect/>' . "\n";
        }
        $firstcorrect_xml_set = false;
        foreach ($choices as $choice) {
            $isCorrect = !empty($choice['isCorrect']);
            // G5: Ensure at least one answer is marked correct.
            if (!$hascorrect_xml && !$firstcorrect_xml_set) {
                $isCorrect = true;
                $firstcorrect_xml_set = true;
            }
            $fraction     = $isCorrect ? '100' : '0';
            // D2/D3: Do NOT htmlspecialchars inside CDATA — CDATA passes content literally;
            //         double-encoding would render '&' as literal '&amp;' after import.
            $choicetext   = isset($choice['text']) ? $choice['text'] : '';
            $choicefeedback = isset($choice['feedback']) ? $choice['feedback'] : '';
            $xml .= '    <answer fraction="' . $fraction . '" format="html">' . "\n";
            $xml .= '      <text>' . aiquizmaker_cdata($choicetext) . '</text>' . "\n";
            $xml .= '      <feedback format="html"><text>' . aiquizmaker_cdata($choicefeedback) . '</text></feedback>' . "\n";
            $xml .= '    </answer>' . "\n";
        }
        $xml .= '  </question>' . "\n";
        return $xml;
    }

    function aiquizmaker_xml_truefalse_block(array $q): string {
        $questiontext  = isset($q['questionText']) ? $q['questionText'] : '';
        $correctanswer = !empty($q['correctAnswer']);
        $truefeedback  = isset($q['trueAnswerFeedback']) ? $q['trueAnswerFeedback'] : '';
        $falsefeedback = isset($q['falseAnswerFeedback']) ? $q['falseAnswerFeedback'] : '';
        $explanation   = isset($q['explanation']) ? $q['explanation'] : '';

        $shortname    = isset($q['_qnum']) ? 'Q' . (int)$q['_qnum'] : aiquizmaker_shortname($questiontext);
        $truefraction  = $correctanswer ? '100' : '0';
        $falsefraction = $correctanswer ? '0' : '100';

        $xml  = '  <question type="truefalse">' . "\n";
        $xml .= '    <name><text>' . $shortname . '</text></name>' . "\n";
        $xml .= '    <questiontext format="html"><text>' . aiquizmaker_cdata('<p>' . nl2br(htmlspecialchars($questiontext)) . '</p>') . '</text></questiontext>' . "\n";
        $xml .= '    <generalfeedback format="html"><text>' . aiquizmaker_cdata($explanation ? '<p>' . nl2br(htmlspecialchars($explanation)) . '</p>' : '') . '</text></generalfeedback>' . "\n";
        $xml .= '    <defaultgrade>1</defaultgrade>' . "\n";
        $xml .= '    <penalty>1</penalty>' . "\n";
        // D1: Moodle XML importer is case-sensitive for T/F answer text — must be lowercase 'true'/'false'.
        // I1: Use format="moodle_auto_format" to match what Moodle's own exporter writes.
        $xml .= '    <answer fraction="' . $truefraction . '" format="moodle_auto_format">' . "\n";
        $xml .= '      <text>true</text>' . "\n";
        // D2-style: truefeedback is plain text — pass to CDATA without htmlspecialchars to avoid double-encoding.
        $xml .= '      <feedback format="html"><text>' . aiquizmaker_cdata($truefeedback) . '</text></feedback>' . "\n";
        $xml .= '    </answer>' . "\n";
        $xml .= '    <answer fraction="' . $falsefraction . '" format="moodle_auto_format">' . "\n";
        $xml .= '      <text>false</text>' . "\n";
        $xml .= '      <feedback format="html"><text>' . aiquizmaker_cdata($falsefeedback) . '</text></feedback>' . "\n";
        $xml .= '    </answer>' . "\n";
        $xml .= '  </question>' . "\n";
        return $xml;
    }

    function aiquizmaker_xml_matching_block(array $q): string {
        $questiontext = isset($q['questionText']) ? $q['questionText'] : 'Match each term to its correct definition:';
        $matchpairs   = isset($q['matchPairs']) && is_array($q['matchPairs']) ? $q['matchPairs'] : [];
        $explanation  = isset($q['explanation']) ? $q['explanation'] : '';

        // B6: Guard — matching with fewer than 2 pairs is invalid; return empty string rather than broken XML.
        if (count($matchpairs) < 2) {
            return '';
        }

        // B5 (XML): Filter pairs where subquestion or subanswer is empty.
        $matchpairs = array_values(array_filter($matchpairs, function ($pair) {
            return trim($pair['subquestion'] ?? '') !== '' && trim($pair['subanswer'] ?? '') !== '';
        }));
        if (count($matchpairs) < 2) {
            return '';
        }

        $totalmarks = count($matchpairs);
        $shortname = isset($q['_qnum']) ? 'Q' . (int)$q['_qnum'] : aiquizmaker_shortname($questiontext);
        $xml  = '  <question type="match">' . "\n";
        $xml .= '    <name><text>' . $shortname . '</text></name>' . "\n";
        $xml .= '    <questiontext format="html"><text>' . aiquizmaker_cdata('<p>' . nl2br(htmlspecialchars($questiontext)) . '</p>') . '</text></questiontext>' . "\n";
        $xml .= '    <generalfeedback format="html"><text>' . aiquizmaker_cdata($explanation ? '<p>' . nl2br(htmlspecialchars($explanation)) . '</p>' : '') . '</text></generalfeedback>' . "\n";
        $xml .= '    <defaultgrade>' . $totalmarks . '</defaultgrade>' . "\n";
        $xml .= '    <penalty>0</penalty>' . "\n";
        $xml .= '    <shuffleanswers>1</shuffleanswers>' . "\n";
        $xml .= '    <correctfeedback format="html"><text>Well done!</text></correctfeedback>' . "\n";
        $xml .= '    <partiallycorrectfeedback format="html"><text>Parts of your response are correct.</text></partiallycorrectfeedback>' . "\n";
        $xml .= '    <incorrectfeedback format="html"><text>That is not correct.</text></incorrectfeedback>' . "\n";
        foreach ($matchpairs as $pair) {
            // D4/D5: Do NOT htmlspecialchars inside CDATA — it double-encodes entities on import.
            $subq = isset($pair['subquestion']) ? $pair['subquestion'] : '';
            $suba = isset($pair['subanswer'])   ? $pair['subanswer']   : '';
            $xml .= '    <subquestion format="html">' . "\n";
            $xml .= '      <text>' . aiquizmaker_cdata($subq) . '</text>' . "\n";
            $xml .= '      <answer><text>' . aiquizmaker_cdata($suba) . '</text></answer>' . "\n";
            $xml .= '    </subquestion>' . "\n";
        }
        $xml .= '  </question>' . "\n";
        return $xml;
    }

    /**
     * Generate Moodle XML block for a Select Missing Words (gapselect) question.
     * questionText must contain [[n]] placeholders (1-based group index).
     * selectOptions is an array of groups; first item in each group is the correct answer.
     */
    function aiquizmaker_xml_gapselect_block(array $q): string {
        $questiontext  = isset($q['questionText']) ? $q['questionText'] : '';
        $selectoptions = isset($q['selectOptions']) && is_array($q['selectOptions']) ? $q['selectOptions'] : [];
        $genfeedback   = isset($q['generalFeedback']) ? $q['generalFeedback'] : '';

        /* BUG-QM-GAPSELECT-GROUPS (XML path): Same normalisation as in the DB creator.
           If selectOptions arrives with 1-based keys (PHP int keys 1, 2) re-index to 0-based
           so placeholder-derived groupnum produces groups 1, 2 instead of 2, 3. */
        if (!empty($selectoptions) && !array_key_exists(0, $selectoptions)) {
            ksort($selectoptions);
            $selectoptions = array_values($selectoptions);
        }

        // FIX-QM-GAPSELECT-JUMPBY3 (v3.16.65): Derive group numbers from the [[N]] placeholders
        // in the question text so that choices land in the correct dropdown for any numbering scheme.
        preg_match_all('/\[\[(\d+)\]\]/', $questiontext, $xmlphmatches);
        $xml_placeholder_nums = array_map('intval', $xmlphmatches[1] ?? []);

        // FIX-QM-GAPSELECT-COUNT-MISMATCH (v3.16.73): Same guardrail as DB path — truncate excess
        // groups or return empty string to suppress a broken XML <question> block.
        $xml_gapcount   = count($xml_placeholder_nums);
        $xml_groupcount = count($selectoptions);
        if ($xml_gapcount === 0 || $xml_groupcount === 0) {
            return '';
        }
        if ($xml_groupcount > $xml_gapcount) {
            $selectoptions   = array_slice($selectoptions, 0, $xml_gapcount);
            $xml_groupcount  = $xml_gapcount;
        } else if ($xml_groupcount < $xml_gapcount) {
            return '';
        }

        // BUG-QM-GAPSELECT-DUPES (v3.16.79): Defence-in-depth per-blank deduplication for the
        // Moodle XML export path. Mirrors the same dedupe applied in the DB writer above so that
        // exported .xml files never contain duplicate dropdown options for a single blank.
        $selectoptions = aiquizmaker_dedupe_gapselect_all($selectoptions);

        $shortname    = isset($q['_qnum']) ? 'Q' . (int)$q['_qnum'] : aiquizmaker_shortname($questiontext);
        $numgaps      = $xml_gapcount;
        $defaultgrade = max(1, $numgaps);

        $xml  = '  <question type="gapselect">' . "\n";
        // D8: $shortname from aiquizmaker_shortname() already has htmlspecialchars applied — use as-is,
        //     do NOT double-encode with another htmlspecialchars call.
        $xml .= '    <name><text>' . $shortname . '</text></name>' . "\n";
        $xml .= '    <questiontext format="html"><text>' . aiquizmaker_cdata('<p>' . nl2br(htmlspecialchars($questiontext)) . '</p>') . '</text></questiontext>' . "\n";
        $xml .= '    <generalfeedback format="html"><text>' . aiquizmaker_cdata($genfeedback ? '<p>' . nl2br(htmlspecialchars($genfeedback)) . '</p>' : '') . '</text></generalfeedback>' . "\n";
        $xml .= '    <defaultgrade>' . $defaultgrade . '</defaultgrade>' . "\n";
        $xml .= '    <penalty>0</penalty>' . "\n";
        $xml .= '    <shuffleanswers>1</shuffleanswers>' . "\n";
        $xml .= '    <correctfeedback format="html"><text>Well done!</text></correctfeedback>' . "\n";
        $xml .= '    <partiallycorrectfeedback format="html"><text>Parts of your response are correct.</text></partiallycorrectfeedback>' . "\n";
        $xml .= '    <incorrectfeedback format="html"><text>That is not correct.</text></incorrectfeedback>' . "\n";
        $xml .= '    <shownumcorrect>1</shownumcorrect>' . "\n";
        foreach ($selectoptions as $groupidx => $choices) {
            $groupnum = isset($xml_placeholder_nums[$groupidx]) ? $xml_placeholder_nums[$groupidx] : ($groupidx + 1);
            foreach ($choices as $choicetext) {
                $xml .= '    <selectoption>' . "\n";
                $xml .= '      <text>' . htmlspecialchars(clean_param($choicetext, PARAM_TEXT), ENT_XML1, 'UTF-8') . '</text>' . "\n";
                $xml .= '      <group>' . $groupnum . '</group>' . "\n";
                $xml .= '    </selectoption>' . "\n";
            }
        }
        $xml .= '  </question>' . "\n";
        return $xml;
    }

    function aiquizmaker_xml_shortanswer_block(array $q): string {
        $questiontext    = isset($q['questionText']) ? $q['questionText'] : '';
        $blanksentence   = isset($q['blankSentence']) ? $q['blankSentence'] : '';
        $acceptedanswers = isset($q['acceptedAnswers']) && is_array($q['acceptedAnswers']) ? $q['acceptedAnswers'] : [];
        $explanation     = isset($q['explanation']) ? $q['explanation'] : '';
        $genfeedback     = isset($q['generalFeedback']) ? $q['generalFeedback'] : $explanation;

        // D7: Filter empty accepted answers before XML output.
        $acceptedanswers = array_values(array_filter($acceptedanswers, fn($a) => trim((string)$a) !== ''));

        // D7 (guard): Return empty string if no valid accepted answers — prevents importing a broken question.
        if (empty($acceptedanswers)) {
            return '';
        }

        $qtexthtml = '<p>' . nl2br(htmlspecialchars($questiontext)) . '</p>';
        if (!empty($blanksentence)) {
            $qtexthtml .= '<p>' . nl2br(htmlspecialchars($blanksentence)) . '</p>';
        }

        $shortname = isset($q['_qnum']) ? 'Q' . (int)$q['_qnum'] : aiquizmaker_shortname($questiontext);
        $xml  = '  <question type="shortanswer">' . "\n";
        $xml .= '    <name><text>' . $shortname . '</text></name>' . "\n";
        $xml .= '    <questiontext format="html"><text>' . aiquizmaker_cdata($qtexthtml) . '</text></questiontext>' . "\n";
        $xml .= '    <generalfeedback format="html"><text>' . aiquizmaker_cdata($genfeedback ? '<p>' . nl2br(htmlspecialchars($genfeedback)) . '</p>' : '') . '</text></generalfeedback>' . "\n";
        $xml .= '    <defaultgrade>1</defaultgrade>' . "\n";
        $xml .= '    <penalty>0</penalty>' . "\n";
        $xml .= '    <usecase>0</usecase>' . "\n";
        foreach ($acceptedanswers as $ans) {
            // D6: Use format="moodle_auto_format" so Moodle compares answer as plain text, not HTML.
            //     format="html" causes Moodle to wrap stored answers in <p> tags before comparison,
            //     meaning student plain-text input never matches.
            // H2: All accepted answers get 'Correct!' feedback — not just the first one.
            $xml .= '    <answer fraction="100" format="moodle_auto_format">' . "\n";
            $xml .= '      <text>' . htmlspecialchars((string)$ans, ENT_XML1, 'UTF-8') . '</text>' . "\n";
            $xml .= '      <feedback format="html"><text>Correct!</text></feedback>' . "\n";
            $xml .= '    </answer>' . "\n";
        }
        $xml .= '  </question>' . "\n";
        return $xml;
    }

    /**
     * Generate Moodle XML block for a description (section heading) question.
     * Description questions display instructional text in a quiz without any answer input.
     */
    function aiquizmaker_xml_description_block(array $q): string {
        $rawtext = isset($q['questionText']) ? $q['questionText'] : '';
        $name    = htmlspecialchars($rawtext, ENT_XML1, 'UTF-8');
        $body    = str_replace(']]>', ']]]]><![CDATA[>', $rawtext);
        $xml = '<question type="description">' . "\n";
        $xml .= '  <name><text>' . $name . '</text></name>' . "\n";
        $xml .= '  <questiontext format="html"><text><![CDATA[<p><strong>' . $body . '</strong></p>]]></text></questiontext>' . "\n";
        $xml .= '  <generalfeedback format="html"><text></text></generalfeedback>' . "\n";
        $xml .= '  <defaultgrade>0</defaultgrade>' . "\n";
        $xml .= '  <penalty>0</penalty>' . "\n";
        $xml .= '  <hidden>0</hidden>' . "\n";
        $xml .= '</question>' . "\n";
        return $xml;
    }

    /**
     * Generate Moodle XML for all question types.
     * Supports essay, multichoice, truefalse, matching, gapselect, shortanswer, description.
     */
    function aiquizmaker_generate_moodle_xml(array $questions): string {
        $xml = '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
        $xml .= '<quiz>' . "\n";
        // N1: Track a separate counter for real questions so description (section heading) questions
        //     do NOT consume a Q number. Descriptions are named from their heading text only.
        //     Real questions are named Q1, Q2, Q3 ... skipping descriptions in the count.
        $xml_real_qnum = 0;
        foreach ($questions as $xml_idx => $q) {
            $qtype = isset($q['moodleQuestionType']) ? $q['moodleQuestionType'] : 'essay';
            if ($qtype === 'description') {
                $q['_qnum'] = null; // no Q number — name uses heading text
            } else {
                $xml_real_qnum++;
                $q['_qnum'] = $xml_real_qnum;
            }
            switch ($qtype) {
                case 'description':
                    $xml .= aiquizmaker_xml_description_block($q);
                    break;
                case 'multichoice':
                    $xml .= aiquizmaker_xml_multichoice_block($q);
                    break;
                case 'truefalse':
                    $xml .= aiquizmaker_xml_truefalse_block($q);
                    break;
                case 'matching':
                    $xml .= aiquizmaker_xml_matching_block($q);
                    break;
                case 'gapselect':
                    $xml .= aiquizmaker_xml_gapselect_block($q);
                    break;
                case 'shortanswer':
                    $xml .= aiquizmaker_xml_shortanswer_block($q);
                    break;
                case 'essay':
                default:
                    $xml .= aiquizmaker_xml_essay_block($q);
                    break;
            }
        }
        $xml .= '</quiz>';
        return $xml;
    }

    /**
     * Get fallback industries list.
     *
     * @return array List of industries.
     */
    function aiquizmaker_get_fallback_industries(): array {
        return [
            'Agriculture, Forestry & Fishing',
            'Mining & Resources',
            'Manufacturing',
            'Electricity, Gas, Water & Waste',
            'Construction',
            'Wholesale Trade',
            'Retail Trade',
            'Accommodation & Food Services',
            'Transport, Postal & Warehousing',
            'Information Media & Telecommunications',
            'Financial & Insurance Services',
            'Rental, Hiring & Real Estate',
            'Professional, Scientific & Technical',
            'Administrative & Support Services',
            'Public Administration & Safety',
            'Education & Training',
            'Health Care & Social Assistance',
            'Community Services',
            'Arts & Recreation Services',
            'Business Services',
            'Other Services',
        ];
    }

    /* ============================================================
       ACTION: GET CREDITS (same pattern as AI Grader)
       ============================================================ */
    if ($action === 'getcredits') {
        // Check configuration first
        if (!$siteid || !$apikey) {
            aiquizmaker_send_response([
                'success' => false,
                'error' => get_string('not_configured', 'local_aiquizmaker'),
                'debug' => [
                    'siteid_set' => !empty($siteid),
                    'apikey_set' => !empty($apikey),
                    'apikey_length' => strlen($apikey)
                ]
            ]);
        }

        $url = 'https://lms-labs.com/api/credits?siteId=' .
                urlencode($siteid) . '&apiKey=' . urlencode($apikey);

        $res = aiquizmaker_fetch($url);

        if (!$res['success']) {
            aiquizmaker_send_response([
                'success' => false,
                'error' => 'Connection to API failed',
                'debug' => [
                    'error' => $res['error'],
                    'httpcode' => $res['httpcode']
                ]
            ]);
        }

        $data = json_decode($res['body'], true);

        if (!$data) {
            aiquizmaker_send_response([
                'success' => false,
                'error' => 'Invalid API response',
                'debug' => [
                    'httpcode' => $res['httpcode'],
                    'body_preview' => substr($res['body'], 0, 200)
                ]
            ]);
        }

        if (isset($data['error'])) {
            aiquizmaker_send_response([
                'success' => false,
                'error' => $data['error'],
                'debug' => [
                    'httpcode' => $res['httpcode'],
                    'apikey_length' => strlen($apikey),
                    'siteid' => $siteid
                ]
            ]);
        }

        // Flexible field mapping (same as AI Grader)
        $credits = null;
        if (isset($data['credits'])) {
            $credits = (int)$data['credits'];
        } elseif (isset($data['balance'])) {
            $credits = (int)$data['balance'];
        } elseif (isset($data['creditsRemaining'])) {
            $credits = (int)$data['creditsRemaining'];
        }

        if ($credits === null) {
            aiquizmaker_send_response([
                'success' => false,
                'error' => 'Credits field missing in response',
                'debug' => ['keys_received' => array_keys($data)]
            ]);
        }

        aiquizmaker_send_response([
            'success' => true,
            'credits' => $credits,
            'buyUrl' => 'https://lms-labs.com/pricing?siteId=' . urlencode($siteid)
        ]);
    }

    /* ============================================================
       ACTION: GET INDUSTRIES
       ============================================================ */
    if ($action === 'getindustries') {
        // Try API first if configured
        if ($siteid && $apikey) {
            $url = 'https://lms-labs.com/api/industries?siteId=' .
                    urlencode($siteid) . '&apiKey=' . urlencode($apikey);

            $res = aiquizmaker_fetch($url);

            if ($res['success']) {
                $data = json_decode($res['body'], true);
                if ($data && isset($data['industries'])) {
                    aiquizmaker_send_response([
                        'success' => true,
                        'industries' => $data['industries']
                    ]);
                }
            }
        }

        // Fallback to static industries
        aiquizmaker_send_response([
            'success' => true,
            'industries' => aiquizmaker_get_fallback_industries()
        ]);
    }

    /* ============================================================
       ACTION: GENERATE QUESTIONS
       ============================================================ */
    if ($action === 'generate') {
        // Check configuration first
        if (!$siteid || !$apikey) {
            aiquizmaker_send_response([
                'success' => false,
                'error' => get_string('not_configured', 'local_aiquizmaker')
            ]);
        }

        // Parse criteria JSON
        $criteriajson = optional_param('criteria', '', PARAM_RAW);
        if (empty($criteriajson)) {
            aiquizmaker_send_response([
                'success' => false,
                'error' => get_string('error_invalid_criteria', 'local_aiquizmaker')
            ]);
        }

        $criteria = json_decode($criteriajson, true);
        if (!$criteria || !is_array($criteria)) {
            aiquizmaker_send_response([
                'success' => false,
                'error' => get_string('error_invalid_criteria', 'local_aiquizmaker')
            ]);
        }

        // Get cmid for direct-to-quiz insertion
        $cmid = optional_param('cmid', 0, PARAM_INT);
        
        // Check if workplace context is enabled
        $workplacecontextenabled = optional_param('workplaceContextEnabled', '0', PARAM_ALPHANUMEXT) === '1';
        
        // Get workplace context parameters (only used if enabled)
        $country = optional_param('country', '', PARAM_TEXT);
        $state = optional_param('state', '', PARAM_TEXT);
        $industry = optional_param('industry', '', PARAM_TEXT);
        $industrydetails = optional_param('industryDetails', '', PARAM_TEXT);
        $jobtitle = optional_param('jobTitle', '', PARAM_TEXT);
        $joblevel = optional_param('jobLevel', '', PARAM_TEXT);
        
        // Get education parameters
        $educationtype = optional_param('educationType', 'vet', PARAM_ALPHA);
        $educationlevel = substr(clean_param(optional_param('educationLevel', '', PARAM_RAW), PARAM_TEXT), 0, 100);
        $questionformatsjson = optional_param('questionFormats', '[]', PARAM_RAW);
        $questionformats = json_decode($questionformatsjson, true);
        if (!is_array($questionformats)) {
            $questionformats = ['short_essay', 'short_answer', 'scenario'];
        }

        $moodlequestiontypesjson = optional_param('moodleQuestionTypes', '["essay"]', PARAM_RAW);
        $moodlequestiontypes = json_decode($moodlequestiontypesjson, true);
        if (!is_array($moodlequestiontypes) || count($moodlequestiontypes) === 0) {
            $moodlequestiontypes = ['essay'];
        }
        // Whitelist allowed types
        $allowedtypes = ['essay', 'multichoice', 'truefalse', 'matching', 'gapselect', 'shortanswer'];
        $moodlequestiontypes = array_values(array_filter($moodlequestiontypes, function ($t) use ($allowedtypes) {
            return in_array($t, $allowedtypes);
        }));
        if (empty($moodlequestiontypes)) {
            $moodlequestiontypes = ['essay'];
        }

        // Get self-marking question styles
        $selfmarkingstylesjson = optional_param('selfMarkingStyles', '["scenario","knowledge_check"]', PARAM_RAW);
        $selfmarkingstyles = json_decode($selfmarkingstylesjson, true);
        if (!is_array($selfmarkingstyles) || count($selfmarkingstyles) === 0) {
            $selfmarkingstyles = ['scenario', 'knowledge_check'];
        }
        $allowedstyles = ['scenario', 'knowledge_check', 'procedure', 'terminology', 'identification'];
        $selfmarkingstyles = array_values(array_filter($selfmarkingstyles, function ($s) use ($allowedstyles) {
            return in_array($s, $allowedstyles);
        }));
        if (empty($selfmarkingstyles)) {
            $selfmarkingstyles = ['scenario', 'knowledge_check'];
        }

        // Get extra AI instructions from settings
        $extrainstructions = optional_param('extraInstructions', '', PARAM_RAW);
        // Sanitize extra instructions (allow multiline text up to 5000 chars)
        $extrainstructions = substr(clean_param($extrainstructions, PARAM_TEXT), 0, 5000);

        // Get pasted learning content (source material for AI context)
        $pastedcontent = optional_param('pastedContent', '', PARAM_RAW);
        $pastedcontent = substr(clean_param($pastedcontent, PARAM_TEXT), 0, 20000);
        
        // Get language parameter - prefer explicit parameter, fallback to user's Moodle language
        $language = optional_param('language', '', PARAM_TEXT);
        if (empty($language)) {
            // Auto-detect from Moodle user's current language setting
            $language = current_language();
            // Convert Moodle language codes to standard format (e.g., 'en_au' -> 'en-AU')
            $language = str_replace('_', '-', $language);
        }

        // Validate workplace context fields only if enabled
        if ($workplacecontextenabled) {
            if (empty($country) || empty($industry) || empty($jobtitle) || empty($joblevel)) {
                aiquizmaker_send_response([
                    'success' => false,
                    'error' => get_string('error_missing_fields_message', 'local_aiquizmaker')
                ]);
            }
        } else {
            // Clear workplace context fields when disabled
            $country = '';
            $state = '';
            $industry = '';
            $industrydetails = '';
            $jobtitle = '';
            $joblevel = '';
        }

        // Transform criteria for API
        $apicriteria = [];
        foreach ($criteria as $c) {
            $apicriteria[] = [
                'text' => clean_param($c['text'], PARAM_TEXT),
                'questionsCount' => (int) $c['count'],
            ];
        }

        // Build payload
        $payload = [
            'siteId' => $siteid,
            'apiKey' => $apikey,
            'criteria' => $apicriteria,
            'country' => $country,
            'state' => $state,
            'industry' => $industry,
            'industryDetails' => $industrydetails,
            'jobTitle' => $jobtitle,
            'jobLevel' => $joblevel,
            'educationType' => $educationtype,
            'educationLevel' => $educationlevel,
            'questionFormats' => $questionformats,
            'moodleQuestionTypes' => $moodlequestiontypes,
            'selfMarkingStyles' => $selfmarkingstyles,
            'extraInstructions' => $extrainstructions,
            'pastedContent' => $pastedcontent,
            'language' => $language,
            'workplaceContextEnabled' => $workplacecontextenabled,
        ];

        $res = aiquizmaker_fetch('https://lms-labs.com/api/generate-essays', true, $payload);

        if (!$res['success']) {
            aiquizmaker_send_response([
                'success' => false,
                'error' => 'Connection failed',
                'debug' => [
                    'error' => $res['error'],
                    'httpcode' => $res['httpcode']
                ]
            ]);
        }

        $data = json_decode($res['body'], true);

        if (!$data) {
            aiquizmaker_send_response([
                'success' => false,
                'error' => 'Invalid response from API',
                'debug' => [
                    'httpcode' => $res['httpcode'],
                    'body_preview' => substr($res['body'], 0, 200)
                ]
            ]);
        }

        // Check for insufficient credits
        if (isset($data['ok']) && !$data['ok']) {
            if (isset($data['error']) && $data['error'] === 'INSUFFICIENT_CREDITS') {
                aiquizmaker_send_response([
                    'success' => false,
                    'error' => 'INSUFFICIENT_CREDITS',
                    'credits' => isset($data['credits']) ? $data['credits'] : 0,
                    'required' => isset($data['required']) ? $data['required'] : 0,
                    'buyUrl' => isset($data['buyUrl']) ? $data['buyUrl'] : 'https://lms-labs.com/pricing'
                ]);
            }
            aiquizmaker_send_response([
                'success' => false,
                'error' => isset($data['error']) ? $data['error'] : 'Generation failed',
                'message' => isset($data['message']) ? $data['message'] : ''
            ]);
        }

        // Success - process questions
        if (isset($data['ok']) && $data['ok'] && isset($data['questions'])) {
            $questions = $data['questions'];

            // Helper function to safely convert any value to string for format_text
            $tostring = function ($val) {
                if (is_null($val)) {
                    return '';
                }
                if (is_string($val)) {
                    return $val;
                }
                if (is_array($val)) {
                    // If it's an array, convert to JSON or concatenate
                    // Check for common nested structures like {text: "..."} or {content: "..."}
                    if (isset($val['text'])) {
                        return is_string($val['text']) ? $val['text'] : json_encode($val['text']);
                    }
                    if (isset($val['content'])) {
                        return is_string($val['content']) ? $val['content'] : json_encode($val['content']);
                    }
                    // Fallback: join array values or encode as JSON
                    if (count($val) > 0 && isset($val[0]) && is_string($val[0])) {
                        return implode("\n\n", $val);
                    }
                    return json_encode($val, JSON_PRETTY_PRINT);
                }
                if (is_object($val)) {
                    if (isset($val->text)) {
                        return is_string($val->text) ? $val->text : json_encode($val->text);
                    }
                    if (isset($val->content)) {
                        return is_string($val->content) ? $val->content : json_encode($val->content);
                    }
                    return json_encode($val, JSON_PRETTY_PRINT);
                }
                return (string)$val;
            };

            // Sanitize question content.
            // E1-E5: For self-marking types, use clean_param(PARAM_TEXT) not format_text(FORMAT_PLAIN).
            // format_text(FORMAT_PLAIN) adds HTML <br> tags and URL links to plain text. Those HTML strings
            // then reach DB creators that call htmlspecialchars() or nl2br() again, producing double-encoded
            // output like visible &lt;br&gt; tags in the rendered question body.
            // clean_param(PARAM_TEXT) simply strips all HTML and returns safe plain text — which is what
            // DB creators expect before they apply their own HTML wrapping.
            // Essay fields keep format_text since essay responses display as HTML in Moodle's essay renderer.
            foreach ($questions as &$q) {
                // E8: Use PARAM_ALPHANUMEXT consistently with addtoquiz/createquestions actions.
                $qtype = isset($q['moodleQuestionType']) ? clean_param($q['moodleQuestionType'], PARAM_ALPHANUMEXT) : 'essay';
                $q['moodleQuestionType'] = $qtype;
                // E1, E5: Plain-text fields — use clean_param so DB creators get unprocessed text.
                $q['questionText']      = clean_param($tostring($q['questionText'] ?? ''), PARAM_TEXT);
                $q['criteriaReference'] = clean_param($tostring($q['criteriaReference'] ?? ''), PARAM_TEXT);

                if ($qtype === 'multichoice') {
                    $q['explanation']    = clean_param($tostring($q['explanation'] ?? ''), PARAM_TEXT);
                    $q['generalFeedback'] = clean_param($tostring($q['generalFeedback'] ?? ''), PARAM_TEXT);
                    if (isset($q['choices']) && is_array($q['choices'])) {
                        foreach ($q['choices'] as &$choice) {
                            // E2: clean_param instead of format_text to avoid double-encoding in MCQ DB creator.
                            $choice['text']     = clean_param($tostring($choice['text'] ?? ''), PARAM_TEXT);
                            $choice['feedback'] = clean_param($tostring($choice['feedback'] ?? ''), PARAM_TEXT);
                            $choice['isCorrect'] = !empty($choice['isCorrect']);
                        }
                        unset($choice);
                    }
                } else if ($qtype === 'truefalse') {
                    $q['explanation']        = clean_param($tostring($q['explanation'] ?? ''), PARAM_TEXT);
                    $q['generalFeedback']    = clean_param($tostring($q['generalFeedback'] ?? ''), PARAM_TEXT);
                    $q['trueAnswerFeedback']  = clean_param($tostring($q['trueAnswerFeedback'] ?? ''), PARAM_TEXT);
                    $q['falseAnswerFeedback'] = clean_param($tostring($q['falseAnswerFeedback'] ?? ''), PARAM_TEXT);
                    $q['correctAnswer']       = !empty($q['correctAnswer']);
                    $q['totalMarks'] = 1;
                } else if ($qtype === 'matching') {
                    $q['explanation']    = clean_param($tostring($q['explanation'] ?? ''), PARAM_TEXT);
                    $q['generalFeedback'] = clean_param($tostring($q['generalFeedback'] ?? ''), PARAM_TEXT);
                    if (isset($q['matchPairs']) && is_array($q['matchPairs'])) {
                        foreach ($q['matchPairs'] as &$pair) {
                            // E3: clean_param to avoid double-encoding in matching DB creator and XML builder.
                            $pair['subquestion'] = clean_param($tostring($pair['subquestion'] ?? ''), PARAM_TEXT);
                            $pair['subanswer']   = clean_param($tostring($pair['subanswer'] ?? ''), PARAM_TEXT);
                        }
                        unset($pair);
                        $q['totalMarks'] = count($q['matchPairs']);
                    }
                } else if ($qtype === 'gapselect') {
                    // E4: questionText must stay plain for [[n]] placeholders — clean_param already applied above.
                    $q['generalFeedback'] = clean_param($tostring($q['generalFeedback'] ?? ''), PARAM_TEXT);
                    if (isset($q['selectOptions']) && is_array($q['selectOptions'])) {
                        foreach ($q['selectOptions'] as &$group) {
                            if (is_array($group)) {
                                foreach ($group as &$choice) {
                                    $choice = clean_param($tostring($choice), PARAM_TEXT);
                                }
                                unset($choice);
                            }
                        }
                        unset($group);
                        // BUG-QM-GAPSELECT-DUPES (v3.16.79): Per-blank deduplication. The AI sometimes
                        // returns the same word twice within a single blank's options (correct answer
                        // repeated, distractor repeated, or correct answer also appearing as a distractor).
                        // Dedupe each group case-insensitively, keeping the first occurrence so the
                        // correct-answer-at-index-0 convention is preserved.
                        $q['selectOptions'] = aiquizmaker_dedupe_gapselect_all($q['selectOptions']);
                        $q['totalMarks'] = count($q['selectOptions']);
                    } else {
                        $q['totalMarks'] = 1;
                    }
                } else if ($qtype === 'shortanswer') {
                    $q['explanation']    = clean_param($tostring($q['explanation'] ?? ''), PARAM_TEXT);
                    $q['generalFeedback'] = clean_param($tostring($q['generalFeedback'] ?? ''), PARAM_TEXT);
                    if (isset($q['acceptedAnswers']) && is_array($q['acceptedAnswers'])) {
                        foreach ($q['acceptedAnswers'] as &$ans) {
                            $ans = clean_param($tostring($ans), PARAM_TEXT);
                        }
                        unset($ans);
                    }
                    $q['totalMarks'] = 1;
                } else {
                    // Essay type — format_text is appropriate here since essay responses are displayed as HTML.
                    if (isset($q['sampleAnswer']) && is_array($q['sampleAnswer']) && isset($q['sampleAnswer'][0])) {
                        foreach ($q['sampleAnswer'] as &$sa) {
                            if (isset($sa['response'])) {
                                $sa['response'] = format_text($tostring($sa['response'] ?? ''), FORMAT_PLAIN, ['trusted' => false]);
                            }
                        }
                        unset($sa);
                    } else {
                        $q['sampleAnswer'] = format_text($tostring($q['sampleAnswer'] ?? ''), FORMAT_PLAIN, ['trusted' => false]);
                    }
                    if (isset($q['rubric'])) {
                        if (is_array($q['rubric']) && isset($q['rubric'][0])) {
                            foreach ($q['rubric'] as &$r) {
                                $r['description'] = format_text($tostring($r['description'] ?? ''), FORMAT_PLAIN, ['trusted' => false]);
                            }
                            unset($r);
                        } else {
                            $q['rubric']['hazard']  = format_text($tostring($q['rubric']['hazard'] ?? ''), FORMAT_PLAIN, ['trusted' => false]);
                            $q['rubric']['example'] = format_text($tostring($q['rubric']['example'] ?? ''), FORMAT_PLAIN, ['trusted' => false]);
                            $q['rubric']['control'] = format_text($tostring($q['rubric']['control'] ?? ''), FORMAT_PLAIN, ['trusted' => false]);
                        }
                    }
                }
            }
            unset($q);

            $moodlexml = aiquizmaker_generate_moodle_xml($questions);
            
            // Get quiz name if in quiz context (for display purposes only - no auto-add)
            $quizname = '';
            if ($cmid > 0) {
                try {
                    list($course, $cm) = get_course_and_cm_from_cmid($cmid, 'quiz');
                    $quizname = $cm->name;
                } catch (\Throwable $e) {
                    // Ignore - just won't have quiz name
                }
            }

            // Return questions for preview - user must click "Add to Quiz" to add them
            aiquizmaker_send_response([
                'success' => true,
                'questions' => $questions,
                'moodleXml' => $moodlexml,
                'credits' => isset($data['credits']) ? $data['credits'] : null,
                'questionsGenerated' => isset($data['questionsGenerated']) ? $data['questionsGenerated'] : count($questions),
                'addedToQuiz' => false,
                'quizName' => $quizname,
                'hasQuizContext' => ($cmid > 0)
            ]);
        }

        // Unknown response format
        aiquizmaker_send_response([
            'success' => false,
            'error' => 'Unexpected response format',
            'debug' => ['keys' => array_keys($data)]
        ]);
    }

    /* ============================================================
       ACTION: GENERATE FROM OWN QUESTIONS
       Accepts user-provided question texts and generates rubric,
       sample answer, and grader info for each.
       ============================================================ */
    if ($action === 'generatefromquestions') {
        // Check configuration first
        if (!$siteid || !$apikey) {
            aiquizmaker_send_response([
                'success' => false,
                'error' => get_string('not_configured', 'local_aiquizmaker')
            ]);
        }

        // Parse questions JSON array (only real question lines — headings stripped by JS)
        $questionsjson = optional_param('questions', '', PARAM_RAW);
        if (empty($questionsjson)) {
            aiquizmaker_send_response([
                'success' => false,
                'error' => 'No questions provided'
            ]);
        }

        $rawquestions = json_decode($questionsjson, true);
        if (!$rawquestions || !is_array($rawquestions) || count($rawquestions) === 0) {
            aiquizmaker_send_response([
                'success' => false,
                'error' => 'Invalid questions format'
            ]);
        }

        // Parse ordered layout sent by JS: [{type:'heading',text:'...'} | {type:'question'}]
        // This lets PHP reconstruct the full XML (with description cards) in the correct order.
        $orderedlayoutjson = optional_param('orderedLayout', '', PARAM_RAW);
        $orderedlayout = [];
        if (!empty($orderedlayoutjson)) {
            $decoded = json_decode($orderedlayoutjson, true);
            if (is_array($decoded)) {
                foreach ($decoded as $item) {
                    if (isset($item['type']) && $item['type'] === 'heading') {
                        $orderedlayout[] = [
                            'type' => 'heading',
                            'text' => substr(clean_param(isset($item['text']) ? (string)$item['text'] : '', PARAM_TEXT), 0, 500),
                        ];
                    } elseif (isset($item['type']) && $item['type'] === 'question') {
                        $orderedlayout[] = ['type' => 'question'];
                    }
                }
            }
        }

        // Sanitize questions — supports plain strings (simple mode) and {text, modelAnswer} objects (model response mode).
        $questions = [];
        foreach ($rawquestions as $q) {
            if (is_array($q)) {
                $text = substr(clean_param(isset($q['text']) ? (string)$q['text'] : '', PARAM_TEXT), 0, 2000);
                $modelanswer = substr(clean_param(isset($q['modelAnswer']) ? (string)$q['modelAnswer'] : '', PARAM_TEXT), 0, 5000);
                if (!empty(trim($text))) {
                    $questions[] = ['text' => $text, 'modelAnswer' => $modelanswer];
                }
            } else {
                $cleaned = substr(clean_param((string)$q, PARAM_TEXT), 0, 2000);
                if (!empty(trim($cleaned))) {
                    $questions[] = ['text' => $cleaned, 'modelAnswer' => ''];
                }
            }
        }

        if (empty($questions)) {
            aiquizmaker_send_response([
                'success' => false,
                'error' => 'No valid questions after sanitisation'
            ]);
        }

        // Get cmid for direct-to-quiz insertion
        $cmid = optional_param('cmid', 0, PARAM_INT);

        // Context parameters
        $workplacecontextenabled = optional_param('workplaceContextEnabled', '0', PARAM_ALPHANUMEXT) === '1';
        $country = optional_param('country', '', PARAM_TEXT);
        $state = optional_param('state', '', PARAM_TEXT);
        $industry = optional_param('industry', '', PARAM_TEXT);
        $industrydetails = optional_param('industryDetails', '', PARAM_TEXT);
        $jobtitle = optional_param('jobTitle', '', PARAM_TEXT);
        $joblevel = optional_param('jobLevel', '', PARAM_TEXT);
        $educationtype = optional_param('educationType', 'vet', PARAM_ALPHA);
        $educationlevel = substr(clean_param(optional_param('educationLevel', '', PARAM_RAW), PARAM_TEXT), 0, 100);
        $extrainstructions = optional_param('extraInstructions', '', PARAM_RAW);
        $extrainstructions = substr(clean_param($extrainstructions, PARAM_TEXT), 0, 5000);
        $pastedcontent = optional_param('pastedContent', '', PARAM_RAW);
        $pastedcontent = substr(clean_param($pastedcontent, PARAM_TEXT), 0, 20000);

        // Language
        $language = optional_param('language', '', PARAM_TEXT);
        if (empty($language)) {
            $language = str_replace('_', '-', current_language());
        }

        // Moodle question types (which types to generate — e.g. essay, multichoice, truefalse, matching, shortanswer)
        $moodlequestiontypesjson = optional_param('moodleQuestionTypes', '["essay"]', PARAM_RAW);
        $moodlequestiontypes = json_decode($moodlequestiontypesjson, true);
        if (!is_array($moodlequestiontypes) || count($moodlequestiontypes) === 0) {
            $moodlequestiontypes = ['essay'];
        }
        $allowedtypes = ['essay', 'multichoice', 'truefalse', 'matching', 'gapselect', 'shortanswer'];
        $moodlequestiontypes = array_values(array_filter($moodlequestiontypes, function ($t) use ($allowedtypes) {
            return in_array($t, $allowedtypes);
        }));
        if (empty($moodlequestiontypes)) {
            $moodlequestiontypes = ['essay'];
        }

        // Get self-marking question styles
        $selfmarkingstylesjson2 = optional_param('selfMarkingStyles', '["scenario","knowledge_check"]', PARAM_RAW);
        $selfmarkingstyles2 = json_decode($selfmarkingstylesjson2, true);
        if (!is_array($selfmarkingstyles2) || count($selfmarkingstyles2) === 0) {
            $selfmarkingstyles2 = ['scenario', 'knowledge_check'];
        }
        $allowedstyles2 = ['scenario', 'knowledge_check', 'procedure', 'terminology', 'identification'];
        $selfmarkingstyles2 = array_values(array_filter($selfmarkingstyles2, function ($s) use ($allowedstyles2) {
            return in_array($s, $allowedstyles2);
        }));
        if (empty($selfmarkingstyles2)) {
            $selfmarkingstyles2 = ['scenario', 'knowledge_check'];
        }

        // Clear workplace context if disabled
        if (!$workplacecontextenabled) {
            $country = '';
            $state = '';
            $industry = '';
            $industrydetails = '';
            $jobtitle = '';
            $joblevel = '';
        }

        // Build payload for AI server
        $payload = [
            'siteId' => $siteid,
            'apiKey' => $apikey,
            'questions' => $questions,
            'country' => $country,
            'state' => $state,
            'industry' => $industry,
            'industryDetails' => $industrydetails,
            'jobTitle' => $jobtitle,
            'jobLevel' => $joblevel,
            'educationType' => $educationtype,
            'educationLevel' => $educationlevel,
            'extraInstructions' => $extrainstructions,
            'pastedContent' => $pastedcontent,
            'language' => $language,
            'moodleQuestionTypes' => $moodlequestiontypes,
            'selfMarkingStyles' => $selfmarkingstyles2,
            'workplaceContextEnabled' => $workplacecontextenabled,
        ];

        $res = aiquizmaker_fetch('https://lms-labs.com/api/generate-from-questions', true, $payload);

        if (!$res['success']) {
            aiquizmaker_send_response([
                'success' => false,
                'error' => 'Connection failed',
                'debug' => ['error' => $res['error'], 'httpcode' => $res['httpcode']]
            ]);
        }

        $data = json_decode($res['body'], true);

        if (!$data) {
            aiquizmaker_send_response([
                'success' => false,
                'error' => 'Invalid response from API',
                'debug' => ['httpcode' => $res['httpcode'], 'body_preview' => substr($res['body'], 0, 200)]
            ]);
        }

        // Check for insufficient credits
        if (isset($data['ok']) && !$data['ok']) {
            if (isset($data['error']) && $data['error'] === 'INSUFFICIENT_CREDITS') {
                aiquizmaker_send_response([
                    'success' => false,
                    'error' => 'INSUFFICIENT_CREDITS',
                    'credits' => isset($data['credits']) ? $data['credits'] : 0,
                    'required' => isset($data['required']) ? $data['required'] : 0,
                    'buyUrl' => isset($data['buyUrl']) ? $data['buyUrl'] : 'https://lms-labs.com/pricing'
                ]);
            }
            aiquizmaker_send_response([
                'success' => false,
                'error' => isset($data['error']) ? $data['error'] : 'Generation failed',
                'message' => isset($data['message']) ? $data['message'] : ''
            ]);
        }

        // Success - process questions (reuse same sanitisation as generate action)
        if (isset($data['ok']) && $data['ok'] && isset($data['questions'])) {
            $generatedquestions = $data['questions'];

            $tostring = function ($val) {
                if (is_null($val)) return '';
                if (is_string($val)) return $val;
                if (is_array($val)) {
                    if (isset($val['text'])) return is_string($val['text']) ? $val['text'] : json_encode($val['text']);
                    if (isset($val['content'])) return is_string($val['content']) ? $val['content'] : json_encode($val['content']);
                    if (count($val) > 0 && isset($val[0]) && is_string($val[0])) return implode("\n\n", $val);
                    return json_encode($val, JSON_PRETTY_PRINT);
                }
                if (is_object($val)) {
                    if (isset($val->text)) return is_string($val->text) ? $val->text : json_encode($val->text);
                    if (isset($val->content)) return is_string($val->content) ? $val->content : json_encode($val->content);
                    return json_encode($val, JSON_PRETTY_PRINT);
                }
                return (string)$val;
            };

            // Sanitise each generated question — honour the moodleQuestionType returned by the server.
            $allowedtypes_gfq = ['essay', 'multichoice', 'truefalse', 'matching', 'gapselect', 'shortanswer', 'description'];
            foreach ($generatedquestions as &$q) {
                $gfq_qtype = clean_param($q['moodleQuestionType'] ?? 'essay', PARAM_ALPHANUMEXT);
                if (!in_array($gfq_qtype, $allowedtypes_gfq)) {
                    $gfq_qtype = 'essay';
                }
                $q['moodleQuestionType'] = $gfq_qtype;

                // E1-E5: clean_param(PARAM_TEXT) for self-marking types — see generate action comment for rationale.
                $q['questionText']      = clean_param($tostring($q['questionText'] ?? ''), PARAM_TEXT);
                $q['criteriaReference'] = clean_param($tostring($q['criteriaReference'] ?? ''), PARAM_TEXT);
                // GFQ-CR: If the AI did not return a criteriaReference (common for non-essay types),
                // fall back to the question text itself so the teacher can see what was generated.
                // Exceptions:
                // - truefalse: the T/F statement IS the question, not a competency criterion.
                //   Using it as criteriaReference makes the card show a misleading duplicate.
                // - matching: the matching questionText is a generic instruction ("Match each term…"),
                //   not a competency criterion. The AI should now return a proper criteriaReference;
                //   if empty, leave it blank rather than showing the instruction text.
                // In both cases the JS hides the criteria-ref div when criteriaReference is empty.
                if (empty($q['criteriaReference']) && $gfq_qtype !== 'truefalse' && $gfq_qtype !== 'matching') {
                    $q['criteriaReference'] = $q['questionText'];
                }

                if ($gfq_qtype === 'multichoice') {
                    $q['explanation']    = clean_param($tostring($q['explanation'] ?? ''), PARAM_TEXT);
                    $q['generalFeedback'] = clean_param($tostring($q['generalFeedback'] ?? ''), PARAM_TEXT);
                    if (isset($q['choices']) && is_array($q['choices'])) {
                        foreach ($q['choices'] as &$ch) {
                            $ch['text']     = clean_param($tostring($ch['text'] ?? ''), PARAM_TEXT);
                            $ch['feedback'] = clean_param($tostring($ch['feedback'] ?? ''), PARAM_TEXT);
                            $ch['isCorrect'] = !empty($ch['isCorrect']);
                        }
                        unset($ch);
                    }
                } else if ($gfq_qtype === 'truefalse') {
                    $q['explanation']        = clean_param($tostring($q['explanation'] ?? ''), PARAM_TEXT);
                    $q['trueAnswerFeedback']  = clean_param($tostring($q['trueAnswerFeedback'] ?? ''), PARAM_TEXT);
                    $q['falseAnswerFeedback'] = clean_param($tostring($q['falseAnswerFeedback'] ?? ''), PARAM_TEXT);
                    $q['correctAnswer']       = !empty($q['correctAnswer']);
                    $q['totalMarks'] = 1;
                } else if ($gfq_qtype === 'matching') {
                    $q['explanation']    = clean_param($tostring($q['explanation'] ?? ''), PARAM_TEXT);
                    $q['generalFeedback'] = clean_param($tostring($q['generalFeedback'] ?? ''), PARAM_TEXT);
                    if (isset($q['matchPairs']) && is_array($q['matchPairs'])) {
                        foreach ($q['matchPairs'] as &$pair) {
                            $pair['subquestion'] = clean_param($tostring($pair['subquestion'] ?? ''), PARAM_TEXT);
                            $pair['subanswer']   = clean_param($tostring($pair['subanswer'] ?? ''), PARAM_TEXT);
                        }
                        unset($pair);
                        $q['totalMarks'] = count($q['matchPairs']);
                    } else {
                        $q['totalMarks'] = 3;
                    }
                } else if ($gfq_qtype === 'gapselect') {
                    $q['generalFeedback'] = clean_param($tostring($q['generalFeedback'] ?? ''), PARAM_TEXT);
                    if (isset($q['selectOptions']) && is_array($q['selectOptions'])) {
                        foreach ($q['selectOptions'] as &$grp) {
                            if (is_array($grp)) {
                                foreach ($grp as &$opt) {
                                    $opt = clean_param($tostring($opt), PARAM_TEXT);
                                }
                                unset($opt);
                            }
                        }
                        unset($grp);
                        // BUG-QM-GAPSELECT-DUPES (v3.16.79): Same per-blank deduplication as the
                        // criteria-mode codepath above. Applies to "Use My Own Questions" mode.
                        $q['selectOptions'] = aiquizmaker_dedupe_gapselect_all($q['selectOptions']);
                        $q['totalMarks'] = count($q['selectOptions']);
                    } else {
                        $q['totalMarks'] = 1;
                    }
                } else if ($gfq_qtype === 'shortanswer') {
                    $q['explanation']    = clean_param($tostring($q['explanation'] ?? ''), PARAM_TEXT);
                    $q['generalFeedback'] = clean_param($tostring($q['generalFeedback'] ?? ''), PARAM_TEXT);
                    if (isset($q['acceptedAnswers']) && is_array($q['acceptedAnswers'])) {
                        foreach ($q['acceptedAnswers'] as &$ans) {
                            $ans = clean_param($tostring($ans), PARAM_TEXT);
                        }
                        unset($ans);
                    }
                    $q['totalMarks'] = 1;
                } else {
                    // Essay: format_text is appropriate since essay responses render as HTML.
                    if (isset($q['sampleAnswer']) && is_array($q['sampleAnswer']) && isset($q['sampleAnswer'][0])) {
                        foreach ($q['sampleAnswer'] as &$sa) {
                            if (isset($sa['response'])) {
                                $sa['response'] = format_text($tostring($sa['response'] ?? ''), FORMAT_PLAIN, ['trusted' => false]);
                            }
                        }
                        unset($sa);
                    } else {
                        $q['sampleAnswer'] = format_text($tostring($q['sampleAnswer'] ?? ''), FORMAT_PLAIN, ['trusted' => false]);
                    }
                    if (isset($q['rubric']) && is_array($q['rubric']) && isset($q['rubric'][0])) {
                        foreach ($q['rubric'] as &$r) {
                            $r['description'] = format_text($tostring($r['description'] ?? ''), FORMAT_PLAIN, ['trusted' => false]);
                        }
                        unset($r);
                    }
                }
            }
            unset($q);

            // If an ordered layout was sent, weave description cards into the questions array
            // at their original positions before generating the XML.
            if (!empty($orderedlayout)) {
                $mergedforxml = [];
                $qi = 0;
                foreach ($orderedlayout as $layoutitem) {
                    if ($layoutitem['type'] === 'heading') {
                        $mergedforxml[] = [
                            'moodleQuestionType' => 'description',
                            'questionText' => $layoutitem['text'],
                        ];
                    } else {
                        if (isset($generatedquestions[$qi])) {
                            $mergedforxml[] = $generatedquestions[$qi];
                        }
                        $qi++;
                    }
                }
                $moodlexml = aiquizmaker_generate_moodle_xml($mergedforxml);
            } else {
                $moodlexml = aiquizmaker_generate_moodle_xml($generatedquestions);
            }

            // Get quiz name if in quiz context
            $quizname = '';
            if ($cmid > 0) {
                try {
                    list($course, $cm) = get_course_and_cm_from_cmid($cmid, 'quiz');
                    $quizname = $cm->name;
                } catch (\Throwable $e) {
                    // Ignore
                }
            }

            aiquizmaker_send_response([
                'success' => true,
                'questions' => $generatedquestions,
                'moodleXml' => $moodlexml,
                'credits' => isset($data['credits']) ? $data['credits'] : null,
                'questionsGenerated' => isset($data['questionsGenerated']) ? $data['questionsGenerated'] : count($generatedquestions),
                'addedToQuiz' => false,
                'quizName' => $quizname,
                'hasQuizContext' => ($cmid > 0)
            ]);
        }

        aiquizmaker_send_response([
            'success' => false,
            'error' => 'Unexpected response format',
            'debug' => ['keys' => array_keys($data)]
        ]);
    }

    /* ============================================================
       ACTION: GET QUESTION CATEGORIES (for direct creation)
       ============================================================ */
    if ($action === 'getcategories') {
        global $DB, $USER;
        
        $categories = [];
        
        // Get all courses the user can access
        $courses = enrol_get_my_courses();
        
        foreach ($courses as $course) {
            $coursecontext = context_course::instance($course->id);
            
            // Check if user can add questions in this course
            if (!has_capability('moodle/question:add', $coursecontext)) {
                continue;
            }
            
            // Get question categories for this course context
            $coursecats = $DB->get_records('question_categories', 
                ['contextid' => $coursecontext->id], 
                'sortorder, name'
            );
            
            foreach ($coursecats as $cat) {
                $categories[] = [
                    'id' => $cat->id,
                    'name' => $cat->name,
                    'course' => $course->shortname,
                    'courseid' => $course->id,
                    'contextid' => $coursecontext->id,
                ];
            }
        }
        
        // Also check system context if user has permission
        $systemcontext = context_system::instance();
        if (has_capability('moodle/question:add', $systemcontext)) {
            $systemcats = $DB->get_records('question_categories', 
                ['contextid' => $systemcontext->id], 
                'sortorder, name'
            );
            foreach ($systemcats as $cat) {
                $categories[] = [
                    'id' => $cat->id,
                    'name' => $cat->name,
                    'course' => 'System',
                    'courseid' => 0,
                    'contextid' => $systemcontext->id,
                ];
            }
        }
        
        aiquizmaker_send_response([
            'success' => true,
            'categories' => $categories
        ]);
    }

    /* ============================================================
       ACTION: CREATE QUESTIONS IN MOODLE QUESTION BANK
       ============================================================ */
    if ($action === 'createquestions') {
        global $DB, $USER;
        
        $categoryid = required_param('categoryid', PARAM_INT);
        $questionsjson = required_param('questions', PARAM_RAW);
        
        $questions = json_decode($questionsjson, true);
        if (!$questions || !is_array($questions)) {
            aiquizmaker_send_response([
                'success' => false,
                'error' => 'Invalid questions data'
            ]);
        }
        
        // Validate category exists
        $category = $DB->get_record('question_categories', ['id' => $categoryid]);
        if (!$category) {
            aiquizmaker_send_response([
                'success' => false,
                'error' => 'Question category not found'
            ]);
        }
        
        // Get the context for this category and check capabilities
        $catcontext = context::instance_by_id($category->contextid);
        
        // Check required capabilities: add questions and use this category
        if (!has_capability('moodle/question:add', $catcontext)) {
            aiquizmaker_send_response([
                'success' => false,
                'error' => 'You do not have permission to add questions in this context'
            ]);
        }
        
        // For course contexts, verify user is enrolled or has proper access
        if ($catcontext->contextlevel == CONTEXT_COURSE) {
            $courseid = $catcontext->instanceid;
            $coursecontext = context_course::instance($courseid);
            if (!is_enrolled($coursecontext, $USER) && !has_capability('moodle/course:manageactivities', $coursecontext)) {
                aiquizmaker_send_response([
                    'success' => false,
                    'error' => 'You are not enrolled in this course'
                ]);
            }
        }
        
        $createdids = [];
        $errors     = [];
        $skipped    = [];

        $allowedtypes_create = ['essay', 'multichoice', 'truefalse', 'matching', 'gapselect', 'shortanswer', 'description'];
        // N1: Separate real-question counter — descriptions don't consume a Q number.
        $create_real_qnum = 0;
        foreach ($questions as $idx => $q) {
            // Whitelist question type before routing.
            $q_type = clean_param($q['moodleQuestionType'] ?? 'essay', PARAM_ALPHANUMEXT);
            if (!in_array($q_type, $allowedtypes_create)) {
                $q_type = 'essay';
            }
            $q['moodleQuestionType'] = $q_type;
            // N1: Description (section heading) questions are NOT numbered — their name comes
            //     from the heading text. All other question types get Q1, Q2, Q3 ... counting
            //     only non-description slots, so numbering is contiguous across headings.
            if ($q_type === 'description') {
                $q['_qnum'] = null;
            } else {
                $create_real_qnum++;
                $q['_qnum'] = $create_real_qnum;
            }
            // G1: Do NOT use start_delegated_transaction() per-question — Moodle's rollback()
            //     re-throws the exception, turning a per-question failure into a full abort.
            //     aiquizmaker_create_question() already handles its own internal errors and
            //     returns false on failure, which is sufficient isolation.
            try {
                $questionid = aiquizmaker_create_question($q, $categoryid);
                if ($questionid) {
                    $createdids[] = $questionid;
                } else {
                    // G2: aiquizmaker_create_question returning false means the question was skipped
                    //     (e.g. empty choices, fewer than 2 match pairs, no accepted answers).
                    $skipped[] = 'Question ' . ($idx + 1) . ' (' . $q_type . '): skipped — invalid or empty content';
                }
            } catch (\Throwable $e) {
                // G3: Catch \Throwable (not just Exception) to handle PHP 7+ Error objects too.
                $errors[] = 'Question ' . ($idx + 1) . ': ' . $e->getMessage();
            }
        }
        
        if (count($createdids) > 0) {
            aiquizmaker_send_response([
                'success' => true,
                'created' => count($createdids),
                'questionIds' => $createdids,
                // F2: Surface skip reasons and errors to the caller.
                'skipped' => $skipped,
                'errors'  => $errors
            ]);
        } else {
            aiquizmaker_send_response([
                'success' => false,
                'error'   => 'Failed to create any questions',
                'skipped' => $skipped,
                'errors'  => $errors
            ]);
        }
    }

    /* ============================================================
       ACTION: GET SETTINGS (extra instructions)
       ============================================================ */
    if ($action === 'getsettings') {
        if (!$siteid || !$apikey) {
            echo json_encode(['ok' => false, 'settings' => ['extraInstructions' => ''], 'message' => 'Plugin not configured']);
            exit;
        }
        
        $url = 'https://lms-labs.com/api/aiquizmaker-settings?siteId=' .
                urlencode($siteid) . '&apiKey=' . urlencode($apikey);
        
        $res = aiquizmaker_fetch($url);
        
        if (!$res['success']) {
            echo json_encode(['ok' => false, 'settings' => ['extraInstructions' => ''], 'message' => 'Connection failed']);
            exit;
        }
        
        $data = json_decode($res['body'], true);
        
        if (isset($data['ok']) && $data['ok']) {
            echo json_encode([
                'ok' => true,
                'settings' => isset($data['settings']) ? $data['settings'] : ['extraInstructions' => '']
            ]);
        } else {
            echo json_encode([
                'ok' => false,
                'settings' => ['extraInstructions' => ''],
                'message' => isset($data['error']) ? $data['error'] : 'Unknown error'
            ]);
        }
        exit;
    }

    /* ============================================================
       ACTION: SAVE SETTINGS (extra instructions)
       ============================================================ */
    if ($action === 'savesettings') {
        $extraInstructions = substr(clean_param(optional_param('extraInstructions', '', PARAM_RAW), PARAM_TEXT), 0, 5000);
        
        if (!$siteid || !$apikey) {
            echo json_encode(['ok' => false, 'message' => 'Plugin not configured']);
            exit;
        }
        
        $payload = [
            'siteId' => $siteid,
            'apiKey' => $apikey,
            'extraInstructions' => $extraInstructions
        ];
        
        $res = aiquizmaker_fetch('https://lms-labs.com/api/aiquizmaker-settings', true, $payload);
        
        if (!$res['success']) {
            echo json_encode(['ok' => false, 'message' => 'Connection failed']);
            exit;
        }
        
        $data = json_decode($res['body'], true);
        
        if (isset($data['ok']) && $data['ok']) {
            echo json_encode(['ok' => true]);
        } else {
            echo json_encode([
                'ok' => false,
                'message' => isset($data['error']) ? $data['error'] : 'Failed to save settings'
            ]);
        }
        exit;
    }

    /* ============================================================
       ACTION: ADD QUESTIONS TO QUIZ (on-demand, after user review)
       ============================================================ */
    if ($action === 'addtoquiz') {
        global $CFG, $DB;
        
        // Get cmid from request
        $cmid = optional_param('cmid', 0, PARAM_INT);
        
        $questions = optional_param('questions', '', PARAM_RAW);
        $questions = json_decode($questions, true);
        
        if (!$questions || !is_array($questions) || count($questions) === 0) {
            aiquizmaker_send_response([
                'success' => false,
                'error' => 'No questions provided'
            ]);
        }
        
        if ($cmid <= 0) {
            aiquizmaker_send_response([
                'success' => false,
                'error' => 'No quiz context'
            ]);
        }
        
        try {
            // H1: locallib.php was removed in Moodle 4.4 (MDL-76897). Guard with file_exists so
            //     the require does not fatal-error on 4.4+ hosts. quiz_add_quiz_question() is still
            //     loaded via locallib on Moodle < 4.4 and guarded with function_exists() below.
            $locallib = $CFG->dirroot . '/mod/quiz/locallib.php';
            if (file_exists($locallib)) {
                require_once($locallib);
            }
            
            list($course, $cm) = get_course_and_cm_from_cmid($cmid, 'quiz');
            $quiz = $DB->get_record('quiz', ['id' => $cm->instance], '*', MUST_EXIST);
            $quizname = $cm->name;
            
            $modcontext = context_module::instance($cmid);
            if (!has_capability('mod/quiz:manage', $modcontext)) {
                throw new moodle_exception('nopermissions', 'error', '', 'manage quiz');
            }
            
            // H2: question_get_default_category() may not exist on all Moodle versions; guard it.
            //     Also fall back to a direct DB lookup or category creation if the call returns nothing.
            $categoryid = 0;
            if (function_exists('question_get_default_category')) {
                $defaultcat = question_get_default_category($modcontext->id);
                if ($defaultcat && is_object($defaultcat)) {
                    $categoryid = (int)$defaultcat->id;
                } else if ($defaultcat && is_numeric($defaultcat)) {
                    $categoryid = (int)$defaultcat;
                }
            }
            if (!$categoryid) {
                // Direct DB fallback — look for any existing category in this context.
                $categoryid = (int)$DB->get_field('question_categories', 'id', ['contextid' => $modcontext->id]);
            }
            if (!$categoryid) {
                // Last resort: create a category in this context.
                $topcat = function_exists('question_get_top_category')
                    ? question_get_top_category($modcontext->id, true)
                    : null;
                $cat = new stdClass();
                $cat->name      = 'Default for ' . format_string($cm->name);
                $cat->info      = '';
                $cat->contextid = $modcontext->id;
                $cat->parent    = $topcat ? $topcat->id : 0;
                $cat->sortorder = 999;
                $cat->stamp     = make_unique_id_code();
                $categoryid = (int)$DB->insert_record('question_categories', $cat);
            }
            
            $maxpage = $DB->get_field_sql(
                'SELECT MAX(page) FROM {quiz_slots} WHERE quizid = ?',
                [$quiz->id]
            );
            $page = ($maxpage !== null && $maxpage !== false) ? (int)$maxpage + 1 : 1;
            
            $addedcount = 0;
            $atq_skipped = [];
            $atq_errors  = [];
            $allowedtypes_atq = ['essay', 'multichoice', 'truefalse', 'matching', 'gapselect', 'shortanswer', 'description'];
            // N1: Separate real-question counter — descriptions don't consume a Q number.
            $atq_real_qnum = 0;
            foreach ($questions as $atq_idx => $q) {
                // Whitelist question type before routing.
                $atq_type = clean_param($q['moodleQuestionType'] ?? 'essay', PARAM_ALPHANUMEXT);
                if (!in_array($atq_type, $allowedtypes_atq)) {
                    $atq_type = 'essay';
                }
                $q['moodleQuestionType'] = $atq_type;
                // N1: Descriptions are not numbered; real questions get Q1, Q2, Q3 ...
                if ($atq_type === 'description') {
                    $q['_qnum'] = null;
                } else {
                    $atq_real_qnum++;
                    $q['_qnum'] = $atq_real_qnum;
                }
                // G1: Do NOT use start_delegated_transaction() per-question — Moodle's rollback()
                //     re-throws the exception, aborting ALL remaining questions if any single one fails.
                //     aiquizmaker_create_question() already returns false on internal errors (safe isolation).
                try {
                    $questionid = aiquizmaker_create_question($q, $categoryid);
                    if ($questionid) {
                        // H3: quiz_add_quiz_question() was removed in Moodle 4.4 (MDL-76897).
                        //     Use it when available (Moodle < 4.4), otherwise use the direct-DB
                        //     helper aiquizmaker_quiz_add_slot() which inserts into quiz_slots and
                        //     question_references directly — no structure API with uncertain method names.
                        if (function_exists('quiz_add_quiz_question')) {
                            quiz_add_quiz_question($questionid, $quiz, $page);
                        } else {
                            aiquizmaker_quiz_add_slot($DB, $quiz, $questionid, $modcontext, $page);
                        }
                        $page++;
                        $addedcount++;
                    } else {
                        // F2: Collect skip reasons for questions that fail validation.
                        $atq_skipped[] = 'Q' . ($atq_idx + 1) . ' (' . $atq_type . '): skipped — invalid or empty content';
                    }
                } catch (\Throwable $atq_e) {
                    // G3: Catch \Throwable (not just Exception) to handle PHP 7+ Error objects too.
                    $atq_errors[] = 'Q' . ($atq_idx + 1) . ': ' . $atq_e->getMessage();
                }
            }
            
            // F5: Only run sumgrades recomputation when at least one question was actually added.
            //     H4: Wrap in try/catch — sumgrades failure is non-critical (questions are still inserted).
            //     Different Moodle versions use different APIs; fall through all known variants.
            if ($addedcount > 0) {
                try {
                    if (class_exists('\mod_quiz\quiz_settings') &&
                        method_exists('\mod_quiz\quiz_settings', 'create')) {
                        $quizobj = \mod_quiz\quiz_settings::create($quiz->id);
                        if (method_exists($quizobj, 'get_grade_calculator')) {
                            $quizobj->get_grade_calculator()->recompute_quiz_sumgrades();
                        } else if (function_exists('quiz_update_sumgrades')) {
                            quiz_update_sumgrades($quiz);
                        }
                    } else if (function_exists('quiz_update_sumgrades')) {
                        quiz_update_sumgrades($quiz);
                    }
                } catch (\Throwable $sg_e) {
                    // Non-fatal — questions are inserted; sumgrades can be recalculated by Moodle cron.
                    $atq_errors[] = 'sumgrades (non-fatal): ' . $sg_e->getMessage();
                }
            }
            
            aiquizmaker_send_response([
                'success' => true,
                'added'   => $addedcount,
                'quizName' => $quizname,
                // F2: Surface skip reasons and errors so the JS UI can inform the user.
                'skipped' => $atq_skipped,
                'errors'  => $atq_errors,
            ]);
        } catch (\Throwable $e) {
            aiquizmaker_send_response([
                'success' => false,
                'error' => 'Failed to add to quiz: ' . $e->getMessage()
            ]);
        }
    }

    /* ============================================================
       ACTION: REGENERATE SINGLE QUESTION
       ============================================================ */
    if ($action === 'extractcriteria') {
        $pastedcontent = optional_param('pastedContent', '', PARAM_RAW);
        $pastedcontent = substr(clean_param($pastedcontent, PARAM_TEXT), 0, 20000);

        if (empty(trim($pastedcontent))) {
            aiquizmaker_send_response([
                'success' => false,
                'error' => 'No content provided'
            ]);
        }

        if (!$siteid || !$apikey) {
            aiquizmaker_send_response([
                'success' => false,
                'error' => get_string('error_missing_config', 'local_aiquizmaker')
            ]);
        }

        $educationtype = optional_param('educationType', 'vet', PARAM_ALPHA);

        $payload = [
            'siteId' => $siteid,
            'apiKey' => $apikey,
            'pastedContent' => $pastedcontent,
            'educationType' => $educationtype,
        ];

        $res = aiquizmaker_fetch('https://lms-labs.com/api/extract-criteria', true, $payload);

        if (!$res['success']) {
            aiquizmaker_send_response([
                'success' => false,
                'error' => 'Connection failed'
            ]);
        }

        $data = json_decode($res['body'], true);

        if (!$data || !isset($data['ok']) || !$data['ok'] || !isset($data['criteria'])) {
            aiquizmaker_send_response([
                'success' => false,
                'error' => isset($data['error']) ? $data['error'] : 'No criteria returned'
            ]);
        }

        aiquizmaker_send_response([
            'success' => true,
            'criteria' => $data['criteria']
        ]);
    }

    if ($action === 'regenerate') {
        $criterionText = optional_param('criterionText', '', PARAM_RAW);
        $criterionText = substr(clean_param($criterionText, PARAM_TEXT), 0, 2000);
        
        if (empty($criterionText)) {
            aiquizmaker_send_response([
                'success' => false,
                'error' => 'No criterion text provided'
            ]);
        }
        
        if (!$siteid || !$apikey) {
            aiquizmaker_send_response([
                'success' => false,
                'error' => get_string('error_missing_config', 'local_aiquizmaker')
            ]);
        }
        
        // Get other context params
        $country = optional_param('country', '', PARAM_TEXT);
        $state = optional_param('state', '', PARAM_TEXT);
        $industry = optional_param('industry', '', PARAM_TEXT);
        $industrydetails = optional_param('industryDetails', '', PARAM_TEXT);
        $jobtitle = optional_param('jobTitle', '', PARAM_TEXT);
        $joblevel = optional_param('jobLevel', '', PARAM_TEXT);
        $educationtype = optional_param('educationType', 'vet', PARAM_ALPHA);
        $educationlevel = substr(clean_param(optional_param('educationLevel', '', PARAM_RAW), PARAM_TEXT), 0, 100);
        $questionformats = optional_param_array('questionFormats', ['short_essay', 'short_answer', 'scenario'], PARAM_ALPHA);
        $extrainstructions = optional_param('extraInstructions', '', PARAM_RAW);
        $extrainstructions = substr(clean_param($extrainstructions, PARAM_TEXT), 0, 5000);
        $pastedcontent = optional_param('pastedContent', '', PARAM_RAW);
        $pastedcontent = substr(clean_param($pastedcontent, PARAM_TEXT), 0, 20000);
        $moodlequestiontypesjson = optional_param('moodleQuestionTypes', '["essay"]', PARAM_RAW);
        $moodlequestiontypes = json_decode($moodlequestiontypesjson, true);
        if (!is_array($moodlequestiontypes) || count($moodlequestiontypes) === 0) {
            $moodlequestiontypes = ['essay'];
        }
        $allowedtypes = ['essay', 'multichoice', 'truefalse', 'matching', 'gapselect', 'shortanswer'];
        $moodlequestiontypes = array_values(array_filter($moodlequestiontypes, function ($t) use ($allowedtypes) {
            return in_array($t, $allowedtypes);
        }));
        if (empty($moodlequestiontypes)) {
            $moodlequestiontypes = ['essay'];
        }

        // Get self-marking question styles
        $selfmarkingstylesjson3 = optional_param('selfMarkingStyles', '["scenario","knowledge_check"]', PARAM_RAW);
        $selfmarkingstyles3 = json_decode($selfmarkingstylesjson3, true);
        if (!is_array($selfmarkingstyles3) || count($selfmarkingstyles3) === 0) {
            $selfmarkingstyles3 = ['scenario', 'knowledge_check'];
        }
        $allowedstyles3 = ['scenario', 'knowledge_check', 'procedure', 'terminology', 'identification'];
        $selfmarkingstyles3 = array_values(array_filter($selfmarkingstyles3, function ($s) use ($allowedstyles3) {
            return in_array($s, $allowedstyles3);
        }));
        if (empty($selfmarkingstyles3)) {
            $selfmarkingstyles3 = ['scenario', 'knowledge_check'];
        }

        // Clear workplace context if not enabled.
        $workplacecontextenabled = optional_param('workplaceContextEnabled', '0', PARAM_ALPHANUMEXT) === '1';
        if (!$workplacecontextenabled) {
            $country = '';
            $state = '';
            $industry = '';
            $industrydetails = '';
            $jobtitle = '';
            $joblevel = '';
        }

        // Language (same as generate action).
        $language = optional_param('language', '', PARAM_TEXT);
        if (empty($language)) {
            $language = str_replace('_', '-', current_language());
        }

        $payload = [
            'siteId' => $siteid,
            'apiKey' => $apikey,
            'criteria' => [['text' => $criterionText, 'questionsCount' => 1]],
            'country' => $country,
            'state' => $state,
            'industry' => $industry,
            'industryDetails' => $industrydetails,
            'jobTitle' => $jobtitle,
            'jobLevel' => $joblevel,
            'educationType' => $educationtype,
            'educationLevel' => $educationlevel,
            'questionFormats' => $questionformats,
            'moodleQuestionTypes' => $moodlequestiontypes,
            'selfMarkingStyles' => $selfmarkingstyles3,
            'extraInstructions' => $extrainstructions,
            'pastedContent' => $pastedcontent,
            'language' => $language,
            'previousQuestionText' => substr(clean_param(optional_param('previousQuestionText', '', PARAM_RAW), PARAM_TEXT), 0, 2000),
            'workplaceContextEnabled' => $workplacecontextenabled,
        ];
        
        $res = aiquizmaker_fetch('https://lms-labs.com/api/generate-essays', true, $payload);
        
        if (!$res['success']) {
            aiquizmaker_send_response([
                'success' => false,
                'error' => 'Connection failed'
            ]);
        }
        
        $data = json_decode($res['body'], true);
        
        if (!$data || !isset($data['ok']) || !$data['ok'] || !isset($data['questions']) || count($data['questions']) === 0) {
            aiquizmaker_send_response([
                'success' => false,
                'error' => isset($data['error']) ? $data['error'] : 'Generation failed',
                'message' => isset($data['message']) ? $data['message'] : ''
            ]);
        }
        
        $question = $data['questions'][0];

        // Whitelist moodleQuestionType.
        $allowedtypes_regen = ['essay', 'multichoice', 'truefalse', 'matching', 'gapselect', 'shortanswer'];
        $regen_qtype = clean_param($question['moodleQuestionType'] ?? 'essay', PARAM_ALPHANUMEXT);
        if (!in_array($regen_qtype, $allowedtypes_regen)) {
            $regen_qtype = 'essay';
        }
        $question['moodleQuestionType'] = $regen_qtype;

        // E1-E7: clean_param(PARAM_TEXT) for self-marking types — prevents double-encoding when the
        // regenerated question is later submitted to addtoquiz/createquestions DB creators.
        $question['questionText']      = clean_param($question['questionText'] ?? '', PARAM_TEXT);
        $question['criteriaReference'] = clean_param($question['criteriaReference'] ?? '', PARAM_TEXT);

        // Sanitise type-specific fields.
        if ($regen_qtype === 'multichoice') {
            $question['explanation']    = clean_param($question['explanation'] ?? '', PARAM_TEXT);
            $question['generalFeedback'] = clean_param($question['generalFeedback'] ?? '', PARAM_TEXT);
            if (isset($question['choices']) && is_array($question['choices'])) {
                foreach ($question['choices'] as &$ch) {
                    $ch['text']     = clean_param($ch['text'] ?? '', PARAM_TEXT);
                    $ch['feedback'] = clean_param($ch['feedback'] ?? '', PARAM_TEXT);
                    $ch['isCorrect'] = !empty($ch['isCorrect']);
                }
                unset($ch);
            }
        } else if ($regen_qtype === 'truefalse') {
            $question['explanation']        = clean_param($question['explanation'] ?? '', PARAM_TEXT);
            $question['trueAnswerFeedback']  = clean_param($question['trueAnswerFeedback'] ?? '', PARAM_TEXT);
            $question['falseAnswerFeedback'] = clean_param($question['falseAnswerFeedback'] ?? '', PARAM_TEXT);
            $question['correctAnswer']       = !empty($question['correctAnswer']);
        } else if ($regen_qtype === 'matching') {
            $question['explanation']    = clean_param($question['explanation'] ?? '', PARAM_TEXT);
            $question['generalFeedback'] = clean_param($question['generalFeedback'] ?? '', PARAM_TEXT);
            if (isset($question['matchPairs']) && is_array($question['matchPairs'])) {
                foreach ($question['matchPairs'] as &$pair) {
                    $pair['subquestion'] = clean_param($pair['subquestion'] ?? '', PARAM_TEXT);
                    $pair['subanswer']   = clean_param($pair['subanswer'] ?? '', PARAM_TEXT);
                }
                unset($pair);
            }
        } else if ($regen_qtype === 'gapselect') {
            $question['generalFeedback'] = clean_param($question['generalFeedback'] ?? '', PARAM_TEXT);
            if (isset($question['selectOptions']) && is_array($question['selectOptions'])) {
                foreach ($question['selectOptions'] as &$grp) {
                    if (is_array($grp)) {
                        foreach ($grp as &$opt) {
                            // E6: Use $tostring() equivalent — ensure $opt is a string before clean_param.
                            $opt = clean_param(is_string($opt) ? $opt : (string)$opt, PARAM_TEXT);
                        }
                        unset($opt);
                    }
                }
                unset($grp);
                // BUG-QM-GAPSELECT-DUPES (v3.16.79): Per-blank deduplication for the regenerate path.
                $question['selectOptions'] = aiquizmaker_dedupe_gapselect_all($question['selectOptions']);
            }
        } else if ($regen_qtype === 'shortanswer') {
            $question['explanation']    = clean_param($question['explanation'] ?? '', PARAM_TEXT);
            $question['generalFeedback'] = clean_param($question['generalFeedback'] ?? '', PARAM_TEXT);
            if (isset($question['acceptedAnswers']) && is_array($question['acceptedAnswers'])) {
                foreach ($question['acceptedAnswers'] as &$ans) {
                    $ans = clean_param(is_string($ans) ? $ans : (string)$ans, PARAM_TEXT);
                }
                unset($ans);
            }
        } else {
            // Essay: format_text is appropriate since essay responses render as HTML.
            if (isset($question['rubric']) && is_array($question['rubric'])) {
                foreach ($question['rubric'] as &$r) {
                    $r['description'] = format_text($r['description'] ?? '', FORMAT_PLAIN, ['trusted' => false]);
                }
                unset($r);
            }
            if (isset($question['sampleAnswer']) && is_array($question['sampleAnswer'])) {
                foreach ($question['sampleAnswer'] as &$sa) {
                    if (isset($sa['response'])) {
                        $sa['response'] = format_text($sa['response'] ?? '', FORMAT_PLAIN, ['trusted' => false]);
                    }
                }
                unset($sa);
            } else {
                $question['sampleAnswer'] = format_text($question['sampleAnswer'] ?? '', FORMAT_PLAIN, ['trusted' => false]);
            }
        }

        aiquizmaker_send_response([
            'success' => true,
            'question' => $question
        ]);
    }

    /* ============================================================
       UNKNOWN ACTION
       ============================================================ */
    aiquizmaker_send_response([
        'success' => false,
        'error' => get_string('error_unknown_action', 'local_aiquizmaker') . ': ' . s($action)
    ]);

} catch (Exception $e) {
    // Catch ALL exceptions including Moodle bootstrap errors.
    echo json_encode([
        'success' => false,
        'error' => 'Server error: ' . $e->getMessage(),
    ]);
    exit;
} catch (Throwable $t) {
    // Catch ALL throwables (PHP 7+ errors).
    echo json_encode([
        'success' => false,
        'error' => 'Server error: ' . $t->getMessage(),
    ]);
    exit;
}
