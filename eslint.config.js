const js = require('@eslint/js');
const globals = require('globals');
const prettierRecommended = require('eslint-plugin-prettier/recommended');
const reactPlugin = require('eslint-plugin-react');
const reactHooksPlugin = require('eslint-plugin-react-hooks');

module.exports = [
    js.configs.recommended,
    prettierRecommended,
    {
        files: ['**/*.js', '**/*.jsx'],
        languageOptions: {
            ecmaVersion: 2021,
            sourceType: 'module',
            parserOptions: {
                ecmaFeatures: {
                    jsx: true,
                },
            },
            globals: {
                ...globals.browser,
                ...globals.node,
                ...globals.es2021,
                wp: 'readonly',
                jQuery: 'readonly',
                aiwaDict: 'readonly',
            },
        },
        plugins: {
            react: reactPlugin,
            'react-hooks': reactHooksPlugin,
        },
        rules: {
            ...reactPlugin.configs.recommended.rules,
            'react-hooks/rules-of-hooks': 'error',
            'react-hooks/exhaustive-deps': 'warn',
            'react/react-in-jsx-scope': 'off',
            'react/prop-types': 'off',
            'no-unused-vars': ['warn', { argsIgnorePattern: '^_' }],
        },
        settings: {
            react: {
                version: 'detect',
            },
        },
    },
    {
        ignores: [
            'node_modules/',
            'dist/',
            'vendor/',
            'assets/',
            'docs/js/scripts/third-party/',
            '**/*.min.js',
            'dest_test/',
            'build_test/',
        ],
    },
];
