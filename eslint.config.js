const js = require('@eslint/js');
const globals = require('globals');
const prettierRecommended = require('eslint-plugin-prettier/recommended');

module.exports = [
    js.configs.recommended,
    prettierRecommended,
    {
        languageOptions: {
            ecmaVersion: 12,
            sourceType: 'commonjs', // Defaulting to commonjs as per package.json type
            globals: {
                ...globals.browser,
                ...globals.node,
                ...globals.es2021,
            },
        },
        rules: {
            // Custom rules can go here
        },
    },
    {
        ignores: ['node_modules/', 'dist/'],
    },
];
