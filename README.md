
<img width="1280" height="640" alt="3iatlas" src="https://github.com/user-attachments/assets/968c1c4c-6ee0-4883-9f02-596c4709b7e9" />

# SPARXSTAR 3iAtlas Dictionary

A WordPress plugin for dictionary management with Smart Custom Fields (SCF) and WPGraphQL integration.

## Quick Start
1. Clone this repo.
2. Run `composer install`.
3. Run `npm install`.
4. Install and activate required plugins:
   - Smart Custom Fields (SCF)
   - WPGraphQL
5. Activate the plugin in WordPress.

## Plugin Structure

- `sparxstar-3iatlas-dictionary.php` - Main plugin file
- `Sparxstar3IAtlasDictionary.php` - Main orchestrator class
- `src/core/` - Core plugin functionality
- `src/js/` - JavaScript files
- `src/css/` - CSS stylesheets
- `src/includes/` - Helper classes and utilities
- `src/templates/` - Template files
- `tailwind.config.js` - Tailwind CSS configuration

## Dependencies

### Required WordPress Plugins
- **Smart Custom Fields (SCF)** - For custom field management
- **WPGraphQL** - For GraphQL API support

For detailed guides and notes, see the [docs/](docs/) directory.
