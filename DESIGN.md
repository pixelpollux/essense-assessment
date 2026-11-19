---
name: Office Game Library
description: A daily, deliberately scarce vote on the office game library; where you stand today sits beside the ranking.
colors:
  burgundy: "#420217"
  lime: "#d0ff15"
  lime-ink: "#536608"
  burgundy-ink: "#cab8be"
  vote-tint-light: "#faffe8"
  vote-tint-dark: "#1e220b"
  white: "#ffffff"
  ground-light: "hsl(0, 0%, 100%)"
  ink-light: "hsl(0, 0%, 3.9%)"
  muted-light: "hsl(0, 0%, 96.1%)"
  muted-ink-light: "hsl(0, 0%, 45.1%)"
  border-light: "hsl(0, 0%, 92.8%)"
  input-light: "hsl(0, 0%, 89.8%)"
  ground-dark: "hsl(0, 0%, 3.9%)"
  ink-dark: "hsl(0, 0%, 98%)"
  muted-dark: "hsl(0, 0%, 6.9%)"
  muted-ink-dark: "hsl(0, 0%, 63.9%)"
  border-dark: "hsl(0, 0%, 14.9%)"
  destructive-light: "#b91c1c"
  destructive-dark: "#f87171"
  destructive-dark-ink: "#450a0a"
typography:
  headline:
    fontFamily: "Instrument Sans, ui-sans-serif, system-ui, sans-serif"
    fontSize: "1.5rem"
    fontWeight: 600
    lineHeight: "2rem"
    letterSpacing: "-0.025em"
  title:
    fontFamily: "Instrument Sans, ui-sans-serif, system-ui, sans-serif"
    fontSize: "1rem"
    fontWeight: 600
    lineHeight: "1.5rem"
  rank:
    fontFamily: "Instrument Sans, ui-sans-serif, system-ui, sans-serif"
    fontSize: "1.125rem"
    fontWeight: 600
    lineHeight: "1.75rem"
    fontFeature: "tnum"
  body:
    fontFamily: "Instrument Sans, ui-sans-serif, system-ui, sans-serif"
    fontSize: "1rem"
    fontWeight: 500
    lineHeight: "1.5rem"
  body-sm:
    fontFamily: "Instrument Sans, ui-sans-serif, system-ui, sans-serif"
    fontSize: "0.875rem"
    fontWeight: 400
    lineHeight: "1.25rem"
  label:
    fontFamily: "Instrument Sans, ui-sans-serif, system-ui, sans-serif"
    fontSize: "0.75rem"
    fontWeight: 600
    lineHeight: "1rem"
rounded:
  sm: "4px"
  md: "6px"
  lg: "8px"
  xl: "12px"
  sheet: "16px"
  full: "9999px"
spacing:
  row-gap: "4px"
  control-gap: "8px"
  gutter: "16px"
  gutter-sm: "24px"
  panel: "20px"
  zone-stack: "32px"
  zone-gap: "48px"
components:
  button-primary:
    backgroundColor: "{colors.burgundy}"
    textColor: "{colors.white}"
    rounded: "{rounded.md}"
    padding: "8px 16px"
    height: "44px"
  button-primary-dark:
    backgroundColor: "{colors.lime}"
    textColor: "{colors.burgundy}"
    rounded: "{rounded.md}"
    padding: "8px 16px"
    height: "44px"
  button-vote:
    backgroundColor: "transparent"
    textColor: "{colors.ink-light}"
    rounded: "{rounded.md}"
    padding: "8px 16px"
    height: "40px"
    width: "80px"
  button-vote-hover:
    backgroundColor: "{colors.burgundy}"
    textColor: "{colors.white}"
  button-destructive:
    backgroundColor: "{colors.destructive-light}"
    textColor: "{colors.white}"
    rounded: "{rounded.md}"
    padding: "8px 16px"
    height: "40px"
  button-destructive-dark:
    backgroundColor: "{colors.destructive-dark}"
    textColor: "{colors.destructive-dark-ink}"
  input-field:
    backgroundColor: "{colors.ground-light}"
    textColor: "{colors.ink-light}"
    rounded: "{rounded.md}"
    padding: "8px 12px"
    height: "44px"
  today-panel-available:
    backgroundColor: "{colors.lime}"
    textColor: "{colors.burgundy}"
    rounded: "{rounded.xl}"
    padding: "{spacing.panel}"
  today-panel-available-dark:
    backgroundColor: "{colors.burgundy}"
    textColor: "{colors.lime}"
  today-panel-used:
    backgroundColor: "{colors.muted-light}"
    textColor: "{colors.ink-light}"
    rounded: "{rounded.xl}"
    padding: "{spacing.panel}"
  game-row:
    backgroundColor: "transparent"
    rounded: "{rounded.lg}"
    padding: "12px"
  game-row-mine:
    backgroundColor: "{colors.vote-tint-light}"
    textColor: "{colors.burgundy}"
    rounded: "{rounded.lg}"
    padding: "12px"
  game-row-mine-dark:
    backgroundColor: "{colors.vote-tint-dark}"
    textColor: "{colors.lime}"
  badge-your-vote:
    backgroundColor: "{colors.lime}"
    textColor: "{colors.burgundy}"
    typography: "{typography.label}"
    rounded: "{rounded.full}"
    padding: "2px 10px"
  badge-your-vote-dark:
    backgroundColor: "{colors.burgundy}"
    textColor: "{colors.lime}"
