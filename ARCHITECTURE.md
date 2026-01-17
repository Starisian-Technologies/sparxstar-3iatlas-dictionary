# Architecture Overview

This template outlines system boundaries, module layout, data flow, and a high level threat model for Starisian WordPress plugins.

## System Boundaries

- PHP 8.2+ and WordPress 6.4+.
- Optional Node toolchain for asset builds.

## Module Layout

- `plugin-entry.php` bootstrap.
- `src/` for PSR-4 classes under `Starisian\src\`.
- `assets/`, `schemas/`, and `data/` for optional resources.

## Data Flow

Requests enter through WordPress hooks, are routed through the core singleton, and fan out to feature modules. External API calls must be sanitized and escaped.

## Threat Model

- Input validation at every boundary.
- Nonces for form and AJAX submissions.
- Escaped output to prevent XSS.

## Performance Expectations

- Load assets only when required.
- Keep front-end bundles small for low bandwidth environments.
