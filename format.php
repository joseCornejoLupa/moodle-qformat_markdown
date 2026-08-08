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
 * Markdown question import format.
 *
 * Supported syntax (v0.1):
 *
 * ---
 * category: Quiz de PHP
 * defaultmark: 2
 * ---
 *
 * ## What is the capital of France?
 * - [ ] London
 * - [x] Paris
 * - [ ] Madrid
 *
 * ## PHP is a compiled language.
 * - [x] False
 * - [ ] True
 *
 * ## What is the capital of Peru?
 * = Lima
 *
 * ## What is the value of Pi to two decimal places?
 * =# 3.14:0.01
 * > Rounded to two decimal places.
 *
 * ## What is the capital of Italy?
 * @ 3
 * - [ ] Milan
 * - [x] Rome
 *
 * A question with more than one "[x]" becomes a multi-answer
 * multichoice question. A question whose only options are True/False
 * becomes a truefalse question. A question with "= " lines instead of
 * checkboxes becomes a shortanswer question, one accepted answer per
 * line. A question with "=# " lines becomes a numerical question; each
 * line is "value" or "value:tolerance". Either way, every listed answer
 * is worth full credit. Don't mix checkbox and "="/"=#" lines in the
 * same block. Any "> " line in a block (regardless of qtype) becomes
 * general feedback shown after the question is answered; multiple "> "
 * lines are joined into one feedback with line breaks. An "@ value" line
 * in a block sets that question's own mark, overriding the file-wide
 * "defaultmark" (if any) for that question only.
 *
 * An optional "---" ... "---" front matter block at the very start of the
 * file sets defaults for the whole import: "category" (created under the
 * category selected in the import screen if it doesn't exist yet; "/"
 * nests subcategories) and "defaultmark" (applied to every question in
 * the file, instead of Moodle's default of 1).
 *
 * @package    qformat_markdown
 * @copyright  2026 José Cornejo <jose.cornejo.lupa@gmail.com>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/**
 * Importer for questions written in a simple Markdown checklist syntax.
 *
 * @copyright  2026 José Cornejo <jose.cornejo.lupa@gmail.com>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class qformat_markdown extends qformat_default {
    /**
     * Question names (as saved to the database) produced by the current
     * import, in the same order as {@see qformat_default::$questionids}.
     * Used to match freshly imported questions back to their heading text
     * when deciding whether to create a new version of an existing
     * question instead of a duplicate.
     *
     * @var string[]
     */
    protected array $importednames = [];

    /**
     * Default mark for every question in this import, from the "defaultmark"
     * key in the file's YAML front matter, or null to use Moodle's own
     * default (1). Only populated when importing through importprocess();
     * calling readquestions() directly (as in unit tests) leaves it null.
     *
     * @var float|null
     */
    protected ?float $frontmatterdefaultmark = null;

    /**
     * This format can be used to import questions.
     *
     * @return bool
     */
    public function provide_import(): bool {
        return true;
    }

    /**
     * Only accept .md files in the import file picker.
     *
     * @return string
     */
    public function export_file_extension(): string {
        return '.md';
    }

    /**
     * Validate that the file looks like our Markdown format.
     *
     * @param stored_file $file the uploaded file.
     * @return bool
     */
    public function can_import_file($file): bool {
        $mimetypes = ['text/markdown', 'text/plain', 'application/octet-stream'];
        return in_array($file->get_mimetype(), $mimetypes);
    }

    /**
     * Run the standard import, then merge any freshly imported question
     * that shares its name with an existing question in the target
     * category into that question as a new version, instead of leaving it
     * as a separate duplicate. qformat_default has no such merging: every
     * import always creates a brand new question_bank_entries row.
     *
     * @return bool success
     */
    public function importprocess() {
        $this->apply_front_matter();

        $before = $this->existing_entries_by_name();

        $result = parent::importprocess();

        if ($result) {
            $this->merge_reimported_questions($before);
        }

        return $result;
    }

    /**
     * Read the file's optional YAML-ish front matter and apply it: switch
     * the target category (creating it under the one selected in the
     * import screen if needed) and/or remember a default mark for every
     * question in this import. Must run before existing_entries_by_name(),
     * so that a category change is already in effect when snapshotting
     * what "before this import" means.
     */
    protected function apply_front_matter(): void {
        $lines = $this->readdata($this->filename);
        if ($lines === false) {
            return;
        }

        $frontmatter = $this->extract_front_matter($lines);

        $this->frontmatterdefaultmark = isset($frontmatter['defaultmark']) && is_numeric($frontmatter['defaultmark'])
                ? (float) $frontmatter['defaultmark']
                : null;

        if (!empty($frontmatter['category'])) {
            $newcategory = $this->create_category_path($frontmatter['category']);
            if (!empty($newcategory)) {
                $this->category = $newcategory;
            }
        }
    }

    /**
     * Parse a "---" ... "---" front matter block at the start of the file,
     * if there is one, into a key => value map. Values are plain strings;
     * surrounding quotes are stripped.
     *
     * @param array $lines raw lines from the file.
     * @return array<string, string>
     */
    protected function extract_front_matter(array $lines): array {
        if (!isset($lines[0]) || trim($lines[0]) !== '---') {
            return [];
        }

        $frontmatter = [];
        foreach ($lines as $index => $line) {
            if ($index === 0) {
                continue;
            }
            if (trim($line) === '---') {
                break;
            }
            if (preg_match('/^([A-Za-z0-9_]+):\s*(.*)$/', trim($line), $matches)) {
                $frontmatter[$matches[1]] = trim($matches[2], " \t\"'");
            }
        }

        return $frontmatter;
    }

    /**
     * Map the name of the current, latest-version question in each bank
     * entry of the target category to that entry's id.
     *
     * @return array name (string) => question_bank_entries.id (int)
     */
    protected function existing_entries_by_name(): array {
        global $DB;

        $sql = "SELECT qbe.id AS entryid, q.name
                  FROM {question_bank_entries} qbe
                  JOIN {question_versions} qv ON qv.questionbankentryid = qbe.id
                  JOIN {question} q ON q.id = qv.questionid
                 WHERE qbe.questioncategoryid = :categoryid
                   AND qv.version = (SELECT MAX(qv2.version)
                                        FROM {question_versions} qv2
                                       WHERE qv2.questionbankentryid = qbe.id)";

        $map = [];
        foreach ($DB->get_records_sql($sql, ['categoryid' => $this->category->id]) as $record) {
            $map[$record->name] = (int) $record->entryid;
        }
        return $map;
    }

    /**
     * For each question just imported whose name matches a pre-existing
     * question in the category, re-point its version row at the existing
     * question_bank_entries row (as the next version number) and drop the
     * now-empty entry that the standard import created for it.
     *
     * @param array $before name (string) => entry id (int), from before this import ran.
     */
    protected function merge_reimported_questions(array $before): void {
        global $DB;

        if (empty($before) || count($this->importednames) !== count($this->questionids)) {
            // Nothing to match against, or a question was filtered out
            // during import (e.g. invalid grade) so positions no longer
            // line up reliably: skip merging rather than risk a wrong match.
            return;
        }

        foreach ($this->questionids as $index => $newquestionid) {
            $name = $this->importednames[$index];
            if (!isset($before[$name])) {
                continue;
            }

            $oldentryid = $before[$name];
            $newversion = $DB->get_record('question_versions', ['questionid' => $newquestionid], '*', MUST_EXIST);
            $orphanedentryid = $newversion->questionbankentryid;
            if ($orphanedentryid == $oldentryid) {
                continue;
            }

            $maxversion = $DB->get_field_sql(
                'SELECT MAX(version) FROM {question_versions} WHERE questionbankentryid = ?',
                [$oldentryid]
            );

            $DB->update_record('question_versions', (object) [
                'id' => $newversion->id,
                'questionbankentryid' => $oldentryid,
                'version' => $maxversion + 1,
            ]);
            $DB->delete_records('question_bank_entries', ['id' => $orphanedentryid]);
        }
    }

    /**
     * Parse the lines of the uploaded file into question objects.
     *
     * @param array $lines array of lines from the input file.
     * @return array of question objects, or false on failure.
     */
    public function readquestions($lines) {
        $questions = [];
        $this->importednames = [];
        $blocks = $this->split_into_blocks($lines);

        foreach ($blocks as $block) {
            $question = $this->parse_block($block);
            if ($question !== null) {
                $questions[] = $question;
            }
        }

        if (empty($questions)) {
            $this->error(get_string('noquestionsfound', 'qformat_markdown'));
            return false;
        }

        return $questions;
    }

    /**
     * Split the file lines into blocks, one per "## " heading.
     *
     * @param array $lines raw lines.
     * @return array array of arrays of lines.
     */
    protected function split_into_blocks(array $lines): array {
        $blocks = [];
        $current = [];

        foreach ($lines as $line) {
            $line = rtrim($line);
            if (preg_match('/^##\s+/', $line)) {
                if (!empty($current)) {
                    $blocks[] = $current;
                }
                $current = [$line];
            } else if (!empty($current)) {
                $current[] = $line;
            }
        }
        if (!empty($current)) {
            $blocks[] = $current;
        }
        return $blocks;
    }

    /**
     * Turn one block (heading + options) into a Moodle question object.
     *
     * @param array $block lines of the block.
     * @return stdClass|null question object or null if the block is invalid.
     */
    protected function parse_block(array $block): ?stdClass {
        $name = trim(preg_replace('/^##\s+/', '', array_shift($block)));
        if ($name === '') {
            return null;
        }

        $checkboxes = [];
        $shortanswers = [];
        $numericals = [];
        $feedbacklines = [];
        $markoverride = null;
        foreach ($block as $line) {
            $line = trim($line);
            if (preg_match('/^-\s*\[( |x|X)\]\s*(.+)$/', $line, $matches)) {
                $checkboxes[] = [
                    'correct' => strtolower($matches[1]) === 'x',
                    'text' => trim($matches[2]),
                ];
            } else if (preg_match('/^=#\s*(.+)$/', $line, $matches)) {
                $numericals[] = trim($matches[1]);
            } else if (preg_match('/^=\s*(.+)$/', $line, $matches)) {
                $shortanswers[] = trim($matches[1]);
            } else if (preg_match('/^>\s?(.*)$/', $line, $matches)) {
                $feedbacklines[] = $matches[1];
            } else if (preg_match('/^@\s*([0-9]+(?:\.[0-9]+)?)\s*$/', $line, $matches)) {
                $markoverride = (float) $matches[1];
            }
        }

        if (!empty($numericals)) {
            $question = $this->build_numerical($name, $numericals);
        } else if (!empty($shortanswers)) {
            $question = $this->build_shortanswer($name, $shortanswers);
        } else if (count($checkboxes) >= 2) {
            $question = $this->is_truefalse($checkboxes)
                    ? $this->build_truefalse($name, $checkboxes)
                    : $this->build_multichoice($name, $checkboxes);
        } else {
            return null;
        }

        if (!empty($feedbacklines)) {
            $question->generalfeedback = implode("\n", $feedbacklines);
            $question->generalfeedbackformat = FORMAT_MARKDOWN;
        }

        if ($this->frontmatterdefaultmark !== null) {
            $question->defaultmark = $this->frontmatterdefaultmark;
        }
        if ($markoverride !== null) {
            $question->defaultmark = $markoverride;
        }

        $this->importednames[] = $question->name;
        return $question;
    }

    /**
     * Detect whether a set of answers is a True/False question.
     *
     * @param array $answers parsed answers.
     * @return bool
     */
    protected function is_truefalse(array $answers): bool {
        if (count($answers) !== 2) {
            return false;
        }
        $texts = array_map(function ($answer) {
            return core_text::strtolower($answer['text']);
        }, $answers);
        sort($texts);
        return $texts === ['false', 'true'];
    }

    /**
     * Build a truefalse question object.
     *
     * @param string $name question name/text.
     * @param array $answers parsed answers.
     * @return stdClass
     */
    protected function build_truefalse(string $name, array $answers): stdClass {
        $question = $this->defaultquestion();
        $question->qtype = 'truefalse';
        $question->name = shorten_text($name, 250);
        $question->questiontext = $name;
        $question->questiontextformat = FORMAT_MARKDOWN;

        $trueiscorrect = false;
        foreach ($answers as $answer) {
            if (core_text::strtolower($answer['text']) === 'true' && $answer['correct']) {
                $trueiscorrect = true;
            }
        }

        $question->correctanswer = $trueiscorrect ? 1 : 0;
        $question->feedbacktrue = ['text' => '', 'format' => FORMAT_MARKDOWN];
        $question->feedbackfalse = ['text' => '', 'format' => FORMAT_MARKDOWN];
        return $question;
    }

    /**
     * Build a multichoice question object.
     *
     * @param string $name question name/text.
     * @param array $answers parsed answers.
     * @return stdClass
     */
    protected function build_multichoice(string $name, array $answers): stdClass {
        $question = $this->defaultquestion();
        $question->qtype = 'multichoice';
        $question->name = shorten_text($name, 250);
        $question->questiontext = $name;
        $question->questiontextformat = FORMAT_MARKDOWN;
        $question->shuffleanswers = 1;
        $question->answernumbering = 'abc';

        $correctcount = 0;
        foreach ($answers as $answer) {
            if ($answer['correct']) {
                $correctcount++;
            }
        }
        $question->single = ($correctcount <= 1) ? 1 : 0;

        $question->answer = [];
        $question->fraction = [];
        $question->feedback = [];

        foreach ($answers as $answer) {
            $question->answer[] = ['text' => $answer['text'], 'format' => FORMAT_MARKDOWN];
            if ($answer['correct']) {
                $question->fraction[] = ($correctcount > 0) ? round(1.0 / $correctcount, 7) : 0;
            } else {
                $question->fraction[] = 0;
            }
            $question->feedback[] = ['text' => '', 'format' => FORMAT_MARKDOWN];
        }

        $question->correctfeedback = ['text' => '', 'format' => FORMAT_MARKDOWN];
        $question->partiallycorrectfeedback = ['text' => '', 'format' => FORMAT_MARKDOWN];
        $question->incorrectfeedback = ['text' => '', 'format' => FORMAT_MARKDOWN];

        return $question;
    }

    /**
     * Build a shortanswer question object. Every listed answer is an
     * alternative worth full credit.
     *
     * @param string $name question name/text.
     * @param string[] $answers accepted answer strings, one per "= " line.
     * @return stdClass
     */
    protected function build_shortanswer(string $name, array $answers): stdClass {
        $question = $this->defaultquestion();
        $question->qtype = 'shortanswer';
        $question->name = shorten_text($name, 250);
        $question->questiontext = $name;
        $question->questiontextformat = FORMAT_MARKDOWN;
        $question->usecase = 0;

        $question->answer = [];
        $question->fraction = [];
        $question->feedback = [];

        foreach ($answers as $answer) {
            $question->answer[] = $answer;
            $question->fraction[] = 1.0;
            $question->feedback[] = ['text' => '', 'format' => FORMAT_MARKDOWN];
        }

        return $question;
    }

    /**
     * Build a numerical question object. Every listed answer is an
     * alternative worth full credit.
     *
     * @param string $name question name/text.
     * @param string[] $answers "value" or "value:tolerance" strings, one per "=# " line.
     * @return stdClass
     */
    protected function build_numerical(string $name, array $answers): stdClass {
        $question = $this->defaultquestion();
        $question->qtype = 'numerical';
        $question->name = shorten_text($name, 250);
        $question->questiontext = $name;
        $question->questiontextformat = FORMAT_MARKDOWN;

        $question->answer = [];
        $question->fraction = [];
        $question->feedback = [];
        $question->tolerance = [];

        foreach ($answers as $answer) {
            [$value, $tolerance] = array_pad(explode(':', $answer, 2), 2, '0');
            $question->answer[] = trim($value);
            $question->tolerance[] = trim($tolerance);
            $question->fraction[] = 1.0;
            $question->feedback[] = ['text' => '', 'format' => FORMAT_MARKDOWN];
        }

        return $question;
    }
}