---

# Design System: Office Game Library

## Overview

**Creative North Star: "The Daily Ballot Box"**

One considered action a day, and the page never lets you lose track of it. The system is shadcn/ui on a strictly neutral ground (pure white, near-black), with two pinned brand colors doing all the talking: a deep burgundy and an electric lime. They are not decoration. Lime as a fill means "you can act today"; a tinted, ringed row means "this one is yours". When the day's action is spent, the color drains out of the status panel and it goes grey, which is the whole state change made visible.

The density is a working list, not a showcase: rows are compact (12px padding, 4px apart), numbers are tabular, counts align in a fixed right-hand column. The status sits beside the ranking on desktop (a sticky 21rem column) and as a solid sticky strip above it on phones. Everything is flat at rest; the only shadows belong to things that genuinely float over content.

The two brand colors swap roles by theme, because each is about 1.2:1 against a background of similar lightness. In light, burgundy leads (primary fills, focus ring, your-vote edge) and lime is a fill carrying burgundy text. In dark, lime leads and burgundy becomes the fill carrying lime text.

**Key Characteristics:**
- Neutral achromatic ground; color is reserved for state (available, mine, destructive).
- Burgundy and lime swap leading roles between light and dark themes.
- Flat surfaces; tonal fills and 1px rings instead of shadows.
- Instrument Sans throughout, three weights (400, 500, 600), tabular numerals for every count and rank.
- Actions disappear rather than disable once today's action is used.
- WCAG 2.2 AA is a hard floor; contrast ratios live as comments beside the tokens in `resources/css/app.css`.

## Colors

A neutral grey ground carrying two loud, user-pinned brand colors that trade places between themes.

### Primary
- **Ballot Burgundy** (`burgundy`): Light theme's leading color: primary button fill (white text, 16.8:1), focus ring, the 1px edge of your-vote rows, input caret, and the text on any lime fill (14.4:1). In dark theme it becomes the fill: Today panel while an action is available, the "Your vote" badge, text selection, all carrying lime text.

### Secondary
- **Signal Lime** (`lime`): Light theme's fill: the Today panel while an action is available, the "Your vote" badge, text selection, the avatar initials disc, always with burgundy text. In dark theme it leads: primary button fill, focus ring, your-vote row edge, caret.
- **Lime Ink** (`lime-ink`): The lime shade for when lime would need to be text or an icon on a light background (6.4:1 on white). Used on the success icon of a flash message.
- **Burgundy Ink** (`burgundy-ink`): The dark-theme counterpart: a burgundy tint for text or icons on the dark ground (10.5:1).
- **Vote Tint** (`vote-tint-light` / `vote-tint-dark`): The faint fill of your-vote rows. Light: a whisper of lime under burgundy text (16.4:1). Dark: a deep olive under lime text (14.0:1).

### Neutral
- **Paper / Night** (`ground-light`, `ground-dark`): Page and card background.
- **Ink** (`ink-light`, `ink-dark`): Body text and game names.
- **Muted Fill** (`muted-light`, `muted-dark`): The Today panel once the action is used (with a 1px border so it holds its edge in dark).
- **Muted Ink** (`muted-ink-light`, `muted-ink-dark`): Secondary copy, counts in headers, ranks below first place, placeholders.
- **Hairline** (`border-light`, `border-dark`; inputs use `input-light`): All borders and dividers, 1px.
- **Destructive** (`destructive-light` with white text; `destructive-dark` with `destructive-dark-ink` text): Remove game confirm button, the Remove game menu item, error flash text and icons. Darkened from the shadcn default for AA (6.5:1 light, 7.2:1 dark as text).

### Named Rules
**The Same-Lightness Rule.** Neither brand color is ever text or a line on a background of similar lightness: no lime on light, no burgundy on dark. When a lime- or burgundy-colored glyph is needed there, use `lime-ink` or `burgundy-ink`.

