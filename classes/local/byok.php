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

namespace local_aiquizmaker\local;

/**
 * Bring your own AI: write quiz questions on this site's own AI instead of LMS Labs credits.
 *
 * A faithful port of the LMS Labs /api/extract-criteria, /api/generate-essays and
 * /api/generate-from-questions services: the same prompts, the same type assignment and the same
 * response shape, run through the shared engine in AI Grader Central Config (local_aiconfig 1.1.0+).
 * Teacher content goes from this site straight to the site's AI provider; nothing is sent to LMS Labs
 * and no credits are spent. The prompts are tested against the service's own code (tests/byok_test.php).
 *
 * @package    local_aiquizmaker
 * @copyright  2026 LMS Hosting Services
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class byok {
    /** This plugin, as named in the licence's products list. */
    const COMPONENT = 'local_aiquizmaker';

    /** Service endpoints this class answers locally, and the method that does it. */
    const ROUTES = [
        '/api/extract-criteria' => 'extract_criteria',
        '/api/generate-essays' => 'generate_essays',
        '/api/generate-from-questions' => 'generate_from_questions',
    ];

    /** Language names, as the service writes them into prompts. */
    const LANGUAGES = [
        'en-AU' => 'English (Australian)',
        'en-GB' => 'English (British)',
        'en-IN' => 'English (Indian)',
        'en-US' => 'English (American)',
        'es-ES' => 'Spanish (Spain)',
        'es-US' => 'Spanish (US)',
        'fr-CA' => 'French (Canadian)',
        'fr-FR' => 'French (France)',
        'de-DE' => 'German',
        'pt-BR' => 'Portuguese (Brazilian)',
        'nl-BE' => 'Dutch (Belgian)',
        'nl-NL' => 'Dutch (Netherlands)',
        'da-DK' => 'Danish',
        'fi-FI' => 'Finnish',
        'nb-NO' => 'Norwegian',
        'sv-SE' => 'Swedish',
        'bg-BG' => 'Bulgarian',
        'cs-CZ' => 'Czech',
        'hr-HR' => 'Croatian',
        'hu-HU' => 'Hungarian',
        'pl-PL' => 'Polish',
        'ro-RO' => 'Romanian',
        'ru-RU' => 'Russian',
        'sk-SK' => 'Slovak',
        'sl-SI' => 'Slovenian',
        'sr-RS' => 'Serbian',
        'uk-UA' => 'Ukrainian',
        'et-EE' => 'Estonian',
        'lt-LT' => 'Lithuanian',
        'lv-LV' => 'Latvian',
        'el-GR' => 'Greek',
        'it-IT' => 'Italian',
        'cmn-CN' => 'Chinese (Mandarin)',
        'ja-JP' => 'Japanese',
        'ko-KR' => 'Korean',
        'id-ID' => 'Indonesian',
        'th-TH' => 'Thai',
        'vi-VN' => 'Vietnamese',
        'bn-IN' => 'Bengali',
        'gu-IN' => 'Gujarati',
        'hi-IN' => 'Hindi',
        'kn-IN' => 'Kannada',
        'ml-IN' => 'Malayalam',
        'mr-IN' => 'Marathi',
        'ta-IN' => 'Tamil',
        'te-IN' => 'Telugu',
        'ur-IN' => 'Urdu',
        'ar-XA' => 'Arabic',
        'he-IL' => 'Hebrew',
        'tr-TR' => 'Turkish',
        'sw-KE' => 'Swahili',
        'ms-MY' => 'Malay',
        'pa-IN' => 'Punjabi',
        'fil-PH' => 'Filipino',
        'cmn-TW' => 'Chinese (Traditional Mandarin)',
        'yue-HK' => 'Cantonese',
        'pt-PT' => 'Portuguese (Portugal)',
        'ca-ES' => 'Catalan',
        'is-IS' => 'Icelandic',
    ];

    /** Moodle question types the services can write. */
    const TYPES = ['essay', 'multichoice', 'truefalse', 'matching', 'gapselect', 'shortanswer'];

    /** Self-marking question styles. */
    const STYLES = ['scenario', 'knowledge_check', 'procedure', 'terminology', 'identification'];

    /** @var callable|null Test hook: replaces the AI call with fn(system, user, maxtokens): string. */
    public static $aicall = null;

    /**
     * Should this site write questions on its own AI?
     *
     * @return bool
     */
    public static function enabled(): bool {
        return class_exists('\local_aiconfig\byok\ai')
            && \local_aiconfig\byok\ai::uses_own_ai(self::COMPONENT, 'text');
    }

    /**
     * Answer a service call locally when bring your own AI is on.
     *
     * @param string $url The LMS Labs URL the plugin was about to call.
     * @param array|null $payload Its JSON payload.
     * @return array|null A fetch result (success, body, error, httpcode), or null to call LMS Labs as usual.
     */
    public static function handle(string $url, ?array $payload): ?array {
        $path = (string) parse_url($url, PHP_URL_PATH);
        if (!isset(self::ROUTES[$path]) || !self::enabled()) {
            return null;
        }
        $method = self::ROUTES[$path];
        $result = self::$method($payload ?? []);
        return ['success' => true, 'body' => json_encode($result), 'error' => null, 'httpcode' => 200];
    }

    /**
     * Port of POST /api/extract-criteria.
     *
     * @param array $p pastedContent, educationType.
     * @return array ok, criteria | ok false, error.
     */
    public static function extract_criteria(array $p): array {
        $pastedcontent = (string) ($p['pastedContent'] ?? '');
        $isacademic = ($p['educationType'] ?? 'vet') === 'academic';
        $rules = <<<'EOT'

You are NOT extracting or copying. You are INTERPRETING the content as a whole and GENERATING formal, assessable competency criteria written in standardised criteria language.

ABSOLUTE RULES — failure to follow any of these is a wrong answer:
1. Read the ENTIRE pasted content first to understand the intent, skills, knowledge and outcomes it implies.
2. Do NOT copy any text from the source. Do not echo phrases, headings, bullet labels, examples, or rubric markers.
3. Do NOT include source-formatting tokens such as "GOOD", "BAD", "GOOD 1", "BAD 1", "Example:", "Note:", numbered list prefixes, dot points, or any heading text from the source.
4. Do NOT preserve source codes/identifiers (e.g. "KE1.1", "PC2.3", "LO1", "1.1"). Generate clean criteria text only.
5. Do NOT summarise the content. This is not a summary — each criterion is a discrete assessable statement.
6. Each criterion MUST start with an assessable competency verb such as: Identify, Demonstrate, Apply, Evaluate, Analyse, Describe, Explain, Implement, Comply with, Follow, Use, Perform, Communicate, Document, Maintain, Monitor, Assess, Plan, Prepare, Review.
7. Each criterion MUST describe ONE of: a skill, a knowledge requirement, or a measurable action — written as something a learner can be assessed against.
8. Each criterion MUST be a complete, self-contained, formal statement that reads like a published competency standard.
9. If the source content contains both desirable and undesirable behaviour examples (e.g. "GOOD: uses PPE / BAD: ignores safety"), CONVERT them into the underlying positive competency requirement (e.g. "Demonstrate correct use of personal protective equipment in accordance with workplace safety procedures").
10. Output ONLY valid JSON in the form {"criteria": ["...","..."]} — no commentary, no preamble, no markdown.

LENGTH AND COUNT:
- Generate between 3 and 30 criteria depending on the breadth of the content.
- Each criterion: 60 to 220 characters, formal English, no abbreviations of common words.

WORKED EXAMPLE — illustrative only, do not copy this wording:
Source: "GOOD: Uses PPE correctly\nBAD: Doesn't follow safety rules"
Correct output:
{"criteria":[
  "Demonstrate correct use of personal protective equipment (PPE) in accordance with workplace safety procedures",
  "Follow established workplace health and safety procedures at all times"
]}
INCORRECT output (do NOT do this): {"criteria":["GOOD 1","BAD 1"]}
EOT;
        if ($isacademic) {
            $system = 'You generate formal academic learning outcomes / assessment criteria from arbitrary learning content '
                . '(lecture notes, course outlines, readings, rubrics, examples). You do not extract or copy — you interpret '
                . 'and rewrite into formal, assessable academic criteria.' . $rules . "\n\nACADEMIC STYLE: Use Bloom's-aligned "
                . 'verbs and academic phrasing. Each criterion should describe a learning outcome the student can be assessed '
                . 'against in an academic setting.';
        } else {
            $system = 'You generate formal Australian VET-style competency criteria (Performance Criteria / Knowledge Evidence '
                . 'wording) from arbitrary training content (unit notes, training material, rubrics, workplace examples). You do '
                . 'not extract or copy — you interpret and rewrite into formal, assessable competency criteria suitable for an '
                . 'Australian RTO unit of competency.' . $rules . "\n\nVET STYLE: Phrase each criterion as a Performance "
                . 'Criterion or Knowledge Evidence statement would be written in an Australian Training Package — formal, '
                . 'present-tense, learner-focused, workplace-aligned, free of source-document artefacts.';
        }
        $user = 'Below is raw learning content. Read it as a whole, interpret the underlying skills, knowledge and assessable '
            . 'actions, then GENERATE formal competency criteria per the rules in the system message. Do not copy, do not '
            . "summarise, do not preserve source labels or examples.\n\n----- BEGIN CONTENT -----\n" . trim($pastedcontent)
            . "\n----- END CONTENT -----";
        try {
            $raw = self::ai($system, $user, 4000);
        } catch (\Throwable $e) {
            return ['ok' => false, 'error' => self::message($e), 'byok' => true];
        }
        $parsed = self::decode($raw, true);
        $criteria = [];
        if (isset($parsed['criteria']) && is_array($parsed['criteria'])) {
            foreach ($parsed['criteria'] as $c) {
                if (is_string($c) && trim($c) !== '') {
                    $criteria[] = $c;
                }
            }
        }
        return ['ok' => true, 'criteria' => array_slice($criteria, 0, 50), 'byok' => true];
    }

    /**
     * Port of POST /api/generate-essays (criteria mode, also used to regenerate one question).
     *
     * @param array $p The service payload.
     * @return array ok, questions, credits, questionsGenerated | ok false, error, message.
     */
    public static function generate_essays(array $p): array {
        \core_php_time_limit::raise(600);
        $criteria = [];
        foreach ((array) ($p['criteria'] ?? []) as $c) {
            $text = trim((string) ($c['text'] ?? ''));
            if ($text !== '') {
                $criteria[] = ['text' => $text, 'questionsCount' => max(1, min(10, (int) ($c['questionsCount'] ?? 1)))];
            }
        }
        $country = (string) ($p['country'] ?? '');
        $state = (string) ($p['state'] ?? '');
        $industry = (string) ($p['industry'] ?? '');
        $industrydetails = (string) ($p['industryDetails'] ?? '');
        $jobtitle = (string) ($p['jobTitle'] ?? '');
        $joblevel = (string) ($p['jobLevel'] ?? '');
        $educationtype = (string) ($p['educationType'] ?? 'vet');
        $educationlevel = (string) ($p['educationLevel'] ?? '');
        $questionformats = array_key_exists('questionFormats', $p) ? (array) $p['questionFormats']
            : ['short_essay', 'short_answer', 'scenario'];
        $extrainstructions = (string) ($p['extraInstructions'] ?? '');
        $pastedcontent = (string) ($p['pastedContent'] ?? '');
        $moodletypes = array_key_exists('moodleQuestionTypes', $p) ? (array) $p['moodleQuestionTypes'] : ['essay'];
        $styles = array_key_exists('selfMarkingStyles', $p) ? (array) $p['selfMarkingStyles'] : ['scenario', 'knowledge_check'];
        $language = (string) ($p['language'] ?? '');
        $previous = (string) ($p['previousQuestionText'] ?? '');
        $wctx = !array_key_exists('workplaceContextEnabled', $p) || !empty($p['workplaceContextEnabled']);

        if (!$wctx) {
            $questionformats = array_values(array_filter($questionformats, function($f) {
                return $f !== 'scenario';
            }));
            if (!$questionformats) {
                $questionformats = ['short_answer', 'definition'];
            }
            $styles = array_values(array_filter($styles, function($s) {
                return $s !== 'scenario';
            }));
            if (!$styles) {
                $styles = ['knowledge_check', 'terminology'];
            }
        }
        if (!$criteria) {
            $criteria = [['text' => 'General knowledge questions', 'questionsCount' => 3]];
        }

        $location = ($state && $country) ? "{$state}, {$country}" : ($country ?: ($state ?: ''));
        $industrycontext = $industrydetails ?: ($industry ?: '');
        $hascontext = $country || $industry || $jobtitle || $joblevel;
        $levellabels = [
            'cert1' => 'Certificate I', 'cert2' => 'Certificate II', 'cert3' => 'Certificate III',
            'cert4' => 'Certificate IV', 'diploma' => 'Diploma', 'adv_diploma' => 'Advanced Diploma',
            'undergraduate' => "Undergraduate (Bachelor's)", 'postgraduate' => 'Postgraduate (Honours/Graduate Diploma)',
            'masters' => 'Masters', 'phd' => 'PhD/Doctoral',
        ];
        $levellabel = $educationlevel ? ($levellabels[$educationlevel] ?? $educationlevel) : '';
        $formatdescriptions = [
            'long_essay' => ['Long Essay', '500+ words', 'comprehensive analysis with multiple paragraphs, citations where relevant'],
            'short_essay' => ['Short Essay', '200-300 words', 'focused response with clear structure and key points'],
            'extended_response' => ['Extended Response', '300-400 words', 'detailed explanation with examples and application'],
            'short_answer' => ['Short Answer', '50-100 words', 'concise, direct response demonstrating understanding'],
            'definition' => ['Definition/Explanation', '50-150 words', 'clear explanation of terms, concepts, or procedures'],
            'list' => ['List/Identify', 'bullet points or numbered list', 'identify and list relevant items, steps, or components'],
            'scenario' => ['Scenario-Based', '200-400 words', 'apply knowledge to a realistic workplace situation'],
        ];
        $formatlines = [];
        foreach ($questionformats as $f) {
            if (is_string($f) && isset($formatdescriptions[$f])) {
                [$name, $words, $style] = $formatdescriptions[$f];
                $formatlines[] = "- {$name}: {$words} - {$style}";
            }
        }
        $formatinstructions = $formatlines ? implode("\n", $formatlines)
            : '- Short Essay: 200-300 words - focused response with clear structure';
        $educationtype = $educationtype ?: 'vet';
        $languageinstruction = self::language_instruction($language);

        $ctx = compact('country', 'state', 'industry', 'industrycontext', 'jobtitle', 'joblevel', 'educationtype',
            'educationlevel', 'levellabel', 'location', 'hascontext', 'formatinstructions', 'extrainstructions',
            'pastedcontent', 'moodletypes', 'styles', 'previous', 'wctx', 'languageinstruction');

        $questions = [];
        $lasterror = '';
        $start = 0;
        foreach ($criteria as $criterion) {
            try {
                $questions = array_merge($questions, self::process_criterion($criterion, $start, $ctx));
            } catch (\Throwable $e) {
                $lasterror = self::message($e);
            }
            $start += $criterion['questionsCount'];
        }
        if (!$questions) {
            return ['ok' => false, 'error' => 'GENERATION_ERROR',
                'message' => $lasterror ?: 'Failed to generate questions', 'byok' => true];
        }
        return ['ok' => true, 'questions' => $questions, 'credits' => -1,
            'questionsGenerated' => count($questions), 'byok' => true];
    }

    /**
     * Prompts for one criterion of /api/generate-essays.
     *
     * @param array $criterion text, questionsCount.
     * @param int $criterionindex Index of this criterion's first question across the whole request.
     * @param array $ctx Request context built by generate_essays().
     * @return array [system, user, hasonlyessay, usetypes]
     */
    public static function criterion_prompts(array $criterion, int $criterionindex, array $ctx): array {
        extract($ctx);
        $ctext = $criterion['text'];
        $count = $criterion['questionsCount'];
        $isacademic = $educationtype === 'academic';
        $forlevel = $levellabel ? " You are creating questions for {$levellabel} students." : '';
        $names = $wctx
            ? '- NAME DIVERSITY (mandatory whenever a person name is used): Use a wide variety of culturally diverse first names. NEVER reuse the same name across the questions you generate in this batch. AVOID over-used defaults (Sarah, John, Emma, Mike, Jane, David). Prefer rotating through diverse names such as: Zara, Daniel, Priya, James, Leila, Tom, Amara, Wei, Sofia, Raj, Fatima, Chris, Ngozi, Ben, Yuki, Alex, Kezia, Jordan, Hana, Mateo, Aaliyah, Finn, Ines, Kofi, Maya. Where a name is not strictly required, omit it entirely.'
            : '- NO PERSON NAMES (ABSOLUTE — workplace context is disabled): NEVER include any named persons, workers, colleagues, customers, or characters in ANY question, rubric, scenario, or sample answer. Do NOT use Marcus, Sarah, John, Emma or any other person name. All questions must be entirely impersonal, concept-based, and direct.';

        if ($isacademic) {
            $nowctx = !$wctx ? '- WORKPLACE CONTEXT IS DISABLED: Do NOT generate workplace scenarios or named worker/colleague characters. Generate direct concept, theory, definition, or analysis questions only.' : '';
            $levelhead = $levellabel ? "ACADEMIC LEVEL CALIBRATION ({$levellabel}):" : 'ACADEMIC EXPECTATIONS:';
            $ug = $educationlevel === 'undergraduate' ? <<<'EOT'
- BLOOM'S FOCUS: Understand, Apply, Analyze
- Demonstrate foundational mastery with evidence-based explanations
- Apply theoretical frameworks to given scenarios
- Compare and contrast different theoretical perspectives
- Begin developing critical evaluation skills
- Reference seminal works and established theories
EOT
                : '';
            $pg = $educationlevel === 'postgraduate' ? <<<'EOT'
- BLOOM'S FOCUS: Analyze, Evaluate, Synthesize
- Critically evaluate competing theories and methodological approaches
- Synthesize information across multiple sources and perspectives
- Identify gaps, limitations, and assumptions in existing literature
- Propose justified positions based on evidence
- Engage with contemporary debates in the field
EOT
                : '';
            $ma = $educationlevel === 'masters' ? <<<'EOT'
- BLOOM'S FOCUS: Evaluate, Create, Synthesize
- Demonstrate advanced critical analysis of complex problems
- Evaluate and compare theoretical frameworks for specific applications
- Apply sophisticated research methodology understanding
- Synthesize complex information into coherent arguments
- Propose evidence-based recommendations or solutions
- Engage with cutting-edge research in the field
EOT
                : '';
            $phd = $educationlevel === 'phd' ? <<<'EOT'
- BLOOM'S FOCUS: Create, Evaluate at Expert Level
- Demonstrate expert-level critical analysis and original thinking
- Contribute novel perspectives or theoretical insights
- Critically evaluate methodological approaches and propose alternatives
- Demonstrate comprehensive and nuanced literature awareness
- Identify significant gaps requiring further research
- Position work within broader scholarly conversations
EOT
                : '';
            $none = !$educationlevel ? <<<'EOT'
- Create questions suitable for educated adult learners
- Balance conceptual understanding with practical application
- Use clear academic language without unnecessary jargon
- Require evidence-based reasoning and structured arguments
EOT
                : '';
            $system = <<<EOT
You are a world-class academic assessment specialist with 25+ years experience designing university examinations, peer-reviewed publications on assessment theory, and expertise in constructive alignment (Biggs).{$forlevel}

NON-NEGOTIABLE RULES:
- Always return valid JSON only. No commentary outside JSON.
- JSON keys MUST remain in English. Only values may be in the target language.
- Avoid vague instructions, generic academic fluff, and repetition between rubric levels.
- In any language, avoid vague corporate/marketing language. If the target language has common buzzword equivalents, do not use them.
{$names}
{$nowctx}

YOUR ASSESSMENT PHILOSOPHY:
1. CONSTRUCTIVE ALIGNMENT: Every question must align with learning outcomes at the appropriate cognitive level
2. AUTHENTIC ASSESSMENT: Questions should mirror real academic/professional challenges
3. HIGHER-ORDER THINKING: Prioritize analysis, synthesis, and evaluation over simple recall
4. ACADEMIC INTEGRITY: Questions should be resistant to AI-generated responses
   "AI-resistant" means the question requires contextual reasoning, methodological justification, and/or evaluation of trade-offs. Sample answers are for markers and should model high-quality reasoning, not provide a memorisable list.

{$levelhead}
{$ug}
{$pg}
{$ma}
{$phd}
{$none}

QUESTION QUALITY STANDARDS:
- Questions must require genuine intellectual engagement, not just information retrieval
- Avoid questions answerable through surface-level web searches
- Include sufficient context for meaningful responses
- Questions should have clear scope while allowing depth

RUBRIC DESIGN PRINCIPLES:
- Rubric criteria must be CRITERION-REFERENCED (describe the quality standard, not specific content)
- Use evaluative language: "demonstrates," "analyzes," "synthesizes," "evaluates"
- NEVER reveal answer content in rubric descriptions
- Each criterion should assess a distinct dimension of quality
- Do not duplicate wording across rubric levels

SELF-CHECK BEFORE RETURN:
- Confirm JSON keys are in English, only values in the target language.
- Confirm no rubric level gives away exact answer text.
- Confirm alignment to the provided criterion.
- Return only valid JSON.{$languageinstruction}
EOT;
        } else {
            $nowctx = !$wctx ? '- WORKPLACE CONTEXT IS DISABLED: Do NOT generate workplace scenarios, do NOT name workers/colleagues/customers, do NOT describe workplace settings, situations, or roles. Generate direct knowledge, definition, procedure, or terminology questions only. Question stems must NOT begin with phrases like "You are working as...", "Imagine you are...", "A colleague approaches you...", "At your workplace...", or any equivalent situational framing.' : '';
            $levelhead = $levellabel ? "AQF LEVEL CALIBRATION ({$levellabel}):" : 'COMPETENCY EXPECTATIONS:';
            $prefer = $wctx
                ? '- Prefer scenario-based workplace tasks, short answers, procedural explanations, and decision-making'
                : '- Prefer short answers, definitions, procedural explanations, and direct knowledge checks (NO workplace scenarios, NO named characters, NO situational framing)';
            $c12 = '';
            if (in_array($educationlevel, ['cert1', 'cert2'], true)) {
                $scen = $wctx ? '- Use straightforward workplace scenarios with clear parameters'
                    : '- Use direct concept/knowledge questions WITHOUT workplace scenarios';
                $c12 = "- COMPETENCY FOCUS: Routine tasks under direct supervision\n"
                    . "- Assess recall of fundamental procedures and safety requirements\n"
                    . "{$scen}\n"
                    . "- Test ability to follow standard operating procedures\n"
                    . "- Focus on \"what to do\" rather than \"why\"\n"
                    . "- Ensure language is accessible to entry-level workers\n"
                    . "{$prefer}\n"
                    . '- Avoid purely theoretical essays unless the criterion explicitly requires it';
            }
            $c34 = '';
            if (in_array($educationlevel, ['cert3', 'cert4'], true)) {
                $scen = $wctx ? '- Include realistic workplace scenarios requiring judgment calls'
                    : '- Use direct concept/knowledge questions WITHOUT workplace scenarios';
                $c34 = "- COMPETENCY FOCUS: Varied contexts, some autonomy, problem-solving\n"
                    . "{$scen}\n"
                    . "- Test ability to adapt procedures to different situations\n"
                    . "- Require selection of appropriate methods or equipment\n"
                    . "- Include troubleshooting and basic problem-solving\n"
                    . "- Assess understanding of \"why\" behind procedures\n"
                    . "{$prefer}\n"
                    . '- Avoid purely theoretical essays unless the criterion explicitly requires it';
            }
            $dip = '';
            if (in_array($educationlevel, ['diploma', 'adv_diploma'], true)) {
                $scen = $wctx ? '- Include scenarios requiring analysis of multiple factors'
                    : '- Use direct conceptual/analytical questions WITHOUT workplace scenarios or named characters';
                $dip = "- COMPETENCY FOCUS: Complex judgments, planning, leadership\n"
                    . "{$scen}\n"
                    . "- Test ability to plan, coordinate, and evaluate work outcomes\n"
                    . "- Require higher-level problem-solving and decision-making\n"
                    . "- Assess ability to mentor others and quality assure work\n"
                    . '- Include scenarios with incomplete information requiring professional judgment';
            }
            $none = '';
            if (!$educationlevel) {
                $scen = $wctx ? '- Use practical, industry-authentic scenarios'
                    : '- Use direct concept/knowledge questions WITHOUT workplace scenarios or named characters';
                $none = "- Create questions suitable for competent workers\n"
                    . "{$scen}\n"
                    . "- Balance knowledge assessment with application\n"
                    . '- Focus on what workers actually need to do safely and effectively';
            }
            if ($wctx && $industrycontext) {
                $authentic = "- Scenarios must feel authentic to the {$industrycontext} industry\n"
                    . "- Use correct industry terminology, equipment names, and procedures\n"
                    . "- Reference realistic workplace situations, not generic examples\n"
                    . '- Include industry-specific hazards, regulations, or considerations';
            } else if ($wctx && $hascontext) {
                $authentic = "- Use realistic workplace scenarios from typical industries\n"
                    . "- Include authentic workplace pressures, constraints, or considerations\n"
                    . '- Avoid overly simplified or obviously "test-like" scenarios';
            } else {
                $authentic = <<<'EOT'
- Generate DEFINITION-STYLE or CONCEPT-FOCUSED questions (NOT workplace scenarios)
- Focus on explaining concepts, defining terms, describing procedures, or listing steps
- Questions should test knowledge and understanding without specific workplace context
- Suitable for general professional or educational settings
- Avoid creating specific workplace scenarios - keep questions generic and conceptual
- Do NOT include names, workplace settings, named characters, colleagues, customers, or any situational framing
EOT;
            }
            $system = <<<EOT
You are a world-class VET assessment specialist with 20+ years experience in Australian Registered Training Organizations. You hold TAE qualifications and have served as an ASQA auditor. Your assessments have passed rigorous compliance audits and are used as exemplars for new assessors.{$forlevel}

NON-NEGOTIABLE RULES:
- Always return valid JSON only. No commentary outside JSON.
- JSON keys MUST remain in English. Only values may be in the target language.
- Avoid vague instructions, generic academic fluff, and repetition between rubric levels.
- In any language, avoid vague corporate/marketing language. If the target language has common buzzword equivalents, do not use them.
{$names}
{$nowctx}

YOUR ASSESSMENT PHILOSOPHY:
1. COMPETENCY-BASED: Questions must assess whether learners can PERFORM in real workplace conditions
2. AUTHENTIC ASSESSMENT: Scenarios must reflect genuine industry situations, not textbook abstractions
3. HOLISTIC JUDGMENT: Assessment captures knowledge, skills, AND performance evidence
4. AUDIT-READY: Every question must meet ASQA/RTO compliance requirements

{$levelhead}
{$c12}
{$c34}
{$dip}
{$none}

INDUSTRY AUTHENTICITY REQUIREMENTS:
{$authentic}

RUBRIC DESIGN FOR VET:
- Rubric criteria must describe observable performance or verifiable knowledge
- Use action verbs: "identifies," "demonstrates," "applies," "selects"
- NEVER include specific answers in rubric descriptions
- Criteria should align with elements of competency where applicable
- Each criterion should be independently assessable
- Do not duplicate wording across rubric levels

SELF-CHECK BEFORE RETURN:
- Confirm JSON keys are in English, only values in the target language.
- Confirm no rubric level gives away exact answer text.
- Confirm alignment to the provided criterion.
- Confirm questions reflect observable performance, not purely theoretical content (for cert1-cert4).
- Return only valid JSON.{$languageinstruction}
EOT;
        }

        // Context section.
        $lines = [];
        if ($educationtype) {
            $lines[] = '- Education Type: ' . ($isacademic ? 'Academic/University' : 'Vocational Education & Training (VET)');
        }
        if ($levellabel) {
            $lines[] = "- Level: {$levellabel}";
        }
        if ($country) {
            $lines[] = "- Country: {$country}";
        }
        if ($location) {
            $lines[] = "- Location: {$location}";
        }
        $spelling = '';
        if ($country === 'Australia' || $country === 'AU') {
            $spelling = <<<'EOT'


LANGUAGE REQUIREMENTS (CRITICAL):
Use Australian English spelling throughout ALL content including questions, rubrics, and sample answers:
- organisation (not organization), behaviour (not behavior), colour (not color)
- recognise, analyse, practise (not recognize, analyze, practice)
- labour, favour, honour (not labor, favor, honor)
- centre, metre, litre (not center, meter, liter)
- travelled, cancelled, modelling (not traveled, canceled, modeling)
- licence (noun), license (verb), defence (not defense)
- programme (for events/plans), program (for software)
This is mandatory - the content MUST use Australian English conventions.
EOT;
        } else if ($country === 'United Kingdom' || $country === 'UK') {
            $spelling = "\n\nLANGUAGE REQUIREMENTS:\nUse British English spelling throughout all content (organisation, behaviour, colour, centre, etc.).";
        } else if ($country === 'United States' || $country === 'US' || $country === 'USA') {
            $spelling = "\n\nLANGUAGE REQUIREMENTS:\nUse American English spelling throughout all content (organization, behavior, color, center, etc.).";
        } else if ($country === 'Canada' || $country === 'CA') {
            $spelling = "\n\nLANGUAGE REQUIREMENTS:\nUse Canadian English spelling throughout all content (organisation/organization acceptable, colour, centre, etc.).";
        } else if ($country === 'New Zealand' || $country === 'NZ') {
            $spelling = "\n\nLANGUAGE REQUIREMENTS:\nUse New Zealand English spelling throughout all content (organisation, behaviour, colour, centre - similar to British/Australian English).";
        }
        if ($industry) {
            $lines[] = "- Industry: {$industry}";
        }
        if ($industrycontext && $industrycontext !== $industry) {
            $lines[] = "- Specific Context: {$industrycontext}";
        }
        if ($jobtitle) {
            $lines[] = "- Job Title/Role: {$jobtitle}";
        }
        if ($joblevel) {
            $lines[] = "- Job Level: {$joblevel}";
        }
        $contextsection = $lines ? "CONTEXT:\n" . implode("\n", $lines) . $spelling
            : 'CONTEXT: Generate general professional/educational questions suitable for adult learners.';

        [$hasmarking, $singlemark] = self::marking_flags($extrainstructions);

        $extrasection = '';
        if (trim($extrainstructions) !== '') {
            if ($singlemark) {
                $markblock = <<<'EOT'

***** SINGLE MARK MODE - EXTREMELY IMPORTANT *****
The teacher wants EXACTLY 1 MARK TOTAL per question.
This means: ONE rubric criterion, ONE sample answer entry, totalMarks = 1.
Do NOT create multiple rubric criteria. The rubric array must have exactly ONE item.
Even though each criterion is worth 1 mark, the teacher wants ONLY ONE criterion per question.
*****

EOT;
            } else if ($hasmarking) {
                $markblock = <<<'EOT'

*** CRITICAL: THE TEACHER HAS PROVIDED MARKING INSTRUCTIONS ***
You MUST follow their exact marking requirements. If they said "2 marks per question",
you MUST create exactly 2 rubric criteria per question (totalMarks: 2).
DO NOT use the default 2-8 criteria guidance - use the teacher's requirement instead.

EOT;
            } else {
                $markblock = '';
            }
            $extrasection = "=== MANDATORY TEACHER INSTRUCTIONS - READ FIRST ===\n"
                . "STOP AND READ THESE INSTRUCTIONS CAREFULLY BEFORE GENERATING ANYTHING.\n"
                . "The teacher has provided specific requirements that OVERRIDE all default settings.\n"
                . $markblock . "\nTEACHER INSTRUCTIONS:\n" . trim($extrainstructions)
                . "\n=== END MANDATORY INSTRUCTIONS ===\n\n";
        }

        $pastedsection = '';
        if (trim($pastedcontent) !== '') {
            $examples = $wctx ? '- Scenarios and examples should reflect details from this content where appropriate'
                : '- Examples and applications should reflect details from this content where appropriate — do NOT use workplace scenarios or situational framing';
            $pastedsection = "SOURCE CONTENT (TEACHER-PROVIDED):\n"
                . "The teacher has provided the following learning material. Use this content as the knowledge base for generating questions and marking criteria. Draw on its terminology, concepts, examples, and details.\n"
                . "---\n" . trim($pastedcontent) . "\n---\n"
                . "When generating questions:\n"
                . "- Ground questions in the specific content, terminology, and concepts from the source above\n"
                . "{$examples}\n"
                . "- Marking criteria should reflect what a student who studied this content would be expected to know\n"
                . "- Still ensure every question DIRECTLY assesses the criterion stated below\n\n";
        }

        $prevsection = '';
        if (trim($previous) !== '') {
            $prevtext = trim($previous);
            $prevsection = <<<EOT

⚠️ REGENERATE — FORCE COMPLETELY NEW QUESTION ⚠️
IMPORTANT: You MUST generate a completely new question. This is a regenerate request.

STRICT RULES (ALL are mandatory):
- Do NOT reuse the previous question text in any form
- Do NOT rephrase or slightly modify the existing question — this counts as reuse
- Do NOT only change the explanation while keeping the same question — this is WRONG
- Create a NEW question stem, NEW answer options, and a NEW explanation
- ALL content must be different: question wording, all options/choices, and feedback text
- Approach from a DIFFERENT angle, scenario, context, or application of the same topic

Previous question(s) to AVOID — do NOT produce anything similar to these:
"""
{$prevtext}
"""

If the new question stem starts with the same first 50 characters as any previous version, the response is INCORRECT and must be regenerated.


EOT;
        }

        // Self-marking style guidance.
        $activestyles = array_values(array_filter($styles, function($s) {
            return in_array($s, self::STYLES, true);
        }));
        $usestyles = $activestyles ?: ['scenario', 'knowledge_check'];
        $styledescriptions = [
            'scenario' => 'Workplace Scenario — open with a brief realistic workplace situation and ask the student to apply their knowledge to it',
            'knowledge_check' => 'Knowledge Check — direct recall of key facts, rules, regulations, or principles relevant to the criterion',
            'procedure' => 'Procedure / Steps — test knowledge of the correct order, sequence, or procedural requirements',
            'terminology' => 'Terminology / Definitions — test understanding of key terms, definitions, and concepts',
            'identification' => 'Identification / Listing — students identify, classify, or select items/characteristics from options',
        ];
        $filtered = $wctx ? $usestyles : array_values(array_filter($usestyles, function($s) {
            return $s !== 'scenario';
        }));
        $safestyles = $filtered ?: ($wctx ? ['knowledge_check'] : ['knowledge_check', 'terminology']);
        $stylelines = array_map(function($s) use ($styledescriptions) {
            return '- ' . ($styledescriptions[$s] ?? $s);
        }, $safestyles);
        $styleinstructions = "QUESTION STYLE GUIDANCE:\nVary question style across these selected approaches:\n"
            . implode("\n", $stylelines) . "\nDistribute across styles where multiple questions are generated."
            . (($wctx && in_array('scenario', $safestyles, true))
                ? ' When using Workplace Scenario style, embed a short (1–2 sentence) realistic '
                    . ($industrycontext ?: 'workplace') . ' scenario in the question stem.' : '')
            . (!$wctx ? "\nDO NOT include workplace scenarios, named workers/customers, or situational role-play in any question — workplace context is disabled." : '');

        // Moodle question types.
        $activetypes = array_values(array_filter($moodletypes, function($t) {
            return in_array($t, self::TYPES, true);
        }));
        $usetypes = $activetypes ?: ['essay'];
        $hasonlyessay = count($usetypes) === 1 && $usetypes[0] === 'essay';
        $formatparts = [];
        $countnote = '';
        $ntypes = count($usetypes);
        if (!$hasonlyessay) {
            $typenames = [
                'essay' => 'Essay (written response with rubric and model answer — requires manual marking)',
                'multichoice' => 'Multiple Choice (4 options, exactly one correct answer — auto-marked)',
                'truefalse' => 'True/False (binary declarative statement — auto-marked)',
                'matching' => 'Matching (4 pairs: terms matched to definitions — auto-marked)',
                'gapselect' => 'Select Missing Words (sentence with [[1]], [[2]] gap placeholders — students pick from dropdowns — auto-marked)',
                'shortanswer' => 'Missing Word / Fill in the Blank (complete the sentence by typing — auto-marked)',
            ];
            $typelist = implode("\n", array_map(function($t) use ($typenames) {
                return '  - ' . ($typenames[$t] ?? $t);
            }, $usetypes));
            if ($count === 1) {
                $countnote = 'Generate exactly 1 question using the type: '
                    . strtoupper($usetypes[$criterionindex % $ntypes]) . '.';
            } else {
                $assign = [];
                for ($qi = 0; $qi < $count; $qi++) {
                    $assign[] = '  Question ' . ($qi + 1) . ': ' . strtoupper($usetypes[($criterionindex + $qi) % $ntypes]);
                }
                $countnote = "MANDATORY TYPE ASSIGNMENT — generate questions in EXACTLY this order, no deviation:\n"
                    . implode("\n", $assign) . "\nTotal: {$count} questions. This assignment is NON-NEGOTIABLE — do NOT change the type for any question.";
            }
            $assigned = [];
            for ($qi = 0; $qi < $count; $qi++) {
                $assigned[$usetypes[($criterionindex + $qi) % $ntypes]] = true;
            }
            if (isset($assigned['multichoice'])) {
                $formatparts[] = <<<EOT
MULTIPLE CHOICE JSON FORMAT:
{
  "moodleQuestionType": "multichoice",
  "questionText": "Which of the following best describes...?",
  "choices": [
    {"text": "Correct answer text", "isCorrect": true, "feedback": "Correct! This is right because [specific reason]."},
    {"text": "Plausible but wrong option 1", "isCorrect": false, "feedback": "Incorrect. [Explain specifically why this option is wrong]."},
    {"text": "Plausible but wrong option 2", "isCorrect": false, "feedback": "Incorrect. [Explain specifically why this option is wrong]."},
    {"text": "Plausible but wrong option 3", "isCorrect": false, "feedback": "Incorrect. [Explain specifically why this option is wrong]."}
  ],
  "explanation": "Comprehensive explanation of why the correct answer is right and the key concept being tested.",
  "generalFeedback": "The correct answer is [answer text] because [detailed explanation for all students after submitting].",
  "criteriaReference": "{$ctext}",
  "totalMarks": 1
}
EOT;
            }
            if (isset($assigned['truefalse'])) {
                $formatparts[] = <<<EOT
TRUE/FALSE JSON FORMAT:
{
  "moodleQuestionType": "truefalse",
  "questionText": "True or False: [A clear, specific, unambiguous factual statement — not a question]",
  "correctAnswer": true,
  "trueAnswerFeedback": "Correct! [Explain why the statement is true and what it means in practice].",
  "falseAnswerFeedback": "Incorrect. [Explain that it is actually true, and why — address the likely misconception].",
  "explanation": "Full explanation of the concept being tested and why the statement is true or false.",
  "generalFeedback": "This statement is [True/False] because [comprehensive explanation for all students].",
  "criteriaReference": "{$ctext}",
  "totalMarks": 1
}
CRITICAL: criteriaReference MUST be the exact criterion text provided — do NOT substitute a generic phrase, copy from questionText, or leave blank. The criterion text IS the skill being assessed.
EOT;
            }
            if (isset($assigned['matching'])) {
                $formatparts[] = <<<EOT
MATCHING JSON FORMAT:
{
  "moodleQuestionType": "matching",
  "questionText": "Match each term or concept on the left to its correct definition or description on the right:",
  "matchPairs": [
    {"subquestion": "Key term or concept 1", "subanswer": "Its precise definition or matching description"},
    {"subquestion": "Key term or concept 2", "subanswer": "Its precise definition or matching description"},
    {"subquestion": "Key term or concept 3", "subanswer": "Its precise definition or matching description"},
    {"subquestion": "Key term or concept 4", "subanswer": "Its precise definition or matching description"}
  ],
  "explanation": "Explanation of all terms and their significance to the criterion.",
  "generalFeedback": "Review the key terms: [summary of all correct matches and what each term means].",
  "criteriaReference": "{$ctext}",
  "totalMarks": 4
}
CRITICAL: criteriaReference MUST be the exact criterion text provided — do NOT copy it from questionText, the matchPairs content, or any part of the question body. The criterion text IS the competency being assessed.
EOT;
            }
            if (isset($assigned['gapselect'])) {
                $formatparts[] = self::gapselect_format($wctx);
            }
            if (isset($assigned['shortanswer'])) {
                $formatparts[] = <<<EOT
MISSING WORD / FILL IN THE BLANK JSON FORMAT:
{
  "moodleQuestionType": "shortanswer",
  "questionText": "A clear contextual question that asks the student to identify the missing concept (e.g., 'According to the hierarchy of control measures, what is the first step a worker should take when managing a workplace hazard?').",
  "blankSentence": "A complete sentence from the topic with ONE key term replaced by ___ (e.g., 'In the workplace, the first step in the hierarchy of control measures is to ___ the hazard.'). The sentence must make grammatical sense when the blank is filled.",
  "acceptedAnswers": ["primary correct answer (most precise)", "acceptable alternative phrasing or spelling"],
  "explanation": "Explanation of the correct term, its meaning, and why it completes the sentence.",
  "generalFeedback": "The correct answer is '[primary answer]'. [Explanation of the concept and its significance].",
  "criteriaReference": "{$ctext}",
  "totalMarks": 1
}
EOT;
            }
            if (isset($assigned['essay'])) {
                $formatparts[] = <<<EOT
ESSAY JSON FORMAT (use existing rubric/sampleAnswer structure):
{
  "moodleQuestionType": "essay",
  "questionText": "Essay question requiring a written response...",
  "rubric": [{"criterion": 1, "marks": 1, "description": "Assessment criterion in imperative tense"}],
  "sampleAnswer": [{"criterion": 1, "marks": 1, "response": "Model response text"}],
  "criteriaReference": "{$ctext}",
  "questionFormat": "short_essay",
  "wordCount": "200-300 words",
  "totalMarks": 3
}
EOT;
            }
            $quoted = implode(', ', array_map(function($t) {
                return "\"{$t}\"";
            }, $usetypes));
            $parts = implode("\n\n", $formatparts);
            $typessection = <<<EOT

MOODLE QUIZ QUESTION TYPES TO GENERATE:
{$typelist}

{$countnote}

QUESTION TYPE RULES:
- EVERY question object MUST include "moodleQuestionType" set to exactly one of: {$quoted}
- Multiple Choice: Exactly 4 choices (1 correct + 3 plausible distractors). Wrong-answer feedback MUST specifically explain WHY that option is incorrect — do NOT use labels like "Option A", "Option B", "Option C", "Option D" in feedback text. Each distractor's feedback must be different from the others.
- True/False: Write a clear, factual declarative statement. Avoid "always/never/all/none". Must be definitively true or false — not opinion-based. Both trueAnswerFeedback and falseAnswerFeedback MUST explain WHY the selection is right or wrong — do NOT just say "Correct" or "Incorrect". Reference the specific fact or rule.
- Matching: Exactly 4 pairs. All terms must relate to the same topic. Each subanswer must be unique and unambiguous. CRITICAL: If multiple matching questions are generated in this set, each one MUST use a COMPLETELY DIFFERENT set of 4 terms — never reuse or rephrase the same terms across questions.
- Missing Word: Use ___ for exactly one blank per question. List the most precise answer first in acceptedAnswers. The answer must be a single word or short phrase (not a full sentence). Each fill-in-the-blank question must test a DIFFERENT key term — never use the same blank word twice in the set.
- Essay: Include questionFormat and wordCount fields. Each rubric criterion must cover a DISTINCT aspect — never restate the question or bundle multiple points into one criterion.
- UNIQUENESS — NON-NEGOTIABLE: Every question in the set MUST test a COMPLETELY DIFFERENT concept, scenario, sub-skill, or dimension. No two questions may overlap, test the same knowledge from different wording, or approach the criterion from the same angle. Before outputting, verify: "If a student knows Q1's answer, does that automatically answer Q2?" If yes, change Q2.
- Follow the MANDATORY TYPE ASSIGNMENT from countNote — every question must use its assigned type.

JSON FORMATS FOR EACH SELECTED TYPE:
{$parts}


EOT;
        } else {
            $typessection = "Include \"moodleQuestionType\": \"essay\" in every question object.\n";
        }

        $diversity = '';
        if ($count > 1) {
            $q2 = $wctx ? ' (may use a scenario)' : ' — direct concept or procedural question only, NO scenario';
            $namesrule = $wctx
                ? 'UNIQUE SCENARIO NAMES — MANDATORY: If any question uses a named person in a scenario, assign one unique name per question from this pool in order: Marcus (Q1), Zara (Q2), Daniel (Q3), Priya (Q4), James (Q5), Leila (Q6), Tom (Q7), Amara (Q8), Wei (Q9), Sofia (Q10). NEVER repeat any name.'
                : '';
            $diversityhead = "\n\nDIVERSITY REQUIREMENT — MANDATORY FOR {$count} QUESTIONS:\n"
                . "Each of the {$count} questions MUST test a COMPLETELY DIFFERENT sub-aspect or dimension of the criterion. Plan questions across different angles:\n"
                . "- Question 1: a specific fact, concept, or definition within the criterion\n"
                . "- Question 2: a different application, consequence, or aspect related to the criterion{$q2}\n"
                . "- Question 3+: a further distinct angle — legislation, procedure, context, or skill element not yet tested\n\n";
        }

        if ($hasonlyessay) {
            if ($count > 1) {
                $diversity = $diversityhead
                    . "ANTI-OVERLAP RULE: Before finalising, check every pair of questions: \"If a student copies 70% of Q1's answer into Q2, are they still correct?\" If yes, change Q2. Questions must require GENUINELY DIFFERENT knowledge.\n\n"
                    . ($wctx ? $namesrule . ' Each scenario must also have a DIFFERENT workplace setting and situation.'
                        : 'NO SCENARIOS OR NAMES — Workplace context is disabled. Do NOT include named persons, workplace roles, or situational framing in any question. All questions must be direct, concept-based, or procedural.');
            }
            $apply = $wctx ? 'give a scenario requiring application'
                : 'ask a direct application or procedural question — NO scenario or situational framing';
            if (!$wctx) {
                $rule6 = '6. ⛔ WORKPLACE CONTEXT IS DISABLED — Do NOT include workplace scenarios, situational framing, named workers/colleagues/customers, or role-play in any question. Questions must be direct, concept-based, knowledge, definition, or procedural only. This overrides all other style guidance.';
            } else if ($industrycontext) {
                $rule6 = "6. Use realistic {$industrycontext} workplace scenarios where appropriate";
            } else {
                $rule6 = '6. Create professional, practical questions suitable for adult learners';
            }
            $rule8 = $wctx
                ? 'UNIQUE SCENARIO NAMES — MANDATORY: If any question uses a named person in a scenario (e.g. "Sarah is a team leader…"), EVERY question must use a COMPLETELY DIFFERENT name. Do NOT repeat any name across questions. Assign names in order from this diverse pool: Marcus, Zara, Daniel, Priya, James, Leila, Tom, Amara, Wei, Sofia, Raj, Fatima, Chris, Ngozi, Ben, Yuki, Alex, Kezia, Jordan, Hana. Q1 gets the first name, Q2 gets the second, and so on. Also ensure each scenario has a DIFFERENT workplace setting, role, and situation — not merely a different name on the same scenario.'
                : 'NO NAMES OR ROLES — Do NOT include named persons, job titles used as characters, or situational role-play in any question. Workplace context is disabled.';
            if ($singlemark) {
                $marking = "***** SINGLE MARK MODE ACTIVE *****\nCREATE EXACTLY ONE (1) RUBRIC CRITERION PER QUESTION.\n"
                    . "The rubric array MUST contain only ONE object. totalMarks MUST be 1.\n"
                    . 'This is NON-NEGOTIABLE. Do NOT create 2, 3, or more criteria. Only ONE.';
            } else if ($hasmarking) {
                $marking = "*** TEACHER HAS SPECIFIED MARKS - FOLLOW EXACTLY ***\n"
                    . "The teacher gave specific marking instructions above. Follow them EXACTLY.\n"
                    . "Count the number of marks they specified and create that many rubric criteria.\n"
                    . 'If they said "2 marks" → 2 criteria. If they said "3 marks" → 3 criteria.';
            } else {
                $marking = "Determine the number of rubric criteria based on what the criterion requires:\n"
                    . "- Simple identification/definition: 2-3 criteria\n"
                    . "- Explanation with understanding: 3-4 criteria  \n"
                    . "- Application to scenario: 4-5 criteria\n"
                    . "- Analysis or evaluation: 5-6 criteria\n"
                    . '- Comprehensive response: 6-8 criteria';
            }
            if ($singlemark) {
                $matchrule = '***** SINGLE MARK = SINGLE CRITERION. The rubric array has exactly 1 item. *****';
            } else if ($hasmarking) {
                $matchrule = "*** FOLLOW TEACHER'S MARKING INSTRUCTIONS - match the number of criteria to their specified marks ***";
            } else {
                $matchrule = 'Match the number of criteria to the complexity of the question';
            }
            if ($hasmarking) {
                $example = <<<EOT
EXAMPLE FOR TEACHER-SPECIFIED MARKS (e.g., "1 mark per question"):
{
  "questions": [
    {
      "questionText": "Question text here",
      "rubric": [
        { "criterion": 1, "marks": 1, "description": "Comprehensive criterion description" }
      ],
      "sampleAnswer": [
        { "criterion": 1, "marks": 1, "response": "Complete model answer" }
      ],
      "criteriaReference": "{$ctext}",
      "questionFormat": "format",
      "wordCount": "length",
      "totalMarks": 1
    }
  ]
}
EOT;
            } else {
                $example = <<<EOT
Return a JSON object with a "questions" array:
{
  "questions": [
    {
      "questionText": "Question that directly tests the criterion",
      "rubric": [
        { "criterion": 1, "marks": 1, "description": "Generic assessment guidance" }
      ],
      "sampleAnswer": [
        { "criterion": 1, "marks": 1, "response": "Detailed model response for this criterion" }
      ],
      "criteriaReference": "{$ctext}",
      "questionFormat": "format used",
      "wordCount": "expected length",
      "totalMarks": number
    }
  ]
}
EOT;
            }
            $usingteacher = $hasmarking ? " USING THE TEACHER'S SPECIFIED MARKING (from instructions above)" : '';
            $user = <<<EOT
{$extrasection}{$prevsection}Generate {$count} question(s) that DIRECTLY ASSESS the following criterion:

CRITERION TO ASSESS: "{$ctext}"

IMPORTANT: The question MUST test whether the student can demonstrate THIS SPECIFIC criterion. Do NOT ask generic questions - the question must directly relate to: "{$ctext}"

{$contextsection}

{$pastedsection}QUESTION FORMAT(S) TO USE:
{$formatinstructions}

QUESTION DESIGN RULES:
1. The question MUST directly test the criterion: "{$ctext}"
2. Read the criterion carefully - if it says "identify legislation", ask about legislation. If it says "communicate", ask about communication.
3. Create VARIED question structures - do NOT always use the same "identify X, provide example, describe action" pattern
4. Match the question type to the criterion:
   - "Identify/List" criteria → ask students to identify or list
   - "Explain/Describe" criteria → ask students to explain or describe
   - "Apply/Demonstrate" criteria → {$apply}
   - "Evaluate/Analyse" criteria → ask for evaluation or analysis
   - "Communicate/Coordinate" criteria → ask about communication methods
5. Each question should feel DIFFERENT in structure and approach — if generating multiple questions, every question must test a DIFFERENT aspect or concept. No two questions may overlap or repeat the same idea. RULE: if a student can copy 70% of Q1's answer into Q2 and still be correct, Q2 must be changed.
{$rule6}
7. The "questionText" field must NOT contain any numbering prefix (no "Q1.", "1.", "Question 1:", etc.) — begin directly with the question.
8. {$rule8}
9. CROSS-CONCEPT RULE: Even if question types differ (MCQ vs Essay vs Matching), each question must test a DIFFERENT core concept. A student who answers Q1 correctly must NOT be able to answer Q2 using the same knowledge, regardless of the question format used.

MARKING SYSTEM:
{$marking}

Each rubric criterion is worth exactly 1 mark. The total marks = number of rubric criteria.

RUBRIC RULES (CRITICAL):
- Each rubric criterion is worth exactly 1 mark (always "marks": 1 in the JSON)
- The NUMBER of rubric criteria determines the total marks for the question
- {$matchrule}
- Rubric criteria must be GENERIC - describe HOW to evaluate, not WHAT the answer is
- Use IMPERATIVE tense (command form) for criteria, NOT third-person
  - BAD: "Explains how to assess the level of risk" (third-person)
  - GOOD: "Explain how to assess the level of risk" (imperative)
  - BAD: "Identifies relevant legislation" → GOOD: "Identify relevant legislation"
  - BAD: "Demonstrates understanding" → GOOD: "Demonstrate understanding"
- Do NOT start descriptions with "Award 1 mark for..." - just describe what earns the mark
- BAD: "Award 1 mark if student mentions Work Health and Safety Act 2020" (reveals answer!)
- GOOD: "Identify relevant legislation applicable to the context"
- DISTINCT CRITERIA: Each rubric criterion must cover a SEPARATE, DISTINCT aspect of the answer. Do NOT repeat the same idea across multiple criteria. Do NOT restate or paraphrase the question text in any criterion description.
- SEPARATE POINTS: If a question has 3 marks, there must be 3 clearly different criteria — e.g. one for identifying the concept, one for explaining the reasoning, one for applying it to context. Never bundle two points into one criterion description.

SAMPLE ANSWER FORMAT:
- For each rubric criterion, show what a correct response looks like
- Make each section detailed enough for teachers AND students to understand

{$example}
{$typessection}Generate exactly {$count} question(s) that DIRECTLY ASSESS: "{$ctext}"{$usingteacher}{$diversity}
EOT;
        } else {
            if ($count > 1) {
                $diversity = $diversityhead
                    . "ANTI-OVERLAP RULE: Before finalising, check every pair of questions: \"If a student copies 70% of Q1's answer into Q2, are they still correct?\" If yes, change Q2. Questions must require GENUINELY DIFFERENT knowledge — this applies ACROSS different question types too (MCQ, Matching, Essay, Gap Fill all count).\n\n"
                    . ($wctx ? $namesrule . ' Each scenario must also have a DIFFERENT workplace setting and situation — a different role, location, or event.'
                        : 'NO SCENARIOS OR NAMES — Workplace context is disabled. Do NOT include named persons, workplace roles, or situational framing in any question. All questions must be direct, concept-based, or procedural.');
            }
            $selected = implode(', ', array_map('strtoupper', $usetypes));
            $stylesblock = $styleinstructions ? $styleinstructions . "\n\n" : '';
            $apply = $wctx ? 'multichoice with scenario works well'
                : 'multichoice with a direct application question — NO scenario or situational framing';
            if (!$wctx) {
                $rule3 = '⛔ WORKPLACE CONTEXT IS DISABLED — Do NOT include workplace scenarios, named workers/colleagues/customers, or situational framing in ANY question. Questions must be direct, concept-based, knowledge, definition, or procedural only. This overrides all other style guidance.';
            } else if ($industrycontext) {
                $rule3 = "Use realistic {$industrycontext} industry scenarios — include specific job roles, equipment, legislation, or procedures from this sector";
            } else {
                $rule3 = 'Use practical, professional scenarios appropriate for adult learners';
            }
            $rule6 = $wctx
                ? 'UNIQUE SCENARIO NAMES — MANDATORY: If any question includes a named person in a scenario, every question MUST use a DIFFERENT name. NEVER repeat any name across questions. Assign unique names in order from this diverse pool: Marcus, Zara, Daniel, Priya, James, Leila, Tom, Amara, Wei, Sofia, Raj, Fatima, Chris, Ngozi, Ben, Yuki, Alex, Kezia, Jordan, Hana. Q1 gets the first name, Q2 the second, and so on. Each scenario must also have a DIFFERENT workplace setting, role, and situation — not just a different name on the same situation.'
                : 'NO NAMES OR ROLES — Do NOT include named persons, job titles used as characters, or situational role-play in any question. Workplace context is disabled.';
            $rule7 = $jobtitle ? "Questions should be relevant to the perspective of a {$jobtitle}"
                : 'Questions should be appropriate for the professional context described';
            $parts = implode("\n\n", $formatparts);
            $quoted = implode(', ', array_map(function($t) {
                return "\"{$t}\"";
            }, $usetypes));
            $gaprule = $wctx
                ? "questionText MUST have TWO parts separated by a blank line: (1) a 1–2 sentence workplace scenario naming a person/role, (2) the sentence with [[1]], [[4]], [[7]] placeholders (JUMP-BY-3). Format: \"SCENARIO\n\nSENTENCE WITH [[1]] [[4]] GAPS\". The scenario person's name and setting MUST differ from any other question in this set."
                : "questionText MUST be a clear, direct question about the concept being tested (e.g. \"What terms correctly describe the steps in hazard identification?\"). Do NOT include a scenario, named person, workplace setting, or situational framing. Format: \"QUESTION\n\nSENTENCE WITH [[1]] [[4]] GAPS\".";
            $essayrule = in_array('essay', $usetypes, true)
                ? '- Essay: Include rubric and sampleAnswer arrays as shown in the essay format above. Each rubric criterion must cover a DISTINCT dimension — never two criteria that test the same knowledge.'
                : '';
            $namesline = $wctx
                ? '- NAMES IN SCENARIOS: Use unique names across ALL questions. Choose from this pool in order: Marcus (Q1), Zara (Q2), Daniel (Q3), Priya (Q4), James (Q5), Leila (Q6), Tom (Q7), Amara (Q8), Wei (Q9), Sofia (Q10). NEVER use the same name in two questions.'
                : '- NO NAMES OR SCENARIOS: Workplace context is disabled. Do NOT use named persons, characters, or situational role-play in any question.';
            $followmarking = $hasmarking ? ' (follow teacher marking instructions above)' : '';
            $user = <<<EOT
{$extrasection}{$prevsection}You are generating self-marking quiz questions for a Moodle LMS. Each question MUST follow the exact JSON structure for its assigned type.

CRITERION TO ASSESS: "{$ctext}"

{$contextsection}

{$pastedsection}TASK: Generate exactly {$count} question(s) that directly assess the criterion above.

SELECTED QUESTION TYPES: {$selected}

{$countnote}

{$stylesblock}QUESTION DESIGN RULES:
1. Questions MUST directly test the criterion: "{$ctext}"
2. Match question type to what the criterion is asking:
   - "Identify/List" → matching or multichoice works well
   - "Define/Explain" → shortanswer or multichoice works well
   - "True/False statement" → truefalse works well
   - "Apply/Demonstrate" → {$apply}
3. {$rule3}
4. All questions must be factually accurate and unambiguous
5. UNIQUENESS ACROSS ALL TYPES — MANDATORY: Every question, regardless of type (MCQ, Matching, Essay, Gap Fill, etc.), MUST test a GENUINELY DIFFERENT concept or sub-skill. NO concept overlap between question types: if Q1 (MCQ) tests concept X, Q2 (Matching) must NOT test the same concept X through different formatting. RULE: if a student can copy 70% of Q1's answer into Q2 and still be correct, Q2 must be changed. Distractors, matching terms, gap words, and essay sub-topics must ALL be distinct across questions and must not echo concepts already tested elsewhere.
6. {$rule6}
7. {$rule7}

REQUIRED JSON FORMATS — FOLLOW EXACTLY:

{$parts}

CRITICAL RULES:
- EVERY question object MUST include "moodleQuestionType" set to exactly one of: {$quoted}
- Multiple Choice: Exactly 4 choices (1 correct + 3 plausible distractors). Wrong-answer feedback MUST specifically explain why that option is incorrect.
- True/False: Write a clear, factual declarative statement (NOT a question). Must be definitively true or false — not opinion-based.
- Matching: Exactly 4 pairs. All terms must relate to the same topic. Each subanswer must be unique and unambiguous. If multiple matching questions exist in this set, each must use a COMPLETELY DIFFERENT set of terms — never reuse or closely echo terms from another question's pairs or distractors.
- Select Missing Words (gapselect): {$gaprule} selectOptions must have one array per gap (first item = correct answer, followed by 2 distractors). Blank placeholders MUST use JUMP-BY-3 numbering: Blank 1=[[1]], Blank 2=[[4]], Blank 3=[[7]] — NEVER sequential [[1]][[2]][[3]]. The number of selectOptions sub-arrays MUST exactly equal the number of [[n]] gaps.
- Missing Word (shortanswer): questionText is the actual question asking the student to identify the concept. blankSentence is a SEPARATE sentence with ONE blank (___) where the answer goes. Both fields are REQUIRED. List the most precise answer first in acceptedAnswers.
{$essayrule}
{$namesline}
- NO CONCEPT DUPLICATION ACROSS TYPES: MCQ distractors, matching pairs, gap words, short-answer blanks, and essay prompts must all test DIFFERENT knowledge from each other — even if their question types differ. A student should need genuinely different knowledge to answer each question.

Return a JSON object with a "questions" array:
{
  "questions": [
    { ... one object per question, each following the exact format shown above ... }
  ]
}

Generate exactly {$count} question(s) for criterion: "{$ctext}"{$followmarking}{$diversity}
EOT;
        }

        $suffix = "\n\nYou always respond with valid JSON. No markdown, no code blocks, just raw JSON."
            . (!$hasonlyessay ? "\n\nCRITICAL OVERRIDE: This request generates structured self-marking quiz questions (NOT essays). Ignore any instructions about questionFormat, wordCount, rubric arrays, or sampleAnswer arrays — those only apply to essay types. For every non-essay question, follow the exact JSON structure specified for its moodleQuestionType in the user prompt. Never generate rubric or sampleAnswer fields unless moodleQuestionType is 'essay'." : '');
        return [$system . $suffix, $user, $hasonlyessay, $usetypes];
    }

    /**
     * Run one criterion of /api/generate-essays and tidy its questions as the service does.
     *
     * @param array $criterion text, questionsCount.
     * @param int $criterionindex Index of this criterion's first question across the whole request.
     * @param array $ctx Request context.
     * @return array Questions.
     */
    private static function process_criterion(array $criterion, int $criterionindex, array $ctx): array {
        [$system, $user, $hasonlyessay, $usetypes] = self::criterion_prompts($criterion, $criterionindex, $ctx);
        $parsed = self::decode(self::ai($system, $user, 6000));
        if (!is_array($parsed)) {
            throw new \moodle_exception('byok_badjson', 'local_aiquizmaker');
        }
        $questions = $parsed['questions'] ?? $parsed;
        $list = (is_array($questions) && self::is_list($questions)) ? $questions : [$questions];
        foreach ($list as $qi => $q) {
            if (!is_array($q)) {
                continue;
            }
            if (!isset($q['criteriaReference']) || !is_string($q['criteriaReference']) || trim($q['criteriaReference']) === '') {
                $q['criteriaReference'] = $criterion['text'];
            }
            if (!$hasonlyessay) {
                $q['moodleQuestionType'] = $usetypes[($criterionindex + $qi) % count($usetypes)];
            }
            $list[$qi] = $q;
        }
        return array_values($list);
    }

    /**
     * The Select Missing Words format block of /api/generate-essays.
     *
     * @param bool $wctx Workplace context on.
     * @return string
     */
    private static function gapselect_format(bool $wctx): string {
        if ($wctx) {
            $example = <<<'EOT'
{
  "moodleQuestionType": "gapselect",
  "questionText": "During a workplace induction, a trainer is explaining the key responsibilities of team leaders to a group of new employees.\n\nTo successfully implement new WHS policies, an organisation should start with a thorough [[1]] of current practices, followed by [[4]] communication to all employees, and conclude with ongoing [[7]] of compliance.",
  "selectOptions": [
    ["review", "celebration", "rejection"],
    ["consistent", "secretive", "delayed"],
    ["monitoring", "abandonment", "dismissal"]
  ],
  "generalFeedback": "Blank [[1]]: review — A review of current practice is the correct first step. Blank [[4]]: consistent — Consistent communication ensures all staff receive the same message. Blank [[7]]: monitoring — Ongoing monitoring confirms continued compliance.",
  "criteriaReference": "Applies workplace safety procedures consistently",
  "totalMarks": 3
}
EOT;
            $qtrule = <<<'EOT'
- questionText MUST have TWO parts separated by a blank line: (1) a 1–2 sentence realistic workplace scenario that sets the context — name a specific person, role, or situation (e.g. "A new team leader is reviewing the site safety handbook before their first shift."), and (2) the sentence containing the [[1]], [[4]], [[7]]... placeholders inline. Format as: "SCENARIO\n\nSENTENCE WITH [[1]] [[4]] [[7]] GAPS". Do NOT include a separate question prompt — the scenario provides context.
EOT;
        } else {
            $example = <<<'EOT'
{
  "moodleQuestionType": "gapselect",
  "questionText": "What are the key terms used to describe the process of identifying and controlling workplace hazards?",
  "selectOptions": [
    ["hazard", "incident", "audit"],
    ["risk", "policy", "report"],
    ["elimination", "escalation", "documentation"]
  ],
  "generalFeedback": "Blank [[1]]: hazard — A hazard is any source of potential harm. Blank [[4]]: risk — Risk is the likelihood that the hazard causes harm. Blank [[7]]: elimination — Elimination is the highest-order control measure.",
  "criteriaReference": "Identifies key hazard management terminology",
  "totalMarks": 3
}
EOT;
            $qtrule = <<<'EOT'
- questionText MUST be a clear, direct question that tells the student what concept is being tested (e.g. "What are the correct terms used to describe the steps in the hierarchy of hazard control?"). Do NOT include a scenario paragraph, named person, workplace setting, or situational framing. The gap sentence stands on its own. Format as: "QUESTION\n\nSENTENCE WITH [[1]] [[4]] [[7]] GAPS".
EOT;
        }
        return <<<EOT
SELECT MISSING WORDS JSON FORMAT:

BLANK NUMBERING SCHEME — JUMP BY 3 (CRITICAL — RESTORED IN v3.16.80):
Blank placeholders in the sentence MUST be numbered with a JUMP-BY-3 pattern, NOT sequentially.
The N-th blank uses placeholder [[1 + 3*(N-1)]].
That is: Blank 1 = [[1]], Blank 2 = [[4]], Blank 3 = [[7]], Blank 4 = [[10]].
Each blank "owns" the next 3 numbers conceptually (Blank 1 owns 1/2/3, Blank 2 owns 4/5/6, Blank 3 owns 7/8/9, Blank 4 owns 10/11/12) — those represent the 1 correct answer + 2 distractors that belong to that blank. Only the FIRST of each trio (1, 4, 7, 10) actually appears as a [[N]] in the sentence.

WRONG (DO NOT DO THIS): "the [[1]] application of [[2]] procedures" — sequential [[1]] [[2]] is FORBIDDEN.
RIGHT: "the [[1]] application of [[4]] procedures" — jump-by-3 (Blank 1 = [[1]], Blank 2 = [[4]]).

selectOptions is an array where each element = ALL choices for ONE blank (correct answer first, then 2 distractors). selectOptions[0] = the 3 choices for Blank 1 (the [[1]] placeholder), selectOptions[1] = the 3 choices for Blank 2 (the [[4]] placeholder), selectOptions[2] = the 3 choices for Blank 3 (the [[7]] placeholder), etc. The mapping is by ORDER, not by placeholder number — selectOptions[i] always belongs to the i-th blank in the sentence.

WORKED EXAMPLE (3 blanks):
{$example}

CRITICAL GROUPING RULES — READ CAREFULLY:
- BLANK NUMBERING MUST JUMP BY 3: Blank 1 = [[1]], Blank 2 = [[4]], Blank 3 = [[7]], Blank 4 = [[10]]. NEVER use [[2]] or [[3]] or [[5]] or [[6]] etc. as a placeholder in the sentence — those numbers are RESERVED conceptually as the distractor slots of the previous blank.
- COUNT MUST MATCH: The number of selectOptions sub-arrays MUST exactly equal the number of [[N]] placeholders in the sentence. If the sentence has 3 placeholders ([[1]], [[4]], [[7]]) then selectOptions must have exactly 3 sub-arrays — no more, no less. Count the placeholders in your sentence before you emit the JSON.
- ONE SUB-ARRAY PER BLANK (NOT PER CHOICE): selectOptions has ONE sub-array per BLANK, and each sub-array contains the 3 CHOICES for that blank. Do NOT create 9 sub-arrays for a 3-blank question. Do NOT flatten the choices.
- selectOptions[0] = the 3 choices for the 1st blank (the [[1]] placeholder): correct answer first, then 2 distractors.
- selectOptions[1] = the 3 choices for the 2nd blank (the [[4]] placeholder): correct answer first, then 2 distractors.
- selectOptions[2] = the 3 choices for the 3rd blank (the [[7]] placeholder): correct answer first, then 2 distractors.
- NEVER put correct answers from different blanks into the same sub-array. Each sub-array belongs to exactly one blank.
- NEVER separate a blank's correct answer from its distractors. They MUST all be in the same sub-array.
- CORRECT ANSWER POSITION: The FIRST item in each sub-array is the CORRECT answer for that blank. The remaining 2 items are distractors.
- EXACTLY 3 OPTIONS PER BLANK: Each sub-array must contain exactly 3 strings — 1 correct + 2 distractors.
- NO DUPLICATES WITHIN A BLANK: Every option inside a single selectOptions sub-array MUST be unique. The correct answer must NEVER also appear as one of its own distractors. Distractors must NEVER repeat each other. Comparison is case-insensitive and whitespace-trimmed (so "Consistent" and "consistent " are duplicates). Before you emit the JSON, mentally check each sub-array: if any option appears twice, replace one of the duplicates with a different plausible distractor from the same semantic category.
- BLANKS MUST BE INDEPENDENT: A student picking the correct answer in Blank 1 must not affect Blanks 2 or 3. Each blank's distractors must fit the surrounding sentence grammar so the student is forced to use real subject knowledge to pick the right one.
{$qtrule}
- criteriaReference MUST be a short skill-based assessment phrase (3-8 words, imperative or gerund), e.g. "Applies workplace safety procedures consistently". It MUST NOT be the question text, a sentence, or copied from questionText.
- Use 2–4 blanks per question (so placeholders [[1]], [[4]], [[7]] for 3 blanks; [[1]], [[4]], [[7]], [[10]] for 4 blanks). Distractors must be plausible but clearly wrong in context.
EOT;
    }

    /**
     * Port of POST /api/generate-from-questions ("Use my own questions").
     *
     * @param array $p The service payload.
     * @return array ok, questions, credits, questionsGenerated | ok false, error, message.
     */
    public static function generate_from_questions(array $p): array {
        \core_php_time_limit::raise(600);
        $items = [];
        foreach (array_slice((array) ($p['questions'] ?? []), 0, 50) as $q) {
            if (is_string($q)) {
                $text = trim($q);
                $model = '';
            } else {
                $text = trim((string) ($q['text'] ?? ''));
                $model = (string) ($q['modelAnswer'] ?? '');
            }
            if ($text !== '') {
                $items[] = [$text, $model];
            }
        }
        if (!$items) {
            return ['ok' => false, 'error' => 'VALIDATION_ERROR', 'message' => 'No questions provided', 'byok' => true];
        }
        $ctx = self::gfq_context($p);
        $types = $ctx['activetypes'];
        $all = '';
        if (count($items) > 1) {
            $lines = [];
            foreach ($items as $qi => $item) {
                $lines[] = 'Q' . ($qi + 1) . ': ' . self::js_substring($item[0], 120);
            }
            $all = implode("\n", $lines);
        }
        $out = [];
        $lasterror = '';
        foreach ($items as $qi => $item) {
            $type = $types[$qi % count($types)];
            [$system, $user, $max] = self::question_prompts($item[0], $item[1], $type, $all, $ctx);
            try {
                $parsed = self::decode(self::ai($system, $user, $max));
                if (!is_array($parsed) || self::is_list($parsed)) {
                    throw new \moodle_exception('byok_badjson', 'local_aiquizmaker');
                }
                if ($type === 'essay') {
                    $parsed['questionText'] = $item[0];
                }
                $parsed['moodleQuestionType'] = $type;
                $out[] = $parsed;
            } catch (\Throwable $e) {
                $lasterror = self::message($e);
            }
        }
        if (!$out) {
            return ['ok' => false, 'error' => 'GENERATION_ERROR',
                'message' => $lasterror ?: 'Failed to generate assessment materials', 'byok' => true];
        }
        return ['ok' => true, 'questions' => $out, 'credits' => -1, 'questionsGenerated' => count($out), 'byok' => true];
    }

    /**
     * Shared context of /api/generate-from-questions.
     *
     * @param array $p The service payload.
     * @return array
     */
    public static function gfq_context(array $p): array {
        $country = (string) ($p['country'] ?? '');
        $state = (string) ($p['state'] ?? '');
        $industry = (string) ($p['industry'] ?? '');
        $industrydetails = (string) ($p['industryDetails'] ?? '');
        $jobtitle = (string) ($p['jobTitle'] ?? '');
        $joblevel = (string) ($p['jobLevel'] ?? '');
        $educationtype = (string) ($p['educationType'] ?? 'vet');
        $educationlevel = (string) ($p['educationLevel'] ?? '');
        $extrainstructions = (string) ($p['extraInstructions'] ?? '');
        $pastedcontent = (string) ($p['pastedContent'] ?? '');
        $previous = (string) ($p['previousQuestionText'] ?? '');
        $wctx = !array_key_exists('workplaceContextEnabled', $p) || !empty($p['workplaceContextEnabled']);
        $rawtypes = array_key_exists('moodleQuestionTypes', $p) ? (array) $p['moodleQuestionTypes'] : ['essay'];
        $rawstyles = array_key_exists('selfMarkingStyles', $p) ? (array) $p['selfMarkingStyles'] : ['scenario', 'knowledge_check'];

        $types = array_values(array_filter($rawtypes, function($t) {
            return in_array($t, self::TYPES, true);
        }));
        $activetypes = $types ?: ['essay'];
        $styles = array_values(array_filter($rawstyles, function($s) {
            return in_array($s, self::STYLES, true);
        }));
        if (!$wctx) {
            $styles = array_values(array_filter($styles, function($s) {
                return $s !== 'scenario';
            }));
        }
        $usestyles = $styles ?: ($wctx ? ['scenario', 'knowledge_check'] : ['knowledge_check', 'terminology']);

        $educationtype = $educationtype ?: 'vet';
        $industrycontext = $industrydetails ?: ($industry ?: '');
        $location = ($state && $country) ? "{$state}, {$country}" : ($country ?: ($state ?: ''));
        $hascontext = (bool) ($country || $industry || $jobtitle || $joblevel);
        $labels = [
            'cert1' => 'Certificate I', 'cert2' => 'Certificate II', 'cert3' => 'Certificate III',
            'cert4' => 'Certificate IV', 'diploma' => 'Diploma', 'adv_diploma' => 'Advanced Diploma',
            'undergraduate' => "Undergraduate (Bachelor's)", 'postgraduate' => 'Postgraduate',
            'masters' => 'Masters', 'phd' => 'PhD/Doctoral',
        ];
        $levellabel = $educationlevel ? ($labels[$educationlevel] ?? $educationlevel) : '';
        $spelling = '';
        if ($country === 'Australia' || $country === 'AU') {
            $spelling = "\n\nUse Australian English spelling throughout (organisation, behaviour, colour, recognise, analyse, practise, labour, centre, programme, etc.). This is mandatory.";
        } else if ($country === 'United Kingdom' || $country === 'UK') {
            $spelling = "\n\nUse British English spelling throughout.";
        }
        $languageinstruction = self::language_instruction((string) ($p['language'] ?? ''));
        $contextdescription = $hascontext
            ? 'for ' . ($jobtitle ?: 'workers') . ' in the ' . ($industrycontext ?: 'general') . ' industry'
                . ($location ? " in {$location}" : '')
            : 'suitable for adult learners in general professional settings';

        $styledescriptions = [
            'scenario' => 'Workplace Scenario — open with a brief realistic workplace situation and ask the student to apply their knowledge to it',
            'knowledge_check' => 'Knowledge Check — direct recall of key facts, rules, regulations, or principles',
            'procedure' => 'Procedure / Steps — test knowledge of correct order, sequence, or procedural requirements',
            'terminology' => 'Terminology / Definitions — test understanding of key terms, definitions, and concepts',
            'identification' => 'Identification / Listing — students identify, classify, or select items/characteristics from options',
        ];
        $stylelist = implode("\n", array_map(function($s) use ($styledescriptions) {
            return '- ' . ($styledescriptions[$s] ?? $s);
        }, $usestyles));
        $styleinstructions = "\nQUESTION STYLE GUIDANCE:\nSelect one of these styles that best fits the question:\n{$stylelist}"
            . (($wctx && in_array('scenario', $usestyles, true) && $industrycontext)
                ? "\nWhen using Workplace Scenario style, embed a short (1-2 sentence) realistic {$industrycontext} scenario in the question stem." : '')
            . (!$wctx ? "\nWORKPLACE CONTEXT IS DISABLED — do NOT include workplace scenarios, named workers, or situational role-play in any question." : '')
            . "\n";

        [$hasmarking, $singlemark] = self::marking_flags($extrainstructions);
        $forlevel = $levellabel ? " Questions are for {$levellabel} students." : '';
        if ($educationtype === 'academic') {
            $system = "You are a world-class academic assessment specialist. You generate professional marking rubrics, sample answers, and grading guides for given essay questions.{$forlevel}\n\n"
                . "NON-NEGOTIABLE RULES:\n"
                . "- Return valid JSON only. No commentary outside JSON.\n"
                . "- JSON keys MUST remain in English. Only values may be in the target language.\n"
                . "- The question text is provided by the teacher — do NOT change it.\n"
                . "- Generate a professional rubric, sample answer, and grader info for the question.\n"
                . "- Rubric criteria must be CRITERION-REFERENCED. Use evaluative language.\n"
                . '- NEVER reveal specific answer content in rubric descriptions — describe the quality standard, not the answer.'
                . $spelling . $languageinstruction;
        } else {
            $system = "You are a world-class VET assessment specialist with 20+ years experience in Australian RTOs. You generate professional marking rubrics, model answers, and grader information for given assessment questions.{$forlevel}\n\n"
                . "NON-NEGOTIABLE RULES:\n"
                . "- Return valid JSON only. No commentary outside JSON.\n"
                . "- JSON keys MUST remain in English. Only values may be in the target language.\n"
                . "- The question text is provided by the teacher — do NOT change it.\n"
                . "- Generate a professional rubric, sample answer, and grader info for the question.\n"
                . "- Rubric criteria must describe observable performance or verifiable knowledge.\n"
                . "- Use action verbs: \"identifies,\" \"demonstrates,\" \"applies,\" \"selects.\"\n"
                . '- NEVER include specific answers in rubric descriptions.' . $spelling . $languageinstruction;
        }
        $pastedsection = trim($pastedcontent) !== ''
            ? "SOURCE CONTENT (TEACHER-PROVIDED):\nThe teacher provided the following learning material. Use it as the knowledge base for rubric and sample answer.\n---\n"
                . trim($pastedcontent) . "\n---\n\n"
            : '';
        $extrasection = '';
        if (trim($extrainstructions) !== '') {
            $lead = $singlemark
                ? "**SINGLE MARK MODE: Create exactly ONE rubric criterion. The rubric array must have exactly 1 item. totalMarks = 1.**\n"
                : ($hasmarking ? "*** Follow teacher's marking requirements exactly. ***\n" : '');
            $extrasection = "=== MANDATORY TEACHER INSTRUCTIONS ===\n{$lead}" . trim($extrainstructions)
                . "\n=== END INSTRUCTIONS ===\n\n";
        }
        $prevsection = trim($previous) !== ''
            ? "\n⚠️ REGENERATE — DO NOT REUSE THE PREVIOUS QUESTION ⚠️\nA question was already generated for this topic. You MUST create a COMPLETELY NEW question.\nDO NOT reuse the question stem, options, phrasing, structure, scenario, or any part of this previous question:\n\"\"\"\n"
                . trim($previous) . "\n\"\"\"\nGenerate entirely fresh content for the same topic from a DIFFERENT angle, context, or question style.\n\n"
            : '';
        return compact('educationtype', 'levellabel', 'wctx', 'spelling', 'languageinstruction', 'contextdescription',
            'styleinstructions', 'hasmarking', 'singlemark', 'system', 'pastedsection', 'extrasection', 'prevsection',
            'previous', 'activetypes');
    }

    /**
     * Prompts for one teacher question of /api/generate-from-questions.
     *
     * @param string $questiontext The teacher's question.
     * @param string $modelanswer The teacher's model answer, if any.
     * @param string $type Moodle question type to write.
     * @param string $all The whole batch, one line per question, or ''.
     * @param array $ctx From gfq_context().
     * @return array [system, user, maxtokens]
     */
    public static function question_prompts(string $questiontext, string $modelanswer, string $type, string $all,
            array $ctx): array {
        extract($ctx);
        $hasmodel = trim($modelanswer) !== '';
        $others = '';
        if ($all !== '') {
            $namerule = $wctx
                ? 'UNIQUE NAME RULE: If this question includes a named person in a scenario, choose a name that has NOT already been used in any other question in this batch. Recommended diverse names: Zara, Daniel, Priya, James, Leila, Tom, Amara, Wei, Sofia, Raj, Fatima, Chris, Ngozi, Ben, Yuki, Alex, Kezia, Jordan, Hana. Do NOT use Marcus, Emma, Sarah, or any name already seen above.'
                : 'NO NAMES RULE: Workplace context is disabled. Do NOT include any named person, worker, colleague, or character in this question. Questions must be entirely impersonal and concept-based.';
            $others = "\n⚠️ OTHER QUESTIONS IN THIS BATCH — AVOID OVERLAP ⚠️\nThe following questions are being processed in the same batch. Your generated content (distractors, matching terms, gap words, statements, rubric criteria) MUST NOT duplicate or closely echo any concept, term, or scenario already addressed by these questions:\n"
                . $all . "\n\nEnsure ALL of your generated content (options, pairs, gaps, feedback) is DISTINCT from what these other questions already cover.\n\n"
                . "CROSS-TYPE RULE: Even if your question type differs from the others (e.g. MCQ vs Essay vs Matching), do NOT test the same core concept as any other question in this batch. A student who correctly answers another batch question MUST NOT be able to answer this one using the same knowledge.\n\n"
                . $namerule . "\n";
        }

        if ($type !== 'essay') {
            $kind = $educationtype === 'academic' ? 'academic' : 'VET/RTO';
            $forlevel = $levellabel ? " Questions are for {$levellabel} students." : '';
            $typesystem = "You are an expert Moodle quiz question designer for {$kind} education.{$forlevel}\n"
                . "NON-NEGOTIABLE RULES:\n"
                . "- Return valid JSON ONLY. No markdown, no code blocks, no commentary.\n"
                . '- JSON keys MUST be in English. Values may be in the target language.'
                . (!$wctx ? "\n- WORKPLACE CONTEXT IS DISABLED: Do NOT include workplace scenarios, situational role-play, named workers/colleagues/customers, or any \"a worker named X is...\" framing in ANY question. Questions must be direct, concept-based, knowledge, or procedural only. This applies to ALL question types without exception." : '')
                . $spelling . $languageinstruction;
            $regen = trim($previous) !== ''
                ? 'MUST CREATE COMPLETELY NEW question text — different angle, different scenario, different phrasing from the previous version. Do NOT preserve the old question stem.'
                : "The complete question text (keep the teacher's wording if it works, or refine slightly)";
            $user = self::type_prompt($type, $questiontext, $ctx, $regen, $others);
            if ($prevsection !== '') {
                $user = $prevsection . $user;
            }
            return [$typesystem, $user, 3000];
        }

        $modelsection = $hasmodel
            ? "TEACHER'S MODEL ANSWER (provided — use it as the sample answer and derive rubric criteria from its content):\n---\n"
                . trim($modelanswer) . "\n---\n\n"
            : '';
        $levelnote = $levellabel ? " Education level: {$levellabel}." : '';
        if ($singlemark) {
            $rubricrules = '**SINGLE MARK MODE: Exactly 1 rubric criterion. totalMarks = 1.**';
        } else if ($hasmarking) {
            $rubricrules = "Follow the teacher's marking instructions above for the number of criteria.";
        } else {
            $rubricrules = "Determine marks based on complexity:\n- Simple recall/identification: 2-3 criteria\n"
                . "- Explanation with understanding: 3-4 criteria\n- Application to scenario: 4-5 criteria\n"
                . "- Analysis or evaluation: 5-6 criteria\n- Comprehensive response: 6-8 criteria";
        }
        $derive = $hasmodel ? "- Rubric criteria MUST be derived from the content and structure of the teacher's model answer above." : '';
        $samplerules = $hasmodel
            ? "- The teacher has provided a model answer above. Use it as the sample answer. You may lightly polish grammar and clarity but must preserve all content, structure, and meaning. Do NOT generate a new answer from scratch."
            : "- Write a detailed model response that directly answers the question.\n- Structure it to clearly address each rubric criterion.\n- Length should match the question's expected word count (typically 150-400 words for essay questions).";
        $escaped = str_replace('"', '\\"', $questiontext);
        $user = <<<EOT
{$extrasection}{$prevsection}{$pastedsection}{$modelsection}The teacher has written the following assessment question. Generate professional assessment materials for it.

QUESTION (do NOT modify): "{$questiontext}"

CONTEXT: This question is {$contextdescription}.{$levelnote}

Your task:
1. Generate a marking RUBRIC — a set of criteria a marker uses to assess student responses.
2. Generate a SAMPLE ANSWER — a model response that would earn full marks.
3. Determine appropriate TOTAL MARKS based on the question's complexity and scope.

RUBRIC RULES:
{$rubricrules}
- Each criterion is worth exactly 1 mark.
- Use IMPERATIVE tense (e.g., "Identify the key risks" not "Identifies the key risks").
- Criteria must be GENERIC assessment guidance — describe what earns the mark without giving away the answer.
- Do NOT start with "Award 1 mark for..." — just describe the performance standard.
{$derive}

SAMPLE ANSWER RULES:
{$samplerules}

CRITERIA REFERENCE:
- Derive a short criteria/topic reference from the question itself (e.g., "WHS legislation compliance").
{$others}
Return a JSON object:
{
  "moodleQuestionType": "essay",
  "questionText": "{$escaped}",
  "rubric": [
    { "criterion": 1, "marks": 1, "description": "Generic imperative-tense criterion" }
  ],
  "sampleAnswer": [
    { "criterion": 1, "marks": 1, "response": "Detailed model response for this criterion" }
  ],
  "criteriaReference": "Short topic/criteria reference derived from the question",
  "questionFormat": "short_essay",
  "wordCount": "200-300 words",
  "totalMarks": 3
}
EOT;
        $system .= "\n\nYou always respond with valid JSON. No markdown, no code blocks, just raw JSON.";
        return [$system, $user, 4000];
    }

    /**
     * The user prompt for one auto-marked question type of /api/generate-from-questions.
     *
     * @param string $type multichoice, truefalse, matching, gapselect or shortanswer.
     * @param string $q The teacher's question.
     * @param array $ctx From gfq_context().
     * @param string $regen The questionText guidance.
     * @param string $others The other-questions block.
     * @return string
     */
    private static function type_prompt(string $type, string $q, array $ctx, string $regen, string $others): string {
        $pasted = $ctx['pastedsection'];
        $cd = $ctx['contextdescription'];
        $styles = $ctx['styleinstructions'];
        $wctx = $ctx['wctx'];
        switch ($type) {
            case 'multichoice':
                return <<<EOT
{$pasted}Generate a multiple choice question for the following topic/question.

TOPIC/QUESTION: "{$q}"
CONTEXT: {$cd}
{$styles}
Create 4 answer choices — exactly ONE correct, THREE plausible distractors that test common misconceptions.
Each distractor's feedback must address a DISTINCT misconception — no two feedbacks may say the same thing.
NEVER use generic distractors that could apply to any question. All four choices must be specific to THIS question's content.

Return this JSON (keys in English, values in the required language):
{
  "moodleQuestionType": "multichoice",
  "questionText": "{$regen}",
  "choices": [
    {"text": "The correct answer", "isCorrect": true, "feedback": "This is correct because [specific reason tied to THIS question]"},
    {"text": "Plausible wrong option 1", "isCorrect": false, "feedback": "Incorrect because [specific misconception 1 — distinct from other feedbacks]"},
    {"text": "Plausible wrong option 2", "isCorrect": false, "feedback": "Incorrect because [specific misconception 2 — distinct from other feedbacks]"},
    {"text": "Plausible wrong option 3", "isCorrect": false, "feedback": "Incorrect because [specific misconception 3 — distinct from other feedbacks]"}
  ],
  "explanation": "General feedback shown after submission explaining the correct concept",
  "criteriaReference": "Short topic reference (3-6 words)"
}
{$others}
EOT;
            case 'truefalse':
                return <<<EOT
{$pasted}Convert the following question into a True/False statement suitable for a Moodle quiz.

QUESTION/TOPIC: "{$q}"
CONTEXT: {$cd}
{$styles}
Create a clear, unambiguous declarative statement that is definitively TRUE or FALSE.
The statement must be SPECIFIC to this question's exact content — do NOT write a generic statement that could apply to any similar question.
trueAnswerFeedback and falseAnswerFeedback MUST each explain WHY that selection is correct or incorrect — reference the key fact, legislation, or principle. Do NOT just say "Correct" or "Incorrect".

Return this JSON:
{
  "moodleQuestionType": "truefalse",
  "questionText": "A clear declarative statement (not a question) that is definitively true or false — specific to THIS question's content",
  "correctAnswer": true,
  "trueAnswerFeedback": "Explain WHY selecting True is correct or incorrect — cite the specific fact or rule that makes this true or false",
  "falseAnswerFeedback": "Explain WHY selecting False is correct or incorrect — cite the specific fact or rule that makes this true or false",
  "explanation": "General explanation of the underlying concept",
  "criteriaReference": "Identifies key concept being tested"
}
CRITICAL: criteriaReference MUST be a short skill-based phrase (3-8 words, e.g. "Identifies hazards in the workplace"). NEVER copy the question text or any sentence into criteriaReference.
{$others}
EOT;
            case 'matching':
                return <<<EOT
{$pasted}Generate a matching question with 4 term-definition pairs based on the topic of this question.

TOPIC/QUESTION: "{$q}"
CONTEXT: {$cd}
{$styles}
Create 4 DISTINCT terms and their precise matching descriptions/definitions.
CRITICAL UNIQUENESS RULES FOR MATCHING:
- The 4 terms MUST be specific to THIS question's exact content — do NOT use generic terms that could apply to any question.
- Each of the 4 terms must be DIFFERENT from the others — no overlapping concepts.
- subanswers must be clearly distinct from each other — a student should not confuse any two answers.
- Do NOT use terms like "Safety", "Procedure", "Policy", "Workplace" as standalone terms — always be more specific (e.g., "Hierarchy of Control" not just "Control").

Return this JSON:
{
  "moodleQuestionType": "matching",
  "questionText": "Match each term with its correct description:",
  "matchPairs": [
    {"subquestion": "Specific term 1 from THIS topic", "subanswer": "Its precise definition — clearly different from all other subanswers"},
    {"subquestion": "Specific term 2 from THIS topic", "subanswer": "Its precise definition — clearly different from all other subanswers"},
    {"subquestion": "Specific term 3 from THIS topic", "subanswer": "Its precise definition — clearly different from all other subanswers"},
    {"subquestion": "Specific term 4 from THIS topic", "subanswer": "Its precise definition — clearly different from all other subanswers"}
  ],
  "explanation": "General explanation of why these terms/concepts are important in this topic",
  "criteriaReference": "Short skill-based phrase derived from THIS question (3-8 words)"
}
CRITICAL: criteriaReference MUST be a short skill-based phrase (3-8 words, gerund or imperative form) that describes the competency being tested, e.g. "Identifies key industry terms and their meanings". NEVER copy criteriaReference from questionText, matchPairs content, or any other field.
{$others}
EOT;
            case 'gapselect':
                if ($wctx) {
                    $qt = 'A 1–2 sentence workplace scenario SPECIFIC TO THIS QUESTION\'s topic (name a specific person, role, or situation from THIS domain).\\n\\nThe sentence with [[1]], [[4]], [[7]] jump-by-3 gap placeholders — containing key terms specific to THIS question\'s topic.';
                    $qtrule = '- questionText MUST have TWO parts separated by a blank line: (1) a 1–2 sentence realistic workplace scenario that sets the context — name a specific person, role, or situation, and (2) the sentence with [[1]], [[4]], [[7]]... jump-by-3 placeholders inline. Format as: "SCENARIO\\n\\nSENTENCE WITH [[1]] [[4]] [[7]] GAPS". Do NOT include a separate question prompt — the scenario provides context.';
                } else {
                    $qt = 'A clear, direct question about the concept being tested (e.g. "What are the correct terms used in the [topic] process?").\\n\\nThe sentence with [[1]], [[4]], [[7]] jump-by-3 gap placeholders — do NOT include a scenario, named person, or workplace setting.';
                    $qtrule = '- questionText MUST have TWO parts separated by a blank line: (1) a clear direct question about the concept being tested — do NOT include a scenario, named person, workplace setting, or situational framing, and (2) the sentence with [[1]], [[4]], [[7]]... jump-by-3 placeholders inline. Format as: "QUESTION\\n\\nSENTENCE WITH [[1]] [[4]] [[7]] GAPS".';
                }
                return <<<EOT
{$pasted}Generate a Select Missing Words question based on the following topic/question.

TOPIC/QUESTION: "{$q}"
CONTEXT: {$cd}
{$styles}
Write a sentence about THIS specific topic with 2-4 key terms replaced by gap placeholders. Use the JUMP-BY-3 numbering scheme: Blank 1 = [[1]], Blank 2 = [[4]], Blank 3 = [[7]], Blank 4 = [[10]]. NEVER use [[2]] or [[3]] etc. as a placeholder in the sentence — those numbers are reserved as the distractor slots of the previous blank. Students will pick the correct term from a dropdown for each gap.
UNIQUENESS: The gap words and scenario MUST be specific to THIS question's exact content. Do NOT use generic filler words like "correct", "proper", "appropriate" as answers — use specific technical terms from this topic.
The distractors for each gap must be plausible alternatives from the SAME semantic category (e.g., if the answer is a specific legislation, distractors are other legislations — not unrelated words).

Return this JSON:
{
  "moodleQuestionType": "gapselect",
  "questionText": "{$qt}",
  "selectOptions": [
    ["specific correct term for blank 1", "plausible distractor from same category", "another plausible distractor"],
    ["specific correct term for blank 2", "plausible distractor from same category", "another plausible distractor"]
  ],
  "generalFeedback": "Blank [[1]]: [correct word] — [explain WHY this is correct, not just what it means]. Blank [[4]]: [correct word] — [explain WHY].",
  "criteriaReference": "Short skill-based phrase specific to THIS question (3-8 words)"
}
CRITICAL GROUPING RULES — READ CAREFULLY:
- BLANK NUMBERING MUST JUMP BY 3: Blank 1 = [[1]], Blank 2 = [[4]], Blank 3 = [[7]], Blank 4 = [[10]]. NEVER use [[2]] [[3]] [[5]] [[6]] [[8]] [[9]] etc. as placeholders in the sentence.
- COUNT MUST MATCH: The number of selectOptions sub-arrays MUST exactly equal the number of [[N]] placeholders in the sentence. 2 placeholders → 2 sub-arrays. 3 placeholders → 3 sub-arrays. Count the placeholders before you emit the JSON.
- ONE SUB-ARRAY PER BLANK (NOT PER CHOICE): each sub-array contains 3 strings = 1 correct answer + 2 distractors. Do NOT flatten or split.
- selectOptions[0] = the 3 choices for the 1st blank (the [[1]] placeholder): 1 correct + 2 distractors.
- selectOptions[1] = the 3 choices for the 2nd blank (the [[4]] placeholder): 1 correct + 2 distractors.
- selectOptions[2] = the 3 choices for the 3rd blank (the [[7]] placeholder): 1 correct + 2 distractors.
- NEVER put correct answers from different blanks into the same sub-array. Each sub-array belongs to exactly one blank.
- CORRECT ANSWER POSITION: The FIRST option in each selectOptions sub-array is the CORRECT answer for that blank.
- BLANKS MUST BE INDEPENDENT: each blank's distractors fit the surrounding sentence grammar; picking the correct answer in one blank must not give away the answer to another.
{$qtrule}
- criteriaReference MUST be a short skill-based phrase (3-8 words), e.g. "Identifies key workplace hazards". NEVER copy the question text into criteriaReference.
- Use 2–4 blanks. Each sub-array must have exactly 3 options (1 correct + 2 distractors).
- NO DUPLICATES WITHIN A BLANK: Every option in a single selectOptions sub-array MUST be unique. The correct answer must NEVER also appear as one of its own distractors. Distractors must NEVER repeat. Comparison is case- and whitespace-insensitive.
{$others}
EOT;
            default:
                return <<<EOT
{$pasted}Generate a fill-in-the-blank (short answer) question based on the following topic/question.

TOPIC/QUESTION: "{$q}"
CONTEXT: {$cd}
{$styles}
The question should have a specific keyword or short phrase as the correct answer (e.g., a term, name, acronym, or brief definition — NOT a full sentence answer). The answer MUST be 1-5 words maximum.
UNIQUENESS: The blank word/phrase MUST be a SPECIFIC technical term central to THIS question's content — not a generic word. Do NOT use "correct", "appropriate", "safety" or other vague filler as the answer.
The blankSentence must be a DIFFERENT sentence from questionText — it should come from the topic content and make clear sense when the blank is filled.

Return TWO separate fields:
- questionText: A clear, contextual question that asks the student to identify the specific term or concept from THIS question.
- blankSentence: A SEPARATE, complete sentence with ONE key term replaced by ___ (the blank). This must be a different sentence from questionText.

Return this JSON:
{
  "moodleQuestionType": "shortanswer",
  "questionText": "A clear question asking the student to identify the specific concept or term from THIS question's exact content",
  "blankSentence": "A complete, different sentence from the topic — with ONE specific technical term replaced by ___ (max 5 words for the answer)",
  "acceptedAnswers": ["primary accepted answer (1-5 words, most precise)", "alternative spelling or abbreviation if applicable"],
  "explanation": "Explanation of why this specific answer is correct and its significance in this context",
  "criteriaReference": "Short topic reference specific to THIS question (3-6 words)"
}
{$others}
EOT;
        }
    }

    /**
     * Has the teacher given marking instructions, and do they ask for a single mark?
     *
     * @param string $extra Extra instructions.
     * @return bool[] [hasmarking, singlemark]
     */
    private static function marking_flags(string $extra): array {
        $l = strtolower($extra);
        if ($extra === '') {
            return [false, false];
        }
        $has = strpos($l, 'mark') !== false || strpos($l, 'total') !== false
            || strpos($l, 'rubric') !== false || strpos($l, 'criteria') !== false;
        $single = strpos($l, '1 mark') !== false || strpos($l, 'one mark') !== false || strpos($l, 'single mark') !== false;
        return [$has, $single];
    }

    /**
     * The service's language instruction for non-English sites.
     *
     * @param string $language Language code as the plugin sends it.
     * @return string
     */
    private static function language_instruction(string $language): string {
        $code = $language !== '' ? $language : 'en-AU';
        if (strpos($code, 'en-') === 0) {
            return '';
        }
        $name = self::LANGUAGES[$code] ?? 'English (Australian)';
        return "\n\nCRITICAL LANGUAGE REQUIREMENT: Generate ALL content in {$name}. This means ALL question text, rubric descriptions, sample answers, and any other values in the JSON. JSON keys must remain in English. Write naturally in {$name} — do not translate word-by-word.";
    }

    /**
     * JavaScript String.substring(0, n): counts UTF-16 code units.
     *
     * @param string $s Text.
     * @param int $n Code units to keep.
     * @return string
     */
    private static function js_substring(string $s, int $n): string {
        $u = mb_convert_encoding($s, 'UTF-16LE', 'UTF-8');
        if (strlen($u) <= $n * 2) {
            return $s;
        }
        return mb_convert_encoding(substr($u, 0, $n * 2), 'UTF-8', 'UTF-16LE');
    }

    /**
     * Call the site's own AI for JSON.
     *
     * @param string $system System prompt.
     * @param string $user User prompt.
     * @param int $maxtokens Output budget.
     * @return string
     */
    private static function ai(string $system, string $user, int $maxtokens): string {
        if (self::$aicall) {
            return (string) call_user_func(self::$aicall, $system, $user, $maxtokens);
        }
        return \local_aiconfig\byok\ai::text(self::COMPONENT, $system, $user, [
            'json' => true, 'maxtokens' => $maxtokens, 'temperature' => 0,
        ]);
    }

    /**
     * Decode a JSON reply, finding the outer object when the model wrapped it in prose.
     *
     * @param string $raw Model output.
     * @param bool $lenient Return [] instead of null when nothing parses.
     * @return mixed
     */
    private static function decode(string $raw, bool $lenient = false) {
        $data = json_decode(trim($raw), true);
        if ($data === null && preg_match('/\{[\s\S]*\}/', $raw, $m)) {
            $data = json_decode($m[0], true);
        }
        return $data ?? ($lenient ? [] : null);
    }

    /**
     * A teacher-readable message for a failed AI call.
     *
     * @param \Throwable $e The failure.
     * @return string
     */
    private static function message(\Throwable $e): string {
        if ($e instanceof \moodle_exception && $e->errorcode === 'byok_notavailable') {
            return get_string('byok_notavailable', 'local_aiquizmaker');
        }
        return get_string('byok_failed', 'local_aiquizmaker', s(preg_replace('/^OPENAI_[A-Z_]+:\s*/', '', $e->getMessage())));
    }

    /**
     * array_is_list() for PHP before 8.1.
     *
     * @param array $a Array.
     * @return bool
     */
    private static function is_list(array $a): bool {
        return $a === [] || array_keys($a) === range(0, count($a) - 1);
    }
}
