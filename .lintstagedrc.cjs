module.exports = {
  '*.{ts,vue}': ['eslint --fix'],
  '*.{scss,vue,ts,md,json}': ['prettier --write'],
  '*.php': [
    'php vendor-bin/cs-fixer/vendor/php-cs-fixer/shim/php-cs-fixer.phar --config=.php-cs-fixer.dist.php fix',
  ],
  'ApiController.php': [() => 'make openapi'],
}
