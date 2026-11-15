# Dependency audit — 2026-11-15

## Scope and result

The installed Composer dependency graph was audited, including development dependencies and the full Laravel framework installed through Testbench. The original local lock file had **38 advisory matches across 10 packages**: 1 critical, 12 high, 15 medium, 3 low, and 7 without a severity supplied by the advisory database. No abandoned packages were reported.

After updating to the latest stable versions compatible with the supported platforms, `composer audit --locked` reports **zero advisories and zero abandoned packages** in every tested configuration. These results describe the resolved dependency versions; they do not establish whether a vulnerable feature was reachable in a consuming application.

The original graph included [CVE-2026-114509, a critical Laravel TokenGuard authentication bypass](https://github.com/advisories/GHSA-f9x3-7vq9-cph6). It also included advisories affecting HTTP handling, Markdown parsing, filesystem path handling, and Symfony components.

## Dependency updates

| Package | Original local version | Updated local version |
| --- | --- | --- |
| `laravel/framework` | 13.16.1 | 13.42.0 |
| `guzzlehttp/guzzle` | 7.12.0 | 8.5.0 |
| `guzzlehttp/psr7` | 2.12.0 | 3.4.0 |
| `league/commonmark` | 2.8.2 | 2.10.7 |
| `league/flysystem` | 3.34.0 | 3.36.0 |
| `symfony/http-foundation`, `http-kernel`, `routing`, `yaml` | 8.1.0 | 8.1.11 |
| `symfony/mime` | 8.1.0 | 8.1.10 |
| `spatie/laravel-package-tools` | 1.93.1 | 1.93.3 |
| `webmozart/assert` | 1.12.1 | 2.5.1 |
| `laravel/pint` | 1.29.3 | 1.32.3 |
| `mockery/mockery` | 1.6.12 | 1.6.15 |
| `orchestra/testbench` | 11.x-dev | 11.3.0 stable |
| `phpunit/phpunit` | 12.5.30 | 13.4.7 |

The `atldays/laravel-eloquent-filters` 2.1.0 and `atldays/laravel-eloquent-query-cache` 1.1.0 releases remain the latest stable versions available at the time of the audit.

The root Composer constraints still support PHP `^8.2` and Laravel `^12.0|^13.0`. The README previously also listed Laravel 10 and 11, although neither was accepted by the existing Composer constraints; the README now matches the manifest. Laravel 13 requires PHP 8.3 or later.

Testbench now uses stable `^10.12|^11.3` releases. PHPUnit 13 is allowed alongside compatible PHPUnit 11 and 12 releases. Assert now requires `^2.5`, which supports the package's PHP 8.2 minimum. Applications that require Assert 1 must update that constraint before installing this package version. Pint resolves to 1.30.4 on PHP 8.2 and 1.32.3 on PHP 8.3 or later.

## Verification

Each row used a separate dependency installation on the actual PHP runtime, without ignoring platform requirements. Every row passed syntax checks, Composer validation, platform checks, **62 tests / 133 assertions**, and the dependency audit.

| PHP | Laravel | Testbench | PHPUnit | Assert | Audit |
| --- | --- | --- | --- | --- | --- |
| 8.2.35 | 12.72.0 | 10.12.0 | 11.5.58 | 2.5.1 | Clean |
| 8.3.36 | 12.72.0 | 10.12.0 | 12.5.44 | 2.5.1 | Clean |
| 8.4.27 | 12.72.0 | 10.12.0 | 13.1.14 | 2.5.1 | Clean |
| 8.5.12 | 12.72.0 | 10.12.0 | 13.1.14 | 2.5.1 | Clean |
| 8.3.36 | 13.42.0 | 11.3.0 | 12.5.44 | 2.5.1 | Clean |
| 8.4.27 | 13.42.0 | 11.3.0 | 13.4.7 | 2.5.1 | Clean |
| 8.5.12 | 13.42.0 | 11.3.0 | 13.4.7 | 2.5.1 | Clean |

The updated local installation also passed Pint for all 85 PHP files and `composer outdated --direct --strict`. The GitHub Actions workflow passed `actionlint`. CI now includes the seven configurations above, Composer validation, and explicit dependency audits; its token permissions are limited to reading repository contents. The updated workflow has been validated locally and has not been run on GitHub as part of this audit.

CI also updates `actions/checkout` from v4 to v7 and `actions/cache` from v4 to v6. Both actions declare the Node.js 24 runtime, addressing the Node.js 20 deprecation warnings. The existing `shivammathur/setup-php@v2` action already declares Node.js 24.

`composer.lock` remains ignored, as it was before this work. Library consumers resolve dependencies using their own lock files, so these local updates do not update already installed applications. The package's PHP source and public API were unchanged.
