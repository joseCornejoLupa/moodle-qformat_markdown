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
}
