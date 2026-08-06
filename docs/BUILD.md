# Build — Saltus Framework Demo

## Prerequisites

- PHP 8.3 or newer
- Composer 2
- WordPress (with Composer-installed plugin dependencies)
- Node.js 18+ and npm (for PostCSS minification only)

## Setup

```bash
# Clone and enter the repository
git clone git@github.com:SaltusDev/framework-demo.git
cd framework-demo

# Install PHP dependencies (including framework)
composer install

# Install Node dependencies (for CSS minification)
npm install
```

Then activate **Saltus Framework Demo** in the WordPress admin.

## Development

| Command | Description |
|---------|-------------|
| `composer validate --strict` | Validate composer.json schema |
| `composer test:lint` | Run PHPCS (WPCS 3.3) |
| `composer test:unit` | Run PHPUnit tests |
| `composer tests` | Run lint then unit tests |
| `composer make-pot` | Generate/update the .pot translation template |
| `composer minify-css` | Minify CSS via PostCSS |
| `composer prefix-namespaces` | Run Strauss to rebuild vendor-prefixed/ |
| `npm run minify-css` | Minify CSS via PostCSS (npm equivalent) |

## Build

```bash
# Build release ZIP
composer package
```

Output: `dist/framework-demo-<version>.zip`

## Test

```bash
vendor/bin/phpunit
vendor/bin/phpcs
```

## Deploy

1. Build release ZIP with dev dependencies excluded:
   ```bash
   composer install --no-dev --optimize-autoloader
   composer package
   ```
2. Upload the ZIP from `dist/` via **Plugins > Add New > Upload Plugin** in WordPress admin
3. Or upload manually to `wp-content/plugins/` and extract, then activate

## Troubleshooting

| Issue | Solution |
|-------|----------|
| "Missing Composer dependencies" notice | Run `composer install` from the plugin directory |
| PHP 8.3 requirement error | Upgrade the server to PHP 8.3 or higher |
| Framework classes not found | Run `composer prefix-namespaces` to rebuild vendor-prefixed/ |
| Rebrand ZIP download fails | Ensure the PHP `zip` extension is installed |
| CSS not updating | Run `npm run minify-css` or `composer minify-css` and clear browser cache |
