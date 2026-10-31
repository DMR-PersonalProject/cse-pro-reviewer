# Content: how source material becomes questions

Nothing in this folder goes straight into the question bank. Every file goes through staged JSON
(or CSV), validation and an admin review, and imported items never arrive as `published`.

```
content/
  inbox/            your raw files (gitignored, never committed)
  staged/           extracted JSON ready to import (gitignored)
    media/          images referenced by staged JSON (gitignored)
  schema/           the import format: schema, example.json, example.csv (committed)
```

## 1. Putting files in the inbox

Name files `inbox/<subtest>/<topic>/<source-slug>_p<page>.<ext>`, for example
`inbox/numerical/number_sequences/reviewer-x-2024_p12.pdf`. If you don't know the topic, use
`inbox/<subtest>/unsorted/`.

- Subtests: `verbal`, `numerical`, `analytical`, `general_info`.
- Topics: the codes in `database/seeders/TaxonomySeeder.php` (for example `word_meaning`,
  `reading_comprehension`, `number_sequences`, `word_analogy`, `ra_6713`).
- `source-slug` names the book, reviewer or site in lowercase with hyphens, plus a year if there is one.
- Send the **answer key pages and explanation pages** too, even if they are at the back of the book.
  Without them the items import as `needs_review` with "No answer key".
- One topic per file is easiest. Multi-page PDFs are fine; keep the page numbers in the name or the file.

## 2. What Claude does with each kind of file

| File | What happens |
|---|---|
| PDF with text | Reads the text, splits it into items, matches the answer key pages, writes staged JSON |
| PDF scan, photo (JPG/PNG) | Reads it visually (OCR), same as above; unclear characters lower `extraction.confidence` |
| Word / text / Markdown | Reads and structures it the same way |
| Charts, tables, figures | Crops them into `staged/media/` (PNG) with alt text, and references them from the item or passage |
| Answer key only | Used to fill `answer` for items already extracted from the same source; never on its own |

For every item Claude records `provenance` (file, page) and `extraction` (`confidence` 0–1,
`needs_review`, notes on anything unclear).

## 3. The never-guess rule

- If the source gives no answer, `answer` is `null`. Claude never fills in a key from its own
  knowledge, and neither does the app (FR-M5). The item imports as `needs_review` with "No answer key".
- If the source gives no explanation, `explanation` is `null` and the item is flagged.
- If a character, number or choice can't be read with certainty, the item keeps what was read,
  `extraction.confidence` goes below 0.8 and the doubt goes into `extraction.notes`.

## 4. Importing

- CLI: `php artisan cse:import content/staged/<batch>.json --dry-run` to check, then without
  `--dry-run` to create a pending batch. `--approve-valid` also approves every row without errors.
  Media paths are read from `content/staged/`.
- Admin: **Import** in `/admin` accepts JSON or CSV (no media files; rows with images get the flag
  "attach in editor").
- Rows with errors can't be approved. Approved rows become `draft`, or `needs_review` with the reasons
  stored, under the file's source. With no source given, a **private** source is used.
- Publishing happens later, item by item, in the question editor.

## 5. Format

JSON (`schema/question-import.schema.json`, version 1) supports passages and media. See
`schema/example.json`. Rules that the schema can't express:

- `answer` is a letter (`A`–`E`) matching the order of `choices`, or `null`.
- `passage_ref` must match a passage `ref` in the same file; refs must be unique.
- `media[].path` is relative to `content/staged/`: PNG, JPG or WebP by real file type, at most 2 MB.
  SVG is refused. Every image needs `alt` text.
- Subtest and topic codes must exist in the database.

CSV (`schema/example.csv`) is for single items without passages or media. Header, in this order:

```
subtest,topic,language,stem,choice_a,choice_b,choice_c,choice_d,choice_e,answer,explanation,difficulty,tags,source_file,source_page,notes,confidence
```

Leave unused choice columns empty. `tags` are separated by `|`. An empty `answer` or `explanation`
is flagged like `null` in JSON.

## 6. Privacy and copyright

- Raw files and staged output stay on your machine: `inbox/` and `staged/` are gitignored. Never
  commit them or any uploaded media.
- Material from commercial reviewers is usually copyrighted. Imports land in a **private** source,
  whose items don't count on the leaderboard unless an admin turns that on. Only mark a source public if you wrote the
  items or have the right to share them.
- Don't put personal data (names, IDs, contact details) in staged files.
