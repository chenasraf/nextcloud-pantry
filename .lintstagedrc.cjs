module.exports = {
  '*.{ts,vue}': ['eslint --fix'],
  '*.{scss,vue,ts,md}': ['prettier --write'],
  '*.json': (files) => {
    const filtered = files.filter(file => !file.includes('openapi.json'))
    return filtered.length > 0 ? `prettier --write ${filtered.join(' ')}` : []
  },
  '*.php': (files) => {
    const nonGenFiles = files.filter(file => !file.includes('/gen/'))
    const commands = []
    if (nonGenFiles.length > 0) {
      commands.push('make php-cs-fixer', 'make test')
    }
    return commands
  },
  '*Controller.php': [() => 'make openapi', () => 'git add openapi.json'],
}
