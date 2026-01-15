
<img width="1280" height="640" alt="3iatlas" src="https://github.com/user-attachments/assets/6a8c2659-a371-4298-8a23-b5e16e7c7472" />

# SPARXSTAR 3IAtlas Dictionary

A comprehensive WordPress plugin for managing dictionary entries with full WPGraphQL and SCF (Secure Custom Fields) integration. This plugin provides a dynamic React-based frontend dictionary interface and a robust backend management system.

[![CodeQL](https://github.com/Starisian-Technologies/sparxstar-3iatlas-dictionary/actions/workflows/github-code-scanning/codeql/badge.svg)](https://github.com/Starisian-Technologies/sparxstar-3iatlas-dictionary/actions/workflows/github-code-scanning/codeql) [![Copilot code review](https://github.com/Starisian-Technologies/sparxstar-3iatlas-dictionary/actions/workflows/copilot-pull-request-reviewer/copilot-pull-request-reviewer/badge.svg)](https://github.com/Starisian-Technologies/sparxstar-3iatlas-dictionary/actions/workflows/copilot-pull-request-reviewer/copilot-pull-request-reviewer) [![Copilot coding agent](https://github.com/Starisian-Technologies/sparxstar-3iatlas-dictionary/actions/workflows/copilot-swe-agent/copilot/badge.svg)](https://github.com/Starisian-Technologies/sparxstar-3iatlas-dictionary/actions/workflows/copilot-swe-agent/copilot) [![Dependabot Updates](https://github.com/Starisian-Technologies/sparxstar-3iatlas-dictionary/actions/workflows/dependabot/dependabot-updates/badge.svg)](https://github.com/Starisian-Technologies/sparxstar-3iatlas-dictionary/actions/workflows/dependabot/dependabot-updates) 

[![Release Code Quality Final Review](https://github.com/Starisian-Technologies/sparxstar-3iatlas-dictionary/actions/workflows/release.yml/badge.svg)](https://github.com/Starisian-Technologies/sparxstar-3iatlas-dictionary/actions/workflows/release.yml) [![Security Checks](https://github.com/Starisian-Technologies/sparxstar-3iatlas-dictionary/actions/workflows/security.yml/badge.svg)](https://github.com/Starisian-Technologies/sparxstar-3iatlas-dictionary/actions/workflows/security.yml)

## 🚀 Features

*   **Dictionary Management**: Custom Post Type (`aiwa_cpt_dictionary`) for managing word entries.
*   **Rich Taxonomies**: Categorize words by:
    *   Language (`starmus_tax_language`)
    *   Dialect (`starmus_tax_dialect`)
    *   Part of Speech (`starmus_part_of_speech`)
    *   Alphabetical Grouping (`aiwa-alpha-letter`)
*   **GraphQL Integration**: Full schema exposure for headless or decoupled applications.
*   **SCF Integration**: Dynamic schema generation based on `acf-json` configurations.
*   **React Frontend**: Built-in, high-performance dictionary viewer with:
    *   Virtual scrolling for large datasets.
    *   Instant search with debouncing.
    *   Audio pronunciation support.
    *   English/French toggles.
*   **Frontend Submission**: Shortcode-based form for contributors to suggest new entries.

## 📋 Requirements

*   **PHP**: 8.2 or higher
*   **WordPress**: 6.4 or higher
*   **Plugins**:
    *   WPGraphQL (Required for API)
    *   Secure Custom Fields (SCF) PRO or Free (Required for data structure)

## 🛠️ Installation

1.  Clone or download the repository into your `wp-content/plugins/` directory.
2.  Install dependencies and build the assets:
    ```bash
    npm install
    npm run build
    ```
3.  Activate the plugin through the WordPress admin interface.
4.  Ensure SCF and WPGraphQL are installed and active.
5.  Navigate to **Custom Field > Tools > Sync** to ensure the fields are correctly registered from `acf-json`.

## 💻 Usage

### Shortcodes

**Frontend Submission Form**
Enable logged-in users (Contributors/Editors) to add or edit dictionary entries directly from the frontend.

```shortcode
[aiwa_dictionary_form]
```

**Attributes:**
*   `entry_id` (optional): The ID of the dictionary entry to edit.

Example:
```shortcode
[aiwa_dictionary_form entry_id="123"]
```

### GraphQL API

The plugin registers a `dictionaries` root query in the GraphQL schema.

**Sample Query:**
```graphql
query GetDictionaryEntries {
  dictionaries(first: 10) {
    edges {
      node {
        title
        aiwaWordDetails {
          aiwaTranslationEnglish
          aiwaTranslationFrench
          aiwaPartOfSpeech
          aiwaExampleSentences {
            aiwaSentence
            aiwaSTranslationEnglish
          }
        }
        starmusTaxLanguages {
          nodes {
            name
          }
        }
      }
    }
  }
}
```

## 🏗️ Development

### Architecture
*   **Root**: `sparxstar-3iatlas-dictionary.php` (Bootloader)
*   **Core Logic**: `src/core/SparxstarIAtlasOrchestrator.php`
*   **Post Types**: `src/includes/SparxstarIAtlasPostTypes.php`
*   **Frontend**: `src/js/app.jsx` (React Application)

### Build Commands
*   `npm run build`: Compile and minify assets for production.
*   `npm run watch`: specific watch command for development.
*   `npm run lint`: Run ESLint and Stylelint.

## 🤝 Contributing

1.  Follow the [PSR-4](https://www.php-fig.org/psr/psr-4/) coding standard for PHP.
2.  Ensure React components in `src/js` are functional and use hooks.

## 📄 License

Proprietary - Starisian Technologies.

For detailed guides and notes, see the [docs/](docs/) directory.
