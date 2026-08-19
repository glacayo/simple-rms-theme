<?php
/**
 * Template Name: SEO Landing Page
 *
 * Renders wizard-generated flexible `page_sections` when present.
 * Injects `breadcrumb-slim` once after the first Hero row only.
 * Falls back to the hardcoded section order when ACF is available but empty.
 * When ACF functions are missing, renders a minimal safe title/content fallback
 * (template parts call `get_sub_field()` and would fatal without ACF).
 *
 * @package Simple_RMS_Theme
 */

get_header();

// ACF flexible helpers required for both generated rows and hardcoded parts.
$acf_available = function_exists( 'have_rows' )
	&& function_exists( 'the_row' )
	&& function_exists( 'get_row_layout' )
	&& function_exists( 'get_sub_field' );

$has_flexible_sections = $acf_available && have_rows( 'page_sections' );

if ( $has_flexible_sections ) :
	$breadcrumb_injected = false;

	while ( have_rows( 'page_sections' ) ) :
		the_row();
		$layout = get_row_layout();

		if ( ! is_string( $layout ) || '' === $layout ) {
			continue;
		}

		get_template_part( 'templates/' . $layout );

		// Inject breadcrumb once after the first Hero row only (not an ACF layout).
		// No Hero in flexible content ⇒ no breadcrumb in this path.
		if ( ! $breadcrumb_injected && 'hero' === $layout ) {
			get_template_part( 'templates/breadcrumb-slim' );
			$breadcrumb_injected = true;
		}
	endwhile;
elseif ( $acf_available ) :
	// ACF present but empty flexible content — legacy hardcoded section order.
	// Template parts may call get_sub_field(); safe only when ACF is loaded.
	get_template_part( 'templates/hero' );
	get_template_part( 'templates/breadcrumb-slim' );
	get_template_part( 'templates/seo-content' );
	get_template_part( 'templates/vision-mission-v1' );
	get_template_part( 'templates/badges' );
	get_template_part( 'templates/portfolio-v1' );
	get_template_part( 'templates/seo-content' );
	get_template_part( 'templates/testimonials-v1' );
	get_template_part( 'templates/seo-content' );
else :
	// ACF missing/degraded — do not load template parts that call get_sub_field().
	?>
	<main id="main" class="landing-page landing-page--acf-missing">
		<?php
		while ( have_posts() ) :
			the_post();
			?>
			<article <?php post_class( 'landing-page__article' ); ?>>
				<header class="landing-page__header">
					<h1 class="landing-page__title"><?php echo esc_html( get_the_title() ); ?></h1>
				</header>
				<div class="landing-page__content entry-content">
					<?php the_content(); ?>
				</div>
			</article>
			<?php
		endwhile;
		?>
	</main>
	<?php
endif;

get_footer();
