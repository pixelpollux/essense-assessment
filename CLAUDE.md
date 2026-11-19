# CLAUDE.md

Guidance for Claude Code when working in this repo.

## What this is

A take-home assessment: an office **game-library voting app**. Employees suggest games and vote on them.
Built on the **Laravel 12 + Inertia 2 + React 19 + TypeScript** starter kit.

Reviewers evaluate: code quality and structure, UX/UI polish, **correct and idiomatic use of the external REST API**,
how carefully the **daily vote rule** is implemented, error handling and edge cases, and the AI chat transcript.
Quality of thinking matters more than the number of features.

### Functional requirements

- List every game, sorted by vote count (highest first).
- Add a game by title. The **backend** rejects duplicate titles, compared case-insensitively.
- Vote for a game. Remove a vote you cast.
- Remove a game.
- Every screen works on mobile and desktop.
- Errors (network or validation) are shown to the user in a friendly way. Nothing fails silently.

### Business rule (the core of the assessment)

- A user may cast **one vote OR add one new game per day** (midnight to midnight, user's local time).
- The external API does **not** enforce this. **Our backend must.**
- User *identity* may come from a cookie, local storage, or auth.
- The limit, and all game and vote data, must be **tracked and persisted in a real database**, never in cookies or local storage.

### Submission constraints

- Backend must be PHP (Laravel here).
- Must run with `docker-compose up` from a clean clone. A deployed URL is optional and does not replace this.
- The full AI chat transcript is included in the submission.

### External API

- The Essense of Australia Code Challenge API. The endpoint reference lives at its `/docs` page.
- The API key is a secret. Keep it in `.env` only, read it through `config/services.php`, and never commit it.
- Only the backend talks to the external API. The browser never calls it directly.
- **Read `docs/api-notes.md` before touching API code.** It records the tested behavior: form-encoded bodies only, game errors come back as `200`, case-sensitive paths (`/games/removeVote`), and IDs reset after a flush.

## Working agreements (from the developer)

The developer is a senior frontend-focused full-stack engineer (React/TS, a11y, design systems) who is **new to Laravel**.

- **Explain Laravel concepts the first time they come up**, briefly, with a React/Node analogy where one helps.
- **Propose a plan before making changes.** Wait for approval before editing.
- **Keep diffs small.** One concern per change.
- **Never add Composer or npm packages without asking first.**
- **Prefer standard Laravel conventions** (Form Requests, Eloquent, resource controllers, named routes, migrations,
  config + env) over custom patterns. If a convention doesn't fit, say why before deviating.
- Accessibility is a first-class requirement: semantic HTML, labelled controls, visible focus, announced errors and
  status changes (`aria-live`), and no information conveyed only by color.

## Stack & layout

| Layer | Where | Notes |
|---|---|---|
| Routes | `routes/web.php` | Named routes. `route('name')` works in TS via Ziggy. |
| Controllers | `app/Http/Controllers/` | Keep them thin. |
| Validation | `app/Http/Requests/` | Form Request classes. |
| Models | `app/Models/` | Eloquent. |
| Schema | `database/migrations/` | Never edit a migration that has already been committed. Add a new one. |
| Shared Inertia props | `app/Http/Middleware/HandleInertiaRequests.php` | Props sent to every page (flash messages, the current user). |
| Root HTML | `resources/views/app.blade.php` | The only Blade view. |
| Pages | `resources/js/pages/*.tsx` | `Inertia::render('games/index')` → `pages/games/index.tsx`. |
| UI primitives | `resources/js/components/ui/` | shadcn/ui (Radix + Tailwind 4). Reuse these before building new ones. |
| Types | `resources/js/types/` | Page prop types. |
| Tests | `tests/Feature`, `tests/Unit` | **PHPUnit** (class-based). `tests/Pest.php` is a leftover; Pest is not installed. |

## Decisions

1. **Identity:** real accounts, using the starter kit's auth trimmed to register, login and logout. No email verification,
   password reset or settings pages. Office use; we assume good faith past "one account, one password".
2. **Source of truth:** the API owns games and vote totals. The local DB stores only *who voted for which game*
   (`votes`) and the daily-action log. Duplicate-title check = fetch `/games/list` and compare normalized titles,
   with the add wrapped in a `Cache::lock` to prevent races.
3. **Local midnight:** the user's IANA timezone is saved on the user record at registration (auto-detected in the browser,
   checked with Laravel's `timezone` validation rule). The server works out `local_date` from it. A unique index on
   (`user_id`, `local_date`) enforces one action per day at the database level.
4. **Database:** SQLite, as a file in a Docker volume.
5. **Flush / ID reuse:** local rows store the API game ID **and** the game name, and a row only counts if both still
   match `/games/list`. There's also an artisan `games:reset` command that flushes the API first, then clears `votes` and
   `daily_actions` in one transaction (if the flush fails, nothing local changes). It keeps user accounts and asks before
   running in production (`--force` skips it).
6. **Rule semantics:**
   - Undoing an action on the same day refunds it: removing your vote the same day you cast it gives back today's action.
   - One active vote per user per game (unique index). No piling votes onto the same game across days.
   - **Anyone can remove a game.** The confirmation dialog shows its vote count. Removal deletes the local vote rows and
     refunds today's action to anyone who voted for it or added it *today*, all in one DB transaction.
     The daily-action status is always visible and comes from the server.
   - **Refunds reuse the daily-log row** (no notifications table). A refund sets `refunded_at` and `refund_reason`
     (`withdrawn`: you undid your own action, by removing your vote or a game you removed yourself |
     `game_removed`: someone else removed the game) on the row. The next action that day updates the same row and clears the refund,
     so the plain unique index on (`user_id`, `local_date`) still holds. Taking today's action is atomic: a conditional
     `UPDATE … WHERE refunded_at IS NOT NULL` that must affect exactly 1 row, or an `INSERT` when there's no row
     (a racing second insert is rejected by the unique index).
   - **The status line is computed from that row:** no row means "1 action left today"; an active row means
     "Today's action used: …"; a row refunded for `game_removed` means "*X* was removed, so today's vote was given back"
     (in an `aria-live` region); a row refunded for `withdrawn` just means "1 action left today".
     Not refunded: votes from earlier days, and removals made outside the app (the leftover rows are ignored, see 5).
   - **A vote stays active across days** until you remove it: voting again for the same game on a later day is rejected
     (`AlreadyVoted`) and doesn't use the day's action. **The UI must make "you voted for this" obvious on each game, and
     show how to remove the vote.**
   - Removing a game that's already gone throws `GameNotFound` and changes nothing locally (UI: "already removed", refresh).
   - Ties in the ranking sort by name A–Z, ignoring case.
7. **Ambiguous API timeouts:** a vote/add that times out may have been applied by the API anyway. We undo locally and
   warn the user ("We couldn't confirm your vote, check the count before trying again"). Worst case the API total is
   one too high, which beats local records that disagree with the API.
8. **Game names** (enforced in the add-game Form Request): trimmed, internal whitespace collapsed, no control characters,
   max **100** characters. Duplicates compare case-insensitively after collapsing whitespace (`docs/api-notes.md` 11–12).

## Business rules implementation

`App\Services\Library\GameLibrary` holds every rule; `App\Services\Essense\EssenseClient` (behind the `EssenseApi`
interface) only talks to the API.

- **Ordering rule:** make the local change first in a short transaction (the database decides races), then call the
  API, then undo the local change if the API fails. Exception: `removeGame` calls the API first, because removal is
  safe to repeat. **Never hold a DB transaction open across an HTTP call** (SQLite has one writer).
- `daily_actions.local_date` is a plain `YYYY-MM-DD` string in the user's timezone (no date cast: it would store a time
  and break lookups).
- Tests: `tests/Feature/GameLibraryTest.php` runs against real tables with `Tests\Fakes\FakeEssenseApi` bound to
  `EssenseApi`. The `Cache::lock` around adds is not covered by tests (a real race needs concurrent processes).

## Commands

```bash
composer dev                 # artisan serve + queue + logs + vite, all together
php artisan migrate          # run migrations
php artisan test             # PHPUnit tests
vendor/bin/pint              # PHP formatter (Laravel's Prettier)
npm run lint                 # ESLint --fix
npm run format               # Prettier on resources/
npx tsc --noEmit             # typecheck
```

Before considering a change done: tests pass, `pint` is clean, `npm run lint` and `tsc` are clean.

## Conventions

- PHP: PSR-12 via Pint, typed parameters and return types.
- TS: strict, no `any`. Components in kebab-case files to match the existing starter kit.
- Return validation errors through Laravel's standard 422 / Inertia `errors` bag so `useForm` picks them up.
- Map failures from the external API to user-facing messages in one place. Never pass raw upstream errors to the UI.
- Tests should cover what matters: the daily-limit rule (including day boundaries and timezones),
  case-insensitive duplicate rejection, and how upstream API failures are handled. Coverage percentage is not a goal.
