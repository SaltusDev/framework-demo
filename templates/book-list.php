<?php
/** @var list<WP_Post> $posts */
/** @var array<int, array<string, mixed>> $meta_by_post */
if ( $posts === array() ) :
	?>
	<p class="saltus-demo-books__empty"><?php echo esc_html__( 'No books found yet.', 'framework-demo' ); ?></p>
	<?php
	return;
endif;
?>
<section class="saltus-demo-books saltus-demo-books--list" aria-label="<?php echo esc_attr__( 'Books', 'framework-demo' ); ?>">
	<header class="saltus-demo-books__intro">
		<p class="saltus-demo-books__eyebrow"><?php echo esc_html__( 'Saltus library', 'framework-demo' ); ?></p>
		<h2 class="saltus-demo-books__heading"><?php echo esc_html__( 'Featured books', 'framework-demo' ); ?></h2>
	</header>
	<ul class="saltus-demo-books__list">
		<?php foreach ( $posts as $book ) :
			$book_meta = isset( $meta_by_post[ $book->ID ] ) && is_array( $meta_by_post[ $book->ID ] ) ? $meta_by_post[ $book->ID ] : array();
			$cover     = $book_meta['cover'] ?? 0;
			if ( is_array( $cover ) ) {
				$cover = reset( $cover );
			}
			?>
			<li class="saltus-demo-books__item">
				<a class="saltus-demo-books__link" href="<?php echo esc_url( get_permalink( $book ) ); ?>">
					<figure class="saltus-demo-books__cover">
						<?php if ( is_numeric( $cover ) && (int) $cover > 0 ) : ?>
							<?php echo wp_get_attachment_image( (int) $cover, 'medium', false, array( 'alt' => esc_attr( $book->post_title ) ) ); ?>
						<?php endif; ?>
					</figure>
					<div class="saltus-demo-books__body">
						<h3 class="saltus-demo-books__title"><?php echo esc_html( $book->post_title ); ?></h3>
						<?php if ( $book->post_excerpt !== '' ) : ?>
							<div class="saltus-demo-books__excerpt"><?php echo wp_kses_post( $book->post_excerpt ); ?></div>
						<?php endif; ?>
						<span class="saltus-demo-books__date"><?php echo esc_html( get_the_date( '', $book ) ); ?></span>
					</div>
				</a>
			</li>
		<?php endforeach; ?>
	</ul>
</section>
