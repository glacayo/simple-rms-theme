<?php
/**
 * Area Coverage V1 Section Template
 *
 * @package Simple_RMS_Theme
 */

$eyebrow     = get_sub_field( 'area_eyebrow' ) ?: 'Regional Coverage';
$headline    = get_sub_field( 'area_headline' ) ?: 'Areas We Proudly Serve';
$description = get_sub_field( 'area_description' );
if ( ! $description ) {
    $description = rms_get_option( 'company_covered_areas' );
}
$radius    = get_sub_field( 'area_radius' ) ?: '50 Miles';
$cities    = get_sub_field( 'area_cities' );
$cta_text  = get_sub_field( 'area_cta_text' ) ?: 'Check Your Location';
$cta_url   = get_sub_field( 'area_cta_url' ) ?: '#';
$map_image = get_sub_field( 'area_map_image' ) ?: 'https://placehold.co/800x600';
?>
<!-- Area Coverage V1 — Regional Service Footprint -->
<section class="area-coverage-v1" aria-labelledby="area-coverage-v1-heading">
    <div class="container">
        <div class="area-coverage-v1__layout">
            <div class="area-coverage-v1__content">
                <p class="area-coverage-v1__eyebrow"><?php echo esc_html( $eyebrow ); ?></p>
                <h2 id="area-coverage-v1-heading" class="area-coverage-v1__headline"><?php echo esc_html( $headline ); ?></h2>
                <?php if ( $description ) : ?>
                    <p class="area-coverage-v1__description">
                        <?php echo wp_kses_post( $description ); ?>
                    </p>
                <?php else : ?>
                    <p class="area-coverage-v1__description">
                        From central city neighborhoods to surrounding communities, our crew delivers consistent craftsmanship across the greater Orlando region with dependable response times and local expertise you can trust.
                    </p>
                <?php endif; ?>

                <p class="area-coverage-v1__radius" role="status" aria-label="Current service radius">
                    Service Radius: <?php echo esc_html( $radius ); ?>
                </p>

                <ul class="area-coverage-v1__cities" aria-label="Cities we currently serve">
                    <?php if ( $cities ) : ?>
                        <?php foreach ( $cities as $city ) : ?>
                            <li class="area-coverage-v1__city"><?php echo esc_html( $city['city_name'] ); ?></li>
                        <?php endforeach; ?>
                    <?php else : ?>
                        <li class="area-coverage-v1__city">Orlando</li>
                        <li class="area-coverage-v1__city">Kissimmee</li>
                        <li class="area-coverage-v1__city">Winter Park</li>
                        <li class="area-coverage-v1__city">Sanford</li>
                        <li class="area-coverage-v1__city">Lake Mary</li>
                        <li class="area-coverage-v1__city">Oviedo</li>
                        <li class="area-coverage-v1__city">Apopka</li>
                        <li class="area-coverage-v1__city">Clermont</li>
                    <?php endif; ?>
                </ul>

                <a class="btn area-coverage-v1__cta" href="<?php echo esc_url( $cta_url ); ?>" aria-label="<?php echo esc_attr( $cta_text ); ?>"><?php echo esc_html( $cta_text ); ?></a>
            </div>

            <div class="area-coverage-v1__map" aria-hidden="true">
                <div class="area-coverage-v1__map-card" style="--coverage-image: url('<?php echo esc_url( $map_image ); ?>');">
                    <div class="area-coverage-v1__map-image"></div>
                    <svg class="area-coverage-v1__map-shape" viewBox="0 0 360 260" xmlns="http://www.w3.org/2000/svg" preserveAspectRatio="xMidYMid meet" focusable="false" aria-hidden="true">
                        <circle class="area-coverage-v1__map-ring" cx="180" cy="130" r="26" />
                        <circle class="area-coverage-v1__map-ring" cx="180" cy="130" r="52" />
                        <circle class="area-coverage-v1__map-ring" cx="180" cy="130" r="78" />
                    </svg>
                </div>
            </div>
        </div>
    </div>
</section>
