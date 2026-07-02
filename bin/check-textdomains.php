<?php
/**
 * Validate that all i18n calls use the correct text domain.
 *
 * Usage: php bin/check-textdomains.php
 */

$root = dirname( __DIR__ );
$text_domain = 'framework-demo';

$i18n_functions = array(
	'__',
	'_e',
	'_n',
	'_x',
	'_ex',
	'_nx',
	'esc_html__',
	'esc_html_e',
	'esc_html_x',
	'esc_attr__',
	'esc_attr_e',
	'esc_attr_x',
	'_n_noop',
	'_nx_noop',
	'translate',
	'translate_with_gettext_context',
	'ngettext',
	'ngettext_with_context',
);

$pattern = '/\b(' . implode( '|', array_map( 'preg_quote', $i18n_functions ) ) . ')\s*\(\s*[^,]+,\s*([\'"])([^\'"]+)\2\s*[),]/';

$iterator = new RecursiveIteratorIterator(
	new RecursiveDirectoryIterator( $root, FilesystemIterator::SKIP_DOTS ),
	RecursiveIteratorIterator::SELF_FIRST
);

$excluded_dirs = array(
	'build', 'dist', 'node_modules', 'vendor', 'vendor-prefixed',
	'release', 'reports', 'tests', '.git',
);

$errors = array();

foreach ( $iterator as $item ) {
	if ( ! $item->isFile() || ! str_ends_with( $item->getFilename(), '.php' ) ) {
		continue;
	}

	$relative = substr( $item->getPathname(), strlen( $root ) + 1 );
	$parts    = explode( DIRECTORY_SEPARATOR, $relative );

	if ( array_intersect( $parts, $excluded_dirs ) ) {
		continue;
	}

	$contents = file_get_contents( $item->getPathname() );

	if ( false === preg_match_all( $pattern, $contents, $matches, PREG_OFFSET_CAPTURE ) ) {
		continue;
	}

	foreach ( $matches[3] as $i => $domain_match ) {
		$domain = $domain_match[0];
		if ( $domain !== $text_domain ) {
			$line_number = substr_count( substr( $contents, 0, $matches[0][ $i ][1] ), "\n" ) + 1;
			$errors[] = sprintf(
				'%s:%d — "%s" uses text domain "%s", expected "%s"',
				$relative,
				$line_number,
				$matches[1][ $i ][0] . '()',
				$domain,
				$text_domain
			);
		}
	}
}

if ( empty( $errors ) ) {
	echo "All i18n calls use the correct text domain.\n";
	exit( 0 );
}

foreach ( $errors as $error ) {
	fwrite( STDERR, $error . "\n" );
}

fwrite( STDERR, sprintf( "\n%d i18n call(s) with incorrect text domain found.\n", count( $errors ) ) );
exit( 1 );
