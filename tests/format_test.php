<?php
// This file is part of Moodle - https://moodle.org/
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
// along with Moodle.  If not, see <https://www.gnu.org/licenses/>.

/**
 * Unit tests for the Markdown question import format.
 *
 * @package    qformat_markdown
 * @copyright  2026 José Cornejo <jose.cornejo.lupa@gmail.com>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace qformat_markdown;

use qformat_markdown;

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->libdir . '/questionlib.php');
require_once($CFG->dirroot . '/question/format.php');
require_once($CFG->dirroot . '/question/format/markdown/format.php');

/**
 * Tests for the qformat_markdown parser.
 *
 * @covers \qformat_markdown
 * @copyright  2026 José Cornejo <jose.cornejo.lupa@gmail.com>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class format_test extends \advanced_testcase {
    /**
     * Helper to run the importer over fixture content.
     *
     * @param string $content markdown content.
     * @return array parsed questions.
     */
    protected function import(string $content): array {
        $importer = new qformat_markdown();
        $questions = $importer->readquestions(explode("\n", $content));
        return $questions === false ? [] : $questions;
    }

    /**
     * A single-answer multichoice question is parsed correctly.
     */
    public function test_import_single_answer_multichoice(): void {
        $md = "## What is the capital of France?\n" .
              "- [ ] London\n" .
              "- [x] Paris\n" .
              "- [ ] Madrid\n";

        $questions = $this->import($md);

        $this->assertCount(1, $questions);
        $question = $questions[0];
        $this->assertSame('multichoice', $question->qtype);
        $this->assertSame(1, $question->single);
        $this->assertCount(3, $question->answer);
        $this->assertEqualsWithDelta(1.0, $question->fraction[1], 0.0001);
        $this->assertEqualsWithDelta(0.0, $question->fraction[0], 0.0001);
    }

    /**
     * Multiple correct options produce a multi-answer question with split fractions.
     */
    public function test_import_multi_answer_multichoice(): void {
        $md = "## Which of these are PHP frameworks?\n" .
              "- [x] Laravel\n" .
              "- [x] Symfony\n" .
              "- [ ] Django\n" .
              "- [ ] Rails\n";

        $questions = $this->import($md);

        $this->assertCount(1, $questions);
        $question = $questions[0];
        $this->assertSame('multichoice', $question->qtype);
        $this->assertSame(0, $question->single);
        $this->assertEqualsWithDelta(0.5, $question->fraction[0], 0.0001);
        $this->assertEqualsWithDelta(0.5, $question->fraction[1], 0.0001);
    }

    /**
     * True/False options become a truefalse question.
     */
    public function test_import_truefalse(): void {
        $md = "## PHP is a compiled language.\n" .
              "- [x] False\n" .
              "- [ ] True\n";

        $questions = $this->import($md);

        $this->assertCount(1, $questions);
        $question = $questions[0];
        $this->assertSame('truefalse', $question->qtype);
        $this->assertSame(0, $question->correctanswer);
    }

    /**
     * "= " lines produce a shortanswer question, one accepted answer per
     * line, each worth full credit.
     */
    public function test_import_shortanswer(): void {
        $md = "## What is the capital of France?\n" .
              "= Paris\n" .
              "= paris\n";

        $questions = $this->import($md);

        $this->assertCount(1, $questions);
        $question = $questions[0];
        $this->assertSame('shortanswer', $question->qtype);
        $this->assertSame(['Paris', 'paris'], $question->answer);
        $this->assertEqualsWithDelta(1.0, $question->fraction[0], 0.0001);
        $this->assertEqualsWithDelta(1.0, $question->fraction[1], 0.0001);
    }

    /**
     * "=# value:tolerance" lines produce a numerical question.
     */
    public function test_import_numerical_with_tolerance(): void {
        $md = "## What is the value of Pi to two decimal places?\n" .
              "=# 3.14:0.01\n";

        $questions = $this->import($md);

        $this->assertCount(1, $questions);
        $question = $questions[0];
        $this->assertSame('numerical', $question->qtype);
        $this->assertSame('3.14', $question->answer[0]);
        $this->assertSame('0.01', $question->tolerance[0]);
    }

    /**
     * "=# value" without a tolerance defaults to an exact match (0).
     */
    public function test_import_numerical_without_tolerance(): void {
        $md = "## What is 6 times 7?\n" .
              "=# 42\n";

        $questions = $this->import($md);

        $question = $questions[0];
        $this->assertSame('numerical', $question->qtype);
        $this->assertSame('42', $question->answer[0]);
        $this->assertSame('0', $question->tolerance[0]);
    }

    /**
     * "> " lines become general feedback, regardless of question type.
     */
    public function test_import_blockquote_feedback(): void {
        $md = "## What is the capital of France?\n" .
              "- [ ] London\n" .
              "- [x] Paris\n" .
              "- [ ] Madrid\n" .
              "> Paris has been the capital since 508 AD.\n";

        $questions = $this->import($md);

        $question = $questions[0];
        $this->assertSame('Paris has been the capital since 508 AD.', $question->generalfeedback);
    }

    /**
     * Several "> " lines in one block are joined with line breaks.
     */
    public function test_import_blockquote_feedback_multiline(): void {
        $md = "## What is the capital of Peru?\n" .
              "= Lima\n" .
              "> Lima is the capital and largest city of Peru.\n" .
              "> It was founded in 1535.\n";

        $questions = $this->import($md);

        $question = $questions[0];
        $this->assertSame(
            "Lima is the capital and largest city of Peru.\nIt was founded in 1535.",
            $question->generalfeedback
        );
    }

    /**
     * A block without any "> " line keeps the default empty feedback.
     */
    public function test_import_without_feedback_leaves_it_blank(): void {
        $md = "## What is 6 times 7?\n" .
              "=# 42\n";

        $questions = $this->import($md);

        $question = $questions[0];
        $this->assertSame('', $question->generalfeedback);
    }

    /**
     * Several questions in one file are all imported.
     */
    public function test_import_multiple_questions(): void {
        $md = file_get_contents(__DIR__ . '/fixtures/sample.md');

        $questions = $this->import($md);

        $this->assertCount(3, $questions);
    }

    /**
     * Blocks without at least two options are skipped.
     */
    public function test_invalid_block_is_skipped(): void {
        $md = "## Orphan question with no options\n\n" .
              "## Valid question\n" .
              "- [x] Yes\n" .
              "- [ ] No\n";

        $questions = $this->import($md);

        $this->assertCount(1, $questions);
        $this->assertSame('Valid question', $questions[0]->name);
    }

    /**
     * Run a markdown string through the full import pipeline (not just
     * readquestions()), so importprocess() writes to the database and the
     * question_bank_entries/question_versions merging logic runs.
     *
     * @param string $content markdown content.
     * @param \stdClass $category question_categories record to import into.
     */
    protected function full_import(string $content, \stdClass $category): void {
        $path = make_request_directory() . '/import.md';
        file_put_contents($path, $content);

        $importer = new qformat_markdown();
        $importer->setCategory($category);
        $importer->setContexts([\context::instance_by_id($category->contextid)]);
        $importer->setCourse(get_course(SITEID));
        $importer->setFilename($path);
        $importer->setStoponerror(true);
        $importer->set_display_progress(false);

        $this->assertTrue($importer->importprocess());
    }

    /**
     * Re-importing a file whose questions share a name with existing
     * questions in the category creates new versions of them, instead of
     * separate duplicate questions (qformat_default alone always creates a
     * brand new question_bank_entries row on every import).
     */
    public function test_reimport_creates_new_version_not_duplicate(): void {
        global $DB;

        $this->resetAfterTest();
        $this->setAdminUser();

        $generator = $this->getDataGenerator()->get_plugin_generator('core_question');
        $category = $generator->create_question_category();

        $md = file_get_contents(__DIR__ . '/fixtures/sample.md');

        $this->full_import($md, $category);
        $entries = $DB->get_records('question_bank_entries', ['questioncategoryid' => $category->id]);
        $this->assertCount(3, $entries);

        $this->full_import($md, $category);

        $this->assertEquals(
            3,
            $DB->count_records('question_bank_entries', ['questioncategoryid' => $category->id])
        );
        foreach (array_keys($entries) as $entryid) {
            $this->assertEquals(
                2,
                $DB->count_records('question_versions', ['questionbankentryid' => $entryid])
            );
        }
    }

    /**
     * Importing a question with a name that doesn't match anything already
     * in the category creates a new question, without touching the
     * existing ones.
     */
    public function test_import_of_new_question_does_not_affect_existing(): void {
        global $DB;

        $this->resetAfterTest();
        $this->setAdminUser();

        $generator = $this->getDataGenerator()->get_plugin_generator('core_question');
        $category = $generator->create_question_category();

        $this->full_import("## Existing question?\n- [x] Yes\n- [ ] No\n", $category);
        $originalentry = $DB->get_record(
            'question_bank_entries',
            ['questioncategoryid' => $category->id],
            '*',
            MUST_EXIST
        );

        $this->full_import("## Brand new question?\n- [x] Yes\n- [ ] No\n", $category);

        $this->assertEquals(
            2,
            $DB->count_records('question_bank_entries', ['questioncategoryid' => $category->id])
        );
        $this->assertEquals(
            1,
            $DB->count_records('question_versions', ['questionbankentryid' => $originalentry->id])
        );
    }
}
