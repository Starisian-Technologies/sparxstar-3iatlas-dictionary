
<img width="1280" height="640" alt="3iatlas" src="https://github.com/user-attachments/assets/6a8c2659-a371-4298-8a23-b5e16e7c7472" />

# SPARXSTAR 3IAtlas Dictionary

A comprehensive WordPress plugin for managing dictionary entries with full WPGraphQL and SCF (Secure Custom Fields) integration. This plugin provides a dynamic React-based frontend dictionary interface and a robust backend management system.

[![CodeQL](https://github.com/Starisian-Technologies/sparxstar-3iatlas-dictionary/actions/workflows/github-code-scanning/codeql/badge.svg)](https://github.com/Starisian-Technologies/sparxstar-3iatlas-dictionary/actions/workflows/github-code-scanning/codeql) [![Copilot code review](https://github.com/Starisian-Technologies/sparxstar-3iatlas-dictionary/actions/workflows/copilot-pull-request-reviewer/copilot-pull-request-reviewer/badge.svg)](https://github.com/Starisian-Technologies/sparxstar-3iatlas-dictionary/actions/workflows/copilot-pull-request-reviewer/copilot-pull-request-reviewer) [![Copilot coding agent](https://github.com/Starisian-Technologies/sparxstar-3iatlas-dictionary/actions/workflows/copilot-swe-agent/copilot/badge.svg)](https://github.com/Starisian-Technologies/sparxstar-3iatlas-dictionary/actions/workflows/copilot-swe-agent/copilot) [![Dependabot Updates](https://github.com/Starisian-Technologies/sparxstar-3iatlas-dictionary/actions/workflows/dependabot/dependabot-updates/badge.svg)](https://github.com/Starisian-Technologies/sparxstar-3iatlas-dictionary/actions/workflows/dependabot/dependabot-updates)

[![Release Code Quality Final Review](https://github.com/Starisian-Technologies/sparxstar-3iatlas-dictionary/actions/workflows/release.yml/badge.svg)](https://github.com/Starisian-Technologies/sparxstar-3iatlas-dictionary/actions/workflows/release.yml) [![Security Checks](https://github.com/Starisian-Technologies/sparxstar-3iatlas-dictionary/actions/workflows/security.yml/badge.svg)](https://github.com/Starisian-Technologies/sparxstar-3iatlas-dictionary/actions/workflows/security.yml)

## 🚀 Features

* **Dictionary Management**: Custom Post Type (`aiwa_cpt_dictionary`) for managing word entries.
* **Rich Taxonomies**: Categorize words by:
  * Language (`starmus_tax_language`)
  * Dialect (`starmus_tax_dialect`)
  * Part of Speech (`starmus_part_of_speech`)
  * Alphabetical Grouping (`aiwa-alpha-letter`)
* **GraphQL Integration**: Full schema exposure for headless or decoupled applications.
* **SCF Integration**: Dynamic schema generation based on `acf-json` configurations.
* **React Frontend**: Built-in, high-performance dictionary viewer with:
  * Virtual scrolling for large datasets.
  * Instant search with debouncing.
  * Audio pronunciation support.
  * English/French toggles.
* **Frontend Submission**: Shortcode-based form for contributors to suggest new entries.

## 📋 Requirements

* **PHP**: 8.2 or higher
* **WordPress**: 6.4 or higher
* **Plugins**:
  * WPGraphQL (Required for API)
  * Secure Custom Fields (SCF) PRO or Free (Required for data structure)

## 🛠️ Installation

1. Clone or download the repository into your `wp-content/plugins/` directory.
2. Install dependencies and build the assets:

    ```bash
    npm install
    npm run build
    ```

3. Activate the plugin through the WordPress admin interface.
4. Ensure SCF and WPGraphQL are installed and active.
5. Navigate to **Custom Field > Tools > Sync** to ensure the fields are correctly registered from `acf-json`.

## 💻 Usage

For detailed guides and notes, see the [docs/](docs/) directory.

### Shortcodes

**Frontend Dictionary**
Enable users to browse the dictionary. Words are displayed alphabetically with
an A-Z index to quickly jump to words beginning with the selected letter. When
clicked, the definition, proununciation, example sentences, audio, images and
more are displayed for each word. Users can scroll by word or scroll the full
view, just like in a printed dictionary.

```shortcode
[sparxstar_dictionary]
```

**Frontend Submission Form**
Enable logged-in users (Contributors/Editors) to add or edit dictionary entries 
directly from the frontend.

```shortcode
[sparxstar_dictionary_form]
```

**Attributes:**

* `entry_id` (optional): The ID of the dictionary entry to edit.

Example:

```shortcode
[sparxstar_dictionary_form entry_id="123"]
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

* **Root**: `sparxstar-3iatlas-dictionary.php` (Bootloader)
* **Core Logic**: `src/core/SparxstarIAtlasOrchestrator.php`
* **Post Types**: `src/includes/SparxstarIAtlasPostTypes.php`
* **Frontend**: `src/js/app.jsx` (React Application)

### Build Commands

* `npm run build`: Compile and minify assets for production.
* `npm run watch`: specific watch command for development.
* `npm run lint`: Run ESLint and Stylelint.

## 🤝 Contributing

1. Follow the [PSR-4](https://www.php-fig.org/psr/psr-4/) coding standard for PHP.
2. Ensure React components in `src/js` are functional and use hooks.

## 📄 License

License: Starisian Technologies Proprietary License (STPD)
Copyright (c) 2026 Starisian Technologies. All rights reserved.

SPARXSTAR and Starisian Technologies is a trademark of Starisian Technologies.  

NOTICE: All information contained herein is, and remains, the property of 
**Starisian Technologies** and its suppliers, if any. The intellectual 
and technical concepts contained herein are proprietary to Starisian 
Technologies and its suppliers and may be protected by U.S. and 
international copyright, trade secret, and patent laws, including 
patents in process.

Unauthorized reproduction, redistribution, transmission, or disclosure 
of any part of this repository is strictly prohibited without prior 
written consent from Starisian Technologies.

