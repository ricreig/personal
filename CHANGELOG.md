# Changelog

## [Unreleased]
### Added
- AUDIT tooling (`tools/run_php_lint.sh`, scanners, endpoint probe) and PHPUnit tests for `lib/vacaciones_calc.php`.
- Diagnostic API responses (`api/diag_env.php`, `api/db_ping.php`) documented in AUDIT procedures.

### Changed
- Normalised API endpoints for prestaciones (PECO, TXT, Vacaciones, Incapacidades) to use consistent parameter binding, JSON headers and station guards.
- Reworked vacaciones calculation helpers to model VAC/PR/ANT assignments, carry-over flags and formatted control numbers.
- Refreshed `public/prestaciones.php` layout and `public/assets/prestaciones.js` logic for stable persona/año switching, filter handling, credentialed fetches and deterministic table rendering.
- Hardened session guard utilities and API ping script to keep `declare(strict_types=1)` at the top of each file.

### Removed
- Legacy front-end behaviours that relied on implicit API bases or unchecked station filters.
