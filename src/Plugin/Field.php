<?php
namespace Saltus\WP\Plugin\Saltus\PluginFrameworkDemo\Plugin;

/**
 * Wrapper for meta fields
 *
 */
class Field {

	/**
	 * Wrapper for get_post_meta.
	 *
	 * Retrieve post meta field for a post.
	 *
	 * @see          get_post_meta
	 *
	 * @param int    $post_id Post ID.
	 * @param string $key     Optional. The meta key to retrieve. By default, returns
	 *                        data for all keys. Default empty.
	 * @param bool   $single  Optional. Whether to return a single value. Default false.
	 * @return mixed          Will be an array if $single is false. Will be value of meta data
	 *                        field if $single is true.
	 */
	public static function get( int $post_id, string $key = '', bool $single = false ) {

		return get_post_meta( $post_id, $key, $single );
	}

	/**
	 * Wrapper for get_post_meta.
	 *
	 * Retrieve **all** post meta fields for a post.
	 *
	 * @see          get_post_meta
	 *
	 * @param  int   $post_id Post ID.
	 * @return array          All the post meta values, in a multidimensional array.
	 */
	public static function get_all( int $post_id ) {

		return get_post_meta( $post_id );
	}

	/**
	 * Parses the postmeta array, and returns a text value
	 *
	 * @param  array   $postmeta  A multidemensional array with meta fields
	 * @param  string  $meta_key  The key for the meta field
	 * @return string             The value for the supplied key, else empty if nothing found.
	 */
	public static function get_meta_text( array $postmeta, string $meta_key ) {
		$value = '';
		if ( ! empty( $postmeta[ $meta_key ][0] ) ) {
			$value = $postmeta[ $meta_key ][0];
		}
		return $value;
	}

	/**
	 * Parses the postmeta array, and returns a image tag
	 *
	 * @param  array   $postmeta  A multidemensional array with meta fields
	 * @param  string  $meta_key  The key for the meta field
	 * @param  string  $size      Optional. An existing image size. Defaults to 'full'.
	 * @return string             A string with the img tag
	 */
	public static function get_meta_img( array $postmeta, string $meta_key, string $size = 'full' ) {

		$image_src = self::get_meta_img_src( $postmeta, $meta_key, $size );
		if ( empty( $image_src ) ) {
			return '';
		}

		$image_id  = self::get_meta_img_id( $postmeta, $meta_key );
		$image_alt = '';
		if ( ! empty( $image_id ) ) {
			$image_alt = get_post_meta( $image_id, '_wp_attachment_image_alt', true );
		}

		return sprintf(
			'<img src="%1$s" alt="%2$s">',
			esc_url( $image_src ),
			esc_attr( $image_alt )
		);
	}

	/**
	 * Parses the postmeta array, and returns an image id
	 *
	 * @param  array   $postmeta  A multidemensional array with meta fields
	 * @param  string  $meta_key  The key for the meta field
	 * @return string             The image id
	 */
	protected static function get_meta_img_id( array $postmeta, string $meta_key ) {

		$meta_key_id = $meta_key . '_id';
		if ( empty( $postmeta[ $meta_key_id ][0] ) ) {
			return '';
		}

		return $postmeta[ $meta_key_id ][0];
	}


	/**
	 * Parses the postmeta array, and returns a image src.
	 *
	 * @param  array   $postmeta  A multidemensional array with meta fields
	 * @param  string  $meta_key  The key for the meta field
	 * @param  string  $size      Optional. An existing image size. Defaults to 'full'.
	 * @return string             A URL for the image src
	 */
	public static function get_meta_img_src( array $postmeta, string $meta_key, string $size = 'full' ) {

		$image_src = '';

		if ( empty( $postmeta[ $meta_key ][0] ) ) {
			return '';
		}

		// could be a remote or local file
		$image_src = $postmeta[ $meta_key ][0];

		// cmb2 stores the attachment full image url on the main key
		if ( $size === 'full' ) {
			return $image_src;
		}

		// cmb2 stores the attachment id with a different suffix, if local
		$image_id = self::get_meta_img_id( $postmeta, $meta_key );
		if ( empty( $image_id ) ) {
			return $image_src;
		}

		// check if if local file
		$image_src_maybe = wp_get_attachment_image_src( $image_id, $size );
		// if exists, return the src, at index 0
		if ( $image_src_maybe && isset( $image_src_maybe[0] ) ) {
			return $image_src_maybe[0];
		}

		return $image_src;
	}


	/**
	 * Parses the postmeta array, and returns a image src.
	 *
	 * @param  array   $postmeta  A multidemensional array with meta fields
	 * @param  string  $meta_key  The key for the meta field
	 * @param  bool    $single    Optional. Expect just a single group
	 * @return string             The meta value, or empty if nothing found.
	 */
	public static function get_meta_group( array $postmeta, string $meta_key, bool $single = true ) {

		if ( empty( $postmeta[ $meta_key ][0] ) ) {
			return '';
		}
		if ( $single ) {
			return maybe_unserialize( $postmeta[ $meta_key ][0] );
		} else {
			return array_map( 'maybe_unserialize', $postmeta[ $meta_key ] );
		}
	}
}
