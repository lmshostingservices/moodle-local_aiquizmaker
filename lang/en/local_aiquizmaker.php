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
 * Language strings for AI Quiz Maker local plugin.
 *
 * @package    local_aiquizmaker
 * @copyright  2025 Essay Grader AI
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

// Plugin metadata.
$string['pluginname'] = 'AI Quiz Maker';
$string['plugindesc'] = 'AI Quiz Maker provides students with a structured, AI-guided essay writing environment embedded directly in Moodle. Students write their essay in a purpose-built editor that offers inline AI feedback on structure, argument quality, evidence use, and writing clarity without writing the essay for them.

The interface guides students through planning (thesis statement, key arguments, evidence mapping) before they write, and offers sentence-level suggestions as they draft. Teachers configure the essay topic, word count range, assessment criteria, and rubric descriptors. The AI coaches students towards the rubric criteria during writing, then produces a pre-submission self-assessment report the student must review before submitting their final draft to the Moodle assignment.

Submission to Moodle is one click from within the AI Quiz Maker, carrying across the full essay text. Integration with AI Essay Grader allows teachers to then auto-grade the submitted essay from the quiz or assignment report page.';
$string['essay_maker'] = 'AI Quiz';
$string['ai_essay_maker'] = 'AI Quiz';
$string['page_title'] = 'AI Quiz Maker';
$string['page_description'] = 'Generate scenario-based essay questions mapped to your competency criteria';

// Settings page.
$string['siteid'] = 'Site ID';
$string['siteid_desc'] = 'Your Moodle site identifier from lms-labs.com (e.g., moodle.yoursite.com)';
$string['apikey'] = 'API Key';
$string['apikey_desc'] = 'Your API key from lms-labs.com';

// Credits.
$string['credits'] = 'Credits';
$string['credits_remaining'] = 'Credits Remaining';
$string['no_credits'] = 'Insufficient Credits';
$string['buy_credits'] = 'Purchase More Credits';
$string['credits_error'] = 'Unable to load credits';
$string['error_fetching_credits'] = 'Unable to fetch credits. Please check your configuration.';

// Paste content section.
$string['section_content'] = 'Paste Learning Content';
$string['section_content_help'] = 'Optional: paste any source material here — textbook excerpts, unit notes, training package content, or workplace documents. Click "Extract Criteria" to have the AI pull assessable criteria directly from your content, or use it as background context for the criteria below.';
$string['content_placeholder'] = 'Paste your learning content here. For example: a unit of competency, training package knowledge evidence, unit notes, or any text containing learning criteria you want to assess...';
$string['content_clear'] = 'Clear';
$string['extract_criteria_btn'] = 'Extract Criteria';
$string['extract_criteria_loading'] = 'Extracting...';
$string['extract_criteria_none'] = 'No criteria found in the pasted content. Try pasting a unit of competency or learning material with clear outcome statements.';
$string['extract_criteria_error'] = 'Failed to extract criteria. Please check your connection and try again.';

// Form sections.
$string['section_criteria'] = 'Competency Criteria';
$string['section_criteria_help'] = 'Enter the knowledge evidence or performance criteria you want to assess. Each criteria will generate one or more essay questions.';
$string['section_context'] = 'Workplace Context';
$string['add_workplace_context'] = 'Add Workplace Context';
$string['workplace_context_help'] = 'Enable this to generate scenario-based questions set in a realistic workplace. Leave disabled for simpler definition-style questions (e.g., "Define what is meant by...").';

// Criteria form.
$string['criteria'] = 'Criteria';
$string['criteria_placeholder'] = 'e.g., "Identify workplace hazards" or "KE1.2 - Apply risk control hierarchy"';
$string['criteria_help'] = 'Enter a knowledge evidence statement or performance criteria from your training package. The AI will create essay questions that assess this specific learning outcome.';
$string['questions_per_criteria'] = 'Questions per Criteria';
$string['add_criteria'] = 'Add Another Criteria';
$string['remove_criteria'] = 'Remove';
$string['questions_count'] = '{$a} question(s)';
$string['question_singular'] = '{$a} question';
$string['question_plural'] = '{$a} questions';

