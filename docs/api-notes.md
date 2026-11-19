# Essense Code Challenge API: findings

Notes from testing the API by hand. These are things the `/docs` page doesn't say or that we needed to confirm.
Each section records what we observed and what it means for our backend.

**Base URL:** `https://codechallenge.essensedesigns.info`

## Endpoints

| Method | Path | Params | Success response |
|---|---|---|---|
| POST | `/games/list` | `api_key` | `{"games":[{"id":1,"name":"…","votes":0}]}` |
| POST | `/games/add` | `api_key`, `name` | `{"id":1}` |
| POST | `/games/search` | `api_key`, `id` | `{"id":1,"name":"…","votes":0}` |
| POST | `/games/remove` | `api_key`, `id` | `{"success":true}` |
| POST | `/games/vote` | `api_key`, `id` | `{"success":true}` |
| POST | `/games/removeVote` | `api_key`, `id` | `{"success":true}` |
| GET | `/lastGameId?api_key=…` | `api_key` (query string) | `{"id":4}` |
| POST | `/cache/flush` | `api_key` | `{"success":true}`. Clears all game data; the key stays valid. |
| GET | `/cache/health` | none | `["ok"]` |

`/games/list` takes no sort parameter (as far as we know), so we sort by votes on our side.

**Paths are case-sensitive.** `/games/removeVote` works; `/games/removevote` returns a routing `404`.
(We lost time on this: the lowercase path was misread from a low-resolution screenshot of the docs.)
A cheap way to check whether a route exists is to call it with no key. A real route returns `400 MISSING_KEY`, and a
route that doesn't exist returns `404`.

## Findings

### 1. Duplicate titles are not rejected

Adding `mario`, `Mario` and `MarIo` created three separate games.

**For us:** our backend must reject duplicates by comparing titles case-insensitively before calling `/games/add`.
We should also decide whether to trim whitespace and collapse internal spaces before comparing.

### 2. Auth failures use real HTTP status codes

| Case | Status | Body |
|---|---|---|
| No `api_key` | `400 Bad Request` | `{"error":"MISSING_KEY"}` |
| Wrong `api_key` | `403 Forbidden` | `{"error":"INVALID_KEY"}` |

Tested on `POST /games/list` and `GET /lastGameId`; both behaved the same. `Content-Type` is `application/json` in both cases.

**For us:** we can branch on the status code instead of inspecting the body of a `200`.
A missing or invalid key is a misconfiguration on our server, not something the user did. We should log it and
show a generic "service unavailable" message, never "invalid key".

### 3. Request bodies must be form-encoded, not JSON

- A JSON body (`Content-Type: application/json`) returns `400 MISSING_KEY`: the API doesn't parse JSON bodies.
- A form-encoded body (`application/x-www-form-urlencoded`) works.
- The query string also works on POST (`POST /games/list?api_key=…` returned `403 INVALID_KEY` for a bad key, so the API did read it).

**For us:** send params with Laravel's `Http::asForm()`. Keep the key in the body rather than the URL so it doesn't end up in access logs.

### 4. There are two error response shapes

- **Application errors** (key problems so far): `{"error":"CODE"}`
- **Routing errors** (wrong HTTP method, unknown path): an RFC 7807-style problem object, for example
  `{"type":"…","title":"An error occurred","status":405,"detail":"Method Not Allowed"}`. We saw `405` for GET on a
  POST endpoint and `404` for an unknown path.

**For us:** the API client should use the status code as the main signal, then read `error` if it's there, and fall back
to `detail`. It must also handle a response that isn't JSON at all, such as a timeout or a proxy error page.

### 5. Game errors mostly return `200`, and each endpoint signals failure differently

Calling each endpoint with a game `id` that doesn't exist (`999999`):

| Endpoint | Status | Body | How failure shows |
|---|---|---|---|
| `/games/search` | `200` | `{}` | empty object |
| `/games/vote` | `200` | `{"success":false}` | `success:false` |
| `/games/remove` | `200` | `{"success":false}` | `success:false` |
| `/games/removeVote` | `200` | `{"success":false}` | `success:false` |
| `/games/add` (bad `name`) | `200` | `{"id":-1}` | `id` of `-1` (see finding 6) |

So **every game-level error is a `200`**. Non-`2xx` statuses only come from key problems (finding 2) and routing (finding 4).

**For us:** a `200` doesn't mean the call worked. The API client needs a check for each endpoint
(`success === true`, a non-empty `search` result, `id > 0`) and should turn each failure into a single
"not found / not applied" error that our controllers handle.

### 6. `/games/add` returns `{"id":-1}` for an invalid name

An empty `name` and a missing `name` both return `200 {"id":-1}`. No game is created; we checked with `/games/list`.

**For us:** validate `name` ourselves (required, trimmed, length limit) in a Form Request before calling the API,
and treat `id <= 0` as a failure in case the API rejects something our validation lets through.

### 7. `removeVote` at 0 votes reports success but does nothing

| Case | Response | Vote count |
|---|---|---|
| Game with 1 vote | `200 {"success":true}` | 1 → 0 |
| Same game, now at 0 votes | `200 {"success":true}` | stays 0 (no-op) |
| Game `id` that doesn't exist | `200 {"success":false}` | n/a |

The count never goes negative, but `success:true` does **not** mean a vote was actually removed.

**For us:** the API only stores a total count per game, with no record of *who* voted, and `removeVote` succeeds even when
there's nothing to remove. Our database has to be the source of truth for "did this user vote for this game", and we only
call `removeVote` for a vote our DB has recorded.

### 8. Without `Accept: application/json`, routing errors come back as HTML

A routing `404` (we hit one by calling the wrong-cased `/games/removevote`) returned a full HTML error page until we
added `Accept: application/json`. After that it returned the JSON problem object.

**For us:** always send `Accept: application/json` (Laravel's `Http::acceptJson()`), and still handle a response that isn't JSON.

### 9. The `/docs` page creates a new API key on every refresh

Each refresh of the docs page calls `/api-key/generate` and gets a new key. Data is stored per key, so a new key starts
with an empty game list and IDs from 1.

**For us:** keep one key in `.env` and stop refreshing the docs page with it. We're using a key generated by the docs page;
it works the same as one from `/api-key`.

### 10. IDs are never reused, except after a flush

- **Deleting doesn't free an ID.** After game `3` was deleted, `lastGameId` still returned `3` and the next add got `4`.
  `lastGameId` is the highest ID ever issued, not the highest that exists right now.
- **`/cache/flush` resets the counter.** With the same key, after a flush `lastGameId` returned `0` and the next add got `1`.

**For us:** an API game `id` is only unique until someone flushes. If our DB stores votes or daily-limit rows that point to
API IDs, a flush followed by new adds would silently point those rows at *different* games. Options: store the game name
next to the ID and treat a mismatch as stale, reconcile against `/games/list`, or never flush in a running environment and
document that. We'll decide this when we design the schema.

### Other things we noticed

- `/games/list` returns games in ID order, not by votes. We sort them ourselves.
- `vote` has no per-user tracking (it just adds 1 to the count), so the one-vote rule lives entirely in our backend.

## Open questions

None blocking. Every open question from testing is answered above.
