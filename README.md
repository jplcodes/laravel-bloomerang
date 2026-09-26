# Laravel Bloomerang

A small, tested Laravel client for [Bloomerang](https://bloomerang.com)'s REST API v2.

- Constituent lookups (including memberships), search, find-by-email, and create.
- Two call modes: **request mode** for calls made while a page renders, **job mode** for queued work.
- Read-only data objects, explicit exceptions, a paginator, and a `request()` escape hatch for everything else.
- Every call goes through Laravel's `Http` client, so `Http::fake()` works in your tests.

> **Status: `0.x`.** Response shapes are built from Bloomerang's published OpenAPI specification and have not yet been checked against a live account. Expect breaking changes before `1.0`.

## Requirements

PHP 8.3+ and Laravel 13.

## Installation

The package isn't on Packagist yet. Add the repository to your `composer.json`, then require it:

```json
"repositories": [
    { "type": "vcs", "url": "https://github.com/jplcodes/laravel-bloomerang" }
]
```

```bash
composer require jplcodes/laravel-bloomerang:^0.1
```

## Configuration

Set your private API key in `.env`:

```dotenv
BLOOMERANG_API_KEY=
```

That's all. Every call sends it in the `X-API-KEY` header. If the key is empty, the client throws `MissingCredentials` instead of sending an unauthenticated request.

Optional settings:

| Variable | Default | |
| --- | --- | --- |
| `BLOOMERANG_BASE_URL` | `https://api.bloomerang.co/v2` | |
| `BLOOMERANG_LOGGING` | `true` | Log each call (see [Logging](#logging)). |
| `BLOOMERANG_LOG_CHANNEL` | your default channel | |

Timeouts and retries live in the config file. Publish it with:

```bash
php artisan vendor:publish --tag=bloomerang-config
```

## Usage

```php
use JplCodes\Bloomerang\Facades\Bloomerang;

$constituent = Bloomerang::constituents()->find(1001);

foreach ($constituent->memberships() as $membership) {
    $membership->programName;  // "Example Membership Program"
    $membership->status;       // "Current", exactly as Bloomerang sent it
    $membership->isCurrent();  // true
    $membership->renewsOn();   // CarbonImmutable, or null
}

// Any number of ids, sent 50 per request and fetched lazily.
Bloomerang::inJobMode()->constituents()->findMany($ids)->each(...);

// People and households matching free text. Search results carry no membership data.
Bloomerang::constituents()->search('Ada');

// Only records whose primary email is exactly this address (ignoring case): zero, one, or several.
// Filters the first 200 search results, which is plenty for an email address.
Bloomerang::constituents()->searchByEmail('ada@example.org');

// Creates an individual. First and last name are required.
Bloomerang::constituents()->create('Ada', 'Example', 'ada@example.org', ['MiddleName' => 'B']);

// Which Bloomerang user owns the key. Handy as a health check.
Bloomerang::currentUser();
```

You can also inject `JplCodes\Bloomerang\Bloomerang` instead of using the facade.

### Membership data: absent vs. empty

A constituent's `Membership` list means two different things depending on whether it is there at all:

- **Present and empty**: `hasMembershipData()` is `true` and `memberships()` returns `[]`. The person has no membership.
- **Absent** (search results, a newly created constituent, or an unexpected response): `hasMembershipData()` is `false` and `memberships()` throws `MembershipDataMissing`.

That way a malformed response can never be mistaken for "not a member".

## Call modes

| | Request mode (default) | Job mode |
| --- | --- | --- |
| Use for | Calls made while a page renders | Queued jobs and scheduled work |
| Timeout | 4 s (connect 2 s) | 30 s (connect 5 s) |
| Retries | None | Up to 3, on 429, 5xx, and timeouts or network failures |

```php
Bloomerang::constituents()->find($id);                // request mode
Bloomerang::inJobMode()->constituents()->find($id);   // job mode
```

In job mode the client waits as long as a 429's `Retry-After` asks (capped at 60 s), and otherwise backs off for about 1, 2, then 4 seconds. It never retries 401, 403, 404, or other 4xx responses.

With the defaults, one job-mode call can take a little over two minutes before it gives up (four 30-second attempts plus the waits), or longer if Bloomerang keeps answering 429. Give jobs that use job mode a `$timeout` above that, or lower the timeouts in the config.

**Writes are careful.** `POST` and `PATCH` requests (including `create()`) are retried only after a 429, which Bloomerang rejects before doing anything. They are never retried after a timeout or a server error, because the first attempt may have succeeded and a retry could create a duplicate record.

## Errors

Everything extends `JplCodes\Bloomerang\Exceptions\BloomerangException`, which carries the HTTP `status` and the call's `correlationId`.

| Exception | When |
| --- | --- |
| `AuthenticationFailed` | 401 or 403 |
| `NotFound` | 404 |
| `RateLimited` | 429 (`retryAfterSeconds` when Bloomerang sent one) |
| `ServerError` | 5xx |
| `RequestRejected` | Any other 4xx |
| `ConnectionFailed` | A timeout or network failure |
| `UnexpectedResponse` | The response isn't JSON, or isn't the expected shape |
| `MissingCredentials` | No API key or token is configured |
| `MembershipDataMissing` | `memberships()` on a constituent without membership data |

Exception messages hold the method, path, status, and correlation id. They never include the API key or the response body, which can contain donor details.

## Logging

Each attempt is logged with a correlation id, the mode, method, path, status, attempt number, and duration: `debug` on success, `warning` on failure. Query parameter names are logged but not their values, because a search value is often an email address. Headers, bodies, and the API key are never logged.

## Anything else: the escape hatch

For endpoints this package doesn't wrap, `request()` uses the same authentication, modes, errors, and logging, and returns the decoded JSON:

```php
Bloomerang::request('GET', 'funds', ['isActive' => 'true']);
Bloomerang::inJobMode()->request('PUT', "constituent/{$id}", body: [...]);

// Walks Bloomerang's skip/take pages lazily.
Bloomerang::paginate('funds')->each(...);
```

Paths are relative to the base URL. Full URLs are refused, so the key is never sent anywhere else.

## OAuth access tokens

If your app connects to Bloomerang through OAuth, pass the access token and it replaces the API key:

```php
Bloomerang::withToken($accessToken)->currentUser();
```

The OAuth authorization flow itself (registering an app, the redirect, refreshing tokens) is not part of this package yet.

## Testing your app

Fake Bloomerang like any other HTTP API:

```php
Http::preventStrayRequests();

Http::fake([
    'api.bloomerang.co/v2/constituent/*' => Http::response([...]),
]);
```

Bloomerang has no sandbox, and a private API key has full read and write access to the organization's CRM. Keep live keys out of your test environment.

## Things the documentation doesn't tell you

- **Membership appears on only two endpoints**: `GET /constituent/{id}` and `GET /constituents`. Search and duplicate-check results don't include it, so look the constituent up by id afterwards.
- **Membership status is free text.** The specification types it as a plain string. Expect `Current`, `Lapsed`, and `Canceled`, but handle anything else. The renewal date is also a plain string, and can be empty.
- **Memberships can't be written, or queried by program,** through the API.
- **Bloomerang matches people on name plus one contact point** (email, phone, or address), never on email alone, and the duplicates endpoint (`/constituent/duplicates`) needs a first and last name. That's why `searchByEmail()` searches, then filters on the primary email itself.
- **`GET /constituents?id=` takes ids joined with a pipe** (`id=1|2|3`), not repeated parameters. `findMany()` does this for you.
- **Search returns households as well as people.** `search()` returns `Household` objects for them.
- **Page size is capped at 50.**
- **No rate limits are documented.** Back off on errors, which job mode does.
- **`POST /constituent` does no duplicate check.**

## License

MIT. See [LICENSE](LICENSE).
