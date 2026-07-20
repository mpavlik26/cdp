# Handoff: Rozpis služeb (dispečerský směnář + střídání)

## Overview
Redesign of an internal dispatcher shift-roster tool (původně `cdp.simiways.cz`). It lets a dispatcher see their own shifts, the whole team's roster, understand every handover (kdo koho střídá), and print a data-thrifty version. Language: **Czech**. Audience skews older (~53), so the UI is deliberately **large, high-contrast, plain, with big touch targets** — keep that constraint in the port.

Three shift posts per day:
- **Dlouhá 514** (long, day) — post code 514
- **Krátká 524** (short, day) — post code 524
- **Noční** (night) — occupies **post 524** overnight (evening of day *X* → morning of *X+1*)

## About the Design Files
The files in this bundle are **design references created in HTML** (a small custom "DC" component runtime used only for prototyping). They are **not production code to copy**. The task is to **recreate these designs inside the existing PHP application**, using its established rendering approach (PHP templates / Twig / Blade, plus whatever JS layer exists) and its own markup/CSS conventions. Treat the HTML/JS here as the source of truth for **look, layout, data rules, and behavior** — re-implement, don't paste.

The prototype data is **synthetic** (deterministic generator + a few hand-set demo days). In the real system the roster comes from the DB; see **Data model** below for the exact shape the views need.

## Fidelity
**High-fidelity.** Final colors, typography, spacing, and interactions are all specified. Recreate pixel-closely, but map to the codebase's existing CSS/design system where one exists. Fonts/spacing can bend to house style; the **data rules and handover semantics must be exact**.

## Views / Screens

The app has **two in-app views** (switched by tabs in a top bar) plus a **separate print page** with two modes.

### 1. Můj směnář — kalendář (per-worker calendar)
- **Purpose:** a worker picks their name and sees their own shifts in a weekly calendar; clicking a shift opens the handover detail below.
- **Layout:**
  - Dark header bar (`#1B2530`): worker `<select>` on the left, title block (`MŮJ SMĚNÁŘ · KALENDÁŘ` + `{person} · červenec – srpen 2026`) in the middle, **🖨 Tiskové zobrazení** link on the right.
  - Calendar grid: CSS grid `grid-template-columns: 84px repeat(7, 1fr)`, `gap: 6px`. First column = week label (e.g. `13. – 19.` + month short). Columns = **Po–Ne** (Mon-first). Rows = weeks.
  - The weekday header row is `position: sticky; top: 0`; the week-label column is `position: sticky; left: 0` (keep context on scroll).
  - Below the grid: **legend**, then the **handover detail** panel (see Interactions).
- **Cell (each day):** `min-height: 88px`, `border-radius: 8px`, `border: 1px solid #E9EDF1`. Day number top-left (`#6B7684`). First-of-month gets a yellow month chip top-right (`background:#FFE08A`). Today gets `outline: 2px solid #2563EB`. Weekend empty cells tint `#FFF7EC`. Empty working day shows "volno" (`#CDD5DE`).
  - **Day shift (514/524):** colored left bar (514 `#E0224B`, 524 `#8A96A2`), tinted bg (514 `#FBE1E5`, 524 `#ECEFF2`), two centered lines: **start** then **end**, each colored by deviation (see Design tokens).
  - **Night shift:** grey (`#ECEFF2` bg, `#8A96A2` bar, same as 524 — night lives on post 524). It is **split across two cells**: the **evening start** fills the **bottom half** of day *X* (`19:00 →`); the **morning end** fills the **top half** of day *X+1* (`→ 7:30`). Two consecutive nights → a cell shows morning-end (top) of the previous night AND evening-start (bottom) of the new one.
- **Selected shift:** dark border `2px solid #1B2530` + a `DETAIL ↓` pill (top for night, bottom otherwise). Clicking again deselects.

### 2. Kompletní směnář — matice (complete roster matrix)
- **Purpose:** everyone × every day at a glance.
- **Layout:** CSS grid `grid-template-columns: 150px repeat(62, 66px)` (62 = July+August). Sticky **month row** (`top:0`), sticky **day-of-week+date header row** (`top:41px`), sticky **worker column** (`left:0`). Scroll container `max-height: 70vh; overflow:auto`.
  - **Sticky gap fix (important):** grid `gap` leaves transparent lanes that let cells peek under sticky edges while scrolling. Sticky header cells use `margin-bottom:-4px`, the sticky worker column uses `margin-right:-4px`, and the corner cells use both, to overlap the 4px gap. Reproduce this (or use a gapless border approach).
  - Month boundary drawn with a dark seam `border-left: 3px solid #1B2530` on the first column of each month.
- **Cell (60px tall):** same shift rendering as the calendar (day = two colored lines; night = split top/bottom halves), left accent bar per post.
- **Worker names** in the sticky first column are **clickable links** (`#1E4FBF`, underline) → open **Můj směnář** for that person.

