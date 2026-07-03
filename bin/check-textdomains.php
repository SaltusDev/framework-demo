<?php
/**
 * Validate that all i18n calls use the correct text domain.
 *
 * Uses PHP's built-in token_get_all() which handles:
 * - Multi-line strings
 * - Commas inside translation strings
 * - Escaped quotes
 * - Variable argument positions per function
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

$domain_arg_map = array(
	'__'                          => 2,
	'_e'                          => 2,
	'_n'                          => 4,
	'_x'                          => 3,
	'_ex'                         => 3,
	'_nx'                         => 5,
	'esc_html__'                  => 2,
	'esc_html_e'                  => 2,
	'esc_html_x'                  => 3,
	'esc_attr__'                  => 2,
	'esc_attr_e'                  => 2,
	'esc_attr_x'                  => 3,
	'_n_noop'                     => 3,
	'_nx_noop'                    => 4,
	'translate'                   => 2,
	'translate_with_gettext_context' => 3,
	'ngettext'                    => 4,
	'ngettext_with_context'       => 5,
);

$function_names = array_flip( $i18n_functions );

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

	$source  = file_get_contents( $item->getPathname() );
	if ( $source === false ) {
		continue;
	}
	$tokens  = @token_get_all( $source );
	$t_count = count( $tokens );

	for ( $i = 0; $i < $t_count; $i++ ) {
		if ( ! is_array( $tokens[ $i ] ) || $tokens[ $i ][0] !== T_STRING ) {
			continue;
		}

		$function = $tokens[ $i ][1];

		if ( ! isset( $function_names[ $function ] ) ) {
			continue;
		}

		// Skip method calls and static calls: $obj->method() or Class::method()
		if ( $i > 0 ) {
			$prev = $tokens[ $i - 1 ];
			if ( ( is_array( $prev ) && (
					$prev[0] === T_OBJECT_OPERATOR
					|| $prev[0] === T_DOUBLE_COLON
					|| ( defined( 'T_NULLSAFE_OBJECT_OPERATOR' ) && $prev[0] === T_NULLSAFE_OBJECT_OPERATOR )
				) )
				|| $prev === '->'
				|| $prev === '::'
				|| $prev === '?->'
			) {
				continue;
			}
		}

		// Locate opening parenthesis (skipping whitespace/comments).
		$j = $i + 1;
		while ( $j < $t_count && is_array( $tokens[ $j ] )
			&& in_array( $tokens[ $j ][0], array( T_WHITESPACE, T_COMMENT, T_DOC_COMMENT ), true ) ) {
			$j++;
		}

		if ( $j >= $t_count || $tokens[ $j ] !== '(' ) {
			continue;
		}

		$func_line     = $tokens[ $i ][2];
		$expected_arg  = $domain_arg_map[ $function ];
		$depth         = 1;
		$args          = array( '' );
		$arg_idx       = 0;

		for ( $k = $j + 1; $k < $t_count && $depth > 0; $k++ ) {
			$token = $tokens[ $k ];

			if ( is_array( $token ) ) {
				$type = $token[0];

				if ( $type === T_WHITESPACE || $type === T_COMMENT || $type === T_DOC_COMMENT ) {
					continue;
				}

				// Skip heredoc/nowdoc body entirely.
				if ( $type === T_START_HEREDOC ) {
					while ( $k < $t_count ) {
						$k++;
						if ( is_array( $tokens[ $k ] ) && $tokens[ $k ][0] === T_END_HEREDOC ) {
							break;
						}
					}
					continue;
				}

				$args[ $arg_idx ] .= $token[1];
			} else {
				if ( $token === '(' || $token === '[' || $token === '{' ) {
					$depth++;
					$args[ $arg_idx ] .= $token;
				} elseif ( $token === ')' || $token === ']' || $token === '}' ) {
					$depth--;
					if ( $depth === 0 ) { // End of the i18n function call.
						break;
					}
					$args[ $arg_idx ] .= $token;
				} elseif ( $token === ',' && $depth === 1 ) {
					$arg_idx++;
					$args[ $arg_idx ] = '';
				} else {
					$args[ $arg_idx ] .= $token;
				}
			}
		}

		$domain_pos = $expected_arg - 1;

		if ( ! isset( $args[ $domain_pos ] ) ) {
			continue;
		}

		$domain_arg = trim( $args[ $domain_pos ] );

		if ( $domain_arg === '' ) {
			continue;
		}

		$domain_value = null;

		// Check if the domain argument is a simple single/double-quoted string literal.
		if ( preg_match( '/^(\'|")(.*)\1$/s', $domain_arg, $m ) ) {
			$domain_value = $m[2];
		}

		if ( $domain_value !== null && $domain_value !== $text_domain ) {
			$errors[] = sprintf(
				'%s:%d — "%s" uses text domain "%s", expected "%s"',
				$relative,
				$func_line,
				$function . '()',
				$domain_value,
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
