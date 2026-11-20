# Product

<!-- impeccable:product-schema 1 -->

## Platform

web

## Users

- **Office employees** of a single office, making a quick visit (often once a day) on a phone or a desktop browser to
  suggest a game for the office library or vote for one they want.
- **Take-home reviewers** evaluating the build: they clone it, run it with Docker, and judge code quality, UX polish,
  API use, the daily-vote rule, and error handling.

## Product Purpose

Office Game Library lets a team collect game suggestions for the office library and vote on the ones they want most,
so the most-wanted games rise to the top. Success: anyone can understand the ranking, cast or withdraw their vote, or
suggest a title in a few seconds, and always knows what they can still do today.

## Positioning

A deliberately scarce vote: each person gets **one action per day** (a vote or a new suggestion, in their own
timezone), enforced on the server. That makes every vote a considered choice rather than a click-fest, and the
ranking reflects what people actually want.

## Operating Context

- Short, task-focused visits: open, scan the ranking, act once, leave.
- Used on phones and desktops; the theme follows the system light/dark setting, with a light/dark/system toggle.
- Accounts are simple (name, email, password). The user's timezone is detected at registration and can't be changed.
- Games and vote totals live in an external API (Essense Code Challenge API); this app adds who voted for what and the
  daily limit.

## Capabilities and Constraints

- List every game sorted by votes (highest first), ties by name A–Z.
- Add a game by title: max 100 characters, extra spaces collapsed, no line breaks; duplicates (ignoring case and
  spacing) rejected.
- Vote for a game; remove your own vote. One active vote per person per game, which stays until removed.
- Remove a game (anyone can), with a confirmation that shows its vote count.
- One action per local day: a vote **or** an add. Undoing your own action the same day gives it back; if someone
  removes a game you voted for or added today, you get your action back too.
- Every error (validation, network, API) must be shown in a friendly way; no silent failures. Timeouts on vote/add are
  ambiguous and the user is told to check before retrying.
- Terminology: "game", "vote", "suggest/add a game", "today's action".
- Stack: Laravel 12 + Inertia 2 + React 19 + TypeScript, Tailwind 4, shadcn/ui components. No new packages without
  the developer's approval.

## Brand Commitments

- Name: **Office Game Library**. Its own neutral identity; no Essense of Australia branding or implied affiliation.
- Voice: friendly, plain-spoken, brief. Messages name the game involved and say what to do next.
- Colors: **primary `#420217`** (burgundy) and **secondary `#D0FF15`** (lime), with tints and shades as needed for
  WCAG AA. They swap roles between themes: neither may be used as text or a line on a background of similar
  lightness (lime on light, burgundy on dark, both about 1.2:1).

## Evidence on Hand

- No logo, imagery, testimonials or brand assets exist. Do not invent any. Game data comes only from the API (titles
  and vote counts; no cover art, genres or descriptions).

## Product Principles

1. **Always know where you stand.** Today's action status is visible at all times, and every outcome is explained.
2. **One considered action.** Make voting and suggesting feel deliberate, not frictionless spam, while staying fast.
3. **Your vote is yours.** It's obvious which games you voted for and how to take a vote back.
4. **Fail kindly.** Errors say what happened and what to do, never leaving the user guessing.

## Accessibility & Inclusion

WCAG 2.2 AA: contrast, full keyboard use with visible focus, 24×24 minimum targets, reflow at 320px, status changes
announced (`aria-live`), no information by color alone, reduced motion respected.
