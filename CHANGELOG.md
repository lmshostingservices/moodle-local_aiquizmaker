# Changelog - AI Quiz Maker Local Plugin

All notable changes to this plugin will be documented in this file.

## [3.16.96] - 2026-08-26

### Fixed
- **The navigation gate added in 3.16.95 ran an expensive query on every page.**
  `local_aiquizmaker_extend_navigation()` fires on every page render, and it was calling
  `get_user_capability_course('moodle/question:add')`, which walks every course the user can
  reach — for a site administrator, every course on the site. That cost was paid on every page
  view by every teacher and manager.

  The callback is already handed the current context. Inside a course or an activity — the only
  place the link is any use — `has_capability()` now answers from the access cache with no query
  at all. Above course level there is nothing specific to ask, so `local/aiquizmaker:use` alone
  decides whether the link is offered and `index.php` performs the real authoring check when it
  is followed. `index.php` still uses the broader test, where it runs once on a page the user
  chose to open.

  No change to who can reach the tool.

## [3.16.95] - 2026-08-26

### Fixed - Marketplace pipeline compliance
- **ZIP root folder** is now `aiquizmaker`, not `local_aiquizmaker`. Moodle installs a `local`
  plugin at `local/<name>`, so the archive must carry the type prefix stripped.
- **`BUILD_INFO.json` excluded from the package.** It is a build artefact, not a plugin file.
- **`db/upgrade.php` had no boilerplate at all** - no GPL header, no `@package`, `@copyright` or
  `@license`. Added, along with a docblock for the upgrade function.
- **`PARAM_RAW` audited across `ajax.php`.** `sesskey` now uses `PARAM_ALPHANUMEXT`. Every other
  occurrence is a JSON blob that is `json_decode()`d and whitelisted on the following lines, or
  free text that is `clean_param(PARAM_TEXT)`d and length-capped immediately; each now carries
  the pipeline's `// pipeline-ignore: PARAM_RAW` annotation with its reason. No input handling
  changed except `sesskey`.
- **`function(` spaced to `function (`** throughout `amd/src` and the matching `amd/build`
  copies. Whitespace only; both files still parse and `src` and `build` remain identical.

## [3.16.94] - 2026-08-26

### Fixed
- **Non-editing teachers were shown the AI Quiz Maker tab on every quiz.** Four entry points —
  the quiz settings-navigation tab, the button on the quiz edit page, the site navigation link
  and `index.php` itself — all gated on `local/aiquizmaker:use` in the **system** context and
  nothing else, and `db/access.php` granted that capability to the `teacher` archetype.

  A non-editing teacher is a marking role: no `moodle/question:add`, no `mod/quiz:manage`. So
  the tab opened a question generator whose output they had no right to save anywhere. The
  settings-navigation callback is handed the quiz's own module context and never looked at it.

  Two changes, both needed:
  - `teacher` removed from the `local/aiquizmaker:use` archetypes, so new sites stop handing it
    out. Existing grants are left alone deliberately — a site may have made that choice.
  - Every entry point now also requires an authoring capability **in the context it is acting
    on**: `mod/quiz:manage` for a specific quiz — the same capability `ajax.php` already
    requires before writing a question — or `moodle/question:add` in at least one course when
    the tool is opened standalone. That takes effect immediately on existing sites regardless
    of role data.

  Editing teachers and managers are unaffected: they hold `mod/quiz:manage` on their quizzes
  and `moodle/question:add` in their courses.

## [3.16.47] - 2026-04-02

### Fixed
- **CSS spacing** — Added `margin-top: 20px` to the Question Types section in `styles.css` — fixes missing visual spacing between the VET/Academic Level dropdown and the Question Types checkboxes (tester-reported layout gap).
- **Type distribution (generate-essays)** — Server `countNote` now builds an explicit per-question type plan (`Question 1: TYPE-A, Question 2: TYPE-B, …` round-robin) instead of instructing the AI to distribute types freely. Lean non-essay `userPrompt` and `moodleTypesSection` bullet updated to use the same MANDATORY plan. Eliminates the remaining cases where GPT-4 still produced 80–100% of one type despite the v3.16.46 count instruction.
- **Regenerate DO-NOT-REUSE** — JS now sends `previousQuestionText` in both criteria and ownquestions modes; `ajax.php` forwards it to the Node.js server; `generate-essays` and `generate-from-questions` inject a `DO NOT REUSE` block when `previousQuestionText` is supplied, preventing the AI from merely tweaking the previous question on regenerate. `version.php` → `202604020047`.

## [3.16.46] - 2026-04-02

### Fixed
- **BUG-AQM-TYPE-DISTRIBUTION** — When 2+ question types were selected, GPT-4 was free to pick any distribution, routinely producing 80–100% of one type. `buildPrompt()` now inserts a MANDATORY count instruction specifying the exact number required per type (`Math.floor(total / typeCount)` with remainder distributed to the first types). `version.php` → `202604020046`.

## [3.16.25] - 2026-03-25

### Fixed
- **Matching questions**: "This part of the question was deleted after the attempt was started." — `$sub->question` corrected to `$sub->questionid` in `qtype_match_subquestions` insert. MySQL non-strict mode had silently stored all subquestions with `questionid=0`, orphaning them from the parent question.

## [3.16.24] - 2026-03-25

### Fixed
- **True/False questions**: Fatal "Table qtype_truefalse does not exist" — table name corrected from `qtype_truefalse` to `question_truefalse`.

## [3.16.16] - 2026-03-25

### Changed
- **RENAME: local_essaymaker → local_aiquizmaker**. Plugin folder, component name, capabilities, AMD modules, lang strings, and CSS classes all renamed.

> **UPGRADE NOTE for v3.16.15 and below:**
> If your Moodle site has this plugin installed in `local/essaymaker/`, you must rename that folder to `local/aiquizmaker/` on your server before running the Moodle upgrade. Otherwise Moodle will throw:
> `Plugin "local_aiquizmaker" is installed in incorrect location "$CFG->dirroot/local/essaymaker"`
>
> **Option A (recommended):** Rename `local/essaymaker` → `local/aiquizmaker` on the server, then copy the new ZIP files over, then visit `/admin/index.php`.
> **Option B:** Uninstall `local_essaymaker` via Site Administration → Plugins overview, delete the folder, then install this ZIP fresh (re-enter API key after install).

## [3.14.0] - 2026-01-02

### Added
- **Editable Marking Criteria**: Teachers can now add, edit, and remove marking criteria directly in the question edit modal
- Each criterion can have 1-5 marks assigned via dropdown selector
- Total marks badge updates dynamically as criteria are modified
- At least one criterion with description is required (validation enforced)
- Enhanced modal UI with wider layout for better editing experience

### Changed
- Question edit modal now shows all rubric controls in a clean card layout
- Dark mode support for enhanced rubric editing interface
- Save confirmation now shows total marks

## [3.13.1] - 2025-01-01

### Fixed
- Add to Quiz now correctly reads cmid parameter

## [3.12.2] - 2025-12-22

### Changed
- Added official Moodle 5.x compatibility declaration (`$plugin->supported = [400, 500]`)



## [3.12.0] - 2025-12-20

### Changed
- Migrated to centralized download architecture
- Updated versioned ZIP filename

## [3.0.0] - 2025-12-01

### Added
- Enhanced essay generation
- Multiple essay styles
- Citation support
- Rubric-based grading

## [1.0.0] - 2025-06-01

### Added
- Initial release
- AI-powered essay generation
- Moodle 4.0+ compatibility