### 3. Print page (tiskové zobrazení) — two modes
Reached from the per-view 🖨 button; **no switchers on the page itself** — mode comes from the URL:
- `?mode=worker&person=<name>` → single-worker table: **Datum · Služba · Čas · Poznámky ke střídání**.
- `?mode=complete` → classic per-day table for both months: **Datum · Dlouhá 514 · Krátká 524 · Noční**.
- Black-on-white, compact 12px, weekend rows `#F4F6F8`, today `#FFF7CC`. Times colored green/red like the app. Controls (`Vytisknout`, `← Zpět`) are `.no-print`. Includes `@page { size:A4 portrait; margin:12mm }` and `@media print` to hide chrome. `Vytisknout` calls `window.print()`.

## Interactions & Behavior
- **Tabs** switch view (`calendar` / `matrix`), no reload.
- **Worker select** (calendar) changes person; **resets the selected shift** (no shift auto-selected on open or on change → no detail shown until a click).
- **Click a shift cell** → toggles selection; the **Detail střídání** panel renders for it. Click again → deselect, panel clears. Both halves of a night (evening + next-morning) select together and both are clickable.
- **Click a worker name** in the matrix → switch to calendar view for that person.
- **🖨 button** → navigate to print page with the current context in the query string (`encodeURIComponent` the person name).

### Detail střídání (handover detail) — the core logic
For the selected shift, show three ordered steps (a "my shift" summary card on the left, the flow on the right):
1. **↓ PŘEBÍRÁŠ** (take over) — when + time, and the person(s) you take over from, each with name, role, and their own green/red start–end times.
2. **↔ SPOLU VE SLUŽBĚ** (alongside) — only for **day** shifts: the other day-post colleague.
3. **↑ PŘEDÁVÁŠ** (hand off) — when + time, and the person(s) you hand off to.

Who appears where:
- **Day shift (514 or 524):** take over from **previous night** (morning); alongside = the **other day post**; hand off to **tonight's night**.
- **Night shift:** take over from **today's two day posts** (evening); hand off to **next day's two day posts** (morning).

## Handover / seat-change markers (the important domain rules)
All of the following concern the **524 post** (day-524 person, and the night which sits on 524). Times are compared in minutes. Standard clean shift = start `07:00`, end `19:00`; night `19:00`→`07:00`. **Only deviations produce markers** — a clean handover shows nothing.

Let `k` = the 524-day shift on day *X*, `d` = the 514 shift, `n` = the night starting day *X*, `prevNightEnd` = previous day's night end, `nextK.start` = next day's 524-day start.

**A. Přesednutí na sousední post 514 (seat change) — red `514` marker:**
- **Start on 514** (`514` before start time): `k.start < d.start` — the 524 person arrives before the 514 colleague, so sits at 514 until they arrive (night still occupies 524). Detail text: *"Začni na sousedním postu 514. Jakmile v {d.start} dorazí {d.person} (514), přesedni na svůj post 524."*
- **End on 514** (`514` after end time): `k.end > n.start && k.end > d.end` — the 524 person works past the night's arrival (night takes 524) and past the 514 person's end, so moves to 514 for the tail. Detail: *"Až ve {n.start} dorazí {n.person} na noční, přesedni na sousední post 514 a dokonči tam směnu do {k.end}."*

**B. Bez předávky / bez převzetí (no handover / no takeover) — the red ⊘ "no" icon:**
- **No takeover** (icon before start): 524-day `k.start > prevNightEnd` (arrives after the night already left → gap on 524, covered by 514). Night equivalent (evening): `n.start > k.end`. Detail: *"Nastupuješ bez převzetí — předchozí služba na 524 už skončila. Mezičas kryje kolega na 514."*
- **No handover** (icon after end): 524-day `k.end < n.start` (leaves before night arrives → gap covered by 514). Night equivalent (morning): `n.end < nextK.start`. Detail: *"Odcházíš bez předávky — noční služba na 524 začíná až po tvém odchodu. Mezičas kryje kolega na 514."*

**Priority:** for the before-start marker, seat-change (`514`) takes precedence over no-takeover; each of before/after is a single marker.

**Edge cases that MUST be handled (all present in the demo data for Ivona, July):**
- 21.7 start on 514 · 22.7 no-takeover · 23.7 no-handover · 24.7 end on 514
- **25.7** — no-takeover at start **AND** no-handover at end (same shift)
- **26.7** — 514 → 524 → 514 (starts on 514 **and** ends on 514, i.e. both markers on one day shift)
- **28.7** — a **night** that is both no-takeover (evening) and no-handover (morning)

