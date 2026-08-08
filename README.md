# Markdown question format for Moodle (qformat_markdown)

![Moodle Plugin CI](https://github.com/JoseCornejoLupa/moodle-qformat_markdown/actions/workflows/moodle-ci.yml/badge.svg)

Import quiz questions into the Moodle question bank from a simple,
human-friendly Markdown checklist syntax. Write your questions in any
text editor, keep them under version control, and import them in one click.

## Question syntax

Each question is a level-2 heading (`## `) followed by checklist options.
Mark correct answers with `[x]`:

```markdown
## What is the capital of France?
- [ ] London
- [x] Paris
- [ ] Madrid

## Which of these are PHP frameworks?
- [x] Laravel
- [x] Symfony
- [ ] Django

## PHP is a compiled language.
- [x] False
- [ ] True

## What is the capital of Peru?
= Lima

## What is the value of Pi to two decimal places?
=# 3.14:0.01
```

Rules:

- One correct option → single-answer multiple choice.
- Several correct options → multi-answer multiple choice (credit split evenly).
- Exactly the options `True` and `False` → a True/False question.
- Blocks with fewer than two `- [ ]` options are skipped.
- One or more `= answer` lines (instead of checkboxes) → short answer question.
  Each line is an accepted answer, all worth full credit.
- One or more `=# value` or `=# value:tolerance` lines → numerical question.
  Tolerance defaults to `0` (exact match) when omitted.
- Don't mix `- [ ]` checkboxes and `=`/`=#` lines in the same block — whichever
  the parser sees first (numerical, then short answer, then checkboxes) wins.

## Installation

1. Copy this directory to `question/format/markdown` inside your Moodle root.
2. Log in as admin and visit *Site administration → Notifications* to install.

Or install from a zip via *Site administration → Plugins → Install plugins*.

## Usage

1. Go to a course → *Question bank* → *Import*.
2. Choose **Markdown format**.
3. Upload your `.md` file and import.

## Re-importing (editing questions)

Re-importing the same file updates existing questions instead of
duplicating them: a question is matched to an existing one in the target
category by its **name** (the heading text). A match creates a new
*version* of that question — visible in the question bank's version
history — rather than a separate entry. A heading with no match in the
category is imported as a new question, and existing questions are left
untouched.

Known limitation: if two questions in the same category share the exact
same heading text, the match is ambiguous — only rename questions you
want to keep distinct.

## Supported versions

Tested against Moodle 4.4, 4.5 and 5.0 on PHP 8.2/8.3 with PostgreSQL
and MariaDB (see the CI matrix).

## Roadmap

- Per-question feedback via blockquotes.
- YAML front matter for categories and default marks.
- Export from the question bank back to Markdown.

## License

GNU GPL v3 or later. See the LICENSE file.
