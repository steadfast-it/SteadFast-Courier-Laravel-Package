# Changelog

All notable changes to `steadfast-courier/steadfast-laravel-package` are documented
in this file.

This project follows [Semantic Versioning](https://semver.org/spec/v2.0.0.html) and the
[Keep a Changelog](https://keepachangelog.com/en/1.1.0/) format.

## [2.0.0] - 2026-11-10

Full coverage of the official SteadFast API, plus fixes for several defects in 1.x. Read
**Breaking changes** before upgrading; most applications need a short checklist rather than a
rewrite.

### Breaking changes

- **The package has been renamed** from `steadfast-courier/steadfast-courier-laravel-package`
  to `steadfast-courier/steadfast-laravel-package`, matching the renamed repository. The old
  package is abandoned in favour of the new one and receives no further releases.
- **The config file and config keys have been renamed** from `steadfast-courier` to
  `steadfast`. Re-publish with `--tag="steadfast-config"` and read `config('steadfast.*')`
  instead of `config('steadfast-courier.*')`. The `STEADFAST_*` environment variable names are
  unchanged.
- **PHP 8.2 or newer, Laravel 12 or newer.** Laravel 11 and earlier are no longer supported:
  Laravel 11 reached end of security support on 12 March 2026, and Composer's `block-insecure`
  audit default refuses to install any 11.x release. Stay on `^1.1` if you cannot upgrade.
- **`bulkCreateOrders()` sends a different request body.** It now posts the order list as the
  request body, matching the current API documentation, instead of
  `{"data": "<json string>"}`. The response is wrapped, so read `$response['data']` rather
  than iterating the response directly.
- **Responses that are not JSON now throw.** An HTML gateway error or an empty body raises
  `SteadfastCourierException` instead of being returned as `null`.
- **Input that cannot succeed now throws `InvalidRequestException`** before any network call:
  an empty identifier, a bulk batch over 500 orders, a single order passed where a list is
  expected, or a return request with no consignment reference.
- **`withConfig()` returns a new client** instead of mutating the shared one. Chained calls
  are unaffected.

### Added

- Nine endpoints that were missing: `createReturnRequest()`, `getReturnRequests()`,
  `getReturnRequest()`, `createPickupRequest()`, `getPayments()`, `getPayment()`,
  `getTrackingsByInvoice()`, `getPoliceStations()` and `fraudCheck()`. All 13 endpoints in the
  official API documentation are now covered, alongside the two status lookups
  (`status_by_invoice`, `status_by_trackingcode`) that the documentation no longer lists but
  the API still serves.
- `DeliveryStatus` enum covering all 11 delivery statuses, with `isFinal()`,
  `isAwaitingApproval()`, `isInProgress()`, `needsSupport()`, `label()` and `fromResponse()`.
- `InvalidRequestException`, raised locally for input that cannot succeed. It extends
  `SteadfastCourierException`, so a single `catch` still covers everything.
- Request timeouts (`STEADFAST_TIMEOUT`, `STEADFAST_CONNECT_TIMEOUT`) and retries
  (`STEADFAST_RETRY_TIMES`, `STEADFAST_RETRY_SLEEP`). Writes are never retried, because a
  `create_order` call that timed out may already have been accepted and the API has no
  idempotency key.
- `webhook_bearer_token` config key, which the README documented but the package never
  shipped.
- `SteadfastCourier::MAX_BULK_ORDERS` for chunking large batches.
- `@method` annotations on the facade for IDE completion and static analysis.
- A test suite of 78 tests, and a GitHub Actions matrix across PHP 8.3-8.5 and
  Laravel 12-13 with Pint and PHPStan.

### Fixed

- A trailing slash on `STEADFAST_BASE_URL` produced a doubled slash in every request URL, such
  as `/api/v1//get_balance`. The base URL is now normalised, and an unset one raises a clear
  `InvalidRequestException` instead of a confusing "URI must include a scheme" error.
- An empty `STEADFAST_TIMEOUT=` in `.env` reached the HTTP client as a timeout of `0`, which
  Guzzle treats as "wait forever", silently removing the protection the timeout was added for.
  Timeout and retry settings now fall back to their defaults when empty or non-numeric, while
  still honouring a retry count of `0` or `1` as "do not retry".
- An identifier passed as `false`, `true`, an array or an object was cast into a URL segment,
  producing a request against a different record or an empty path segment. Only usable scalars
  are accepted now.
- `DeliveryStatus::fromResponse()` only looked at `delivery_status`, so it returned `null` for a
  `placeOrder()` response even though the consignment carries the same status vocabulary. It now
  reads `delivery_status`, `consignment.status` and a webhook's `status`, in that order.
- **Credentials leaked between tenants.** `withConfig()` mutated the instance cached by the
  facade, so the credentials passed for one tenant were reused by every later call in the same
  process, booking parcels against the wrong merchant account.
- **Webhook authentication could be bypassed.** The `webhook_bearer_token` config key did not
  exist, so the check in the documented webhook controller compared the incoming header
  against `'Bearer '` and accepted any request sending exactly that.
- **URL path segments were not encoded.** An invoice such as `INV/2024/01` added path segments
  and reached a different endpoint; a `#` truncated the path into a URL fragment.
- **Non-JSON replies were returned as `null`,** which callers could not distinguish from an
  empty result.
- **No request timeout,** so an unresponsive endpoint could block a web request or queue
  worker indefinitely.
- **The Laravel facade alias pointed at a class that does not exist,** so the registered
  `SteadFastCourier` alias silently did nothing.
- **The test suite could not run at all.** `tests/TestCase.php` declared a namespace that did
  not match the autoloader, and the service provider was never registered under test.
- **Broken Composer scripts.** `composer clear` called a nonexistent Testbench command and
  `composer analyse` called PHPStan, which was not installed.
- **An unsatisfiable PHP constraint.** `composer.json` advertised `^7.2|^8.0` while its
  dependencies required PHP 8.0 or newer.

### Changed

- `base_url` and the README now agree on `https://portal.packzy.com/api/v1`. The README
  previously told users to set a different host.
- The `content_type` config option is now actually applied to requests.
- Requests send an explicit `Accept: application/json` header.
- README rewritten with an endpoint coverage table, per-endpoint reference, upgrade notes and
  a hardened webhook example.

## 1.1.4 - 2025-08-13

## 1.1.3 - 2024-09-29

## 1.1.2 - 2024-04-25

## 1.1.1 - 2024-03-24

## 1.1.0 - 2024-03-24

## 1.0.0 - 2024-03-24

Releases before 2.0.0 predate this changelog. See the
[commit history](https://github.com/steadfast-it/SteadFast-Laravel-Package/commits/main) for
what changed in each.

[2.0.0]: https://github.com/steadfast-it/SteadFast-Laravel-Package/compare/1.1.4...2.0.0
