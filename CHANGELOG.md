# Changelog - AI Quiz Maker Local Plugin

All notable changes to this plugin will be documented in this file.

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
