<?php
/**
 * Blog V1 index chrome. Posts render in home.php, not here.
 *
 * @package Simple_RMS_Theme
 */

$headline = function_exists( 'get_sub_field' ) ? get_sub_field( 'blog_headline' ) : '';
$cta_text = function_exists( 'get_sub_field' ) ? get_sub_field( 'blog_cta_text' ) : '';
$cta_url  = function_exists( 'get_sub_field' ) ? get_sub_field( 'blog_cta_url' ) : '';
$headline = is_string( $headline ) ? trim( $headline ) : '';
$cta_text = is_string( $cta_text ) ? trim( $cta_text ) : '';
$cta_url  = is_string( $cta_url ) ? trim( $cta_url ) : '';
$cta_href = ( '' !== $cta_url && function_exists( 'esc_url' ) ) ? esc_url( $cta_url ) : '';
?>
<section class="blog-v1" aria-labelledby="blog-v1-heading">
	<div class="container">
		<?php if ( '' !== $headline ) : ?>
			<h2 id="blog-v1-heading" class="blog-v1__headline"><?php echo esc_html( $headline ); ?></h2>
		<?php endif; ?>
		<?php if ( '' !== $cta_text && '' !== $cta_href ) : ?>
			<div class="blog-v1__cta-wrap">
				<a href="<?php echo esc_url( $cta_href ); ?>" class="btn blog-v1__cta"><?php echo esc_html( $cta_text ); ?></a>
			</div>
		<?php endif; ?>
	</div>
</section>
