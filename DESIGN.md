---
name: Office Game Library
description: A daily, deliberately scarce vote on the office game library, carried by one physical token you place once a day.
colors:
  claret: "#420217"
  volt: "#d0ff15"
  volt-ink: "#536608"
  claret-ink: "#cab8be"
  paper: "#ffffff"
  ink: "#1d1418"
  mist: "#f6f1f2"
  smoke: "#6e5d63"
  hairline: "#ece4e6"
  edge: "#8f7d83"
  night: "#1a0b11"
  night-surface: "#221018"
  night-hover: "#2e1820"
  night-ink: "#f6eef1"
  night-smoke: "#b8a5ac"
  night-hairline: "#35212a"
  night-edge: "#7a6168"
  destructive: "#b91c1c"
  destructive-night: "#f87171"
  destructive-night-ink: "#450a0a"
typography:
  status:
    fontFamily: "Schibsted Grotesk, ui-sans-serif, system-ui, sans-serif"
    fontSize: "1.75rem"
    fontWeight: 600
    lineHeight: 1.2
    letterSpacing: "-0.025em"
  headline:
    fontFamily: "Schibsted Grotesk, ui-sans-serif, system-ui, sans-serif"
    fontSize: "1.25rem"
    fontWeight: 700
    lineHeight: "1.75rem"
    letterSpacing: "-0.025em"
  rank:
    fontFamily: "Schibsted Grotesk, ui-sans-serif, system-ui, sans-serif"
    fontSize: "1.5rem"
    fontWeight: 600
    lineHeight: "2rem"
    fontFeature: "tnum"
  title:
    fontFamily: "Schibsted Grotesk, ui-sans-serif, system-ui, sans-serif"
    fontSize: "1.0625rem"
    fontWeight: 500
    lineHeight: 1.4
  body:
    fontFamily: "Schibsted Grotesk, ui-sans-serif, system-ui, sans-serif"
    fontSize: "1rem"
    fontWeight: 400
    lineHeight: "1.5rem"
  body-sm:
    fontFamily: "Schibsted Grotesk, ui-sans-serif, system-ui, sans-serif"
    fontSize: "0.875rem"
    fontWeight: 400
    lineHeight: "1.25rem"
  label:
    fontFamily: "Schibsted Grotesk, ui-sans-serif, system-ui, sans-serif"
    fontSize: "0.75rem"
    fontWeight: 400
    lineHeight: "1rem"
    fontFeature: "tnum"
rounded:
  md: "6px"
  lg: "8px"
  full: "9999px"
spacing:
  xs: "8px"
  sm: "12px"
  md: "16px"
  lg: "24px"
  xl: "56px"
components:
  button-primary:
    backgroundColor: "{colors.claret}"
    textColor: "{colors.paper}"
    rounded: "{rounded.md}"
    padding: "8px 20px"
    height: "44px"
  button-primary-night:
    backgroundColor: "{colors.volt}"
    textColor: "{colors.claret}"
    rounded: "{rounded.md}"
    padding: "8px 20px"
    height: "44px"
  button-vote:
    backgroundColor: "transparent"
    textColor: "{colors.ink}"
    rounded: "{rounded.md}"
    padding: "8px 16px"
    height: "40px"
    width: "80px"
  button-vote-hover:
    backgroundColor: "{colors.claret}"
    textColor: "{colors.paper}"
  button-destructive:
    backgroundColor: "{colors.destructive}"
    textColor: "{colors.paper}"
    rounded: "{rounded.md}"
    padding: "8px 16px"
    height: "40px"
  input-field:
    backgroundColor: "{colors.paper}"
    textColor: "{colors.ink}"
    rounded: "{rounded.md}"
    padding: "8px 12px"
    height: "44px"
  action-token:
    backgroundColor: "{colors.volt}"
    textColor: "{colors.claret}"
    typography: "{typography.headline}"
    rounded: "{rounded.full}"
    size: "48px"
  action-token-sm:
    backgroundColor: "{colors.volt}"
    textColor: "{colors.claret}"
    typography: "{typography.label}"
    rounded: "{rounded.full}"
    size: "24px"
  status-bar:
    backgroundColor: "{colors.paper}"
    textColor: "{colors.ink}"
    padding: "10px 16px"
  flash-message:
    backgroundColor: "{colors.paper}"
    textColor: "{colors.ink}"
    typography: "{typography.body-sm}"
    rounded: "{rounded.lg}"
    padding: "4px 4px 4px 16px"
