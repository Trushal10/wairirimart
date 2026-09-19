# 23 — Coding Standards

## 1. Overall philosophy

- **Thin controllers, fat services.** Controllers validate + call a service + return a response. Anything more complex belongs in `app/Services`.
- **Recompute, don't trust.** Never trust client-provided prices, totals, discounts, or IDs. Recompute server-side.
- **Atomic where money moves.** Any code path that touches stock, payments, or coupons must run inside `DB::transaction()` with `lockForUpdate()` where necessary.
- **Guard exactly-once with atomic UPDATEs.** For notifications and other one-shot side effects, use `UPDATE ... WHERE guard IS NULL` — the returned row-count is the mutex.
- **Fail loud in dev, gracefully in prod.** `APP_DEBUG=true` locally to see stack traces; `APP_DEBUG=false` in production with user-friendly error pages.
- **No secrets in git.** `.env` is git-ignored. `.env.example` documents variables, not values.

---

## 2. PHP style

### 2.1 Formatter
- **Tool:** [Laravel Pint](https://laravel.com/docs/12.x/pint) (already in `require-dev`).
- **Config:** default Laravel preset (uses PSR-12 as base + Laravel conventions).
- **Run:**
  ```bash
  vendor/bin/pint            # format in place
  vendor/bin/pint --test     # check-only (CI)
  ```

### 2.2 Naming
- Classes: `PascalCase`.
- Methods: `camelCase`.
- Constants: `SCREAMING_SNAKE_CASE`.
- DB columns: `snake_case`.
- Route names: `dot.namespace.action`.
- Route URIs: `kebab-case`.
- Env vars: `SCREAMING_SNAKE_CASE` (`RAZORPAY_KEY`).

### 2.3 Type declarations
- Use scalar type hints (`string`, `int`, `bool`, `?string`, `array`) everywhere.
- Use return types (`: void`, `: array`, `: Response`).
- Prefer `readonly` properties for value objects.
- Use `enum` where the domain has a fixed set (Laravel 12 supports native enums; not yet used here — consider adding for `Order::STATUS_*`).

### 2.4 Nullability
- Explicit is better than implicit. Use `?string` when null is a valid value.
- Use `Str::of()` or null-safe operator (`?->`) instead of `if ($obj) { $obj->method(); }`.

### 2.5 Comments
- **Default: no comments.** Well-named identifiers speak for themselves.
- Only write a comment when the *why* is non-obvious (a hidden constraint, a workaround, an invariant).
- Never write `// foo the bar` — replace with a well-named helper method.
- No commented-out code — delete it (git remembers).

---

## 3. Laravel conventions

### 3.1 Controllers
- Prefer single-action controllers (`__invoke`) or resource-style controllers (`index`, `create`, `store`, `edit`, `update`, `delete`).
- Never inject request data directly — always use a FormRequest.
- Never write raw SQL in a controller — use Eloquent or the query builder.
- Return values:
  - Blade: `view('...', $data)`.
  - Inertia: `Inertia::render('Admin/Product/Index', $data)`.
  - JSON: `response()->json($data, $status)`.
  - Redirect: `redirect()->route('...')->with('success', ...)`.

### 3.2 Requests
- Always define `authorize()` — even if it just returns `true` (documents the choice).
- Rules in `rules()`. Custom messages in `messages()` only when you need friendlier copy.
- Use FormRequest properties (`$this->safe()->only([...])`) to hand off to the service.

### 3.3 Models
- Cast columns explicitly in `$casts`: JSON → `array`, booleans → `bool`, dates → `datetime`, money → `decimal:2`.
- Relationships as methods (no `@property` phpdoc if you can help it — types are visible from usage).
- Prefer scopes over static methods for filter reuse (`Product::active()->latest()`).
- Use `$fillable` (never `$guarded = []`).
- Soft-deleted models use the `SoftDeletes` trait.

### 3.4 Services
- Constructor DI — no facades inside services if avoidable (helps testing).
- Method signatures: verb-first (`consume`, `refund`, `assignAwb`).
- Services are stateless (state lives in the DB / session, not the service).
- Wrap external calls (Razorpay, Shiprocket, MSG91) with `try/catch` and log + rethrow a domain exception.

### 3.5 Notifications
- Always `implements ShouldQueue`.
- Never call the notification from inside a DB transaction — dispatch **after** commit.

### 3.6 Migrations
- One migration per change; never edit a merged migration.
- Always add an `up()` **and** a `down()` where possible.
- Add indexes for foreign keys and columns you filter on.
- Use explicit `onDelete()` policies (`cascade`, `restrict`, `set null`).

### 3.7 Routes
- Group related routes; apply middleware at the group level.
- Route model binding wherever possible (`Route::get('/orders/{order}', ...)`).
- Constrain params (`->whereNumber('id')`, `->whereAlpha('slug')`, `->whereAlphaNumeric('orderNo')`) to reduce injection surface.
- Name every route — never link by URL string.

### 3.8 Middleware
- Prefer route-level middleware (`->middleware('throttle:5,1')`) over global middleware for anything not truly global.
- Register aliases in `bootstrap/app.php` (`'admin' => AdminOnly::class`).

---

## 4. Vue / Inertia style

### 4.1 File organisation
- Pages: `resources/js/Pages/{Domain}/{Action}.vue`.
- Components: `resources/js/Components/{ui,common,dashboard}/*.vue`.
- Composables: `resources/js/Composables/use*.js`.
- Utilities: `resources/js/Utils/*.js`.

### 4.2 Component style
- **Composition API only** (`<script setup>`).
- Single-file component with `<template>`, `<script setup>`, `<style scoped>` (scoped styles rare — Tailwind handles most).
- Props typed via `defineProps({...})`.
- Emits declared via `defineEmits([...])`.
- No `data()` — use `ref()` / `reactive()`.

### 4.3 Inertia
- Pass minimal data from controllers (only what the page needs).
- Use `Link` / `router.visit` / `router.post` for navigation and state changes.
- Use `useForm()` for forms with client-side state + server errors.
- Preserve scroll where useful (`{ preserveScroll: true }`).

### 4.4 Styling
- Tailwind CSS 4 with `@custom-variant dark`.
- No inline styles unless dynamic (e.g. progress bars).
- Reusable UI in `Components/ui/`; do not repeat button/input markup.

### 4.5 Naming
- Components: PascalCase (`DataTable.vue`).
- Composables: `useXxx.js`.
- Props: camelCase.
- Events: kebab-case (`@row-click`).

---

## 5. Blade style (storefront)

- Use `@extends('layouts.client')` and `@section`/`@yield`.
- Escape by default with `{{ }}`. Only use `{!! !!}` for admin-controlled content.
- Extract repeated markup into `resources/views/components/*.blade.php` (Blade components) or partials.
- Keep logic minimal — heavy loops or conditions belong in the controller / view composer.
- Use `@csrf` on every form.

---

## 6. JavaScript (storefront jQuery)

The storefront uses jQuery-based `public/client/js/main.js`. Rules for changes:

- No global state — attach handlers scoped to page-specific classes.
- Use `axios` (loaded via Vite) for AJAX where possible; jQuery.ajax only where already established.
- New pages should ideally not add more jQuery; consider inlining a small Vue island for interactivity.

---

## 7. Database / SQL

- Use Eloquent / query builder — never string-concatenate SQL.
- Use `where(['col' => $val])` or `where('col', $val)` — bindings are automatic.
- For raw expressions (`whereRaw`), always pass bindings as the second argument.
- Always index FK columns.
- Money columns: `decimal(12,2)`.
- Currency-independent columns: consider `bigInteger` in the smallest unit if adding multi-currency.

---

## 8. Security must-dos (code review checklist)

- [ ] All state-changing routes require the correct guard middleware.
- [ ] FormRequests validate every input.
- [ ] No `request()->all()` into `Model::create` / `Model::update`.
- [ ] File uploads validated via `CommonHelper::getImageValidationRule` or explicit rule.
- [ ] No `env()` call outside `config/*.php` (breaks `config:cache`).
- [ ] All webhooks verify signatures.
- [ ] All money operations inside `DB::transaction()` with `lockForUpdate()` where relevant.
- [ ] All notifications `implements ShouldQueue`.
- [ ] No secrets in log messages, error responses, or JSON payloads.
- [ ] CSRF token present on all POST/PUT/DELETE from browser.
- [ ] Rate limit applied to any endpoint an attacker can spam.

---

## 9. Git & PR conventions

### 9.1 Branches
- `main` — long-lived, production-ready.
- `master` — current dev branch (see git status).
- Feature branches: `feature/short-slug`.
- Bugfix branches: `fix/short-slug`.
- Hotfix branches: `hotfix/short-slug`.

### 9.2 Commits
- Short (< 72-char subject) + body (wrap at 100).
- Prefix with domain: `payments: fix Razorpay callback race`.
- Use imperative mood: "Add", "Fix", "Update" — not "Added", "Fixed", "Updated".
- One logical change per commit.
- Never commit `vendor/`, `node_modules/`, `.env`, `storage/logs/*.log`, `public/build/*` (Vite output is committed if you want zero-CI deploys, otherwise ignored).

### 9.3 Pull requests
- Small (< 400 LOC diff ideal).
- Include what + why in the description.
- Screenshots for UI changes.
- Manual QA notes for payment/shipping/auth changes (see [21 QA § 3](21-qa-report.md)).
- Reference issue / ticket if any.

### 9.4 Reviews
- Review for correctness first, style second (Pint handles style).
- Ask "what happens if this runs twice?" (idempotency) and "what happens if two users hit this at once?" (race).
- Verify migrations are reversible.
- Verify tests are added (once test suite exists).

---

## 10. Documentation

- README kept short — link to `docs/bmad/README.md` for the full documentation set.
- Every material feature change updates `docs/bmad/` (feature matrix, roadmap, whichever relevant document).
- Every gateway/courier addition adds a section to [10 Payment](10-payment-architecture.md) or [11 Courier](11-courier-architecture.md).
- Every new integration adds a row to [24 Third-Party Integrations](24-third-party-integrations.md).

---

## 11. Anti-patterns to avoid

- `Model::update($request->all())` — mass-assignment risk.
- `dd()` / `dump()` in committed code.
- `try { ... } catch (\Exception $e) {}` without logging.
- `sleep()` in HTTP requests (block worker thread).
- Direct HTTP calls in a controller — use a service.
- `env()` outside config files.
- Editing an already-migrated migration file.
- Committing `.env`.
- `--no-verify` on commits (bypasses hooks).
- `git push --force main`.

---

## 12. Tooling summary

| Tool         | Purpose                     | Command                        |
| ------------ | --------------------------- | ------------------------------ |
| Pint         | PHP formatter               | `vendor/bin/pint`              |
| PHPUnit      | Tests                       | `php artisan test`             |
| Pail         | Log tail                    | `php artisan pail`             |
| Tinker       | REPL                        | `php artisan tinker`           |
| Sail         | Dockerised dev env          | `vendor/bin/sail up`           |
| Vite         | Frontend build              | `npm run dev` / `npm run build`|
| Composer     | PHP deps                    | `composer install`             |
| npm          | JS deps                     | `npm ci`                       |

Recommended additions:
- `phpstan/phpstan` — static analysis.
- `rector/rector` — automated upgrades / cleanups.
- `larastan/larastan` — Laravel-aware PHPStan rules.
