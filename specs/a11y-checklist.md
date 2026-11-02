# Accessibility checklist (M7-02, M7-03)

Flow: start → test → submit → results → review → leaderboard. ✅ checked, 🔧 fixed (regression test), ⏳ manual run still to do.

## Keyboard and screen reader (M7-02)

Markup audit by Claude on 2026-11-01. NVDA itself can't be run from here: the ⏳ rows are for your own pass.

| Area | Finding | Status |
|---|---|---|
| Layout | Skip link to `#main`, `lang` set, one `<nav aria-label="Main">`, `aria-current="page"` on the active link | ✅ |
| Theme tokens | AA contrast in both themes (`tests/Unit/ContrastTest.php`); input border was 1.4:1 | 🔧 M7-01 |
| Forms (start, sign-in, profile, configure) | Every input has a `<label for>`; errors use `role="alert"`, `aria-invalid` and `aria-describedby`; radio/checkbox groups are `<fieldset>` + `<legend>` | ✅ |
| Runner: choices | Native radio group with a hidden legend; 1–5 / A–E pick and focus the choice | ✅ |
| Runner: moving items | Focus goes to the stem, but its item number was not read out. Stem is now `role="group"` named "Item N of M" | 🔧 `RunnerUiTest` |
| Runner: flag button | Label flipped to "Unflag" while also `aria-pressed`, so NVDA read "Unflag, pressed". Label is now fixed, state is `aria-pressed` only | 🔧 `RunnerUiTest` |
| Runner: timer | `role="timer"` (not live), 10 and 1 minute warnings through a polite live region | ✅ |
| Runner: save state | `role="status"`; offline banner is `role="alert"` | ✅ |
| Runner: navigator | `N` focuses it; buttons say "Item N, answered/unanswered, flagged"; current item `aria-current="step"`; filters use `aria-pressed` | ✅ |
| Runner: arrows | ←/→ move items even on a focused radio (spec FR-T2); ↑/↓ still move between choices | ✅ by design |
| Submit dialog | Native `<dialog>` with `showModal`, labelled, focus starts on Cancel, Escape cancels on the server | ✅ |
| Results | Score section labelled; tables have `<th scope>` in a focusable labelled scroll region. "By subtest/topic" was a region inside a region with the same name | 🔧 `ResultsBreakdownTest` |
| Review | Filter in the URL, passage in `<details>` | ✅ |
| Leaderboard | Labelled selects, table caption, `<th scope>` | ✅ |
| Leaderboard | Changing board/period updates the table without an announcement | ⏳ judge in NVDA; idea parked in SUGGESTIONS.md |
| Full mock, keyboard only | One complete run without a mouse | ⏳ |
| Full mock, NVDA on | One complete run with NVDA + Firefox or Chrome | ⏳ |

## Mobile and 320 px (M7-03)

Markup audit by Claude on 2026-11-01 (no device run from here: ⏳ rows are yours, at 320, 375 and 768 px in DevTools or on your phone).

| Area | Finding | Status |
|---|---|---|
| All pages | Viewport meta set; `main` has a 16 px gutter; header and nav `flex-wrap` | ✅ |
| Tables (results, progress, leaderboard, mock plan, Markdown tables) | Scroll inside their own labelled region, not the page | ✅ |
| Images | `max-w-full h-auto` with width/height set | ✅ |
| Passages | `max-w-[70ch]`; runner goes two-column only at `lg` | ✅ |
| Long unbroken text (number series, URLs) in stems, choices, review | Could widen the page: the choice `label` is a flex child without `min-w-0`. Now `min-w-0 break-words`; Markdown and review choices `break-words` | 🔧 `RunnerUiTest` |
| Touch targets | Buttons and selects ≥ 28 px tall; navigator items 36 px; text links (header, "Keyboard shortcuts", table links) are under 24 px tall but spaced ≥ 16 px apart (WCAG 2.5.8 spacing exception) | ✅ |
| Dialogs | `w-full max-w-md`, UA max-width keeps a margin at 320 px | ✅ |
| Every page at 320 / 375 / 768 px | No horizontal page scroll | ⏳ |
