module.exports = {
    root: true,
    env: {
        browser: true,
        es2021: true,
        jquery: true,
        node: true
    },
    extends: [
        'eslint:recommended',
        'plugin:@wordpress/eslint-plugin/recommended'
    ],
    parserOptions: {
        ecmaVersion: 12,
        sourceType: 'module'
    },
    rules: {
        'camelcase': ['error', {
            properties: 'never',
            ignoreDestructuring: true,
            ignoreImports: true,
            ignoreGlobals: true
        }],
        'comma-dangle': ['error', 'never'],
        'indent': ['error', 4],
        'linebreak-style': ['error', 'unix'],
        'no-console': ['warn', { allow: ['warn', 'error'] }],
        'quotes': ['error', 'single'],
        'semi': ['error', 'always'],
        'space-before-function-paren': ['error', 'never'],
        'space-before-blocks': ['error', 'always'],
        'space-in-parens': ['error', 'never'],
        'space-infix-ops': ['error', { int32Hint: false }],
        'space-unary-ops': ['error', {
            words: true,
            nonwords: false
        }],
        'spaced-comment': ['error', 'always'],
        'yoda': ['error', 'never'],
        '@wordpress/no-unsafe-wp-apis': 'warn',
        '@wordpress/no-unused-vars-before-return': 'error',
        '@wordpress/no-var-expressions-in-functions': 'error',
        '@wordpress/no-wp-apis': 'warn'
    },
    globals: {
        wp: 'readonly',
        dpoAdmin: 'readonly'
    }
}; 