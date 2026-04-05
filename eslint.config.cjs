const eslint = require('@eslint/js')
const tseslint = require('typescript-eslint')

module.exports = [
  // {
  //   extends: ['@nextcloud'],
  //   rules: {
  //     'jsdoc/require-jsdoc': 'off',
  //     'vue/first-attribute-linebreak': 'off',
  //   },
  // },
  ...tseslint.config(eslint.configs.recommended, ...tseslint.configs.recommended),
  {
    rules: {
      'no-unused-vars': ['warn', { argsIgnorePattern: '^_', varsIgnorePattern: '^_' }],
      '@typescript-eslint/no-unused-vars': [
        'warn',
        { argsIgnorePattern: '^_', varsIgnorePattern: '^_' },
      ],
    },
  },
  {
    ignores: ['node_modules/', 'build/', 'dist/', 'gen/'],
  },
]