// Bulk add criteria.
$string['bulk_add_label'] = 'Bulk Add Criteria';
$string['bulk_add_help'] = 'Save time by pasting multiple criteria at once. Copy directly from your training package or unit outline - one criteria per line.';
$string['bulk_add_placeholder'] = 'Paste multiple criteria here, one per line. For example:
Identify workplace hazards
Apply risk control measures
Use personal protective equipment';
$string['bulk_add_button'] = 'Add All Criteria';
$string['bulk_add_success'] = '{$a} criteria added';
$string['bulk_add_empty'] = 'No criteria to add';

// Apply to All feature.
$string['apply_to_all_label'] = 'Questions per Criteria';
$string['apply_to_all_help'] = 'Quickly set the same question count for all criteria. Select a number and click "Apply to All".';
$string['apply_to_all_button'] = 'Apply to All';
$string['apply_to_all_success'] = 'Question count applied to all {$a} criteria';

// Country names (for reference - values sent to API).
$string['country_australia'] = 'Australia';
$string['country_new_zealand'] = 'New Zealand';
$string['country_uk'] = 'United Kingdom';
$string['country_us'] = 'United States';
$string['country_canada'] = 'Canada';
$string['country_singapore'] = 'Singapore';

// Australian state names.
$string['state_wa'] = 'Western Australia';
$string['state_qld'] = 'Queensland';
$string['state_nsw'] = 'New South Wales';
$string['state_vic'] = 'Victoria';
$string['state_sa'] = 'South Australia';
$string['state_tas'] = 'Tasmania';
$string['state_nt'] = 'Northern Territory';
$string['state_act'] = 'ACT';

// Education type section.
$string['section_education'] = 'Education Settings';
$string['education_type'] = 'Education Type';
$string['select_education_type'] = 'Select education type...';
$string['education_academic'] = 'Academic / University';
$string['education_vet'] = 'Vocational Education & Training (VET)';

// Academic levels.
$string['academic_level'] = 'Academic Level';
$string['select_academic_level'] = 'Select academic level...';
$string['academic_undergraduate'] = 'Undergraduate (Bachelor\'s)';
$string['academic_postgraduate'] = 'Postgraduate (Honours/Graduate Diploma)';
$string['academic_masters'] = 'Masters';
$string['academic_phd'] = 'PhD / Doctoral';

// VET levels (Australian Qualifications Framework).
$string['vet_level'] = 'VET Certificate Level';
$string['select_vet_level'] = 'Select certificate level...';
$string['vet_cert1'] = 'Certificate I';
$string['vet_cert2'] = 'Certificate II';
$string['vet_cert3'] = 'Certificate III';
$string['vet_cert4'] = 'Certificate IV';
$string['vet_diploma'] = 'Diploma';
$string['vet_adv_diploma'] = 'Advanced Diploma';

// Education type tooltips/descriptions.
$string['academic_tooltip_title'] = 'Academic Questions';
$string['academic_tooltip'] = 'Academic-style questions focus on theoretical understanding, critical analysis, and research-based responses. Students are expected to demonstrate deep conceptual knowledge, cite academic sources, and present structured arguments. Questions typically require formal academic writing with proper referencing.';
$string['vet_tooltip_title'] = 'VET Questions';
$string['vet_tooltip'] = 'Vocational Education & Training questions are practical and workplace-focused. They use realistic workplace scenarios and require students to demonstrate applied competencies. Questions emphasise how knowledge is used in real job situations, including identifying hazards, applying procedures, and solving workplace problems.';

// Moodle quiz question types section.
$string['moodle_question_types'] = 'Moodle Quiz Question Types';
$string['select_moodle_types'] = 'Choose which question types to generate. Select one or more.';
$string['mtype_essay'] = 'Essay (Written Response)';
$string['mtype_essay_tip'] = 'Open-ended questions students type a written response to. Includes marking rubric and model answer for teacher grading. Requires manual marking.';
$string['mtype_multichoice'] = 'Multiple Choice';
$string['mtype_multichoice_tip'] = 'Four options with exactly one correct answer. Auto-marked instantly. Each wrong option includes feedback explaining why it is incorrect.';
$string['mtype_truefalse'] = 'True / False';
$string['mtype_truefalse_tip'] = 'Students decide if a statement is true or false. Auto-marked. Separate feedback is provided for each choice.';
$string['mtype_matching'] = 'Matching';
$string['mtype_matching_tip'] = 'Students match 4 terms on the left to their definitions or descriptions on the right. Auto-marked. Great for testing terminology.';
$string['mtype_shortanswer'] = 'Missing Word (Fill in the Blank)';
$string['mtype_shortanswer_tip'] = 'Students complete a sentence by typing the missing key term. Auto-marked with accepted answer variations for spelling flexibility.';
$string['mtype_gapselect'] = 'Select Missing Words';
$string['mtype_gapselect_tip'] = 'Students select the correct word or phrase from a dropdown menu embedded in the sentence. Auto-marked. Ideal for vocabulary and key-term recall.';