Reference implementation of the marker computation (port this exactly):
```js
// per day i, with prev/next neighbours
if (k.s < d.s)                    k.preKind = '514';   // start on 514
else if (k.s > prevNightEnd)      k.preKind = 'no';    // no takeover
if (k.e > n.s && k.e > d.e)       k.postKind = '514';  // end on 514
else if (k.e < n.s)               k.postKind = 'no';   // no handover
n.preKind  = (n.s > k.e)               ? 'no' : '';    // night evening: no takeover
n.postKind = (n.e < nextDay.k.s)       ? 'no' : '';    // night morning: no handover
// 514 post is never marked
```
The same physical gap surfaces on both parties (e.g. 524 "no handover" evening = that night's "no takeover"), which is correct — show it on both.

### Icons
- **Seat change:** a small pill reading **`514`** (red `#C0223F` text, `#E6A3AE` border) — placed **before** the start time (start on 514) or **after** the end time (end on 514).
- **No handover/takeover:** a **red circle with a diagonal slash** (universal "no" symbol), `#C0223F`, ~11–14px. **Do not use a handshake emoji or the `↮` glyph** — both were rejected as illegible. Built with a bordered round span + an absolutely-positioned rotated 2px line; reproduce with a small inline SVG or a font/icon-set equivalent in the target app.
- In the detail notes, the note card is amber (`bg #FDF0D5`, border `#E7B85C`, text `#8A5A00`) for no-handover and red (`bg #FBE1E5`, border `#E6A3AE`, text `#C0223F`) for seat-change.

## State Management
- `view`: `'calendar' | 'matrix'`
- `person`: selected worker name (default first/relevant worker)
- `selId`: selected shift id (`<globalDayIndex><postKey>`, e.g. `14n`), or null. Reset on person change.
- Print page: `mode` + `person` read from URL query only.

## Data model (what the views need from PHP/DB)
A flat list of days, each with three assignments. Minimum per assignment:
```
day:  { date, weekday, isWeekend, isToday, isFirstOfMonth, monthLabel }
shift (per post d/k/n): {
  person,            // display name
  startMin, endMin,  // minutes from midnight; night end is next-day morning
  startStr, endStr,  // formatted "H:MM"
  startDelta, endDelta   // 'better' | 'worse' | null  (vs. that post's standard time)
}
```
Everything else (calendar bucketing into weeks Mon-first, the split-night rendering, and **all markers**) is **derived** from times + neighbours — compute it in one pass as shown above; don't store markers in the DB. Colors map from `startDelta`/`endDelta`.

## Design Tokens
**Colors**
- Ink text `#1B2530`; secondary `#5B6672`; muted `#8A96A2`.
- App background `#DCE1E7`; panel `#F3F5F8`; card `#FFFFFF`; header bar `#1B2530` / `#12181F`.
- Accent (brand) `#EB0037`; link `#1E4FBF` (hover `#1E4FBF`); today outline/blue `#2563EB`.
- **514 (dlouhá):** accent `#E0224B` / detail `#C0223F`, tint `#FBE1E5`.
- **524 (krátká) & noční:** accent `#8A96A2` / `#4B5563`, tint `#ECEFF2`.
- **Time deviation:** better/shorter = green `#1B7F3B`; worse/longer = red `#C43331`; neutral = ink.
- Weekend tint `#FFF3E6` (grid) / `#FFF7EC` (empty cell); today tint `#EAF2FF`; month chip `#FFE08A`.
- No-handover marker `#C0223F`; note amber `#FDF0D5`/`#E7B85C`/`#8A5A00`.

**Typography:** `Source Sans 3` (weights 400/600/700), system-ui fallback. Chosen for legibility incl. a non-slashed zero. Large sizes throughout: calendar times 16px, matrix times 13px, headings 20–24px, body 14–15px, badges 8–13px. Never go below ~12px in the app.

**Geometry:** radii 6–18px; gaps 4–16px; sticky offsets 41px (matrix header). Left accent bars 3–6px.

## Assets
None external except the **Source Sans 3** web font (Google Fonts). No image assets. Icons are pure CSS/markup (no icon library required); swap for the codebase's icon set if it has one. Emoji 🖨 on the print button — replace with a house print icon if available.

## Files (in this bundle)
- `Rozpis služeb.dc.html` — the main app (both in-app views + all logic). Primary reference.
- `Rozpis-tisk.dc.html` — the print page (both modes).
- `Rozpis smen - smery.dc.html` — earlier exploration with alternative layouts (denní karty, časová osa, měsíční mřížka). Optional reference only.
- `source_pages/` — the original tool's saved HTML (home picker, per-worker, complete roster, handover) for context on the legacy data/URLs.

## Screenshots
See `screenshots/`:
- `01-muj-smenar-kalendar.png` — per-worker calendar view
- `02-kompletni-smenar.png` — complete roster matrix
- `03-detail-stridani.png` — handover detail (selected shift)
- `04-tisk-kompletni.png` — print view, complete roster
- `05-tisk-muj-smenar.png` — print view, single worker

## Suggested implementation order (PHP)
1. Build the day/shift data provider from the DB (times + standard-time deltas).
2. Port the derived-markers pass (verbatim from the rules above) as a pure function — unit-test it against the four edge cases.
3. Render the complete matrix (mostly static markup + the split-night rule).
4. Render the per-worker calendar + the click-driven handover detail.
5. Add the two print modes (query-driven) with print CSS.
