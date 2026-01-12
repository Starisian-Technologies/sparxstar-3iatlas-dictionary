# Documentation

## Supported Stack
- WordPress 6.4+
- PHP 8.2+
- Node.js (LTS) for optional asset tooling

## Usage
1. Clone this repo.
2. Rename namespaces and plugin headers.
3. Run `composer install` and `npm install` if using JS tooling.
4. Implement features in `src/` and enqueue assets from `assets/`.

### When to Fork vs. Extend
- **Fork** to create a new plugin.
- **Extend** by importing as a subtree if you need to keep upstream changes.

For architecture details see [ARCHITECTURE.md](../ARCHITECTURE.md). Contribution guidelines live in [CONTRIBUTING.md](../CONTRIBUTING.md).
