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
| `php bin/bump.php <type>` | Bump plugin version (patch/minor/major/semver) |
| `npm run minify-css` | Minify CSS via PostCSS (npm equivalent) |

### MCP tool validation

`bin/validate-mcp-tools.php` exercises the `saltus-framework/v1` MCP/REST tools against a live site:

```bash
php bin/validate-mcp-tools.php https://example.test --user=admin --app-pass='xxxx xxxx xxxx xxxx' --readonly --verbose
```

Run without `--readonly` to also exercise write tools (duplicate/export/reorder).

## Build

```bash
# Build release ZIP
composer package
```

Output: `dist/framework-demo-<version>.zip`

> Note: `composer.json` points `saltus/framework` at the `dev-feature/mcp-v1` VCS branch for the in-progress MCP features. Restore a tagged constraint before production release.

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
