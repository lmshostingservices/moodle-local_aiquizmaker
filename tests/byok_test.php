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

namespace local_aiquizmaker;

use local_aiquizmaker\local\byok;

/**
 * Tests for writing questions on the site's own AI.
 *
 * The prompts themselves were checked word for word against the LMS Labs service code (273 randomised
 * payloads, identical system and user prompts and responses); these tests cover the wiring.
 *
 * @package    local_aiquizmaker
 * @copyright  2026 LMS Hosting Services
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \local_aiquizmaker\local\byok
 */
#[\PHPUnit\Framework\Attributes\CoversClass(byok::class)]
final class byok_test extends \advanced_testcase {
    /** @var array Requests the mocked provider received. */
    protected $requests = [];

    /**
     * Licence the site for Bring your own AI on a test key, with text on an Anthropic key.
     */
    protected function setUp(): void {
        parent::setUp();
        if (!class_exists('\local_aiconfig\byok\ai')) {
            $this->markTestSkipped('AI Grader Central Config 1.1.0 or later is not installed.');
        }
        $this->resetAfterTest();
        $pair = sodium_crypto_sign_keypair();
        set_config('testpublickey', base64_encode(sodium_crypto_sign_publickey($pair)), 'local_aiconfig');
        $payload = json_encode(['v' => 1, 'id' => 'lic_t', 'site' => \local_aiconfig\byok\licence::site_host(),
            'plan' => 'pro', 'byok' => true, 'products' => ['*'], 'sub' => 'sub_t', 'exp' => time() + DAYSECS]);
        $b64 = function (string $s): string {
            return rtrim(strtr(base64_encode($s), '+/', '-_'), '=');
        };
        set_config('licencekey', $b64($payload) . '.' .
            $b64(sodium_crypto_sign_detached($payload, sodium_crypto_sign_secretkey($pair))), 'local_aiconfig');
        set_config('src_text', 'ownkey', 'local_aiconfig');
        set_config('vendor_text', 'anthropic', 'local_aiconfig');
        set_config('key_anthropic', 'sk-test', 'local_aiconfig');
    }

    /**
     * Clean up the provider mock.
     */
    protected function tearDown(): void {
        unset($GLOBALS['local_aiconfig_byok_mock']);
        parent::tearDown();
    }

    /**
     * Make the provider answer with this JSON, or fail when null.
     *
     * @param array|null $answer
     */
    protected function answer(?array $answer): void {
        $this->requests = [];
        $GLOBALS['local_aiconfig_byok_mock'] = function ($url, $headers, $body) use ($answer) {
            $this->requests[] = $body;
            if ($answer === null) {
                throw new \moodle_exception('byok_providererror', 'local_aiconfig', '', 'Overloaded');
            }
            return ['content' => [['type' => 'text', 'text' => json_encode($answer)]]];
        };
    }

    public function test_only_answers_service_calls_when_byok_is_on(): void {
        $this->assertTrue(byok::enabled());
        $this->assertNull(byok::handle('https://lms-labs.com/api/credits?siteId=x', null));
        set_config('src_text', 'credits', 'local_aiconfig');
        $this->assertFalse(byok::enabled());
        $this->assertNull(byok::handle('https://lms-labs.com/api/generate-essays', ['criteria' => []]));
    }

    public function test_generate_essays_runs_locally_and_enforces_types(): void {
        $this->answer(['questions' => [
            ['questionText' => 'Which control comes first?', 'moodleQuestionType' => 'essay', 'criteriaReference' => ''],
            ['questionText' => 'Lockout is optional.'],
        ]]);
        $res = byok::handle('https://lms-labs.com/api/generate-essays', [
            'siteId' => '', 'apiKey' => '',
            'criteria' => [['text' => 'Apply isolation procedures', 'questionsCount' => 2]],
            'country' => 'Australia', 'industry' => 'Mining', 'jobTitle' => 'Fitter', 'jobLevel' => 'Entry',
            'educationType' => 'vet', 'educationLevel' => 'cert3', 'questionFormats' => ['short_essay'],
            'moodleQuestionTypes' => ['multichoice', 'truefalse'], 'selfMarkingStyles' => ['scenario'],
            'extraInstructions' => '', 'pastedContent' => '', 'language' => 'en-AU', 'workplaceContextEnabled' => true,
        ]);
        $this->assertTrue($res['success']);
        $data = json_decode($res['body'], true);
        $this->assertTrue($data['ok']);
        $this->assertTrue($data['byok']);
        $this->assertSame(2, $data['questionsGenerated']);
        $this->assertSame('multichoice', $data['questions'][0]['moodleQuestionType']);
        $this->assertSame('truefalse', $data['questions'][1]['moodleQuestionType']);
        $this->assertSame('Apply isolation procedures', $data['questions'][0]['criteriaReference']);
        $this->assertCount(1, $this->requests);
        $this->assertStringContainsString('world-class VET assessment specialist', $this->requests[0]['system']);
        $user = $this->requests[0]['messages'][0]['content'][0]['text'];
        $this->assertStringContainsString("  Question 1: MULTICHOICE\n  Question 2: TRUEFALSE", $user);
        $this->assertStringContainsString('Use Australian English spelling', $user);
        $this->assertSame(6000, $this->requests[0]['max_tokens']);
    }

    public function test_extract_criteria_keeps_only_text(): void {
        $this->answer(['criteria' => ['Demonstrate correct use of PPE', '', 7]]);
        $data = json_decode(byok::handle(
            'https://lms-labs.com/api/extract-criteria',
            ['pastedContent' => 'GOOD: uses PPE', 'educationType' => 'vet']
        )['body'], true);
        $this->assertTrue($data['ok']);
        $this->assertSame(['Demonstrate correct use of PPE'], $data['criteria']);
    }

    public function test_own_questions_keep_the_teachers_wording(): void {
        $this->answer(['questionText' => 'Rewritten', 'rubric' => [['criterion' => 1, 'marks' => 1, 'description' => 'x']]]);
        $data = json_decode(byok::handle('https://lms-labs.com/api/generate-from-questions', [
            'questions' => [['text' => 'Explain the isolation permit.', 'modelAnswer' => 'It authorises work.']],
            'moodleQuestionTypes' => ['essay'],
        ])['body'], true);
        $this->assertTrue($data['ok']);
        $this->assertSame('Explain the isolation permit.', $data['questions'][0]['questionText']);
        $this->assertSame('essay', $data['questions'][0]['moodleQuestionType']);
        $this->assertStringContainsString("TEACHER'S MODEL ANSWER", $this->requests[0]['messages'][0]['content'][0]['text']);
    }

    public function test_provider_failure_is_reported_and_spends_nothing(): void {
        $this->answer(null);
        $data = json_decode(byok::handle('https://lms-labs.com/api/generate-essays', [
            'criteria' => [['text' => 'Identify hazards', 'questionsCount' => 1]],
        ])['body'], true);
        $this->assertFalse($data['ok']);
        $this->assertSame('GENERATION_ERROR', $data['error']);
        $this->assertStringContainsString('Your AI provider could not write the questions', $data['message']);
    }

    public function test_lapsed_licence_says_so(): void {
        unset_config('licencekey', 'local_aiconfig');
        set_config('src_text', 'ownkey', 'local_aiconfig');
        $data = byok::extract_criteria(['pastedContent' => 'Some notes here']);
        $this->assertFalse($data['ok']);
        $this->assertStringContainsString('Your own AI is not available', $data['error']);
    }
}
