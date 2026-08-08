# Markdown question format for Moodle (qformat_markdown)

![Moodle Plugin CI](https://github.com/JoseCornejoLupa/moodle-qformat_markdown/actions/workflows/moodle-ci.yml/badge.svg)

Import quiz questions into the Moodle question bank from a simple,
human-friendly Markdown checklist syntax. Write your questions in any
text editor, keep them under version control, and import them in one click.

## Question syntax

Each question is a level-2 heading (`## `) followed by checklist options.
Mark correct answers with `[x]`:

```markdown
---
category: Quiz de PHP
defaultmark: 2
---

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
> Rounded to two decimal places.

## What is the capital of Italy?
@ 3
- [ ] Milan
- [x] Rome
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
- Any `> feedback text` line, regardless of question type, becomes general
  feedback shown after the question is answered. Several `> ` lines in the
  same block are joined into one feedback with line breaks between them.
- An `@ value` line sets that question's own mark, overriding the
  front matter's `defaultmark` (if any) for that question only.

## Front matter

An optional `---` / `---` block at the very start of the file sets
defaults for every question in it:

- `category`: created (or reused, if it already exists) as a subcategory
  of the category selected in the import screen. Use `/` to nest, e.g.
  `Quiz de PHP/Preguntas basicas`.
- `defaultmark`: applied to every question in the file, instead of
  Moodle's own default of `1`.

Both keys are optional, and the block itself is optional — a file with no
front matter behaves exactly as before.

## Installation

1. Copy this directory to `question/format/markdown` inside your Moodle root.
2. Log in as admin and visit *Site administration → Notifications* to install.

Or install from a zip via *Site administration → Plugins → Install plugins*.

## Usage

1. Go to a course → *Question bank* → *Import*.
2. Choose **Markdown format**.
3. Upload your `.md` file and import.

## Export

Go to *Question bank* → *Export*, choose **Markdown format** and the
category to export, and download the `.md` file. The file uses the same
syntax as import, including a `category` front matter line and, for any
question worth something other than `1`, an `@ value` line.

Only `multichoice`, `truefalse`, `shortanswer` and `numerical` questions
are exported — other types (essay, matching, cloze, description, ...)
can't be represented in this syntax and are left out of the file.

If the export recurses into subcategories, every question is still written
to the same `category` front matter line (the category you exported from):
re-importing the file puts everything back into that one category, so
subcategory structure isn't preserved round-trip.

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

## License

GNU GPL v3 or later. See the LICENSE file.
