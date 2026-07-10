# Contributing to WebBlocks CMS

Thanks for your interest in improving WebBlocks CMS! This guide explains how to set up
a development environment, the standards we hold code to, and how to propose changes.

By participating, you agree to abide by our [Code of Conduct](CODE_OF_CONDUCT.md).

## Reporting Bugs & Requesting Features

- Search [existing issues](https://github.com/fklavyenet/webblocks-cms/issues) first.
- Open a **Bug report** or **Feature request** using the issue templates.
- For security vulnerabilities, **do not** open a public issue — follow
  [SECURITY.md](SECURITY.md) instead.

## Development Setup

Requirements:

- PHP **8.4+**
- Composer 2
- SQLite (used by the test suite; no external database required for tests)

```bash
git clone https://github.com/fklavyenet/webblocks-cms.git
cd webblocks-cms
composer install
cp .env.example .env
php artisan key:generate
php artisan migrate
php artisan serve
```

See [docs/installation.md](docs/installation.md) and
[DEVELOPMENT.md](DEVELOPMENT.md) for the full setup and architecture notes, and
[docs/testing-strategy.md](docs/testing-strategy.md) for how the test suite is organized.

## Making Changes

1. **Create a branch** off `main` (e.g. `fix/contact-form-validation`). Do not commit
   directly to `main`.
2. Keep pull requests **focused** — one logical change per PR is much easier to review.
3. Match the surrounding code style, naming, and structure. This project has no
   frontend build step (no Vite/Tailwind/npm) — do not introduce one.
4. Add or update **tests** for any behavior change.
5. Update relevant **docs** under `docs/` and the `CHANGELOG.md` when appropriate.

## Before You Submit

Run these locally and make sure they pass — CI runs the same checks:

```bash
composer format      # auto-fix code style with Pint
composer format:test # verify code style (what CI checks)
composer test        # run the test suite
```

## Pull Requests

- Fill out the PR template describing **what** changed and **why**.
- Link any related issues (e.g. `Closes #123`).
- Ensure CI is green. PRs with failing tests or style checks will not be merged.
- Be responsive to review feedback; maintainers may request changes before merging.

## Commit Messages

Write clear, imperative-mood commit subjects (e.g. "Fix contact form recipient
fallback"). Explain the reasoning in the body when the change is non-trivial.

## Trademark Note

The code is MIT licensed, but the "WebBlocks CMS" name and logos are not (see the
Trademark section in the [README](README.md)). Contributions are accepted under the
MIT license; forks and derived products must remove or replace the branding.

## Questions

Open a [Discussion](https://github.com/fklavyenet/webblocks-cms/discussions) or a
regular issue if you are unsure about anything before investing time in a change.
