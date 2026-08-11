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
 * AI Quiz Maker v3.16.84 - FIX-QM-NO-IMAGE-GEN: Removed image generation from the
 *   AI Quiz Maker content pipeline. Images were never part of the quiz-maker student
 *   experience, but generateImage() was still being called for every title slide and
 *   every content slide — consuming Imagen 4 / OpenAI quota, slowing module generation,
 *   and producing cartoon-style images that appeared nowhere in the plugin UI. Fix:
 *   removed all imagePrompt fields from the OpenAI content-generation prompt, removed
 *   the imagePrompt example template entries, removed the two generateImage() call blocks
 *   (title slide + content slides), and set imageUrl/imageAlt to empty strings on all
 *   slide objects. Server-side only (server/routes.ts). No PHP, AMD or DB schema changes.
 *   version.php → 2026042200084.
 *
 * AI Quiz Maker v3.16.83 - FIX-QM-WCTX-USERPROMPT: Comprehensive fix for remaining
 *   workplace-context-OFF scenario leaks across ALL question types in both generation
 *   modes (Generate from Criteria + Use My Own Questions). Root cause: the system prompt
 *   correctly contained a WORKPLACE CONTEXT IS DISABLED rule, but the user prompt
 *   contained multiple direct, unconditional instructions to use scenarios that overrode
 *   it. The user prompt takes priority in AI context, so the system prompt rule was
 *   ineffective. Fixed locations (server/routes.ts, prompts only):
 *   (1) Essay-only QUESTION DESIGN RULES #4 "Apply/Demonstrate" now says "direct
 *   application question — NO scenario or situational framing" when context is OFF.
 *   (2) Essay-only QUESTION DESIGN RULES #6 "Use realistic [industry] workplace
 *   scenarios where appropriate" now gates on workplaceContextEnabled first — when OFF
 *   emits an explicit ⛔ WORKPLACE CONTEXT IS DISABLED block that overrides all styles.
 *   (3) Essay-only QUESTION DESIGN RULES #8 UNIQUE SCENARIO NAMES block now replaced
 *   with "NO NAMES OR ROLES — Do NOT include named persons..." when OFF.
 *   (4) Mixed/non-essay QUESTION DESIGN RULES #2 "Apply/Demonstrate → multichoice with
 *   scenario" is now conditional.
 *   (5) Mixed/non-essay QUESTION DESIGN RULES #6 UNIQUE SCENARIO NAMES is now
 *   conditional.
 *   (6) Mixed/non-essay CRITICAL RULES "NAMES IN SCENARIOS" is now conditional.
 *   (7) Both DIVERSITY REQUIREMENT blocks (essay + non-essay) updated: "scenario" in
 *   "Question 2: a different scenario, application..." is now conditional; UNIQUE
 *   SCENARIO NAMES footer replaced with NO SCENARIOS OR NAMES when OFF.
 *   (8) pastedContentSection "Scenarios and examples should reflect details from this
 *   content" now gates on workplaceContextEnabled.
 *   (9) GFQ (generate-from-questions) typeSystemPrompt now includes a hard
 *   NON-NEGOTIABLE WORKPLACE CONTEXT IS DISABLED rule in the SYSTEM PROMPT (not just
 *   the user prompt) when context is OFF. This is the authoritative fix for GFQ.
 *   (10) GFQ gapselect JSON template questionText example is now conditional.
 *   (11) GFQ gapselect CRITICAL GROUPING RULE line is now conditional (scenario-free
 *   format when OFF, with explicit "QUESTION\n\nGAPS" format instruction).
 *   No PHP, AMD, or DB schema changes. Server prompts (server/routes.ts) only.
 *   version.php → 2026042200083.
 *
 * AI Quiz Maker v3.16.82 - FIX-QM-WCTX-PROMPT-BYPASS + FIX-QM-GAPSELECT-NOCONTEXT:
 *   Two further issues with Workplace Context OFF. (1) FIX-QM-WCTX-PROMPT-BYPASS:
 *   Although v3.16.81 added the workplaceContextEnabled flag and the WORKPLACE CONTEXT
 *   IS DISABLED rule to system prompts, several places in the user prompt still
 *   instructed the AI to use workplace scenarios regardless of the flag — the
 *   INDUSTRY AUTHENTICITY REQUIREMENTS block only checked industryContext/hasContext
 *   and not workplaceContextEnabled, so if the user had a country or industry set the
 *   AI still received "Use realistic workplace scenarios from typical industries".
 *   Also the mixed-types QUESTION DESIGN RULES item 3 defaulted to "practical,
 *   professional scenarios" rather than explicitly forbidding scenarios. All three
 *   checks now gate on workplaceContextEnabled first, falling through to
 *   DEFINITION-STYLE / no-scenario-no-names instructions when the flag is false.
 *   (2) FIX-QM-GAPSELECT-NOCONTEXT: The "Select Missing Words" (gapselect) format
 *   hard-coded questionText as TWO parts with a scenario paragraph in both the
 *   criteria-mode worked example and the CRITICAL GROUPING RULES section — this
 *   applied regardless of workplaceContextEnabled. When context is OFF, the format
 *   now uses a single clear concept question (no scenario, no named person) plus
 *   the gap sentence. The worked example in the prompt switches to a vocabulary/
 *   definition style (hazard/risk/elimination) when workplace context is OFF.
 *   The mixed-types CRITICAL RULES gapselect line was also updated: it now
 *   conditionally enforces scenario-free format when context is OFF, AND the old
 *   wrong sequential [[1]][[2]][[3]] numbering reference is corrected to jump-by-3
 *   ([[1]][[4]][[7]]). Server prompts (server/routes.ts) only. No PHP, AMD, or DB
 *   schema changes. version.php → 2026042200082.
 *
 * AI Quiz Maker v3.16.81 - FIX-QM-WCTX-OFF + FIX-QM-NAME-DIVERSITY: Two boss-reported issues
 *   in the AI Quiz Maker generator. (1) Workplace-context toggle OFF was still producing
 *   workplace-scenario-style questions ("You are a worker at...", named characters in
 *   workplace settings). Root cause: although ajax.php cleared country/industry/jobTitle/
 *   jobLevel when the toggle was off, the server prompt's `selfMarkingStyles` defaulted to
 *   include "scenario" and `questionFormats` defaulted to include "scenario", and the VET
 *   level-specific blocks (cert1-adv_diploma) all hard-coded "Prefer scenario-based
 *   workplace tasks" — these conflicted with the no-context "DEFINITION-STYLE" branch and
 *   the AI followed the scenario instructions. FIX: Added explicit `workplaceContextEnabled`
 *   boolean to the /api/generate-essays and /api/generate-from-questions Zod schemas
 *   (default true to preserve existing behaviour). When false: scenario is stripped from
 *   `questionFormats` and `selfMarkingStyles`; the level-specific blocks now conditionally
 *   suppress scenario-based language; a hard NON-NEGOTIABLE rule is added to the system
 *   prompt forbidding workplace scenarios, named workers/colleagues/customers, and
 *   "You are working as..." style stems. ajax.php now passes the flag explicitly in all 3
 *   generate payloads (generate, regenerate, generatefromquestions). (2) Repetitive names
 *   across questions in the same batch ("Sarah" or "Emma" appearing in 4 of 8 questions).
 *   Root cause: the criteria-mode loop fans out parallel OpenAI calls — each call had no
 *   knowledge of names other parallel calls were generating. FIX: Added a NAME DIVERSITY
 *   non-negotiable rule to both VET and Academic system prompts forbidding name reuse and
 *   over-used defaults (Sarah/John/Emma/Mike), and providing a 26-name diverse rotation
 *   pool. Where a name is not strictly required, the AI is told to omit it entirely.
 *   No PHP behaviour change beyond the new payload field. No AMD changes. No DB schema
 *   changes. Server prompts + ajax.php only. version.php → 2026042200081.
 *
 * AI Quiz Maker v3.16.74 - PROMPT ENHANCEMENT: Stronger question uniqueness and name diversity
 *   across all question types and both generation modes (Generate from Criteria + Use My Own
 *   Questions + /api/quizmaker/generate).
 *   PROBLEM: Boss reported that in an 8-question quiz the names "Emma" and "Sarah" each appeared
 *   4 times across scenarios. Additionally, questions across different types (MCQ, Matching, Essay,
 *   Gap Fill) were testing the same concepts through different formats — a student could copy 70%
 *   of one answer into another question and still be correct.
 *   FIX 1 — Name diversity pool (server/routes.ts, quizmaker/generate route): Added a 40-name
 *   shuffled pool (male + female, Anglo + multicultural Australian mix). Each question is pre-assigned
 *   a unique name from the pool before the OpenAI call. "Names already used" are injected into the
 *   alreadyGeneratedSection so the AI knows which names to avoid. Emma and Sarah explicitly excluded.
 *   Names used are tracked in usedNames[] and pushed after each successful generation.
 *   FIX 2 — Criteria mode prompts (server/routes.ts, 4 locations): Added UNIQUE SCENARIO NAMES rule
 *   to QUESTION DESIGN RULES (essay branch), QUESTION DESIGN RULES (non-essay branch), both
 *   DIVERSITY REQUIREMENT blocks, and CRITICAL RULES. Assigns names in order from pool per question.
 *   FIX 3 — Cross-type concept separation (server/routes.ts, all prompt locations): Added explicit
 *   rule — even if question types differ (MCQ vs Essay vs Matching vs Gap Fill), each question must
 *   test a DIFFERENT core concept. The "70% rule" is now stated explicitly: if a student can copy
 *   70% of Q1's answer into Q2 and still be correct, Q2 must change.
 *   FIX 4 — otherQuestionsBlock (server/routes.ts): Expanded with CROSS-TYPE RULE (don't test the
 *   same concept even across different types) and UNIQUE NAME RULE (choose from pool, avoid names
 *   already seen in the batch context).
 *   FIX 5 — Hardcoded "Sarah" example (server/routes.ts, story-based content style hint):
 *   Replaced fixed "Sarah, a supervisor" example with a generic "unique name" instruction.
 *   Server-side only (server/routes.ts prompts). No PHP, AMD, or DB schema changes.
 *   version.php → 2026041700074.
 *
 * AI Quiz Maker v3.16.73 - BUG FIX: Select Missing Words (gapselect) PHP warnings on teacher
 *   side: "Undefined array key 10", "Undefined array key ''", and "foreach() argument must be
 *   of type array|object, null given" in Moodle's questiontypebase.php and questionbase.php.
 *   ROOT CAUSE: When the AI generated a gapselect question with N gap placeholders in the text
 *   (e.g. [[1]],[[2]],[[3]],[[4]]) but returned fewer than N selectOptions groups, the plugin
 *   created question_answers rows for each existing group but left one or more gaps with no
 *   choices in the DB. Moodle then warned "Undefined array key N" on every render or attempt.
 *   FIX A — PHP (ajax.php, two locations):
 *   (1) aiquizmaker_create_gapselect_question(): after extracting [[n]] placeholder numbers,
 *   compare count($selectoptions) vs count($placeholder_nums). If MORE groups than gaps,
 *   silently truncate the excess. If FEWER groups than gaps, return false (skip question) —
 *   better a "skipped" notice than a question that always emits PHP warnings.
 *   (2) aiquizmaker_xml_gapselect_block(): same validation; returns '' if counts mismatched.
 *   FIX B — Prompts (server/routes.ts, 3 locations):
 *   Switched all gapselect prompts from jump-by-3 ([[1]],[[4]],[[7]],[[10]]) to sequential
 *   ([[1]],[[2]],[[3]],[[4]]) numbering. Jump-by-3 frequently caused the AI to supply fewer
 *   selectOptions groups than gaps, triggering the count-mismatch write described above.
 *   Added explicit rule: "the number of selectOptions sub-arrays MUST exactly equal the number
 *   of [[n]] gaps — no more, no less." The PHP creation code handles any numbering scheme via
 *   regex extraction — no further PHP changes needed.
 *   NOTE: Existing questions created before v3.16.62 (feedback='') or between v3.16.62–64
 *   (sequential groups with jump-by-3 text) will continue to show warnings until deleted and
 *   regenerated. The plugin cannot retroactively fix data already in the Moodle database.
 *   PHP + server/routes.ts (prompts only). No AMD or DB schema changes.
 *   version.php → 2026041700073.
 *
 * AI Quiz Maker v3.16.72 - AGGRESSIVE ANTI-REPETITION FIX: Deep audit and fix of question
 *   repetition across ALL 6 question types (Essay, MCQ, True/False, Matching, Select Missing
 *   Words, Fill in the Blanks) for BOTH input methods (Generate from Criteria and Use My Own
 *   Questions). Root cause identified by systematic per-type audit:
 *   (A) "Generate from Criteria" — Non-essay QUESTION DESIGN RULES block was missing an
 *   explicit uniqueness rule (the DIVERSITY REQUIREMENT only fired at the bottom of the prompt
 *   when questionsCount > 1). FIXED: Added UNIQUENESS — MANDATORY rule as item 5 in QUESTION
 *   DESIGN RULES. Strengthened CRITICAL RULES: Matching must use COMPLETELY DIFFERENT set of 4
 *   terms across questions; Fill-in-the-Blank must test a DIFFERENT key term per question;
 *   UNIQUENESS rule strengthened to include self-check ("change Q2 if knowing Q1 auto-answers it").
 *   (B) "Use My Own Questions" — ALL question types ran via Promise.all() with pLimit(5) —
 *   fully parallel with ZERO cross-question awareness. Each processQuestion call had no idea
 *   what the other 4 concurrent calls were generating. FIXED: Build allQuestionsContext from
 *   all teacher-provided question texts BEFORE the parallel loop; inject into every processQuestion
 *   call as the "OTHER QUESTIONS IN THIS BATCH — AVOID OVERLAP" block. All 6 type branches
 *   (MCQ, T/F, Matching, gapselect, shortanswer, essay) now receive this context. Per-type
 *   prompt improvements: MCQ distractors must be specific to THIS question (no generic options);
 *   T/F statements must be specific to THIS question's exact content; Matching terms must be
 *   specific technical terms (no standalone "Safety", "Procedure"); gapselect gap words must be
 *   specific technical terms from the topic (not "correct", "appropriate"); shortanswer blank
 *   must be a specific technical term (1-5 words, not generic filler).
 *   (C) "/api/quizmaker/generate" — already correctly fixed in v3.16.71 (sequential + accumulator).
 *   Server-side only (server/routes.ts). No PHP, AMD, or DB schema changes.
 *   version.php → 2026041700072.
 *
 * AI Quiz Maker v3.16.71 - SERVER-SIDE FIX: Question repetition prevention across all question
 *   types in both "Generate from Criteria" and the self-marking quiz generator. ROOT CAUSE: each
 *   question was generated by an independent OpenAI call with no knowledge of questions already
 *   produced in the same batch, causing the AI to reuse the same concept, scenario, or wording.
 *   FIX 1 (/api/quizmaker/generate): Added generatedQuestionTexts[] accumulator. Before each
 *   call, the user prompt receives an "ALREADY GENERATED IN THIS SET — DO NOT REPEAT" section
 *   listing all prior question texts (first 120 chars each). Covers all types: MCQ, True/False,
 *   Matching, Fill in the Blanks, Select Missing Words, Short Answer.
 *   FIX 2 (/api/generate-essays criteria route): When questionsCount > 1, a DIVERSITY
 *   REQUIREMENT block is injected — Q1=concept/fact, Q2=scenario/application, Q3+=further
 *   distinct angle — plus a self-check ("if knowing Q1 auto-answers Q2, change Q2"). Applied
 *   to both essay-only and mixed-type prompt branches.
 *   Server-side only (server/routes.ts). No PHP or AMD changes. No DB schema changes.
 *   version.php → 2026041700071.
 *
 * AI Quiz Maker v3.16.70 - FEAT: Tester feedback improvements — question uniqueness, per-type
 *   prompt rules, and question text prefix stripping. (1) UNIQUENESS: Added explicit uniqueness
 *   rules to all AI prompts so every question tests a different concept — no overlap or repetition.
 *   (2) NO PREFIX IN QUESTION TEXT: All prompts forbid Q1./1./Question 1: prefixes in questionText.
 *   stripQuestionPrefix() helper added to aiquizmaker.js — strips prefixes when pasted questions
 *   are parsed (block-based and line-by-line paths both apply the strip). (3) ESSAY RUBRIC: Added
 *   DISTINCT CRITERIA + SEPARATE POINTS rules — each criterion covers a different aspect, never
 *   restates the question text. (4) TRUE/FALSE FEEDBACK: trueFeedback/falseFeedback must explain
 *   WHY — not just repeat the statement. Updated typeInstruction + QUESTION TYPE RULES. (5) MCQ
 *   FEEDBACK: Each distractor has a distinct misconception explanation; NEVER use Option A/B/C/D
 *   labels in feedback. (6) GAP SELECT: questionText is brief instruction only; sentenceWithGaps
 *   must be different and complete. Feedback explains the concept, not the completed sentence.
 *   (7) SHORT ANSWER: Answer is 1-5 words max. AMD triple-match MD5: 2e19e256f029a70cea0578a9f55b9b0e.
 *   No DB schema changes. version.php → 2026041700070.
 *
 * AI Quiz Maker v3.16.63 - BUG FIX (x3): BUG-QM-GAPSELECT-GROUPS + BUG-QM-CRITERIA-QTEXT-ALLTYPES (ShortAnswer) + BUG-QM-CRITERIA-QTEXT-XML (MCQ).
 *   BUG-QM-GAPSELECT-GROUPS (ajax.php + aiquizmaker.js): selectOptions arrived from the AI API as a
 *   1-based JSON object {"1":[...],"2":[...]}. PHP json_decode converted "1"/"2" string keys to integer keys
 *   1 and 2 (not 0 and 1). The DB creator's $groupnum = $groupidx + 1 then produced groups 2 and 3 instead
 *   of 1 and 2, leaving Group 1 empty. Moodle rendered both [[1]] and [[2]] blanks with the same choices from
 *   the non-empty group, causing all drop-downs to display identical options. Fix (two locations):
 *   (a) JS displayResults() normalises selectOptions to a 0-indexed array immediately after AI response so
 *   all downstream paths (addtoquiz, XML export, edit modal) see correct indices; (b) PHP DB creator and XML
 *   builder both normalise the array via ksort+array_values if keys start at 1 — defensive against old data.
 *   BUG-QM-CRITERIA-QTEXT-ALLTYPES (ShortAnswer): criteriaReference was being prepended to the question HTML
 *   body as <p><em>Criteria: ...</em></p>. Students saw teacher-only assessment metadata in every question.
 *   Removed injection; criteriaReference now stored in $question->idnumber (consistent with MCQ, TrueFalse,
 *   Matching, GapSelect which were fixed in v3.16.62/prior).
 *   BUG-QM-CRITERIA-QTEXT-XML (MCQ XML): aiquizmaker_xml_multichoice_block() had the same criteria injection
 *   in the $qtextbody for XML export. Removed; XML question text is now clean student-facing content only.
 *   No DB schema changes. AMD triple-match MD5: 54a721320e4f1a03c834441327b344c0. version.php → 2026041400063.
 *
 * AI Quiz Maker v3.16.62 - FIX-QM-GAPSELECT-FEEDBACK.
 *   FIX-QM-GAPSELECT-FEEDBACK (ajax.php): gapselect question creation was storing the group
 *   number in the wrong question_answers field. v3.16.61 set fraction=$groupnum and feedback=''
 *   — Moodle qtype_gapselect_base::get_question_options() actually groups choices by the
 *   FEEDBACK field (not fraction), so all choices had feedback='' (same empty group) and appeared
 *   in a single dropdown showing all six choices for BOTH blanks instead of 3 per blank.
 *   Fix: fraction=0 (gapselect does not grade by answer weight), feedback=(string)$groupnum.
 *   No DB schema changes. AMD unchanged. PHP ajax.php only. version.php → 2026041400062.
 *
 * AI Quiz Maker v3.16.54 - TWO BUG FIXES.
 *   FIX-1 (server/routes.ts): Regenerate prompt strengthened with explicit STRICT RULES:
 *       "Do NOT only change the explanation while keeping the same question", "Create a NEW
 *       question stem, NEW answer options, and a NEW explanation", and the 50-char stem
 *       similarity check rule. Prevents the AI from making micro-edits on 3rd+ clicks.
 *   FIX-2 (server/routes.ts): SRV-TYPE-ENFORCE — after parsing each criterion's AI response,
 *       the server now force-assigns moodleQuestionType to the pre-computed expected type
 *       (useTypes[(criterionIndex + qi) % useTypes.length]). This ensures the 6-questions ×
 *       6-types scenario always yields exactly one question of each type, even when the AI
 *       ignores the MANDATORY TYPE ASSIGNMENT instruction and returns repeated types.
 *   No DB schema changes. AMD unchanged. Server-only. version.php → 202604090054.
 *
 * AI Quiz Maker v3.16.50 - BUG FIX (regenerate A→B→A loop prevention).
 * Both ownquestions-mode and criteria-mode regenerate now accumulate all previously generated
 * question versions in a _generatedVersions array on the question object. Each regenerate call
 * sends the full accumulated history as previousQuestionText (up to 2000 chars, ---PREV--- delimited)
 * so the server avoids ALL prior outputs, not just the last one. Previously sending only the
 * most-recently-generated text caused the AI to cycle: A→"avoid A"→B→"avoid B"→A→loop.
 * AMD triple-match MD5: dbf7fe3c48fe3e770eae833d79a130ec. version.php → 202604070050.
 *
 * AI Quiz Maker v3.16.48 - BUG FIX (x2): (1) Criteria-mode Regenerate: workplaceContextEnabled flag
 * now sent with AJAX data object so PHP correctly includes workplace context fields (industry, jobTitle,
 * jobLevel, country, state, educationType, educationLevel) when the teacher has them configured.
 * Previously the flag was absent from the criteria-mode AJAX call, causing regenerated questions in
 * criteria mode to always ignore any workplace context the teacher had set up (non-criteria/ownquestions
 * mode was unaffected). (2) fetchCredits() now called after a successful criteria-mode regeneration
 * response so the credit counter in the page header updates immediately without requiring a full page
 * reload. AMD: aiquizmaker.js updated. version.php → 202604030048.
 *
 * AI Quiz Maker v3.16.44 - BUG FIX: Gapselect (Select Missing Words) question text ([[n]] sentence) was
 * corrupted after a failed edit-modal validation. Root cause: saveQuestionEdit() mutated q.questionText at the
 * top of the function before any gapselect-specific validation ran — if validation then failed (e.g. placeholder
 * count mismatch) the function returned early without rebuilding the card, leaving the in-memory question object
 * with a potentially blank or edited questionText that no longer contained [[n]] placeholders. The next
 * operation that called buildQuestionCard() (regenerate, re-edit, or any subsequent display) then rendered an
 * empty "Answer (with blanks)" section. Fixes: (1) originalQuestionText saved before the mutation; restored
 * on both early-return validation paths in the gapselect branch. (2) buildQuestionCard gapselect branch now
 * uses an explicit gapQText variable with || '' fallback, and guards the "Answer (with blanks)" block against
 * empty text so the instruction line always renders. (3) getSelectedQuestions() and addSingleQuestionToQuiz()
 * both apply a defensive fallback (questionText || "Complete the sentence by selecting the correct words.")
 * before serialising gapselect questions for addtoquiz/createquestions — prevents PHP creator from silently
 * skipping questions with empty questionText. aiquizmaker.js AMD triple-match MD5: 44fe1a17e766451c45a7f5586dbdf3b0. version.php → 202604014400.
 *
 * AI Quiz Maker v3.16.43 - BUG FIX: Selecting 5 questions in 'Use My Own Questions' mode generated 25
 * instead of 5. Root cause: server used flatMap producing one AI call per pasted question × selected type
 * (5 × 5 = 25); JS orderedLayout built typesCount question slots per pasted question; merge loop consumed
 * typesCount results per question. Fix: server now assigns one type per pasted question (round-robin across
 * selected types) via map(); JS orderedLayout allocates 1 slot per question; merge loop takes 1 result per
 * question. Result: N pasted questions → exactly N output cards, with types distributed evenly.
 * aiquizmaker.js AMD triple-match MD5: aef2e4fda3c2e22d2b55a694ad442a9c. version.php → 202603314300.
 *
 * AI Quiz Maker v3.16.42 - BUG FIX (x2): (1) CRITICAL: 'Use My Own Questions' mode — questions generated
 * successfully but NONE rendered in the UI after any mode switch. Root cause: switchInputMode() called
 * $('#aiquizmaker-results').empty() which destroyed the static #questions-list DOM element; all subsequent
 * displayResults() append calls targeted a detached jQuery set (no-op), leaving the results container visually
 * empty. Fix: replaced .empty() on the whole container with $('#questions-list').empty() (targeted clear) +
 * $('#aiquizmaker-results').hide() (proper hide). #questions-list is now always present in the DOM so
 * displayResults() can append cards correctly. (2) Success toast in 'Use My Own Questions' mode showed the
 * total N×M count (e.g. "30 questions generated" for 5 pasted × 6 types selected) instead of the number of
 * pasted questions the teacher actually submitted (5). Fix: toast now uses questionItems.length (count of
 * pasted/provided questions) so teachers see "5 questions generated" matching their input expectation.
 * aiquizmaker.js AMD triple-match MD5: 78c7b6df2fa56fbdbb4e2ef87622bf3a. version.php → 202603314200.
 *
 * AI Quiz Maker v3.16.41 - BUG FIX (x3) + ENHANCEMENT (x1): (1) FIX: 'Use My Own Questions' mode only generated
 * Essay questions even when other types (Multiple Choice, True/False, etc.) were selected. Root cause: the server
 * generated exactly one question per pasted input cycling round-robin — so 1 pasted question + 3 types selected
 * always yielded only 1 essay question (the first type). Fix: changed to one-per-type-per-question — each pasted
 * question now produces one output for every selected type (N pasted questions × M types = N×M output questions).
 * Server updated to flatMap approach; PHP orderedLayout now repeats question slots by type count; JS merge logic
 * updated to consume typesCount outputs per input question. (2) FIX: Success toast hardcoded 'essay questions'
 * regardless of actual types generated — lang string success_generated_message changed from 'Successfully generated
 * {$a} essay questions.' to 'Successfully generated {$a} questions.' (3) FIX: Missing Word (Fill in the Blank) /
 * shortanswer question type was accepted by server and PHP but had no checkbox in the question types UI — teachers
 * could never select it. Added shortanswer checkbox to index.php question types grid after gapselect. (4) ENHANCEMENT:
 * chatgpt_qtypes_note tip text updated to explain the new one-per-type-per-question behaviour. version.php → 202603314100.
 *
 * AI Quiz Maker v3.16.36 - BUG FIX (x5) + NEW FEATURE (x1): (1) CRITICAL: generatefromquestions action
 * (Use My Own Questions mode) was missing totalMarks assignment for truefalse, matching, gapselect,
 * and shortanswer — the main generate action set totalMarks correctly but generatefromquestions never
 * did, causing matching to always show "3 marks" regardless of pair count, and all other non-essay
 * self-marking types to show 0 marks. Fixed: matching now sets totalMarks=count(matchPairs), gapselect
 * sets totalMarks=count(selectOptions), truefalse and shortanswer set totalMarks=1. (2) criteriaReference
 * in Use My Own Questions mode was always empty for non-essay types (AI omits it for self-marking types)
 * — added PHP fallback in generatefromquestions: if criteriaReference is empty after cleaning, substitute
 * the question text so teachers always see their original question in the Criteria row. (3+4) JS
 * buildQuestionCard belt-and-suspenders: matching marks now derived from matchPairs.length directly
 * (independent of server-returned totalMarks), gapselect marks derived from selectOptions group count.
 * (5) NEW: per-question "Add to Quiz" button (plus icon) added to each question card when in quiz context
 * — teachers can now add a single question directly to the quiz without using the bulk checkbox flow;
 * button turns green with a tick on success. Event handler wired in the main init block. CSS added for
 * .aiquizmaker-add-single-done state. version.php → 202603313600.
 *
 * AI Quiz Maker v3.16.34 - INVESTIGATION + SERVER FIX: Investigated tester-reported "Generation Failed". Root cause confirmed as OpenAI API quota exhaustion during testing period — not a plugin code bug. Plugin code already correctly propagates error messages since v3.16.32. Server-side improvement: /api/generate-essays and /api/generate-from-questions catch blocks now strip internal error-type prefixes (OPENAI_API_ERROR:, OPENAI_TIMEOUT:, OPENAI_TOKEN_LIMIT:) from error messages before forwarding to the plugin, so teachers see clean human-readable messages instead of code prefixes. No plugin code or DB schema changes. version.php → 202603313400.
 *
 * AI Quiz Maker v3.16.33 - FIX (x2) + IMPROVEMENT: (1) Language support added to Generate from Topics mode — the Moodle site language code is now accepted and forwarded by the /api/generate-essays server route (previously the language field was sent by PHP but silently dropped by the server schema, so non-English Moodle sites always got English output in topic-based quiz generation). languageInstruction injected into both VET and Academic system prompts inside processCriterion. (2) Non-essay question token budget increased from 2000 → 3000 tokens in processQuestion() — reduces OPENAI_TOKEN_LIMIT failures for complex question types (multichoice with 4 annotated choices, matching with 4 pairs, shortanswer with alternatives). Improved error logging: err.constructor.name now included in processQuestion catch logs to distinguish timeout/API/parse errors. version.php → 202603303300.
 *
 * AI Quiz Maker v3.16.32 - FIX (x8): Tester feedback round 2. (1+2) Error messages now surface correctly — JS shows response.message (actual error text) instead of response.error code "GENERATION_ERROR"; PHP now passes the message field through from Node.js in all three error-response sites (generate, generate-from-questions, regenerate actions); .fail() handlers log HTTP status and show HTTP error detail instead of generic connection error. (3) Use My Own Questions now validates minimum 20-char content before calling AI — prevents submitting a single word or empty input. (4) MCQ option blocks stripped from criteria text before AI call — Option A/B/C/D and A./B./C./D. trailing option patterns are removed; criteria capped at 300 chars. Fixes 5-8 (strict JSON prompt, JSON.parse guard, credits pre-check, AbortController timeout) were already in place from prior releases. version.php → 202603303200.
 *
 * AI Quiz Maker v3.16.31 - FIX (x5): Tester feedback implementation. (1) novalidate added to <form id="aiquizmaker-form"> in index.php — browser HTML5 validation was blocking form submission whenever any criteria input with required attribute was hidden or empty, causing "Generate Questions", "Extract Criteria" and all other form actions to silently do nothing. (2) removed required attribute from all criteria text inputs in addCriteriaRow() and addCriteriaRowWithText() — manual JS validation already handles empty criteria gracefully via the fallback default. (3) Criteria quality filter: data.criteria now filtered to entries with text.trim().length >= 10 before sending — prevents weak/empty one-word criteria from reaching the AI. (4) Question selection UI: each question card now has a checkbox (pre-checked) so teachers can individually tick/untick questions before adding to quiz or question bank; "Select all / none" toggle and live selection count shown above the list; "Add N Questions to Quiz" button label updates dynamically to reflect current selection count; addQuestionsToQuiz() and createQuestionsInMoodle() now send only the ticked subset to the server. (5) createQuestionsInMoodle() also respects the selection. version.php → 202603303100.
 *
 * AI Quiz Maker v3.16.29 - VERSION BUMP: Clean release following master release process.
 *   schema.safeParse() on the generate-questions endpoint. Invalid AI responses now return
 *   HTTP 400 with a descriptive error instead of crashing with HTTP 500.
 *   version.php → 202603272300.
 *
 * AI Quiz Maker v3.16.27 - BUG FIX (x3): Description cards (Part A/Part B section headings) were still creating essay questions even after the whitelist fix in v3.16.26. Three additional bugs identified in aiquizmaker_create_description_question() and aiquizmaker_quiz_add_slot(): (D1) questiontext was wrapped in a plain <p> tag instead of a styled heading card — description questions now render as a styled blue-left-border card with an <h4> heading. (D2) $question->length was set to 1 instead of the Moodle-required 0 — Moodle requires length=0 for description questions (they must not count toward the quiz question total); with length=1 Moodle may mishandle or reject the description slot. (D3) In aiquizmaker_quiz_add_slot() the PHP falsy-zero bug: $defaultmark = (float)($rawmark ?: 1) evaluates to 1.0 when $rawmark=0 because PHP treats 0 as falsy — description questions (defaultmark=0) received maxmark=1.0 in the quiz_slots table instead of 0.0; fixed with explicit !== false check. version.php → 202603262300.
 *
 * AI Quiz Maker v3.16.25 - BUG FIX: Matching questions showed "This part of the question was deleted after the attempt was started." Root cause: aiquizmaker_create_matching_question() used $sub->question = $questionid when inserting into qtype_match_subquestions — the FK column in that table is 'questionid', not 'question'. MySQL non-strict mode silently accepted the insert with questionid=0, orphaning all subquestions. Moodle then queried WHERE questionid=X, found nothing, and showed the deletion error. Fix: changed $sub->question to $sub->questionid on line 719 of ajax.php. version.php → 202603252200.
 *
 * AI Quiz Maker v3.16.24 - BUG FIX: True/False questions failed to save to Moodle quiz with "Table qtype_truefalse does not exist". Root cause: aiquizmaker_create_truefalse_question() called $DB->insert_record('qtype_truefalse', ...) — the table does not exist in Moodle. The correct Moodle table for True/False type options is 'question_truefalse'. Fixed the insert on line 644 of ajax.php. All other question type table names (qtype_essay_options, qtype_multichoice_options, qtype_match_options, qtype_match_subquestions, qtype_shortanswer_options, question_gapselect) were already correct. version.php → 202603252100.
 *
 * AI Quiz Maker v3.16.23 - ENHANCEMENT: Self-marking question styles + block parser heading fix. (1) NEW UI: Self-Marking Question Styles section in index.php — five checkboxes (Workplace Scenario, Knowledge Check, Procedure/Steps, Terminology/Definitions, Identification/Listing) with scenario+knowledge_check pre-checked; section auto-shows when any non-essay question type is selected, auto-hides when only essay. (2) AI prompt enhancement: both /api/generate-essays and /api/generate-from-questions now accept selfMarkingStyles param; non-essay question prompts include QUESTION STYLE GUIDANCE instructions to vary question framing across selected styles; scenario style instructs AI to embed a realistic workplace scenario in the question stem. (3) BUG FIX: Block parser heading detection now correctly handles multi-line blocks — previously bLines.length===1 guard caused lines like "Part B: Topic" inside a multi-line block to be silently discarded as questions instead of becoming Moodle description cards; fixed with per-line scan that flushes each heading line immediately and accumulates remaining lines as question text. (4) All three generate/regenerate AJAX calls (generateQuestions, generateFromOwnQuestions, both regenerate paths) now forward selfMarkingStyles to the server. version.php → 202603252000.
 *
 * AI Quiz Maker v3.16.22 - FIX: Three critical JS bugs caused by wrong AMD module name. index.php was calling js_call_amd('local_aiquizmaker/essaymaker', 'init') — the old pre-rename module name (renamed to aiquizmaker in v3.16.16). Moodle resolves this to amd/build/essaymaker.min.js which does not exist; amd/build/aiquizmaker.min.js is the correct file. No JS ran at all on the page, causing: (1) credits badge stuck on '...' (fetchCredits never called), (2) 'Use My Own Questions' tab completely non-functional (switchInputMode handler never registered), (3) all other event handlers (generate, add to quiz, bulk add, etc.) never registered. Fix: corrected module name to local_aiquizmaker/aiquizmaker. Also fixed: lang string key 'essaymaker:use' corrected to 'aiquizmaker:use' to match the capability 'local/aiquizmaker:use' in access.php (cosmetic — fixes garbled label on the role definition page). Quiz target banner dismiss button already shipped in v3.16.21. version.php → 202603251300.
 *
 * AI Quiz Maker v3.16.21 - FIX: "AI Quiz" button appeared twice on the quiz edit page. Root cause: two hook callbacks both called js_call_amd('local_aiquizmaker/quizbutton', 'init') — before_standard_head_html_generation and before_footer_html_generation. Moodle does not deduplicate js_call_amd calls, so quizbutton.init() ran twice and inserted the button twice. Fix: removed the before_standard_head_html_generation entry from db/hooks.php. Only the footer hook is retained — the DOM and AMD loader are fully ready at that point. version.php → 202603251200.
 *
 * AI Quiz Maker v3.16.20 - FIX: "Add to Quiz" still failing on Moodle 4.4+ despite v3.16.17 fix. Root causes: (H1) require_once('locallib.php') fatally errored on Moodle 4.4+ where locallib.php was removed (MDL-76897) — now guarded with file_exists(). (H2) question_get_default_category() guarded with function_exists(); three-stage fallback: API → DB lookup → create category. (H3) The Moodle 4.4+ fallback used $structure->add_question() which does not exist on \mod_quiz\structure — replaced with new aiquizmaker_quiz_add_slot() helper that directly inserts into quiz_slots + question_references + quiz_sections (fully Moodle 4.4+ compatible without any quiz_settings API). (H4) Sumgrades recomputation wrapped in try/catch with method_exists guards — failure is now non-fatal (questions are still inserted). version.php → 202603251100.
 *
 * AI Quiz Maker v3.16.19 - FIX: Gapselect questions displayed "Essay" badge and "Marking Rubric" body — two bugs: (1) typeBadgeMap in buildQuestionCard was missing 'gapselect', so it fell through to the 'Essay' default label; (2) the type-specific body switch also had no gapselect branch, so the card fell into the essay rubric renderer. Fixed: added 'gapselect' → 'Select Missing Words' to typeBadgeMap; added gapselect card body renderer that shows [[n]] → correct answer (green) + distractors (grey italic) for each gap group. New CSS: aiquizmaker-gap-groups-preview, aiquizmaker-gap-preview-row, aiquizmaker-gap-preview-num (purple monospace), aiquizmaker-gap-preview-correct (green badge), aiquizmaker-gap-preview-distractors. version.php → 202603251000.
 *
 * AI Quiz Maker v3.16.18 - UX: Type-aware question edit modal. Previously the edit modal showed only "Question Text + Rubric Criteria" for ALL question types — rendering it useless for MCQ, True/False, Matching, Gapselect and Short Answer questions. The modal now branches on moodleQuestionType and shows the correct fields for each type: MCQ → editable answer choices with radio button to select correct answer, add/remove choices, explanation textarea; True/False → correct answer radio (True/False), per-answer feedback inputs, explanation; Matching → add/remove term→answer pairs; Gapselect → sentence with [[n]] placeholders, per-gap group option editors with correct/distractor badges, add/remove distractors, general feedback; Short Answer → add/remove accepted answers list, explanation. saveQuestionEdit now reads back the type-specific fields and writes them to the question object before rebuilding the card. New CSS: aiquizmaker-edit-choice-row, aiquizmaker-tf-options, aiquizmaker-edit-pair-row, aiquizmaker-edit-gap-group, aiquizmaker-edit-answer-row, field-hint, dark-mode variants. Also added escapeAttr() helper to prevent XSS in attribute values. version.php → 202603250900.
 *
 * AI Quiz Maker v3.16.17 - FIX "Add to Quiz" button silent failure: Moodle's start_delegated_transaction()->rollback() re-throws the exception, which turned any single-question failure into a total abort of all 26 questions. Removed per-question delegated transactions from both addtoquiz and createquestions action loops — aiquizmaker_create_question() already returns false on internal failure (sufficient isolation). Changed all inner and outer catch(Exception) blocks to catch(\Throwable) so PHP 7+ Error objects (e.g. calling undefined or deprecated quiz functions) are properly caught and surfaced as per-question errors instead of crashing the entire operation. Added function_exists('quiz_add_quiz_question') guard with Moodle 4.4+ fallback via quiz_settings::create()->get_structure()->add_question(). version.php → 202603250800.
 *
 * AI Quiz Maker v3.16.16 - RENAME: Full rename from local_essaymaker to local_aiquizmaker. All file names, folder names, PHP namespaces, AMD modules (essaymaker.js→aiquizmaker.js), lang file (local_essaymaker.php→local_aiquizmaker.php), CSS class prefixes (essaymaker-→aiquizmaker-), capabilities, API routes (/api/essaymaker-settings→/api/aiquizmaker-settings), download path, install path, and display strings updated throughout. No functional changes. version.php → 202603250700.
 *
 * AI Quiz Maker v3.16.15 - MAJOR BUG FIX RELEASE (50-item audit): All 50 identified bugs causing self-marking questions (MCQ, T/F, matching, gapselect, shortanswer) to not display or auto-mark correctly in Moodle quizzes. Groups: (A) DB format fields — MCQ answerformat/feedbackformat FORMAT_PLAIN→FORMAT_HTML, T/F feedbackformat FORMAT_PLAIN→FORMAT_MOODLE, Matching subquestiontextformat FORMAT_PLAIN→FORMAT_HTML, Shortanswer answerformat FORMAT_PLAIN→FORMAT_MOODLE + feedbackformat FORMAT_PLAIN→FORMAT_HTML. (B) Guards — MCQ empty choices guard + rollback, matching filter empty pairs, shortanswer filter empty answers + guard + rollback, gapselect placeholder sequential validation. (C) question_bank_entry — timecreated/timemodified/createdby/modifiedby audit fields, cached table_exists(), next version number. (D) XML format — T/F True/False→true/false + format=moodle_auto_format, MCQ/matching remove double-encoding inside CDATA, shortanswer format=moodle_auto_format (was format=html which broke answer comparison), gapselect shortname double-encoding removed, all XML guard/filter blocks. (E) Sanitisation pipeline — format_text(FORMAT_PLAIN)→clean_param(PARAM_TEXT) in all three action blocks (generate/generatefromquestions/regenerate) for all self-marking types; PARAM_ALPHA→PARAM_ALPHANUMEXT for moodleQuestionType in generate action. (F) addtoquiz — per-question DB transaction, collect+return skip reasons, JS surface errors to teacher, top category as parent in auto-create, guard recompute_quiz_sumgrades when addedcount=0. (G) MCQ — dynamic penalty round(1/(n-1),7), multi-answer detection from choices, shownumcorrect for multi-answer, criteriaref in XML, no-correct fallback in XML. (H) Shortanswer — all accepted answers get Correct! feedback in both DB and XML. (I) Cross-cutting — T/F XML format=moodle_auto_format standardised, lang string auto_category_info added. version.php → 202603250600.
 *
 * AI Quiz Maker v3.16.14 - CRITICAL FIX: gapselect DB creator used completely wrong storage convention — verified against Moodle MOODLE_403_STABLE source. Options table was 'qtype_gapselect_options' — correct is 'question_gapselect'. fraction was (float)$groupnum — correct is 0 for ALL answers. feedback was '' — correct is (string)$groupnum (group number). answerformat was FORMAT_PLAIN — correct is FORMAT_HTML. feedbackformat was FORMAT_HTML — correct is 0 (FORMAT_MOODLE). Orphan cleanup table also corrected. Without these fixes every gapselect question saved to DB was broken — dropdowns had no group mapping. XML export unaffected. version.php → 202603250500.
 *
 * AI Quiz Maker v3.16.13 - FIX: Three audit findings resolved. (1) gapselect DB creator orphan-question bug: if every selectOptions group had < 2 choices and was skipped, an empty question was committed to the DB — now the qtype_gapselect_options and question records are cleaned up and the creator returns false. (2) System prompt contamination for non-essay types: VET and academic system prompts contained essay-specific SELF-CHECK rules ("Confirm only one questionFormat per question", "Confirm wordCount aligns to the chosen format band") and a NON-NEGOTIABLE RULE ("Select exactly ONE questionFormat per question") that bled into MCQ/T/F/matching/gapselect/shortanswer AI calls — removed from both system prompts; the essay user-prompt enforces these rules for essay types. (3) Same essay-specific NON-NEGOTIABLE removed from academic system prompt. version.php → 202603250400.
 *
 * AI Quiz Maker v3.16.11 - NEW: "Select Missing Words" (gapselect) question type. Students select the correct word from a dropdown embedded in a sentence — auto-marked by Moodle. Added in full pipeline: index.php checkbox (value=gapselect), lang strings mtype_gapselect / mtype_gapselect_tip, aiquizmaker_create_gapselect_question() writing to qtype_gapselect_options + question_answers, aiquizmaker_xml_gapselect_block() for XML export with <selectoption> elements, gapselect case in both switch statements (aiquizmaker_create_question + aiquizmaker_generate_moodle_xml), gapselect added to all three allowedtypes arrays, sanitisation block for selectOptions in the generate action, gapselect format spec in server AI prompt with [[n]] placeholder syntax and selectOptions array structure, gapselect branch in /api/generate-from-questions server route. Retained shortanswer (fill-in-the-blank, type answer) as a separate type. version.php → 202603250200.
 *
 * AI Quiz Maker v3.16.10 - FIX: MCQ, True/False, Matching, and Short Answer question types were never generated in "Use My Own Questions" mode. Root causes: (1) generateFromOwnQuestions() in essaymaker.js omitted moodleQuestionTypes from the AJAX payload — server always received default ["essay"]; (2) ajax.php generatefromquestions action never read or forwarded moodleQuestionTypes in the API payload; (3) server /api/generate-from-questions route did not accept moodleQuestionTypes and hardcoded moodleQuestionType="essay" on every result. Fixes: moodleQuestionTypes now sent from JS → PHP → API server; server builds type-specific AI prompts (multichoice with 4 choices, truefalse with statement + feedback, matching with 4 pairs, shortanswer with accepted answers); types distributed round-robin across questions. ChatGPT Prompt Helper updated: removed erroneous "not multiple choice, not fill-in-the-blank" rule, now reads selected question types and reflects them in the prompt TASK and QUESTION QUALITY sections. version.php → 202603250100.
 *
 * AI Quiz Maker v3.16.9 - UX: Number of questions in ChatGPT Prompt Helper now supports 1–50 (individual options) instead of the previous limited set [3,5,8,10,15,20,30,50]. Default remains 10. version.php → 202603242100.
 *
 * AI Quiz Maker v3.16.8 - FIX: Question type selector was broken — only Essay questions were ever generated regardless of which types the teacher checked. Root cause: essaymaker.js getMoodleQuestionTypes() used the wrong CSS class (.aiquizmaker-moodle-qtype-checkbox) while index.php renders checkboxes with class .aiquizmaker-qtype-check — a complete mismatch meaning zero checkboxes were ever read, fallback ['essay'] always fired. Fix: corrected selector to .aiquizmaker-qtype-check. Also fixed regenerate action: now preserves the original question's type (moodleQuestionType) when regenerating, and ajax.php regenerate action now accepts, whitelists, and forwards moodleQuestionTypes to the AI server. version.php → 202603242000.
 *
 * AI Quiz Maker v3.16.7 - FIX: Five bugs fixed from v3.16.0–v3.16.5 audit: (1) PARAM_ALPHANUMEXT on educationLevel silently stripped spaces, corrupting "Certificate IV" etc — replaced with clean_param PARAM_TEXT in all three actions (generatefromquestions, generatefromquestions 2nd, regenerate); (2) Community Services missing from PHP fallback industries list — added after Health Care & Social Assistance; (3) savesettings used PARAM_RAW with no sanitisation — now uses clean_param PARAM_TEXT capped at 5000 chars; (4) savesettings bypassed aiquizmaker_fetch() — refactored to use unified HTTP wrapper; (5) isHeadingLine() false-positived on question text ending with colon (e.g. "List three factors that contribute to the following:") — imperative verb guard added. version.php → 202603241800.
 *
 * AI Quiz Maker v3.16.6 - VERSION BUMP: Routine release packaging. version.php → 202603241700.
 *
 * AI Quiz Maker v3.16.5 - NEW: ChatGPT Prompt Helper — collapsible panel in "Use My Own Questions" mode. Generates a precision-crafted ChatGPT prompt covering question format, Moodle description cards (Part A/B/C section headings with colon syntax), Model Response blocks, blank-line separation rules, and output constraints. Options: topic, question count (3–50), education level (Cert II → Postgraduate), include model responses toggle, include section headings toggle. Copy-to-clipboard button with confirmation feedback. version.php → 202603241600.
 *
 * AI Quiz Maker v3.16.4 - VERSION BUMP: Routine release packaging. version.php → 202603241500.
 *
 * AI Quiz Maker v3.16.3 - NEW: Model Response support in "Use My Own Questions" mode. Paste a "Model Response:" heading under any question, then paste the expected answer. The AI uses the teacher's model answer as the sample answer (polishes grammar/clarity only) and derives rubric criteria directly from its content. Backward compatible — questions without a Model Response block behave as before. version.php → 202603241400.
 *
 * AI Quiz Maker v3.16.2 - VERSION BUMP: Renamed plugin button label from "AI Quiz Maker" to "AI Quiz". version.php → 202603241300.
 *
 * AI Quiz Maker v3.16.1 - ENHANCEMENT: Added dedicated "Community Services" industry with 6 job levels and 50+ role titles covering disability support, aged care, child care, youth work, family services, mental health, drug & alcohol, housing, and community development. Also expanded Health Care & Social Assistance with additional clinical and allied health roles. Community Services now appears in the industry dropdown between Health Care and Arts & Recreation. version.php → 202603241200.
 *
 * AI Quiz Maker v3.16.0 - NEW: "Use My Own Questions" mode — teachers paste pre-written questions (one per line) and the AI generates a professional marking rubric, sample answer, and grader information for each. Mode toggle tabs at the top of the form switch between "Generate from Criteria" and "Use My Own Questions". New /api/generate-from-questions endpoint. 1 credit per question. version.php → 202603240900.
 *
 * v3.15.2: VERSION BUMP — Routine release packaging. version.php → 202603231502.
 * v3.14.15: FIX BUG-EM-ADDTOQUIZ-STATIC — "Add to Quiz" threw fatal PHP error on Moodle 4.2+: "Non-static method mod_quiz\grade_calculator::recompute_quiz_sumgrades() cannot be called statically". Root cause: v3.14.13 called recompute_quiz_sumgrades() via :: (static). Fix: obtain instance via quiz_settings::create($quiz->id)->get_grade_calculator() then call ->recompute_quiz_sumgrades() as an instance method. Falls back to quiz_update_sumgrades() on Moodle < 4.2. version.php → 202603191006.
 * v3.14.14: VERSION BUMP — Clean release packaging. version.php → 202603191005.
 * v3.14.13: FIX — "Add to Quiz" deprecation fix attempt for Moodle 4.2+ (superseded by v3.14.15 — static call was incorrect).
 * v3.14.12: ENHANCEMENT — Job Level field now uses multi-select checkboxes (tick multiple levels for a mixed-level cohort). Job Title / Role field now uses a searchable scrollable checkbox panel showing all roles for the selected industry and levels, with Select All / Clear All controls and a custom free-text input for unlisted roles. All 19 industries × 6 levels fully covered.
 * v3.14.11: NEW FEATURE — "Extract Criteria" button in the Paste Learning Content section. Teachers paste a unit of competency or any learning material and click "Extract Criteria" to have the AI automatically pull all assessable performance criteria and knowledge evidence into the criteria rows. Powered by GPT-4o-mini via /api/extract-criteria. Both VET and Academic prompts. The pasted content also continues to serve as source context during generation.
 * v3.14.10: NEW FEATURE — Added "Paste Learning Content" textarea so teachers can paste source material (unit notes, training package content, legislation, etc.) to give the AI context when generating questions. Works alongside the criteria section. Both the generate and regenerate actions pass pastedContent to the API, which injects it as a SOURCE CONTENT block into the AI prompt.
 * v3.16.66: BUG FIX (BUG-GS-GROUPNUM): Gapselect group numbering now jumps by 3 (gi*3+1) so
 *   each gap gets its own group (each gap has 3 distractors + 1 correct = 4 choices mapped to
 *   one group). Previously sequential numbering caused all gaps to pull from the same group.
 *   Fix: amd/src/aiquizmaker.js buildGapGroupEditBlock uses gi*3+1; ajax.php extracts groupnums
 *   from [[N]] placeholders in sentence text (fallback gi*3+1); AI prompts updated to generate
 *   [[1]], [[4]], [[7]] … format. version.php → 2026041600066.
 *
 * v3.16.68 - FIX: Prevent standalone generic words (INFORMATION, NOTE, WARNING, OVERVIEW,
 *   INTRODUCTION, CONCLUSION, SUMMARY, etc.) from being detected as section heading description
 *   cards by isHeadingLine(). Previously the all-caps rule matched any short all-caps word,
 *   so AI output containing a standalone "INFORMATION" line would create an unwanted empty-looking
 *   description card in the quiz, and in Moodle 4.3+ the quiz editor labels all description
 *   questions as "Information" — causing visible duplication. Added standaloneBlocklist regex
 *   to exclude these generic words from the all-caps heading detection path.
 *   UX: Removed the redundant "Section Heading" label text from the description card preview
 *   (the "Description" badge already identifies the card type). AMD only. version.php → 2026041700068.
 *
 * v3.14.9: SESSION LOCK FIX — Added \core\session\manager::write_close() after auth checks to prevent blocking concurrent requests during AI generation.
 * v3.14.8: FIX: Added missing lang string for essaymaker:use capability - fixes Moodle role definition page error
 * v3.14.7: LIVE TEST VERIFIED - HLTAID009 CPR generation confirmed: 4 questions (short essay + scenario), rubrics with 4-5 criteria, sample answers 94-108 words
 * v3.14.6: Prompt engine rewrite - ChatGPT-recommended multilingual consistency, banned word enforcement, JSON schema drift elimination, Australian English spelling conventions
 *
 * @package    local_aiquizmaker
 * @copyright  2025 Essay Grader AI
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$plugin->component = 'local_aiquizmaker';
$plugin->version   = 2026072300094;
$plugin->requires = 2022041900; // Moodle 4.0+.
$plugin->supported  = [400, 500];  // Moodle 4.0 to 5.x
$plugin->maturity = MATURITY_STABLE;
$plugin->release   = '3.16.93'; // FIX-AIQM-REDECL: Wrapped local_aiquizmaker_extend_navigation() and local_aiquizmaker_extend_settings_navigation() in function_exists() guards in lib.php. Sites with old local_essaymaker plugin still installed alongside local_aiquizmaker got a fatal PHP 500 "Cannot redeclare local_aiquizmaker_extend_navigation()". The old local_essaymaker/lib.php shipped during the rename transition used these same function names. PHP-only change to lib.php. No AMD, CSS, or DB schema changes. Savepoint 2026060400088.
