<?php
/** @var WP_Post $post */
/** @var array<string, mixed> $meta */
$cover = $meta['cover'] ?? 0;
if ( is_array( $cover ) ) {
	$cover = reset( $cover );
}
?>
<article class="saltus-demo-books saltus-demo-books--single">
	<?php if ( is_numeric( $cover ) && (int) $cover > 0 ) : ?>
		<figure class="saltus-demo-books__cover">
			<?php echo wp_get_attachment_image( (int) $cover, 'large', false, array( 'alt' => esc_attr( $post->post_title ) ) ); ?>
		</figure>
	<?php endif; ?>
	<header class="saltus-demo-books__intro">
		<p class="saltus-demo-books__eyebrow"><?php echo esc_html__( 'Book detail', 'framework-demo' ); ?></p>
		<h1 class="saltus-demo-books__heading"><?php echo esc_html( $post->post_title ); ?></h1>
	</header>
	<div class="saltus-demo-books__content">
		<?php echo wp_kses_post( apply_filters( 'the_content', $post->post_content ) ); // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound ?>
	</div>
	<?php if ( $meta !== array() ) : ?>
		<dl class="saltus-demo-books__meta">
			<?php foreach ( $meta as $key => $value ) :
				if ( $key === 'cover' || is_array( $value ) || $value === '' || $value === null ) {
					continue;
				}
				?>
				<dt><?php echo esc_html( ucfirst( str_replace( '_', ' ', (string) $key ) ) ); ?></dt>
				<dd><?php echo esc_html( is_scalar( $value ) ? (string) $value : wp_json_encode( $value ) ); ?></dd>
			<?php endforeach; ?>
		</dl>
	<?php endif; ?>
</article>