**The Role Swap Rule.** Write against the semantic roles (`primary`, `secondary`, `brand-line`, `brand-tint`), never the raw hex. The theme decides which brand color plays which role.

**The Lime Means Go Rule.** A lime (light) or burgundy (dark) fill on the Today panel means an action is available. When it is used, the panel goes to the muted neutral. Don't fill other large surfaces with the secondary color.

## Typography

**Display Font:** none (no display tier)
**Body Font:** Instrument Sans (with ui-sans-serif, system-ui, sans-serif), loaded from fonts.bunny.net at 400, 500, 600
**Label/Mono Font:** Instrument Sans; numerals switch to tabular figures

**Character:** One grotesque in three weights; hierarchy comes from size and weight steps, not from a second family. Plain and legible, so the brand colors carry the personality.

### Hierarchy
- **Headline** (600, 1.5rem, tight -0.025em): Zone headings: "Ranking" and the Today panel headline on desktop. Balanced wrapping.
- **Title** (600, 1rem): Sub-headings ("Suggest a game"), the compact Today headline on phones, the wordmark in the header.
- **Rank** (600, 1.125rem, tabular): The rank numeral at the start of every row; first place in full ink, the rest muted.
- **Body** (500, 1rem): Game names (break long words, never truncate) and empty-state messages.
- **Body small** (400 or 500, 0.875rem): Status detail, helper copy, vote counts, flash messages, character counter.
- **Label** (600, 0.75rem): Badges and the compact reset countdown.

### Named Rules
**The Tabular Rule.** Every rank, vote count, game count and character counter uses tabular numerals so columns of numbers align.

**The Weight Ceiling Rule.** Weights stop at 600. Emphasis is 500 (game names, the near-limit counter), structure is 600.

## Layout

A centered container (max 72rem) with a 16px gutter, 24px from 640px up. Under 1024px the page is a single column; from 1024px it becomes two zones: a 21rem sticky status column (top 24px) and the ranking (`minmax(0, 1fr)`), 48px apart. Inside the status column, the Today panel and the suggest form stack 32px apart.

On phones the Today panel collapses to a compact strip (status left, a Suggest button right) inside a solid, full-bleed sticky band at the top of the viewport; the suggest form moves into a bottom sheet. Because that band overlays content, `html` gets `scroll-padding-top: 7.5rem` below 1024px so focused and scrolled-to elements clear it (WCAG 2.4.11). Flash messages render inside the sticky region on both layouts, so they are visible wherever the user has scrolled and the ranking never shifts.

Each ranking row is a grid: rank | name | action | count | menu. From 640px the columns are 2.5rem, flexible, auto, 6rem, 2.5rem with 12px gaps, so the count and menu sit at a fixed right edge and counts align whichever button a row shows. Below 640px the count moves under the name and the grid drops to four columns (2.25rem rank, 8px gaps). Rows sit 4px apart.

Interactive targets: 40px for row controls and icon buttons, 44px for the suggest input, filter and primary submit. The filter field appears only once the list reaches 9 games.

## Elevation & Depth

Flat by default. Depth comes from tonal fills (lime or muted panel, vote tint) and 1px rings, not shadow. A soft small shadow appears only on elements that float over scrolling content in the mobile sticky band and on flash messages; shadcn's own overlays (dialog, sheet, dropdown) keep their stock larger shadows.

### Shadow Vocabulary
- **Sticky lift** (`box-shadow: 0 1px 3px 0 rgb(0 0 0 / 0.1), 0 1px 2px -1px rgb(0 0 0 / 0.1)`): The compact Today strip on phones and flash messages.
- **Overlay** (shadcn stock `shadow-md` / `shadow-lg`): Dropdown menus, the remove dialog, the bottom sheet.

### Named Rules
**The Floats-Only Rule.** A shadow means "this sits above scrolling content". Rows, panels in the flow and empty states never get one.

## Shapes

Gently rounded, from a 0.5rem base radius: 6px on buttons and inputs, 8px on list rows and flash messages, 12px on the Today panel and empty/error containers, 16px on the top corners of the mobile bottom sheet, fully round for badges and the avatar. Borders are 1px hairlines in the neutral border color; empty states use a dashed hairline. Your-vote rows use an inset 1px ring in the brand line color rather than a border, so they don't shift the grid.

## Components