// Question format section.
$string['question_formats'] = 'Essay Response Style';
$string['question_formats_help'] = 'Select one or more formats. The AI will create questions in the formats you choose. Different formats suit different assessment needs.';
$string['select_formats'] = 'Select one or more question formats';
$string['format_long_essay'] = 'Long Essay (500+ words)';
$string['format_short_essay'] = 'Short Essay (250 words)';
$string['format_short_answer'] = 'Short Answer (50-100 words)';
$string['format_extended_response'] = 'Extended Response (300-400 words)';
$string['format_definition'] = 'Definition/Explanation';
$string['format_list'] = 'List/Identify Items';
$string['format_scenario'] = 'Scenario-Based Response';

// Question format tooltips.
$string['format_long_essay_tip'] = 'Best for summative assessment. Requires comprehensive analysis with multiple examples and evidence.';
$string['format_short_essay_tip'] = 'Good for formative assessment. Students cover key points with brief explanations.';
$string['format_short_answer_tip'] = 'Quick knowledge checks. Students demonstrate understanding in 2-3 sentences.';
$string['format_extended_response_tip'] = 'Balanced assessment. Detailed answer with explanation and workplace application.';
$string['format_definition_tip'] = 'Tests terminology knowledge. Students explain what terms or concepts mean.';
$string['format_list_tip'] = 'Tests recall of steps or components. Students list items, procedures, or requirements.';
$string['format_scenario_tip'] = 'Tests application of knowledge. Students respond to a realistic workplace situation.';

// Self-marking question styles section.
$string['selfmarking_styles'] = 'Self-Marking Question Style';
$string['selfmarking_styles_help'] = 'Choose how the AI frames auto-marked questions. Select one or more styles to vary how knowledge is assessed.';
$string['select_selfmarking_styles'] = 'Select one or more question styles';
$string['style_scenario'] = 'Workplace Scenario';
$string['style_knowledge_check'] = 'Knowledge Check';
$string['style_procedure'] = 'Procedure / Steps';
$string['style_terminology'] = 'Terminology / Definitions';
$string['style_identification'] = 'Identification / Listing';

// Self-marking style tooltips.
$string['style_scenario_tip'] = 'Questions open with a brief realistic workplace scenario that students must apply their knowledge to.';
$string['style_knowledge_check_tip'] = 'Direct recall questions testing whether students know key facts, rules, or principles.';
$string['style_procedure_tip'] = 'Questions about the correct order of steps, processes, or procedural requirements.';
$string['style_terminology_tip'] = 'Tests understanding of key terms, definitions, and concepts from the topic area.';
$string['style_identification_tip'] = 'Students identify, classify, or select items from a set of options related to the topic.';

// Context form.
$string['country'] = 'Country';
$string['select_country'] = 'Select country...';
$string['state'] = 'State/Region (Optional)';
$string['select_state'] = 'Select state...';
$string['industry'] = 'Industry';
$string['select_industry'] = 'Select industry...';
$string['industry_details'] = 'Industry Details';
$string['industry_details_placeholder'] = 'e.g., Civil Construction on a WA Iron Ore Mine Site';
$string['job_title'] = 'Job Title / Role';
$string['job_title_placeholder'] = 'e.g., Site Supervisor';
$string['job_title_multi_help'] = 'Select all roles that apply. Tick multiple to cover a mixed cohort.';
$string['job_title_search_placeholder'] = 'Search job titles...';
$string['job_title_select_all'] = 'Select all';
$string['job_title_clear_all'] = 'Clear all';
$string['job_title_custom_placeholder'] = 'Or type custom role(s), e.g. Scaffolder, Rigger';
$string['select_job_title'] = 'Select job title...';
$string['job_title_other'] = 'Other (enter manually)';
$string['job_level'] = 'Job Level';
$string['job_level_multi_help'] = 'Select one or more levels — tick multiple for a mixed-level cohort.';
$string['select_level'] = 'Select level...';
$string['level_entry'] = 'Entry Level';
$string['level_intermediate'] = 'Intermediate';
$string['level_senior'] = 'Senior';
$string['level_supervisor'] = 'Supervisor';
$string['level_manager'] = 'Manager';
$string['level_executive'] = 'Executive';

