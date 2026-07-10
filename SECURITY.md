# Security Policy

WebBlocks CMS is a content management system that people run in production, including
agencies hosting client sites. We take security reports seriously and appreciate
responsible disclosure.

## Supported Versions

Security fixes are applied to the latest released minor version. Please make sure you
are on the most recent release before reporting, and keep production installs updated.

| Version   | Supported          |
| --------- | ------------------ |
| latest `1.x` | :white_check_mark: |
| older `1.x`  | :x: (upgrade first) |

## Reporting a Vulnerability

**Please do not open a public GitHub issue for security vulnerabilities.**

Report privately through one of these channels:

1. **GitHub Security Advisories (preferred):** open the repository's
   [Security tab](https://github.com/fklavyenet/webblocks-cms/security/advisories/new)
   and choose "Report a vulnerability". This keeps the report private until a fix ships.
2. **Contact the maintainer** via https://fklavye.net.

<!-- Maintainer: consider adding a dedicated security inbox (e.g. security@fklavye.net)
     and listing it above so reporters have a direct channel. -->

When reporting, please include:

- a description of the vulnerability and its impact,
- the affected version(s) and configuration,
- clear steps to reproduce (proof-of-concept if possible),
- any suggested remediation you may have.

## What to Expect

- We aim to acknowledge new reports within **5 business days**.
- We will confirm the issue, keep you updated on remediation progress, and coordinate
  a disclosure timeline with you.
- With your permission, we will credit you in the release notes once a fix is published.

## Scope

In scope: the WebBlocks CMS code in this repository (core package, install/update flow,
Internal Content API, admin surfaces, first-party plugins).

Out of scope: vulnerabilities in third-party dependencies (report those upstream),
issues that require physical access or a pre-compromised server, and findings on
installations you do not own or have permission to test.

Thank you for helping keep WebBlocks CMS and its users safe.
