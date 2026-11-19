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
   match `/games/list`. There's also an artisan `games:reset` command that flushes the API and clears local tables together.
6. **Rule semantics:**
   - Undoing an action on the same day refunds it: removing your vote the same day you cast it gives back today's action.
   - One active vote per user per game (unique index). No piling votes onto the same game across days.
   - **Anyone can remove a game.** The confirmation dialog shows its vote count. Removal deletes the local vote rows and
     refunds today's action to anyone who voted for it or added it *today*, all in one DB transaction.
     Refunded users get a Laravel database notification ("*X* was removed, so today's vote was given back"), shown once
     in an `aria-live` region. The daily-action status is always visible and comes from the server.
     Not refunded: votes from earlier days, and removals made outside the app (the leftover rows are ignored, see 5).

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