// Generation.
$string['generate'] = 'Generate Questions';
$string['generating'] = 'Generating';
$string['processing'] = 'Processing...';

// Results.
$string['question'] = 'Question';
$string['questions_generated'] = 'Questions Generated';
$string['questions_generated_count'] = 'Successfully generated {$a} essay questions.';
$string['question_number'] = 'Question {$a}';
$string['marks'] = '3 marks';
$string['copy_to_clipboard'] = 'Copy XML';
$string['copied'] = 'Copied!';
$string['download_xml'] = 'Download XML';
$string['xml_export_title'] = 'Or Export as XML';
$string['import_instructions'] = 'Alternative: Import via Question Bank → Import';
$string['download_excel'] = 'Download Excel Mapping';
$string['excel_export_title'] = 'Question-Criteria Mapping';
$string['excel_instructions'] = 'Download a spreadsheet showing which question maps to which criteria you entered.';

// Rubric.
$string['marking_rubric'] = 'Marking Rubric:';
$string['rubric_hazard'] = 'Hazard';
$string['rubric_example'] = 'Example';
$string['rubric_control'] = 'Control';
$string['one_mark'] = '1 mark';
$string['marks_label'] = 'marks';
$string['mark_singular'] = 'mark';
$string['criteria_reference'] = 'Criteria';

// Configuration.
$string['not_configured'] = 'Plugin Not Configured';
$string['configure_message'] = 'Please configure your Site ID and API Key in Site Administration → Plugins → Local plugins → AI Quiz Maker';

// Error messages.
$string['error'] = 'Error';
$string['error_missing_criteria'] = 'Missing Criteria';
$string['error_missing_criteria_message'] = 'Please enter at least one competency criteria.';
$string['error_missing_fields'] = 'Missing Fields';
$string['error_missing_fields_message'] = 'Please fill in all required fields.';
$string['error_connection'] = 'Connection error. Please try again.';
$string['error_invalid_response'] = 'Invalid response from server';
$string['error_generation'] = 'Generation failed. Please try again.';
$string['error_generation_failed'] = 'Failed to generate questions';
$string['error_invalid_criteria'] = 'Invalid criteria data';
$string['error_unknown_action'] = 'Unknown action';
$string['error_server'] = 'Server error';
$string['insufficient_credits'] = 'Insufficient Credits';
$string['insufficient_credits_message'] = 'You do not have enough credits.';

// Success messages.
$string['success'] = 'Success';
$string['success_generated'] = 'Questions Generated';
$string['success_generated_message'] = 'Successfully generated {$a} questions.';
$string['generation_complete'] = 'Generation complete';

// Footer.
$string['powered_by'] = 'Powered by Essay Grader AI';

// Privacy.
$string['privacy:metadata:essaygraderai_api'] = 'In order to generate essay questions, competency criteria and workplace context are sent to the Essay Grader AI service.';
$string['privacy:metadata:essaygraderai_api:criteria'] = 'The competency criteria text is sent to generate relevant questions.';
$string['privacy:metadata:essaygraderai_api:industry'] = 'The industry context is sent to make questions relevant to the workplace.';
$string['privacy:metadata:essaygraderai_api:jobtitle'] = 'The job title and level are sent to calibrate question difficulty.';

// Accessibility.
$string['aria_generate_button'] = 'Generate essay questions';
$string['aria_copy_button'] = 'Copy Moodle XML to clipboard';
$string['aria_add_criteria'] = 'Add another criteria row';
$string['aria_remove_criteria'] = 'Remove this criteria row';
$string['aria_credits_display'] = 'Current credit balance';
$string['aria_loading'] = 'Loading, please wait';

// Direct to quiz.
$string['quiz_target_title'] = 'Direct Quiz Insertion';
$string['quiz_target_message'] = 'Questions will be added directly to: {$a}';

