module.exports = {
  extends: ['@commitlint/config-conventional'],
  rules: {
    'type-enum': [
      2,
      'always',
      [
        'feat',
        'fix',
        'security',
        'refactor',
        'perf',
        'docs',
        'test',
        'chore',
        'release',
        'hotfix',
      ],
    ],
    'subject-case': [0],
  },
};
