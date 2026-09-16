import js from '@eslint/js';
import globals from 'globals';

export default [
    {
        ignores: ['public/build/**', 'node_modules/**', 'vendor/**'],
    },
    {
        files: ['resources/js/**/*.js', 'public/js/**/*.js', 'scripts/**/*.mjs'],
        ...js.configs.recommended,
        languageOptions: {
            ecmaVersion: 'latest',
            sourceType: 'module',
            globals: {
                ...globals.browser,
                ...globals.node,
                bootstrap: 'readonly',
                Chart: 'readonly',
                Swal: 'readonly',
                Turbo: 'readonly',
            },
        },
        rules: {
            'no-undef': 'off',
            'no-unused-vars': 'off',
            'no-empty': ['error', { allowEmptyCatch: true }],
            'no-eval': 'error',
            'no-implied-eval': 'error',
            'no-new-func': 'error',
        },
    },
];