### Buttons
Restrained shadcn buttons; filled brand color is rare and therefore meaningful.
- **Shape:** gently rounded (6px), 14px medium label, icon 16px with 8px gap.
- **Primary:** filled with the theme's primary (burgundy light, lime dark). Reserved for the suggest submit (full width, 44px) and the mobile Suggest button (40px). Hover drops to 90% opacity.
- **Vote:** outline on a transparent background, 40px tall, at least 80px wide. On hover it fills with the primary color. "Remove vote" is the same outline without the fill-on-hover.
- **Ghost icon:** 40px square (36px for flash dismiss) for the row menu, header controls and dismiss.
- **Destructive:** filled destructive, used only for the final "Remove game" confirmation, beside an outline "Keep game".
- **Focus:** 2px ring in the theme's ring color (burgundy light, lime dark) with a 2px offset.
- **Pending:** a spinning loader icon precedes the label, the button disables and sets `aria-busy`.

### Badges
- **"Your vote":** secondary fill (lime with burgundy text in light, burgundy with lime text in dark), fully round, 12px semibold, preceded by a 12px check icon. It is the non-color signal that a row is yours.

### Cards / Containers
- **Today panel:** 12px corners, 20px padding (16px by 12px compact). Secondary fill while an action is available, muted fill with a 1px border once used. Headline and detail are one polite live region; the reset countdown sits outside it so the minute tick is never announced. Secondary lines inside it are set at reduced opacity of the panel's ink.
- **Empty / error states:** 12px corners, 1px border (dashed for empty), centered, generous padding (40px or 32px vertical).

### Inputs / Fields
- **Style:** 1px input-color border, ground background, 6px corners, 44px tall in the product surface. The filter carries a 16px search icon inset 12px left.
- **Focus:** the same 2px ring with 2px offset as buttons; the caret is the brand line color.
- **Error:** message below the field, linked with `aria-describedby`; the character counter (`n/100`) turns full ink and medium weight within 10 characters of the limit.

### Navigation
- **Header:** 56px bar with a bottom hairline; wordmark "Office Game Library" as a 16px semibold link; on the right, the theme toggle and a 32px avatar (lime initials disc) inside a 40px ghost button.

### Ranking Row (signature)
The core of the product. A grid row (see Layout) with rank, name, action, count, menu. Your-vote rows take the vote tint, brand-tint text and an inset 1px brand-line ring, plus the "Your vote" badge, so ownership never relies on color alone. When the action is used, rows with no vote of yours show no action button at all. The row menu (ellipsis) holds "Remove game…" in destructive text, which opens a confirmation dialog stating the vote count at stake.

**Motion:** when the order changes, moved rows slide from their old position (FLIP, 420ms, `cubic-bezier(0.16, 1, 0.3, 1)`), skipped under reduced motion. After a vote change or "jump to my vote", focus lands on the row's new button and a static 2px ring-colored outline (2px offset) shows for 1.6s as an arrival cue; if that button no longer exists, focus goes to the Today heading.

### Flash Message
A 8px-rounded, 1px-bordered strip on the ground color with a small shadow, 14px medium text, a 16px status icon (destructive for errors; lime-ink / burgundy-ink check for success) and a 36px dismiss button. Errors use a 40%-opacity destructive border and destructive text. Both live regions (alert and status) are always mounted; messages never auto-clear; dismissing moves focus to the Today heading.

## Do's and Don'ts

### Do:
- **Do** put the brand colors through the semantic roles (`primary`, `secondary`, `brand-tint`, `brand-line`, `secondary-ink`) so they swap correctly between themes.
- **Do** keep vote buttons outline (40px, min 80px wide) and reserve filled primary for the Today panel's submit and the mobile Suggest button.
- **Do** hide actions that today's limit makes unavailable, and replace a used-up form with one sentence saying when it opens again.
- **Do** mark ownership with tint, an inset 1px brand-line ring and a "Your vote" badge together.
- **Do** use tabular numerals and a fixed right-hand count column so counts align across rows.
- **Do** use competition ranking (1, 2, 2, 4) for tied vote counts.
- **Do** keep status and action messages inside the sticky region, dismissible, never auto-clearing.
- **Do** honor `prefers-reduced-motion` for any movement; static cues (the 1.6s arrival outline) need no variant.
- **Do** record the contrast ratio beside any new color token, and hold every pairing to WCAG 2.2 AA.

### Don't:
- **Don't** use lime as text or a line on a light background, or burgundy on a dark one (about 1.2:1).
- **Don't** show rows of disabled Vote buttons once today's action is used.
- **Don't** stack the status and suggest form above the list as the page's first block on desktop; status sits beside the ranking.
- **Don't** add shadows to rows or in-flow panels; shadow is for elements floating over content.
- **Don't** use the shadcn default destructive red; use the darkened destructive tokens.
- **Don't** add a second typeface or weights above 600.
- **Don't** announce the countdown; only the status headline and detail are live.
