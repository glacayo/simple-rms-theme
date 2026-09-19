<?php
/**
 * Template Part: Breadcrumb
 *
 * Reusable breadcrumb hero for internal pages. Background mode, background
 * image, solid/gradient colors, and optional text color come from the
 * sanitized Theme Settings style contract (rms_get_breadcrumb_style()).
 * This template only emits the resolved mode class, scoped CSS custom
 * properties, and the conditional image overlay.
 *
 * @package SimpleRMS
 */

$rms_bc_style = function_exists( 'rms_get_breadcrumb_style' ) ? rms_get_breadcrumb_style() : array();
$rms_bc_type  = isset( $rms_bc_style['type'] ) && in_array( $rms_bc_style['type'], array( 'image', 'solid', 'gradient' ), true )
    ? $rms_bc_style['type']
    : 'image';

$rms_bc_image = 'image' === $rms_bc_type && ! empty( $rms_bc_style['has_image'] ) && ! empty( $rms_bc_style['image_url'] )
    ? (string) $rms_bc_style['image_url']
    : '';
$rms_bc_overlay = '' !== $rms_bc_image && ! empty( $rms_bc_style['overlay'] );

$rms_bc_vars = array();
if ( '' !== $rms_bc_image ) {
    $rms_bc_vars[] = "--breadcrumb-image: url('" . esc_url( $rms_bc_image ) . "')";
}
if ( 'solid' === $rms_bc_type && ! empty( $rms_bc_style['solid_color'] ) ) {
    $rms_bc_vars[] = '--breadcrumb-solid: ' . esc_attr( $rms_bc_style['solid_color'] );
}
if ( 'gradient' === $rms_bc_type ) {
    if ( ! empty( $rms_bc_style['gradient_start'] ) ) {
        $rms_bc_vars[] = '--breadcrumb-gradient-from: ' . esc_attr( $rms_bc_style['gradient_start'] );
    }
    if ( ! empty( $rms_bc_style['gradient_end'] ) ) {
        $rms_bc_vars[] = '--breadcrumb-gradient-to: ' . esc_attr( $rms_bc_style['gradient_end'] );
    }
    $rms_bc_vars[] = '--breadcrumb-gradient-angle: ' . (int) ( $rms_bc_style['angle'] ?? 180 ) . 'deg';
}
if ( ! empty( $rms_bc_style['text_color'] ) ) {
    $rms_bc_vars[] = '--breadcrumb-text: ' . esc_attr( $rms_bc_style['text_color'] );
}
?>
<section class="<?php echo esc_attr( 'breadcrumb-page breadcrumb-page--' . $rms_bc_type ); ?>"<?php
    echo $rms_bc_vars ? ' style="' . implode( '; ', $rms_bc_vars ) . '"' : ''; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- esc_url()/esc_attr()/int only.
?>>
    <?php if ( $rms_bc_overlay ) : ?>
        <div class="breadcrumb-page__overlay" aria-hidden="true"></div>
    <?php endif; ?>

    <div class="container breadcrumb-page__content">
        <?php if ( ! is_single() && ! is_page_template( 'pages/landing-page.php' ) ) : ?>
            <h1 class="breadcrumb-page__title"><?php echo esc_html( get_the_title() ); ?></h1>
        <?php endif; ?>

        <nav class="breadcrumb-page__nav" aria-label="Breadcrumb">
            <?php if ( function_exists( 'yoast_breadcrumb' ) ) : ?>
                <?php yoast_breadcrumb( '<div class="breadcrumb-page__yoast">', '</div>' ); ?>
            <?php else : ?>
                <span class="breadcrumb-page__plugin-warning">Install Plugin Needed</span>
            <?php endif; ?>
        </nav>
    </div>
</section>