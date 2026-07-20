<?php
/**
 * Create a distributable ZIP for the demo plugin.
 */

require_once __DIR__ . '/PackageBuilder.php';

( new PackageBuilder() )->run();