// Extra AI Instructions.
$string['extra_instructions'] = 'Extra AI Instructions';
$string['extra_instructions_help'] = 'Add custom instructions to modify how the AI generates questions. These instructions will be applied to all generated questions.';
$string['extra_instructions_placeholder'] = 'e.g., "Use simpler language for beginner students" or "Focus on practical workplace scenarios" or "Include specific regulatory references"';
$string['save_instructions'] = 'Save Instructions';
$string['instructions_saved'] = 'Saved';
$string['instructions_error'] = 'Failed to save';
$string['aiquizmaker:use'] = 'Use AI Quiz Maker';

// Input mode toggle (v3.16.0).
$string['section_input_mode'] = 'Input Mode';
$string['section_input_mode_help'] = 'Choose how you want to use the AI: generate questions from your criteria, or add your own questions and let the AI create the rubric, sample answer, and marking guide.';
$string['mode_tab_criteria'] = 'Generate from Criteria';
$string['mode_tab_ownquestions'] = 'Use My Own Questions';

// Own questions section (v3.16.0).
$string['section_ownquestions'] = 'Your Questions';
$string['section_ownquestions_help'] = 'Paste your pre-written questions below. The AI will generate a professional marking rubric, sample answer, and grader information for each question. Optionally include a Model Response under each question — the AI will use it to align the rubric criteria to your expected answer.';
$string['ownquestions_placeholder'] = 'Part A: Workplace Health and Safety

Q1. Describe the legal obligations an employer has under the Work Health and Safety Act 2011.
Model Response:
Employers must ensure the health, safety and welfare of all workers by providing safe systems of work, safe premises, adequate training and supervision, and consulting with workers on WHS matters.

Q2. Explain three strategies a worker can use to report a workplace hazard.
Model Response:
A worker can report a hazard verbally to their supervisor, complete a workplace hazard report form, or use a digital reporting system if available. All reports should be documented and followed up.

Q3. What are the key differences between a hazard and a risk?
Model Response:
A hazard is anything with the potential to cause harm, such as a wet floor or faulty equipment. A risk is the likelihood that the hazard will actually cause harm and the severity of that harm.';
$string['ownquestions_hint'] = 'Simple format: one question per line. Model Response format: paste a "Model Response:" heading under each question, then paste the expected answer. Separate each question block with a blank line. Maximum 50 questions. Credits: 1 per question.';
$string['ownquestions_btn'] = 'Generate for My Questions';
$string['ownquestions_empty_error'] = 'Please enter at least one question, one per line.';
$string['ownquestions_generating'] = 'Generating for {$a} question(s)...';

// ChatGPT Prompt Helper (v3.16.5).
$string['chatgpt_helper_title'] = 'Generate a ChatGPT Prompt';
$string['chatgpt_helper_help'] = 'Not sure what to paste? Fill in the options below to get a precision-crafted prompt you can copy into ChatGPT. ChatGPT will output questions in the exact format this tool expects — paste the result into the textarea below.';
$string['chatgpt_topic_label'] = 'Topic or subject';
$string['chatgpt_topic_placeholder'] = 'e.g. Workplace Health and Safety, NDIS Support Work, Food Safety...';
$string['chatgpt_count_label'] = 'Number of questions';
$string['chatgpt_level_label'] = 'Education level';
$string['chatgpt_modelresponses_label'] = 'Include Model Responses (teacher\'s expected answers — aligns the AI rubric to your content)';
$string['chatgpt_headings_label'] = 'Include section headings (Part A, Part B...)';
$string['chatgpt_generate_btn'] = 'Generate Prompt';
$string['chatgpt_copy_btn'] = 'Copy Prompt';
$string['chatgpt_copied'] = 'Copied!';
$string['chatgpt_topic_required'] = 'Please enter a topic before generating the prompt.';
$string['chatgpt_qtypes_note'] = 'Tip: For each question you paste, the plugin generates one version in each selected type. Select Essay only to get written-response questions; add Multiple Choice, True/False, etc. to also get auto-marked versions of every question.';
$string['chatgpt_paste_label'] = 'Paste questions below:';

// I8: Lang string used when auto-creating a question category in the quiz context.
$string['auto_category_info'] = 'Auto-created category for AI Quiz Maker questions';
