# Contributing

## Branch Strategy
- `main` is stable; feature branches use `feat/*` or `fix/*` prefixes.

## Commit Style
- Follow [Conventional Commits](https://www.conventionalcommits.org/) via commitlint and cz.

## Pull Requests
- Include tests and docs.
- Pass all lint and analysis checks.

## Required Checks
- `composer lint:php`
- `composer analyze:php`
- `composer test:php`
- `npm test`
