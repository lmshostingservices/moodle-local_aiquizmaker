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
 * Upgrade steps for local_aiquizmaker.
 *
 * @package    local_aiquizmaker
 * @copyright  2025 Essay Grader AI
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

function xmldb_local_aiquizmaker_upgrade($oldversion) {

    // v3.16.30: VERSION BUMP — Routine release increment. upgrade.php created (was missing).
    // AMD triple-match verified: aiquizmaker.js (11006d0f) + quizbutton.js (50d4b7be).
    // No DB schema changes. version.php → 202603303000.
    if ($oldversion < 202603303000) {
        upgrade_plugin_savepoint(true, 202603303000, 'local', 'aiquizmaker');
    }

    // v3.16.31: FIX (x5): Tester feedback — novalidate on form, required removed from criteria inputs,
    // criteria quality filter (>= 10 chars), question selection checkboxes + Select All/None toggle,
    // addQuestionsToQuiz/createQuestionsInMoodle respect selection. No DB schema changes.
    // version.php → 202603303100.
    if ($oldversion < 202603303100) {
        upgrade_plugin_savepoint(true, 202603303100, 'local', 'aiquizmaker');
    }

    // v3.16.32: FIX (x8): Tester feedback round 2 — error message surfacing (JS + PHP passthrough),
    // Use My Own Questions 20-char minimum validation, MCQ option block stripping from criteria,
    // criteria capped at 300 chars. No DB schema changes. version.php → 202603303200.
    if ($oldversion < 202603303200) {
        upgrade_plugin_savepoint(true, 202603303200, 'local', 'aiquizmaker');
    }

    // v3.16.33: FIX (x2) + IMPROVEMENT — language support in generate-essays route (language field
    // now accepted by server schema and wired into VET/Academic system prompts via languageInstruction);
    // non-essay token budget 2000→3000; improved error logging in processQuestion catch blocks.
    // No DB schema changes. version.php → 202603303300.
    if ($oldversion < 202603303300) {
        upgrade_plugin_savepoint(true, 202603303300, 'local', 'aiquizmaker');
    }

    // v3.16.34: INVESTIGATION + SERVER FIX — "Generation Failed" tester report confirmed as
    // OpenAI quota exhaustion during testing (not a plugin code bug). Server /api/generate-essays
    // and /api/generate-from-questions catch blocks now strip OPENAI_*: prefixes from error
    // messages so teachers see clean, readable errors. No plugin code or DB schema changes.
    // version.php → 202603313400.
    if ($oldversion < 202603313400) {
        upgrade_plugin_savepoint(true, 202603313400, 'local', 'aiquizmaker');
    }

    // v3.16.35: VERSION BUMP — Routine release following all 6-location sync rules.
    // No code changes. No PHP or DB schema changes. version.php → 202603313500.
    if ($oldversion < 202603313500) {
        upgrade_plugin_savepoint(true, 202603313500, 'local', 'aiquizmaker');
    }

    // v3.16.36: BUG FIX (x5) + NEW FEATURE (x1):
    // (1) generatefromquestions action was missing totalMarks for truefalse (always blank → 0),
    //     matching (showed 3 instead of actual pair count), gapselect (0 instead of group count),
    //     and shortanswer (0 instead of 1) — now each type sets totalMarks correctly, matching
    //     issue: the generate action did this correctly already but generatefromquestions did not.
    // (2) criteriaReference in ownquestions mode was empty for all non-essay types (AI omits it
    //     for self-marking question types) — added PHP fallback: if criteriaReference is empty
    //     after cleaning, substitute the question text so teachers always see their original question.
    // (3) Matching marks in buildQuestionCard JS now derived directly from matchPairs.length
    //     (belt-and-suspenders over server-returned totalMarks for both generate and generatefromquestions).
    // (4) gapselect marks in buildQuestionCard JS derived from selectOptions group count.
    // (5) NEW: per-question "Add to Quiz" button added to each question card in quiz context
    //     (currentHasQuizContext=true) — teachers can now add a single question directly
    //     without using checkboxes; button turns green with a tick on success.
    // version.php → 202603313600.
    if ($oldversion < 202603313600) {
        upgrade_plugin_savepoint(true, 202603313600, 'local', 'aiquizmaker');
    }

    // v3.16.37: BUG FIX (x4) + UI FIX (x1) + CSS (x1):
    // (1) T/F redo full regeneration: criteria mode redo now preserves criteriaReference on the
    //     regenerated question object — previously the server omitted criteriaReference for T/F
    //     after first redo, causing subsequent redos to send empty criterionText and fail silently
    //     (only explanation appeared to update). Fix: JS restores q.criteriaReference after redo.
    // (2) T/F redo ownquestions mode: original questionText now frozen in q.originalQuestionText
    //     on first redo and reused on all subsequent redos — prevents the AI from regenerating
    //     from its own prior output (which was nearly identical), ensuring a fresh generation.
    // (3) T/F criteria formatting: generatefromquestions PHP now excludes truefalse from the
    //     GFQ-CR fallback (criteriaReference = questionText) — for T/F the statement IS the
    //     question, not a competency criterion; leaving criteriaReference empty for T/F stops
    //     the card from showing a misleading duplicate of the question as "Criteria:".
    // (4) Select Missing Words structure: gapselect card now shows a two-part layout — "Question:"
    //     (the criteria reference / skill competency instruction) above the "Answer (with blanks):"
    //     (the questionText with [[n]] placeholders). criteriaReference not shown again in the
    //     bottom criteria-ref bar for gapselect to avoid duplication.
    // (5) Removed "+" per-card Add to Quiz button (aiquizmaker-add-single-btn) added in v3.16.36
    //     as NEW FEATURE (x1) — removed per product review; bulk "Add to Quiz" button remains.
    //     JS criteria-ref div now conditionally rendered (hidden when criteriaReference is empty).
    // (6) CSS: added .aiquizmaker-gap-instruction and .aiquizmaker-gap-answer-label rules in
    //     styles.css for the new gapselect two-part display.
    // aiquizmaker.js AMD triple-match MD5: 181ef8e7fa8096380721f86d2bd510d1.
    // Files changed: amd/src/aiquizmaker.js, amd/build/aiquizmaker.js, amd/build/aiquizmaker.min.js,
    //   ajax.php, styles.css. No DB schema changes. version.php → 202603313700.
    if ($oldversion < 202603313700) {
        upgrade_plugin_savepoint(true, 202603313700, 'local', 'aiquizmaker');
    }

    // v3.16.38: BUG FIX (x3) + SERVER FIX (x2):
    // (1) Criteria mode redo corrupted criteriaReference — the server response was winning over
    //     the original value because the JS `||` order was wrong (line 2977). Fixed: JS now
    //     restores q.criteriaReference = q.criteriaReference || response.question.criteriaReference
    //     so the teacher's original criteria text is always preserved on redo.
    // (2) gapselect criteria badge was excluded from display (qtype !== 'gapselect' guard removed
    //     from line 2116) — gapselect now shows the Criteria badge like all other question types.
    // (3) gapselect card body now shows a fixed instructional sentence ("Complete the sentence by
    //     selecting the correct words.") instead of rendering criteriaReference as "Question:
    //     [criteriaReference]" which was confusing. criteriaReference is displayed separately in
    //     the Criteria badge. The fixed instruction correctly tells students what to do.
    // SERVER FIX (x2): routes.ts gapselect prompts (criteria mode + ownquestions mode) now
    //     enforce that criteriaReference is a short skill-based phrase (3-8 words, not question
    //     text) and that questionText never starts with "Question:" or a heading. truefalse prompts
    //     also updated with explicit criteriaReference enforcement.
    // aiquizmaker.js AMD triple-match MD5: 8dc7dfa827b9b79a9eb581e3802615f5.
    // Files changed: amd/src/aiquizmaker.js, amd/build/aiquizmaker.js, amd/build/aiquizmaker.min.js,
    //   server/routes.ts. No DB schema changes. version.php → 202603313800.
    if ($oldversion < 202603313800) {
        upgrade_plugin_savepoint(true, 202603313800, 'local', 'aiquizmaker');
    }

    // v3.16.39: BUG FIX (x3) + SERVER FIX (x2):
    // ROOT CAUSE INVESTIGATION: T/F no criteria + matching criteria = question text.
    // (1) REGRESSION FIX: In v3.16.38, the criteria-mode T/F prompt accidentally had the
    //     criteriaReference example hardcoded as a generic phrase instead of "${criterion.text}".
    //     This caused the AI to return wrong/empty criteriaReference for T/F in criteria mode.
    //     Fix: restored `"criteriaReference": "${criterion.text}"` in the T/F criteria-mode
    //     format template in routes.ts.
    // (2) Matching ownquestions mode: the JSON schema in the ownquestions matching prompt
    //     was missing the criteriaReference field entirely — the AI had no instruction to return
    //     it, so it always returned empty. The PHP GFQ-CR fallback then copied the matching
    //     questionText ("Match each term…") into criteriaReference, which is not a criterion.
    //     Fix: added criteriaReference field + CRITICAL enforcement rule to ownquestions
    //     matching prompt in routes.ts.
    // (3) PHP GFQ-CR fallback: excluded matching from the questionText fallback (same as T/F).
    //     Matching questionText is a generic instruction, not a competency criterion.
    //     If AI returns empty criteriaReference for matching, it stays empty (criteria-ref div
    //     is hidden by JS when empty) rather than showing the wrong instruction text.
    // SERVER FIX (x2):
    // (4) Added CRITICAL enforcement rule to criteria-mode matching format: criteriaReference
    //     must be the exact criterion text, not copied from question body or matchPairs.
    // (5) Server-side fallback (SRV-CR) added in processCriterion: if AI returns empty
    //     criteriaReference for any question type in criteria mode, automatically set it to
    //     criterion.text — guarantees criteria-mode cards always show the correct criterion.
    // No JS changes. aiquizmaker.js AMD MD5 unchanged: 8dc7dfa827b9b79a9eb581e3802615f5.
    // Files changed: ajax.php, server/routes.ts. No DB schema changes. version.php → 202603313900.
    if ($oldversion < 202603313900) {
        upgrade_plugin_savepoint(true, 202603313900, 'local', 'aiquizmaker');
    }

    // v3.16.40: CRITICAL BUG FIX (x1) + BUG FIX (x1):
    // (1) CRITICAL: "formatParts is not defined" — generation completely broken for all
    //     non-essay question types (multichoice, truefalse, matching, gapselect, shortanswer)
    //     in Generate from Criteria mode. Root cause: in routes.ts processCriterion(), the
    //     `formatParts` array was declared with `const` inside the `if (!hasOnlyEssay)` block
    //     (block-scoped), but was also referenced in the non-essay ternary branch of `userPrompt`
    //     which is OUTSIDE that block — causing a JavaScript ReferenceError at runtime that
    //     propagated to the Moodle plugin as "formatParts is not defined". Fix: hoisted
    //     `formatParts` declaration to `let formatParts: string[] = []` immediately before the
    //     `if (!hasOnlyEssay)` block so it is accessible in both the if-block (where it is
    //     populated) and the outer ternary (where it is used). No double-population risk as
    //     the pushes still only occur inside the if-block.
    // (2) Mode-switch state leak: switching between "Generate from Criteria" and "Use My Own
    //     Questions" modes did not clear the results container — previously generated questions
    //     from one mode remained visible when switching to the other mode. Fix: switchInputMode()
    //     now resets lastGeneratedQuestions=[], empties #aiquizmaker-results, hides and clears
    //     the XML export section, and removes any alert messages on every mode switch.
    // AMD: aiquizmaker.js changed (mode-switch clear). AMD triple-match MD5: 294111f4ba14304f60da99fc33f33337.
    // Files changed: server/routes.ts, amd/src/aiquizmaker.js + amd/build/ sync. No DB schema changes.
    // version.php → 202603314000.
    if ($oldversion < 202603314000) {
        upgrade_plugin_savepoint(true, 202603314000, 'local', 'aiquizmaker');
    }

    // v3.16.41: BUG FIX (x3) + ENHANCEMENT (x1):
    // (1) 'Use My Own Questions' mode now generates one question per selected type per pasted
    //     input — previously cycled round-robin (1 input × N types = 1 output). Server flatMap
    //     approach; JS merge logic and orderedLayout updated to consume typesCount outputs per
    //     input question.
    // (2) Success toast 'success_generated_message' changed from 'essay questions' → 'questions'.
    // (3) Missing shortanswer (Fill in the Blank) checkbox added to question types UI in index.php.
    // (4) chatgpt_qtypes_note tip updated to explain one-per-type-per-question behaviour.
    // version.php → 202603314100.
    if ($oldversion < 202603314100) {
        upgrade_plugin_savepoint(true, 202603314100, 'local', 'aiquizmaker');
    }

    // v3.16.42: BUG FIX (x2):
    // (1) CRITICAL: switchInputMode() called $('#aiquizmaker-results').empty() which destroyed
    //     the static #questions-list element — displayResults() append calls were no-ops causing
    //     zero question cards to render after any mode switch. Fix: targeted clear via
    //     $('#questions-list').empty() + $('#aiquizmaker-results').hide().
    // (2) Success toast in ownquestions mode showed N×M total (e.g. 30 for 5×6) instead of the
    //     pasted question count (5). Fix: toast now uses questionItems.length.
    // version.php → 202603314200.
    if ($oldversion < 202603314200) {
        upgrade_plugin_savepoint(true, 202603314200, 'local', 'aiquizmaker');
    }

    // v3.16.43: BUG FIX:
    // Selecting 5 questions in 'Use My Own Questions' mode generated 25 questions (N×M)
    // instead of 5. Root cause: server used flatMap producing one AI call per pasted question
    // × selected type (5 × 5 = 25); JS orderedLayout and merge loop also iterated typesCount
    // times per question. Fix: server now assigns one type per pasted question (round-robin
    // across selected types) via map(); JS orderedLayout allocates 1 slot per question and
    // merge loop consumes 1 result per question. Result: 5 pasted questions → always 5 outputs.
    // version.php → 202603314300.
    if ($oldversion < 202603314300) {
        upgrade_plugin_savepoint(true, 202603314300, 'local', 'aiquizmaker');
    }

    // v3.16.44: BUG FIX:
    // Gapselect question text ([[n]] sentence) was corrupted after a failed edit-modal
    // validation. saveQuestionEdit() mutated q.questionText before validation ran — on early
    // return the in-memory object retained the mutated (possibly empty) text, breaking
    // subsequent card renders. Fixes: (1) originalQuestionText saved and restored on failure;
    // (2) buildQuestionCard gapselect branch guards against empty questionText;
    // (3) getSelectedQuestions/addSingleQuestionToQuiz apply defensive fallback before
    // serialising for addtoquiz/createquestions. No DB schema change.
    // version.php → 202604014400.
    if ($oldversion < 202604014400) {
        upgrade_plugin_savepoint(true, 202604014400, 'local', 'aiquizmaker');
    }

    // v3.16.45: UPGRADE FIX — Corrected upgrade.php savepoint ordering. v3.16.36 block
    //   (202603313600) was mistakenly inserted AFTER v3.16.40 block (202603314000) in
    //   db/upgrade.php instead of its correct position between v3.16.35 (202603313500)
    //   and v3.16.37 (202603313700). Sites upgrading from v3.16.35 or earlier would hit
    //   202603314000 first (setting version to 202603314000), then attempt to set
    //   202603313600 — triggering a fatal "Cannot downgrade" error. Fixed by moving the
    //   v3.16.36 block to its correct position. No code, JS, CSS, or DB schema changes.
    //   version.php → 202604014500.
    if ($oldversion < 202604014500) {
        upgrade_plugin_savepoint(true, 202604014500, 'local', 'aiquizmaker');
    }

    // v3.16.46 FIX-AQM-TYPE-DIST: buildChatGPTPrompt listed selected question types as a
    //   suggestion only ("types to include: X, Y, Z"). GPT-4 ignored the distribution and
    //   produced 80–100% of one type. Fix: add an explicit per-type count instruction
    //   ("TYPE DISTRIBUTION — MANDATORY: exactly N × Type-A, M × Type-B…").
    //   No DB schema changes. AMD: aiquizmaker.js updated. version.php → 202604020046.
    if ($oldversion < 202604020046) {
        upgrade_plugin_savepoint(true, 202604020046, 'local', 'aiquizmaker');
    }

    // v3.16.47 FIX-AQM-REGEN (x3): (1) CSS: added margin-top: 20px to Question Types section
    //   — fixes missing spacing between VET/Academic Level dropdown and Question Types checkboxes.
    //   (2) Type distribution: generate-essays countNote now builds an explicit per-question type
    //   plan (Question 1: TYPE-A, Question 2: TYPE-B, …) instead of asking AI to distribute.
    //   Lean non-essay userPrompt updated to use the same mandatory countNote. Bullet "Distribute
    //   types evenly" replaced with "Follow the MANDATORY TYPE ASSIGNMENT". Server also updated
    //   for generate-from-questions (no change — already uses correct server-side round-robin).
    //   (3) Regenerate: JS passes previousQuestionText in both criteria and ownquestions modes;
    //   ajax.php forwards it to the server; server injects DO-NOT-REUSE instruction into
    //   generate-essays and generate-from-questions prompts when previousQuestionText is supplied.
    //   No DB schema changes. AMD: aiquizmaker.js updated. version.php → 202604020047.
    if ($oldversion < 202604020047) {
        upgrade_plugin_savepoint(true, 202604020047, 'local', 'aiquizmaker');
    }

    // v3.16.48 - BUG FIX (x2):
    //   (1) Criteria-mode Regenerate: workplaceContextEnabled flag now sent with AJAX data so
    //       PHP correctly includes workplace context fields (industry, jobTitle, jobLevel, etc.)
    //       when the teacher has configured them. Previously omitted, causing all regenerated
    //       questions to ignore the workplace context setup.
    //   (2) Criteria-mode Regenerate: fetchCredits() now called after a successful regeneration
    //       response so the credit counter updates immediately without requiring a page reload.
    //   No DB schema changes. AMD: aiquizmaker.js updated. version.php → 202604030048.
    if ($oldversion < 202604030048) {
        upgrade_plugin_savepoint(true, 202604030048, 'local', 'aiquizmaker');
    }

    // v3.16.49: AMD SYNC FIX — amd/build/aiquizmaker.js was stale (MD5 3f402d3dcc4e52200024657978b66f56)
    //   while amd/src/aiquizmaker.js and amd/build/aiquizmaker.min.js had matching MD5
    //   dc63637072b106000fc716a1f3a2d94e. Root cause: a previous release copied src→min but
    //   omitted src→build, leaving the non-minified build file behind at the v3.16.47 state.
    //   Moodle in debug/developer mode loads amd/build/MODULENAME.js (not .min.js), so sites
    //   in debug mode were serving stale JS. Fix: amd/build/aiquizmaker.js resynced to src.
    //   src=build=min triple-match MD5: dc63637072b106000fc716a1f3a2d94e.
    //   No DB schema changes. No PHP changes. version.php → 202604040049.
    if ($oldversion < 202604040049) {
        upgrade_plugin_savepoint(true, 202604040049, 'local', 'aiquizmaker');
    }

    // v3.16.50: BUG FIX (regenerate loop — A→B→A cycle prevention).
    //   Both ownquestions-mode and criteria-mode regenerate now accumulate all previously
    //   generated question versions in a _generatedVersions array on the question object.
    //   Each regenerate call sends the full accumulated history as previousQuestionText
    //   (up to 2000 chars, delimited by ---PREV---) so the server can avoid ALL prior
    //   outputs, not just the last one. Previously, sending only the most-recently-generated
    //   text caused the AI to alternate: generate A → send "avoid A" → generates B → send
    //   "avoid B" → generates A → loop indefinitely. The fix breaks this cycle by ensuring
    //   every prior version is consistently avoided.
    //   AMD triple-match MD5: dbf7fe3c48fe3e770eae833d79a130ec.
    //   No DB schema changes. JS only: amd/src/aiquizmaker.js. version.php → 202604070050.
    if ($oldversion < 202604070050) {
        upgrade_plugin_savepoint(true, 202604070050, 'local', 'aiquizmaker');
    }

    // v3.16.51 — SERVER-SIDE FIXES + VERSION BUMP: (1) Question-type distribution: 6-criteria
    //   generation now assigns a distinct type per row — processCriterion receives criterionIndex
    //   and uses useTypes[criterionIndex % useTypes.length] so each of the 6 criteria maps to a
    //   different question type. (2) Select Missing Words (gapselect) questionText now always has
    //   two parts: a brief instruction line followed by the sentence with [[n]] gap placeholders —
    //   in both criteria-mode and single-question regenerate-mode prompts. Server-side only
    //   (server/routes.ts); no PHP or AMD changes. AMD triple-match MD5 unchanged:
    //   dbf7fe3c48fe3e770eae833d79a130ec. No DB schema changes. version.php → 202604070051.
    if ($oldversion < 202604070051) {
        upgrade_plugin_savepoint(true, 202604070051, 'local', 'aiquizmaker');
    }

    // v3.16.52 — TESTER FEEDBACK FIXES (2 bugs, server-side only):
    //   FIX-QM-REGEN: generatefromquestions — when previousQuestionText is set (regeneration),
    //     the AI is now instructed to create COMPLETELY NEW question text instead of
    //     "keep the teacher's wording if it works", resolving same-stem regeneration.
    //   FIX-QM-TYPE-DIST: processCriterion now uses assignedTypesForCriterion (Set of types
    //     for this criterion only) to build formatParts, instead of including ALL selected
    //     type formats. Prevents AI defaulting to familiar types and ignoring countNote.
    //   Server-side only (server/routes.ts). No PHP or AMD changes.
    //   AMD triple-match MD5 unchanged: dbf7fe3c48fe3e770eae833d79a130ec.
    //   No DB schema changes. version.php → 202604080052.
    if ($oldversion < 202604080052) {
        upgrade_plugin_savepoint(true, 202604080052, 'local', 'aiquizmaker');
    }

    // v3.16.53 - VERSION BUMP: Clean release following full tester-feedback cycle.
    //   All fixes from v3.16.52 (FIX-QM-REGEN, FIX-QM-TYPE-DIST) confirmed in ZIP
    //   and all 6 delivery locations. No code changes. AMD MD5 unchanged: dbf7fe3c48fe3e770eae833d79a130ec.
    //   No DB schema changes. version.php → 202604080053.
    if ($oldversion < 202604080053) {
        upgrade_plugin_savepoint(true, 202604080053, 'local', 'aiquizmaker');
    }

    // v3.16.54 - VERSION BUMP: Clean release following full tester-feedback cycle.
    //   All fixes from v3.16.53 (FIX-QM-REGEN, FIX-QM-TYPE-DIST) confirmed in ZIP.
    //   No new code changes. AMD MD5 unchanged: dbf7fe3c48fe3e770eae833d79a130ec.
    //   No DB schema changes. version.php → 202604090054.
    if ($oldversion < 202604090054) {
        upgrade_plugin_savepoint(true, 202604090054, 'local', 'aiquizmaker');
    }

    // v3.16.55 - RELEASE SYNC: Full 6-location sync for v3.16.54 clean release.
    //   AMD triple-match confirmed: dbf7fe3c48fe3e770eae833d79a130ec.
    //   No new code changes. No DB schema changes. version.php → 202604090055.
    if ($oldversion < 202604090055) {
        upgrade_plugin_savepoint(true, 202604090055, 'local', 'aiquizmaker');
    }

    // v3.16.56 - BUG FIX (gapselect display): buildQuestionCard() gapselect branch now splits
    //   questionText on the first \n\n, rendering only the sentence part (with [[n]] blanks) in
    //   the "Answer (with blanks):" section. The AI-prepended instruction line is stripped from
    //   the answer area — it is already shown by the fixed heading above. AMD updated.
    //   AMD triple-match MD5: c74c567bea4f99a658cba26cb4655899. No DB schema changes.
    //   version.php → 202604090056.
    if ($oldversion < 202604090056) {
        upgrade_plugin_savepoint(true, 202604090056, 'local', 'aiquizmaker');
    }

    // v3.16.57 - BUG FIX (x2): (1) shortanswer two-field format: AI prompt (criteria mode +
    //   ownquestions mode) now returns TWO fields: questionText (the actual question) AND blankSentence
    //   (the completion sentence with ___). Previously only a single field contained the blank sentence
    //   with no separate question. buildQuestionCard() now renders blankSentence below the question.
    //   Edit modal adds blankSentence textarea. saveQuestionEdit() saves blankSentence. PHP
    //   aiquizmaker_create_shortanswer_question() and aiquizmaker_xml_shortanswer_block() both output
    //   the question text followed by the blank sentence. (2) GLOBAL-TYPE-DIST: criteria-mode type
    //   distribution now uses cumulative starting indices across all criteria so the round-robin is
    //   globally sequential — with 10 questions and 6 types, the first 6 questions each use a different
    //   type before any repeats. Previously each criterion restarted type-assignment from its ordinal
    //   index, causing non-global distribution across multiple criteria. ownquestions mode unaffected
    //   (already global). AMD triple-match MD5: ce0c6d3c33679d8c73af25e97e2fc067. No DB schema changes.
    //   version.php → 2026040900057.
    if ($oldversion < 2026040900057) {
        upgrade_plugin_savepoint(true, 2026040900057, 'local', 'aiquizmaker');
    }

    // v3.16.58 - AUTO-TEST CONFIRMATION: All ongoing tester issues (reported at v3.16.51/v3.16.53)
    //   confirmed resolved via code audit. (1) Regenerate-after-2-clicks: fixed in v3.16.50
    //   (accumulated _generatedVersions history sent as previousQuestionText, prevents A→B→A loop)
    //   and v3.16.54 (STRICT RULES prompt: "Do NOT only change the explanation", "Create a NEW
    //   question stem, NEW answer options, and a NEW explanation", 50-char similarity check).
    //   (2) 6-types × 6-questions distribution: fixed in v3.16.54 (SRV-TYPE-ENFORCE: server
    //   force-assigns moodleQuestionType from pre-computed useTypes array, guaranteeing every
    //   selected type is used exactly once when question count equals type count).
    //   No code changes. No DB schema changes. AMD unchanged. version.php → 2026041000058.
    if ($oldversion < 2026041000058) {
        upgrade_plugin_savepoint(true, 2026041000058, 'local', 'aiquizmaker');
    }

    // v3.16.59 - AI PROMPT FIX (FIX-QM-GAPSELECT-FORMAT):
    //   Select Missing Words (gapselect) question format corrected in all 3 generation paths.
    //   Previously, AI generated arbitrary answer options without a workplace scenario.
    //   Now enforces 3-part structure: (1) realistic workplace/industry scenario sentence,
    //   (2) question prompt for context, (3) sentence with [[n]] gap placeholders where
    //   n matches a named answer option. Incorrect options must be plausible synonyms.
    //   Affects main generation route, single-question regeneration route, and CRITICAL RULES.
    //   No DB schema changes. AMD unchanged. version.php → 2026041000059.
    if ($oldversion < 2026041000059) {
        upgrade_plugin_savepoint(true, 2026041000059, 'local', 'aiquizmaker');
    }

    // v3.16.60 - DISPLAY FIX (FIX-QM-GAPSELECT-DISPLAY): gapselect card now shows the
    //   AI-generated scenario as italicised context text above "Answer (with blanks):",
    //   and extracts only the LAST \n\n-separated part as the gap sentence (backward-
    //   compatible with old 3-part questions). CSS: .aiquizmaker-gap-scenario added to
    //   styles.css. AMD: aiquizmaker.js + styles.css updated.
    //   AMD MD5: bca2e4f0cc933b5d6ae1b607db4c33fd. No DB schema changes.
    //   version.php → 2026041000060.
    if ($oldversion < 2026041000060) {
        upgrade_plugin_savepoint(true, 2026041000060, 'local', 'aiquizmaker');
    }

    // v3.16.61 - BUG FIX (FIX-QM-GAPSELECT-GROUPS): aiquizmaker_create_gapselect_question() in ajax.php
    //   was storing the group number in the wrong question_answers field. Moodle's qtype_gapselect_base
    //   uses fraction to determine group membership (get_question_options() groups answers by fraction),
    //   but our code set fraction=0 for ALL choices and put the group number in feedback instead.
    //   Result: every [[n]] blank showed ALL choices from every group (all shared "group 0").
    //   Fix: $answer->fraction = $groupnum (1, 2, 3 … matching [[1]], [[2]], [[3]]);
    //        $answer->feedback = ''; $answer->feedbackformat = FORMAT_HTML.
    //   XML export path was already correct (<group>N</group> maps to fraction on import).
    //   PHP-only change. No AMD changes. No DB schema changes. ajax.php updated.
    //   version.php → 2026041000061.
    if ($oldversion < 2026041000061) {
        upgrade_plugin_savepoint(true, 2026041000061, 'local', 'aiquizmaker');
    }

    // v3.16.62 - FIX-QM-GAPSELECT-FEEDBACK: Corrected gapselect answer storage. v3.16.61
    //   incorrectly set fraction=$groupnum and feedback='' — Moodle qtype_gapselect_base
    //   groups choices by the FEEDBACK field (not fraction), so all choices had feedback=''
    //   and appeared in the same group (Group A) regardless of which blank they belonged to.
    //   Fix: fraction=0, feedback=(string)$groupnum. PHP-only change to ajax.php.
    //   No DB schema changes. version.php → 2026041400062.
    if ($oldversion < 2026041400062) {
        upgrade_plugin_savepoint(true, 2026041400062, 'local', 'aiquizmaker');
    }

    // v3.16.63 - BUG FIX (x3):
    //   BUG-QM-GAPSELECT-GROUPS (ajax.php + JS): Select Missing Words both drop-downs showed
    //     identical choices. AI API returns selectOptions as a 1-based JSON object {"1":[...],"2":[...]};
    //     PHP json_decode turned those into integer keys 1 and 2 so $groupnum = $groupidx + 1 gave
    //     groups 2 and 3 instead of 1 and 2, leaving Group 1 empty and Moodle rendering both [[1]]
    //     and [[2]] from the same non-empty group. Fix: JS normalises to 0-indexed array on receipt;
    //     PHP DB creator and XML builder defensively re-index if keys start at 1.
    //   BUG-QM-CRITERIA-QTEXT-ALLTYPES (ShortAnswer): criteriaReference removed from question HTML
    //     body; stored in idnumber only.
    //   BUG-QM-CRITERIA-QTEXT-XML (MCQ XML): criteria injection removed from XML export question text.
    //   No DB schema changes. version.php → 2026041400063.
    if ($oldversion < 2026041400063) {
        upgrade_plugin_savepoint(true, 2026041400063, 'local', 'aiquizmaker');
    }

    // v3.16.64 - VERSION BUMP: Clean release. All fixes from v3.16.63 confirmed in ZIP.
    //   No code changes. No DB schema changes. version.php → 2026041500064.
    if ($oldversion < 2026041500064) {
        upgrade_plugin_savepoint(true, 2026041500064, 'local', 'aiquizmaker');
    }

    // v3.16.65 - BUG FIX (x3): BUG-QM-GAPSELECT-FRACTION + BUG-QM-GAPSELECT-PROMPT-GROUPING +
    //   BUG-QM-GAPSELECT-DISTRACTOR-COUNT. All three bugs affect "Select Missing Words" (gapselect)
    //   question generation quality and correctness.
    //   BUG-QM-GAPSELECT-FRACTION (ajax.php): fraction=0 was set for ALL choices in every group.
    //   Moodle's qtype_gapselect_base::is_correct_response() checks fraction > 0 to determine the
    //   correct answer — with fraction=0 for all, no student response was ever graded as correct,
    //   and Moodle's correct_response() display in review mode could not identify the right answer.
    //   Fix: first choice in each group (the correct answer) now has fraction=1.0; distractors keep
    //   fraction=0. No DB schema changes.
    //   BUG-QM-GAPSELECT-PROMPT-GROUPING (server/routes.ts, both gapselect prompt sections): AI
    //   prompt did not explicitly explain that all choices for a blank (correct + its distractors)
    //   must live in the SAME sub-array. AI was occasionally placing all correct answers first in
    //   group 1 followed by distractors in subsequent groups, causing correct answers to appear at
    //   sequential positions [[1]] and [[2]] instead of [[1]] and [[4]] (for 3-choice groups).
    //   Fix: added explicit BAD/GOOD examples with numbered groups; made grouping rules unambiguous.
    //   BUG-QM-GAPSELECT-DISTRACTOR-COUNT (server/routes.ts): prompt hard-coded "2-3 distractors"
    //   contradicting the requirement for any number ≥ 1. Fix: prompt now says "1 or more distractors;
    //   2-3 recommended" allowing flexible distractor counts per the design intent.
    //   AMD unchanged. version.php → 2026041500065.
    if ($oldversion < 2026041500065) {
        upgrade_plugin_savepoint(true, 2026041500065, 'local', 'aiquizmaker');
    }

    // v3.16.67 - THREE BUG FIXES for "Use My Own Questions" + section headings:
    //
    // (1) QUESTION NUMBERING: Questions starting after a section heading were numbered from
    //     Q2 instead of Q1. Root cause: buildQuestionCard used index+1 (position in the
    //     full questions array including descriptions). Fix: displayResults now computes a
    //     running questionCounter that only increments for non-description items and passes
    //     it to buildQuestionCard as a separate questionNum argument.
    //
    // (2) SECTION HEADINGS NOT ADDED IN SEQUENCE: The description card HTML had no checkbox,
    //     so getSelectedQuestions() (which queries .aiquizmaker-question-checkbox:checked)
    //     never included descriptions in the selection — they were silently dropped before
    //     the JSON was sent to addtoquiz. PHP addtoquiz already supported description type.
    //     Fix: description cards now render a checkbox (same class). getSelectedQuestions()
    //     and the addtoquiz payload therefore include headings at their correct positions.
    //
    // (3) SELECTION COUNT MISMATCH + BUTTON LABEL: updateAddToQuizButtonLabel used
    //     lastGeneratedQuestions.length for total (includes descriptions) but
    //     .aiquizmaker-question-checkbox:checked for selected (excluded descriptions — no
    //     checkbox), producing "4 of 6 selected" / "Add 4 of 6 Questions to Quiz" when only
    //     4 questions + 2 headings = 6 items exist. With description checkboxes added, both
    //     counts are now consistent. Button label changed to "Add X of Y to Quiz" (always).
    //
    // No DB schema changes. AMD only (aiquizmaker.js + build copies).
    // version.php → 2026041700067.
    if ($oldversion < 2026041700067) {
        upgrade_plugin_savepoint(true, 2026041700067, 'local', 'aiquizmaker');
    }

    // v3.16.68 - FIX: Added standaloneBlocklist to isHeadingLine() to prevent generic all-caps
    //   words (INFORMATION, NOTE, WARNING, OVERVIEW, etc.) from being falsely detected as section
    //   heading description cards. Removed redundant "Section Heading" label text from the
    //   description card preview — the "Description" badge already identifies the card type.
    //   AMD only (aiquizmaker.js + build copies). No DB schema changes. version.php → 2026041700068.
    if ($oldversion < 2026041700068) {
        upgrade_plugin_savepoint(true, 2026041700068, 'local', 'aiquizmaker');
    }

    // v3.16.69 - FIX: Question name and question text double-up in Moodle question bank.
    //   All question creator functions now use sequential label Q1/Q2/Q3 as the question name
    //   when a _qnum index is available. No DB schema changes. version.php → 2026041700069.
    if ($oldversion < 2026041700069) {
        upgrade_plugin_savepoint(true, 2026041700069, 'local', 'aiquizmaker');
    }

    // v3.16.70 - FEAT: Tester feedback fixes — question uniqueness, per-type prompt rules.
    //   (1) UNIQUENESS: Added explicit uniqueness rules to all AI prompts (server typeInstructions,
    //       systemPrompt, buildChatGPTPrompt in aiquizmaker.js) so every question tests a different
    //       concept — no overlap or repetition across questions in a set.
    //   (2) NO PREFIX IN QUESTION TEXT: Added rule to all prompts that questionText must not contain
    //       question number prefixes (Q1., 1., Question 1:). Added stripQuestionPrefix() helper in
    //       aiquizmaker.js that strips these prefixes when parsing pasted questions from the Own
    //       Questions tab — block-based and line-by-line parsing both apply the strip.
    //   (3) ESSAY RUBRIC: Added DISTINCT CRITERIA and SEPARATE POINTS rules to RUBRIC RULES section
    //       so each criterion covers a different aspect and never restates the question text.
    //   (4) TRUE/FALSE FEEDBACK: trueFeedback/falseFeedback must explain WHY the selection is right
    //       or wrong — not just repeat the statement. Both the typeInstruction and the JSON FORMAT
    //       template updated with explain-WHY rules.
    //   (5) MCQ FEEDBACK: Each distractor must have a different, specific misconception explanation.
    //       NEVER use "Option A/B/C/D" labels in feedback text. Updated typeInstruction and
    //       QUESTION TYPE RULES.
    //   (6) GAP SELECT / MISSING WORDS: questionText is brief instruction only; sentenceWithGaps
    //       is the actual sentence (must be different from questionText). Feedback explains the
    //       concept rather than repeating the completed sentence.
    //   (7) SHORT ANSWER: Answer must be 1-5 words (single word or short phrase), not a full
    //       sentence. Updated typeInstruction with 5-word max rule.
    //   AMD only (aiquizmaker.js + build copies). No DB schema changes.
    //   version.php → 2026041700070.
    if ($oldversion < 2026041700070) {
        upgrade_plugin_savepoint(true, 2026041700070, 'local', 'aiquizmaker');
    }

    // v3.16.71 - SERVER-SIDE FIX: Question repetition prevention across all question types in
    //   both "Generate from Criteria" and the self-marking quiz generator.
    //   ROOT CAUSE: Each question was generated via an independent OpenAI API call with no
    //   awareness of other questions already produced in the same batch. AI would reuse the same
    //   concept, scenario, or question stem across multiple questions.
    //   FIX 1 (/api/quizmaker/generate loop): Added generatedQuestionTexts[] tracker. Before
    //   each call, the AI receives an "ALREADY GENERATED IN THIS SET" block listing all prior
    //   question texts (first 120 chars each). Applies to MCQ, True/False, Matching, Fill in the
    //   Blanks, Select Missing Words, Short Answer.
    //   FIX 2 (/api/generate-essays criteria route — both essay-only and mixed-type branches):
    //   Added DIVERSITY REQUIREMENT block when questionsCount > 1, directing the AI to plan each
    //   question across a different dimension: (Q1) concept/fact, (Q2) scenario/application,
    //   (Q3+) further distinct angle. Self-check: "if knowing Q1's answer auto-answers Q2, change Q2."
    //   Server-side only (server/routes.ts). No PHP or AMD changes. No DB schema changes.
    //   version.php → 2026041700071.
    if ($oldversion < 2026041700071) {
        upgrade_plugin_savepoint(true, 2026041700071, 'local', 'aiquizmaker');
    }

    // v3.16.72 - AGGRESSIVE ANTI-REPETITION FIX: Deep audit and fix of question repetition
    //   across ALL 6 question types (Essay, MCQ, True/False, Matching, Select Missing Words,
    //   Fill in the Blanks) for BOTH input methods (Generate from Criteria + Use My Own Questions).
    //   Root causes identified:
    //   (A) "Generate from Criteria" non-essay QUESTION DESIGN RULES block was missing an explicit
    //   uniqueness rule — the DIVERSITY REQUIREMENT only fired when questionsCount > 1 and was at
    //   the bottom of the prompt. FIX: Added UNIQUENESS — MANDATORY as item 5 in QUESTION DESIGN
    //   RULES. Strengthened CRITICAL RULES: Matching → COMPLETELY DIFFERENT set of 4 terms across
    //   questions; Fill-in-the-Blank → DIFFERENT key term per question; UNIQUENESS rule now
    //   includes the self-check ("change Q2 if knowing Q1 auto-answers it").
    //   (B) "Use My Own Questions" — all 6 types ran via Promise.all()+pLimit(5), fully parallel
    //   with ZERO cross-question awareness. FIX: Build allQuestionsContext from all teacher-provided
    //   question texts BEFORE the parallel loop; inject into every processQuestion call as the
    //   "OTHER QUESTIONS IN THIS BATCH — AVOID OVERLAP" block. All 6 type branches (MCQ, T/F,
    //   Matching, gapselect, shortanswer, essay) now receive this context. Per-type improvements:
    //   MCQ distractors specific to THIS question (no generic options); T/F statements specific to
    //   THIS content; Matching requires specific technical terms (no standalone "Safety"); gapselect
    //   gap words must be specific technical terms (not "correct"/"appropriate"); shortanswer blank
    //   must be a specific technical term 1-5 words.
    //   (C) /api/quizmaker/generate already correctly fixed in v3.16.71. No changes.
    //   Server-side only (server/routes.ts). No PHP, AMD, or DB schema changes.
    //   version.php → 2026041700072.
    if ($oldversion < 2026041700072) {
        upgrade_plugin_savepoint(true, 2026041700072, 'local', 'aiquizmaker');
    }

    // v3.16.73: BUG FIX — gapselect count-mismatch validation (PHP only, no DB schema change).
    if ($oldversion < 2026041700073) {
        upgrade_plugin_savepoint(true, 2026041700073, 'local', 'aiquizmaker');
    }

    // v3.16.74: PROMPT ENHANCEMENT — stronger uniqueness, name diversity pool, cross-type
    //   concept separation, 70% overlap rule. Server/routes.ts prompts only. No DB schema change.
    //   version.php → 2026041700074.
    if ($oldversion < 2026041700074) {
        upgrade_plugin_savepoint(true, 2026041700074, 'local', 'aiquizmaker');
    }

    // v3.16.76: FIX-DESCRIPTION-QNUMBER — description (section heading) questions were consuming
    //   a Q number in the question name (e.g. "Q1 PART A: ...") when they should display as plain
    //   heading text with no Q prefix. Real questions now count from Q1 skipping descriptions, so
    //   the sequence is: "Part A" (no number) → Q1 → Q2 → Q3 → "Part B" (no number) → Q4 → etc.
    //   Fixed in three loops: aiquizmaker_generate_moodle_xml(), createquestions action, addtoquiz
    //   action. Also hardened aiquizmaker_create_description_question() to always use heading text
    //   as name. No DB schema changes. PHP only (ajax.php). version.php → 2026041700076.
    if ($oldversion < 2026041700076) {
        upgrade_plugin_savepoint(true, 2026041700076, 'local', 'aiquizmaker');
    }

    // v3.16.77 — FIX-DESCRIPTION-INFO-ALIGN:
    //   In the Moodle quiz preview, the "Information" label for section heading (description)
    //   questions floated misaligned — above and to the left of the content box rather than
    //   sitting alongside it in the standard 2-column quiz layout.
    //   Root cause: questiontext used <p style="margin:0;font-weight:600"> which collapsed the
    //   paragraph's natural margin/spacing. The reduced content area height caused Moodle's left-
    //   column "Information" label to appear vertically out of sync with the content box.
    //   Fix: changed to <p><strong>text</strong></p> — natural paragraph margins are preserved,
    //   the content box gets the expected height, and the "Information" label aligns correctly.
    //   PHP only (ajax.php). No AMD or DB schema changes.
    //   version.php → 2026042100077.
    if ($oldversion < 2026042100077) {
        upgrade_plugin_savepoint(true, 2026042100077, 'local', 'aiquizmaker');
    }

    // v3.16.75: FIX-DESCRIPTION-HEADING — section heading (description) question HTML simplified.
    //   The previous HTML stored a styled <div> with a <h4> heading inside. Moodle's own
    //   qtype_description renderer already wraps the content with an "Information" label/block,
    //   so our nested styled box created a double-heading effect that looked oversized and caused
    //   the content to appear to overflow the container. Fix: questiontext now uses a plain bold
    //   paragraph <p style="margin:0;font-weight:600">...</p>. XML export path also updated to
    //   use <p><strong>...</strong></p> for consistency. No DB schema changes. PHP only (ajax.php).
    //   version.php → 2026041700075.
    if ($oldversion < 2026041700075) {
        upgrade_plugin_savepoint(true, 2026041700075, 'local', 'aiquizmaker');
    }

    // v3.16.78 — EXTRACT CRITERIA PROMPT REWRITE (server-side AI prompt fix; no plugin
    //   file changes beyond version metadata). Per spec dated 21 Apr 2026, the Extract
    //   Criteria function was producing wrong output: it was copying source labels
    //   verbatim (e.g. emitting "GOOD 1" / "BAD 1" when the pasted content used
    //   "GOOD: ..." / "BAD: ..." rubric examples) and was preserving source codes
    //   like "KE1.1" / "PC2.3". Root cause: the system prompt at the upstream API
    //   (lms-labs.com /api/extract-criteria) instructed the AI to "Extract"
    //   and to "Preserve any meaningful codes or identifiers (e.g., KE1.1, PC2.3,
    //   1.1) at the start of the criterion". This caused both the verbatim copying
    //   and the label preservation. Fix: prompt rewritten to INTERPRET the content
    //   as a whole and GENERATE formal competency-style criteria using assessable
    //   verbs (Identify, Demonstrate, Apply, Evaluate, etc.). Source labels,
    //   headings, bullet markers, codes, and rubric examples are now explicitly
    //   forbidden from the output. A worked example is included in the prompt
    //   showing "GOOD: Uses PPE / BAD: doesn't follow safety" being correctly
    //   converted to formal positive competency statements.
    //   Plugin client behaviour unchanged — the plugin only forwards the request.
    //   No AMD changes. No DB schema changes. version.php → 2026042100078.
    if ($oldversion < 2026042100078) {
        upgrade_plugin_savepoint(true, 2026042100078, 'local', 'aiquizmaker');
    }

    // v3.16.79 — BUG-QM-GAPSELECT-DUPES (PHP + server prompts).
    //   Problem: For "Select Missing Words" (gapselect) questions, the AI occasionally
    //   returned the SAME word twice within a single blank's selectOptions sub-array —
    //   either the correct answer repeated, a distractor repeated, or the correct
    //   answer also appearing as one of its own distractors. When this slipped through
    //   to Moodle, the dropdown for that blank rendered the same option twice, which
    //   was confusing for students and ambiguous for grading. The defect was random
    //   (one question in a batch, not all of them) because it depended on which key
    //   terms the AI chose for the gaps and how it generated distractors.
    //   Fix (PHP, ajax.php — five codepaths, defence in depth):
    //     1. New helper aiquizmaker_dedupe_gapselect_group($group) — deduplicates a
    //        single group case-insensitively and trim-insensitively, keeping the
    //        FIRST occurrence so the correct-answer-at-index-0 convention is preserved.
    //        Also strips empty/whitespace-only entries.
    //     2. New helper aiquizmaker_dedupe_gapselect_all($selectoptions) — applies #1
    //        to every group in the selectOptions array.
    //     3. Applied at the criteria-mode normalisation site (createquestions / addtoquiz).
    //     4. Applied at the use-my-own-questions normalisation site
    //        (generate-from-questions path).
    //     5. Applied at the regenerate path so per-question regeneration is also clean.
    //     6. Applied inside aiquizmaker_create_gapselect_question() immediately before
    //        writing question_answers rows — defensive guard so older or third-party
    //        AI payloads can never write duplicates to the Moodle DB.
    //     7. Applied inside aiquizmaker_xml_gapselect_block() for the Moodle XML
    //        export path so exported .xml files never contain duplicate dropdown
    //        options for a single blank.
    //   Fix (server-side prompts, server/routes.ts — three gapselect prompt blocks):
    //     1. Criteria-mode gapselect prompt — added explicit
    //        "NO DUPLICATES WITHIN A BLANK" rule.
    //     2. Use-my-own-questions gapselect prompt — same rule added.
    //     3. Self-marking quiz generator gapselect prompt — equivalent rule added
    //        (worded for the {group, text, isCorrect} schema variant).
    //   Each rule states: every option in a single sub-array MUST be unique, the
    //   correct answer must NEVER appear as one of its own distractors, distractors
    //   must NEVER repeat, and comparison is case- and whitespace-insensitive.
    //   No DB schema changes. AMD unchanged. PHP + server prompts only.
    //   version.php → 2026042100079.
    if ($oldversion < 2026042100079) {
        upgrade_plugin_savepoint(true, 2026042100079, 'local', 'aiquizmaker');
    }

    // v3.16.80 — REGRESSION FIX: gapselect blank numbering scheme restored to JUMP-BY-3.
    //   Boss reports the "Select Missing Words" question type was generating blanks
    //   numbered sequentially ([[1]], [[2]], [[3]]) instead of the required JUMP-BY-3
    //   pattern ([[1]], [[4]], [[7]], [[10]]). The boss confirms this had been fixed
    //   previously and has now regressed.
    //   ROOT CAUSE — WHY THIS HAPPENED AGAIN:
    //     v3.16.65 added PHP support for jump-by-3 placeholder numbering (the DB writer
    //     and XML writer derive group numbers from the actual [[N]] tokens in the
    //     question text via array_map('intval', preg_match_all output)), and at that
    //     time the prompts were also asking for jump-by-3 output.
    //     Then v3.16.73 deliberately CHANGED THE PROMPTS to ask for sequential
    //     [[1]] [[2]] [[3]] numbering, with the rationale that "jump-by-3 was the
    //     primary trigger for AI count mismatches" between selectOptions sub-arrays
    //     and placeholder count. That prompt change also added a count-match guardrail
    //     ("the number of selectOptions sub-arrays MUST exactly equal the number of
    //     [[n]] gaps"). At the time, sequential numbering was thought to be safer.
    //     The boss tested the jump-by-3 behaviour shortly after v3.16.65 shipped and
    //     was satisfied. v3.16.73 (which switched to sequential to chase a different
    //     bug) silently regressed the boss's preferred behaviour. v3.16.74 through
    //     v3.16.79 carried the sequential prompts forward without revisiting them.
    //   Fix in v3.16.80 (server prompts only — three gapselect prompt blocks in
    //   server/routes.ts):
    //     1. Criteria-mode gapselect prompt (selectOptions array schema) — restored
    //        to jump-by-3 with a fully worked 3-blank example showing
    //        [[1]] [[4]] [[7]] in the sentence and 3 sub-arrays in selectOptions.
    //     2. Use-my-own-questions gapselect prompt (selectOptions array schema) —
    //        same restoration with an explicit "NEVER use [[2]] [[3]] [[5]]..." rule.
    //     3. Self-marking quiz generator gapselect prompt ({group, text, isCorrect}
    //        schema) — example sentence updated to "The [[1]] is responsible for...
    //        [[4]]..." and the choice-group example updated so the "group" field
    //        matches the placeholder numbers (1 and 4, not 1 and 2). Three
    //        choices per group (1 correct + 2 distractors).
    //     The count-match guardrail introduced in v3.16.73 is RETAINED in both PHP
    //     paths: the DB writer (aiquizmaker_create_gapselect_question) and the XML
    //     writer (aiquizmaker_xml_gapselect_block) still validate that the number of
    //     selectOptions sub-arrays equals the number of [[N]] placeholders in the
    //     sentence (truncating excess, refusing to create on shortfall). Combined
    //     with the new very-explicit jump-by-3 worked example in the prompt, this
    //     prevents the count-mismatch issue that originally motivated v3.16.73.
    //     The dedupe helper from v3.16.79 is also retained.
    //   No PHP behaviour change. No AMD changes. No DB schema changes.
    //   Server prompts only. version.php → 2026042100080.
    if ($oldversion < 2026042100080) {
        upgrade_plugin_savepoint(true, 2026042100080, 'local', 'aiquizmaker');
    }

    // v3.16.81 — FIX-QM-WCTX-OFF + FIX-QM-NAME-DIVERSITY.
    //   (1) Workplace-context toggle OFF was still producing workplace-scenario-style
    //       questions because the server prompt's defaults included "scenario" in
    //       selfMarkingStyles/questionFormats and the VET level blocks (cert1-adv_diploma)
    //       hard-coded "Prefer scenario-based workplace tasks". Added explicit
    //       workplaceContextEnabled boolean to the /api/generate-essays and
    //       /api/generate-from-questions schemas; when false, scenario is stripped from
    //       both arrays and a hard NON-NEGOTIABLE rule forbids workplace scenarios,
    //       named workers/colleagues/customers, and "You are working as..." stems.
    //       ajax.php now passes the flag in all 3 generate payloads.
    //   (2) Repetitive names ("Sarah"/"Emma" appearing 4 of 8 times). Added a NAME
    //       DIVERSITY non-negotiable rule to both VET and Academic system prompts
    //       forbidding name reuse and over-used defaults, with a 26-name diverse pool.
    //   No DB schema changes. version.php → 2026042200081.
    if ($oldversion < 2026042200081) {
        upgrade_plugin_savepoint(true, 2026042200081, 'local', 'aiquizmaker');
    }

    // v3.16.82 — FIX-QM-WCTX-PROMPT-BYPASS + FIX-QM-GAPSELECT-NOCONTEXT.
    //   (1) Even after v3.16.81's WORKPLACE CONTEXT IS DISABLED system-prompt rule,
    //       several user-prompt blocks still instructed the AI to use scenarios:
    //       INDUSTRY AUTHENTICITY REQUIREMENTS only checked industryContext/hasContext
    //       (not workplaceContextEnabled), so a set country/industry caused scenario
    //       instructions to fire. The mixed-types item 3 defaulted to "practical,
    //       professional scenarios". Both now gate on workplaceContextEnabled, falling
    //       to explicit no-scenario/no-names/definition-style instructions when false.
    //   (2) "Select Missing Words" (gapselect) format hard-coded a scenario paragraph
    //       in questionText regardless of workplaceContextEnabled — fixed in both the
    //       worked example and the CRITICAL GROUPING RULES section. When context is
    //       OFF the format switches to a plain concept question (no scenario, no name).
    //       Also corrects old [[1]][[2]][[3]] sequential numbering reference in the
    //       mixed-types CRITICAL RULES to correct jump-by-3 ([[1]][[4]][[7]]).
    //   Server prompts (server/routes.ts) only. No PHP, AMD or DB schema changes.
    //   version.php → 2026042200082.
    if ($oldversion < 2026042200082) {
        upgrade_plugin_savepoint(true, 2026042200082, 'local', 'aiquizmaker');
    }

    // v3.16.83: FIX-QM-WCTX-USERPROMPT — Comprehensive fix for remaining workplace-context-OFF
    //   scenario leaks across ALL question types. Root cause: user prompt contained unconditional
    //   instructions to use scenarios that overrode the system prompt's DISABLED rule. Fixed in
    //   11 locations across essay-only branch, mixed/non-essay branch, and GFQ endpoint:
    //   (1) Essay QUESTION DESIGN RULES #4 "Apply/Demonstrate" conditional; (2) Essay QUESTION
    //   DESIGN RULES #6 scenario instruction gated on workplaceContextEnabled; (3) Essay QUESTION
    //   DESIGN RULES #8 UNIQUE SCENARIO NAMES conditional; (4) Non-essay QUESTION DESIGN RULES #2
    //   "multichoice with scenario" conditional; (5) Non-essay QUESTION DESIGN RULES #6 UNIQUE
    //   SCENARIO NAMES conditional; (6) Non-essay CRITICAL RULES NAMES IN SCENARIOS conditional;
    //   (7) Both DIVERSITY REQUIREMENT blocks updated — "scenario" conditional, UNIQUE SCENARIO
    //   NAMES footer replaced with NO SCENARIOS OR NAMES when OFF; (8) pastedContentSection
    //   "Scenarios and examples" conditional; (9) GFQ typeSystemPrompt now includes hard
    //   NON-NEGOTIABLE WORKPLACE CONTEXT IS DISABLED rule in system prompt when OFF; (10) GFQ
    //   gapselect questionText JSON example conditional; (11) GFQ gapselect CRITICAL GROUPING
    //   RULE conditional. Server prompts (server/routes.ts) only. No PHP, AMD or DB schema changes.
    //   version.php → 2026042200083.
    if ($oldversion < 2026042200083) {
        upgrade_plugin_savepoint(true, 2026042200083, 'local', 'aiquizmaker');
    }

    // v3.16.84 - FIX-QM-NO-IMAGE-GEN: Removed image generation from the AI Quiz Maker
    //   content pipeline. generateImage() was being called for every title slide and content
    //   slide but images were never displayed in the plugin UI. Removed imagePrompt fields
    //   from the OpenAI prompt, the JSON template examples, and both generateImage() call
    //   blocks. Server-side only (server/routes.ts). No PHP, AMD or DB schema changes.
    //   version.php → 2026042200084.
    if ($oldversion < 2026042200084) {
        upgrade_plugin_savepoint(true, 2026042200084, 'local', 'aiquizmaker');
    }

    // v3.16.85 - MAINTENANCE: Full AMD build audit completed. All AMD files
    //   (aiquizmaker.js, quizbutton.js) verified triple-match clean — src, build,
    //   and .min.js all have identical MD5 hashes. No PHP, AMD or DB schema changes.
    //   version.php → 2026042200085.
    if ($oldversion < 2026042200085) {
        upgrade_plugin_savepoint(true, 2026042200085, 'local', 'aiquizmaker');
    }

    // v3.16.86 - FIX-QM-WCTX-NAMES-UNCONDITIONAL: Gated NAME DIVERSITY rule on
    //   workplaceContextEnabled in both academic and VET system prompts. When
    //   workplace context is OFF, the AI now receives an absolute NO PERSON NAMES
    //   rule instead of a names rotation pool, preventing named characters (e.g.
    //   "Marcus") from appearing in questions. Also gated scenario name rules in
    //   topic/document quiz flows, generate-from-questions cross-batch block, and
    //   topic quiz nameInstruction. No PHP, AMD or DB schema changes.
    //   version.php → 2026042200086.
    if ($oldversion < 2026042200086) {
        upgrade_plugin_savepoint(true, 2026042200086, 'local', 'aiquizmaker');
    }
    // v3.16.87: AMD ENCODING FIX: All non-ASCII characters (em dashes, arrows, box-drawing chars, ellipsis, bullets, emoji, accented Latin) scrubbed from all AMD JS files (amd/src, amd/build, amd/build/*.min.js). Root cause of Moodle primary/secondary navigation menus disappearing site-wide: non-ASCII bytes in any installed plugin's AMD file cause a SyntaxError inside RequireJS's first.js bundle, throwing "No define call for core/first" and aborting the entire AMD module chain. No PHP, DB schema, or functional changes in this release.
    if ($oldversion < 2026042200087) {
        upgrade_plugin_savepoint(true, 2026042200087, 'local', 'aiquizmaker');
    }

    // v3.16.88: FIX-AIQM-REDECL: Wrapped local_aiquizmaker_extend_navigation() and
    //   local_aiquizmaker_extend_settings_navigation() in function_exists() guards in
    //   lib.php. Sites with old local_essaymaker plugin still installed alongside
    //   local_aiquizmaker got a fatal PHP 500 "Cannot redeclare" error. PHP-only change
    //   to lib.php. No AMD, CSS, or DB schema changes.
    if ($oldversion < 2026060400088) {
        upgrade_plugin_savepoint(true, 2026060400088, 'local', 'aiquizmaker');
    }

    if ($oldversion < 2026072300089) {
        // FIX-API-URL: Changed API_BASE_URL in classes/api_client.php from
        // lms-labs.com to lms-labs.com (working DNS — lms-labs.com has no DNS
        // records so all generate-essays/credits API calls were silently failing).
        if (function_exists('opcache_invalidate')) {
            $_pluginDir = realpath(__DIR__ . '/..');
            foreach (['classes/api_client.php', 'version.php', 'db/upgrade.php'] as $_f) {
                $_full = $_pluginDir . '/' . $_f;
                if (file_exists($_full)) {
                    opcache_invalidate($_full, true);
                }
            }
        } elseif (function_exists('opcache_reset')) {
            opcache_reset();
        }
        upgrade_plugin_savepoint(true, 2026072300089, 'local', 'aiquizmaker');
    }

    if ($oldversion < 2026072300090) {
        // FIX-API-DOMAIN: Rebuilt ZIP with corrected API endpoint (lms-labs.com -> lms-labs.com).
        // ajax.php and index.php now call lms-labs.com for all credit/API operations.
        if (function_exists('opcache_invalidate')) {
            $_pluginDir = realpath(__DIR__ . '/..');
            foreach (['ajax.php', 'index.php', 'version.php', 'db/upgrade.php'] as $_f) {
                $_full = $_pluginDir . '/' . $_f;
                if (file_exists($_full)) { opcache_invalidate($_full, true); }
            }
        } elseif (function_exists('opcache_reset')) { opcache_reset(); }
        upgrade_plugin_savepoint(true, 2026072300090, 'local', 'aiquizmaker');
    }

    if ($oldversion < 2026072300091) {
        // FIX-API-DOMAIN: Reverted API endpoint to lms-labs.com (correct domain).
        // essaygraderai.app was the original single-plugin domain; lms-labs.com is correct.
        if (function_exists('opcache_invalidate')) {
            $_pluginDir = realpath(__DIR__ . '/..');
            foreach (['version.php', 'db/upgrade.php'] as $_f) {
                $_full = $_pluginDir . '/' . $_f;
                if (file_exists($_full)) { opcache_invalidate($_full, true); }
            }
        } elseif (function_exists('opcache_reset')) { opcache_reset(); }
        upgrade_plugin_savepoint(true, 2026072300091, 'local', 'aiquizmaker');
    }

    if ($oldversion < 2026072300092) {
        // Domain update: lms-labs.com → lms-labs.com
        if (function_exists('opcache_invalidate')) {
            $_pluginDir = realpath(__DIR__ . '/..');
            foreach (['version.php', 'lib.php', 'db/upgrade.php'] as $_f) {
                $_full = $_pluginDir . '/' . $_f;
                if (file_exists($_full)) { opcache_invalidate($_full, true); }
            }
        } elseif (function_exists('opcache_reset')) { opcache_reset(); }
        upgrade_plugin_savepoint(true, 2026072300092, 'local', 'aiquizmaker');
    }

    return true;
}