---

# Design System: Office Game Library

## Overview

**Creative North Star: "The Daily Token"**

Today's action is a physical game token: a Volt disc with a claret "1" on it. It is the only bold object on the page. While the action is available it sits beside the status sentence; once used, the status keeps only its dashed empty spot; a vote places it on the row it went to, with the words "Your vote" beside it. When you vote, the token travels from the status to that row. Everything else is quiet: one left-aligned column of type and hairlines, with no cards, no tinted rows, no badges and no dashboard panels.

The page is type-led. Where you stand today is a 28px sentence ("You have today's action.", "Your action today went to "Catan"."), not a filled panel, and the suggest field sits inline under it. The ranking below is a plain hairline-divided list with large tabular rank numerals. The density is that of a working list: one row per game, counts in a fixed right-hand column, actions that disappear rather than disable once today's action is used.

The neutrals carry the brand quietly. Light theme is Paper with claret-tinted greys; dark theme is a claret Night, never neutral black. Claret (#420217) and Volt (#D0FF15) are kept exact and swap leading roles by theme, because each is about 1.2:1 against a ground of similar lightness. Accessibility to WCAG 2.2 AA is a hard floor, and contrast ratios are recorded as comments beside each token in `resources/css/app.css`.

**Key Characteristics:**
- One bold object, the Volt action token, in three states (available, spent, placed).
- A single left-aligned 42rem column on every screen size, header aligned to it.
- Status as a 28px sentence, not a panel; suggest field inline beneath it.
- Hairline-divided ranking rows; no cards, tints or badges.
- Claret-tinted neutrals in both themes; dark ground is claret Night.
- Schibsted Grotesk throughout, tabular numerals for every rank, count and countdown.
- One orchestrated motion (the token flight) plus a rank-reorder slide, both skipped under reduced motion.

## Colors

Two exact brand colors over claret-tinted neutrals; the brand colors swap roles by theme and are reached through semantic roles, never raw hex.

### Primary
- **Claret** (#420217): light theme primary fill (the Suggest button, Vote hover fill), focus ring, `brand-line` (token edge, the underline on the voted game's name in the status, caret), and `brand-ink` (the wordmark and the rank-1 numeral). In dark theme it becomes the secondary fill and the text on Volt.

### Secondary
- **Volt** (#d0ff15): the token fill in both themes (`token`, a constant). In dark theme it is also primary (the Suggest button), focus ring, `brand-line` and `brand-ink`. In light theme it appears only as the token, the selection highlight and the avatar fill, always carrying claret on it.
- **Volt Ink** (#536608): Volt's text-safe shade on light (`secondary-ink`, 6.4:1), used for the success icon in messages.
- **Claret Ink** (#cab8be): the dark-theme `secondary-ink` (10.5:1 on Night).

### Tertiary
- **Destructive** (#b91c1c, 6.5:1 on Paper) and **Destructive Night** (#f87171 on Night, with **Destructive Night Ink** #450a0a on it): error messages, the "Remove game..." menu item and the dialog's confirm button only.

### Neutral
- **Paper** (#ffffff) / **Night** (#1a0b11): page grounds.
- **Ink** (#1d1418, 18:1) / **Night Ink** (#f6eef1): body text.
- **Smoke** (#6e5d63, 6.2:1) / **Night Smoke** (#b8a5ac, 8.2:1): secondary text, ranks 2 onward, counts, helper lines.
- **Mist** (#f6f1f2) / **Night Hover** (#2e1820): hover surfaces for ghost and outline controls.
- **Night Surface** (#221018): dark card and popover surface (messages, menus, dialog); light uses Paper.
- **Hairline** (#ece4e6) / **Night Hairline** (#35212a): decorative dividers between rows, header and status-bar rules, empty-state bounds.
- **Edge** (#8f7d83, 3.9:1) / **Night Edge** (#7a6168, 3.4:1): control outlines and the dashed spent token; passes WCAG 1.4.11 at 3:1.

### Named Rules
**The Same-Lightness Rule.** Neither brand color is ever text or a line on a ground of similar lightness: no Volt on Paper, no claret on Night. Use `brand-line`, `brand-ink` or `secondary-ink`, which resolve to the safe color per theme.

**The Role Swap Rule.** Write against semantic roles (`primary`, `secondary`, `token`, `brand-line`, `brand-ink`, `secondary-ink`), never raw hex. The theme decides which brand color plays which role; only `token` is constant.

**The One Bold Object Rule.** Volt as a fill belongs to the token (and, in dark theme, the primary button). No other surface, row or panel is filled or tinted with a brand color.

**The Edge Rule.** A line that identifies a control uses Edge (3:1 or better); Hairline is for decorative division only.

## Typography

**Display Font:** Schibsted Grotesk (with ui-sans-serif, system-ui, sans-serif)
**Body Font:** Schibsted Grotesk

**Character:** One newsy grotesk, loaded from Bunny Fonts at 400, 500 and 700, doing everything by size and weight. The status sentence is the headline; there is no hero type beyond it.

### Hierarchy
- **Status** (600, 1.75rem/1.2, -0.025em, balanced): the daily status sentence; the page's type-led headline.
- **Headline** (700, 1.25rem, -0.025em): section headings ("Ranking"). The token's "1" also uses this size at 700.
- **Rank** (600, 1.5rem, tabular): row rank numeral; rank 1 in `brand-ink`, the rest in Smoke.
- **Title** (500, 1.0625rem/1.4): game names in the ranking; empty-state headings at 600.
- **Body** (400, 1rem): status detail line and empty-state copy, Smoke.
- **Body small** (400 or 500, 0.875rem): reset countdown, counts, labels, messages, button text, the compact status sentence (600).
- **Label** (400, 0.75rem, tabular): compact bar countdown and the small token numeral.

### Named Rules
**The Tabular Rule.** Every rank, vote count, game count, character counter and countdown uses tabular numerals.

**The Sentence Status Rule.** Where you stand today is written as a sentence at Status size, not a label-plus-value panel.

## Layout

A single left-aligned column, max 42rem (`max-w-2xl`), centered in the viewport on every screen size, with 16px gutters (24px from 640px). The header (56px tall, hairline below) aligns its wordmark and controls to the same column. The status opens the page (40px top, 56px from 640px), token on the left with a 16px gap (24px from 640px), then the sentence, detail, countdown and, while an action is available, the suggest form 24px below. The ranking starts 56px under the status.

Ranking rows are a grid: rank | name | action | count | menu, 16px vertical padding, divided by hairlines. From 640px the columns are 3rem, flexible, auto, 5.5rem, 2.5rem with 12px gaps, so counts align whichever button a row shows; below 640px the count moves under the name and the gap tightens to 8px. A filter field appears once the library holds 9 or more games.

Once the status scrolls out of view (IntersectionObserver), a compact status bar fixes to the top on all viewports; `scroll-padding-top: 4.5rem` keeps focused and scrolled-to elements clear of it. Messages fix to the bottom of the screen in the same 42rem column; the page has 128px of bottom padding so the last row clears them.

## Elevation & Depth

Flat. Depth is conveyed by hairlines and position, not shadow. The compact status bar is fixed but separated only by a Paper/Night fill and a bottom hairline. Shadows belong to things that float over content: the bottom-pinned messages (`shadow-lg`) and the stock shadcn menus and dialog.

### Shadow Vocabulary
- **Float** (`box-shadow: 0 10px 15px -3px rgb(0 0 0 / 0.1), 0 4px 6px -4px rgb(0 0 0 / 0.1)`): bottom-pinned messages.
- **Overlay** (shadcn stock `shadow-md` / `shadow-lg`): dropdown menus and the remove-game dialog.

### Named Rules
**The Floats-Only Rule.** A shadow means "this sits above scrolling content". Rows, the status, empty states and the status bar never get one.

## Shapes

Gently rounded controls (6px) for buttons, inputs and rows (the row radius only shows in its arrival outline); 8px for messages and the dialog; full circles for the token and the avatar. Everything else is unbounded: sections are separated by space, rows and empty states by top and bottom hairlines. The token's edge is a 2px border: `brand-line` when filled, a 2px dashed Edge ring when spent.

## Components

### Buttons
Restrained shadcn buttons; only one filled button is ever on screen at rest.
- **Shape:** gently rounded (6px), 14px medium text.
- **Primary:** theme `primary` fill (claret on light, Volt on dark) for the Suggest submit only, 44px tall, 20px horizontal padding; hover at 90% opacity.
- **Vote (outline):** transparent with an Edge outline, 40px tall, min 80px wide; hover fills with `primary`. Hidden entirely once today's action is used. "Remove vote" uses the same outline without the fill hover.
- **Ghost / icon:** 40px row menu trigger and 36px message dismiss; Mist / Night Hover on hover.
- **Destructive:** dialog confirm only.
- **Link:** the "Suggest "X"" action in an empty filter result.
- **Focus:** 2px `ring` with 2px offset on every control.

### Inputs / Fields
- **Style:** Edge 1px outline, Paper/Night background, 6px radius, 44px tall; caret in `brand-line`.
- **Suggest field:** small medium label above; input and "Suggest" button on one row with 8px gap; a tabular "0/100" counter right-aligned beneath, turning Ink and medium in the last 10 characters; error text to its left.
- **Filter:** search input with a 16px leading icon, 44px tall.

### Navigation
- **Header:** wordmark "Office Game Library" in `brand-ink`, 16px semibold, tight tracking; appearance toggle and a 32px avatar (secondary fill) as ghost icon buttons on the right.

### Action Token (signature)
A 48px circle (24px small), Volt fill, claret "1" in bold tabular numerals, 2px `brand-line` edge. Decorative (`aria-hidden`): the sentence or "Your vote" label beside it says the same in words. States: **available** beside the status and in the compact bar; **spent**, a dashed Edge ring with no numeral; **placed**, 24px beside "Your vote" on the voted row.

**Token flight.** On a vote, the placed token animates from the visible available token's position and scale to its place on the row (480ms, `cubic-bezier(0.16, 1, 0.3, 1)`). Skipped under `prefers-reduced-motion`.

### Ranking Row
Hairline-divided list item, no fill. Rank numeral at Rank size; game name at Title; "Your vote" with the small placed token beneath the name; outline action; right-aligned tabular count; ghost menu with "Remove game...". When the ranking reorders, moved rows slide to their new position (420ms, same easing; skipped under reduced motion). After a vote change or a jump to your vote, focus lands on the row's button and a static 2px `ring` outline (4px offset) shows for 1.6s. Ties share a rank (competition ranking: 1, 2, 2, 4).

### Status and Status Bar
The full status is a section: token, Status sentence (live region with its detail line), and a non-live countdown ("Resets at midnight (Sydney time), in 5 h 12 min"). The voted game's name in the sentence is a button with a 2px `brand-line` underline (4px offset) that jumps to its row. The compact bar repeats the small token, the sentence at 14px semibold and a 12px tabular countdown; it is not a live region and is unmounted while hidden.

### Messages
Bottom-pinned, 8px radius, card fill, hairline border (error: destructive border at 50% and destructive text), leading 16px icon (success in `secondary-ink`), 36px dismiss. Never auto-clear; dismissing returns focus to the status heading. Both the alert and status live regions are always mounted; only their content changes.

### Empty States
A hairline-bounded block (top and bottom rule, 32 to 40px vertical padding) with a Title-size heading and a Smoke explanation; never a card or illustration.

## Do's and Don'ts

### Do:
- **Do** route brand colors through `primary`, `secondary`, `token`, `brand-line`, `brand-ink` and `secondary-ink` so they swap correctly between themes.
- **Do** keep the token as the only bold object: Volt fill, claret "1", 2px `brand-line` edge, in available, spent and placed states.
- **Do** write today's status as a sentence at Status size (1.75rem) beside the token.
- **Do** keep everything in one left-aligned 42rem column, header included, on every screen size.
- **Do** divide rows and bound empty states with hairlines, and outline controls with Edge (3:1 or better).
- **Do** keep vote buttons outline (40px, min 80px wide) and hide them once today's action is used.
- **Do** use tabular numerals and a fixed right-hand count column; use competition ranking (1, 2, 2, 4) for ties.
- **Do** pin messages to the bottom of the screen, dismissible, never auto-clearing, with both live regions always mounted.
- **Do** skip the token flight and rank slide under `prefers-reduced-motion`; static cues such as the 1.6s arrival outline need no variant.
- **Do** record a contrast ratio beside any new color token and hold every pairing to WCAG 2.2 AA.

### Don't:
- **Don't** use Volt as text or a line on Paper, or claret on Night (about 1.2:1).
- **Don't** fill or tint rows, panels or the status with a brand color; no cards, badges or dashboard panels.
- **Don't** use a neutral black or grey ground in dark theme; the ground is claret Night.
- **Don't** show rows of disabled Vote buttons once today's action is used.
- **Don't** add shadows to rows, the status, the status bar or empty states.
- **Don't** add a second typeface.
- **Don't** announce the countdown; only the status sentence and its detail are live.
