<?php
require dirname( __DIR__ ) . '/vendor/autoload.php';

if ( ! function_exists( 'sanitize_text_field' ) ) {
	function sanitize_text_field( $value ) {
		return trim( strip_tags( (string) $value ) );
	}
}

if ( ! function_exists( 'esc_url_raw' ) ) {
	function esc_url_raw( $value ) {
		return trim( (string) $value );
	}
}

if ( ! function_exists( 'wp_tempnam' ) ) {
	function wp_tempnam( $filename = '' ) {
		return tempnam( sys_get_temp_dir(), sanitize_file_name( (string) $filename ) );
	}
}

if ( ! function_exists( 'sanitize_file_name' ) ) {
	function sanitize_file_name( $filename ) {
		return preg_replace( '/[^A-Za-z0-9_.-]/', '-', (string) $filename );
	}
}
