<?php
/**
 * Badges Section Template
 *
 * @package Simple_RMS_Theme
 */

$badges_label = get_sub_field( 'badges_label' ) ?: 'Trusted & Verified On';
$badges_items = get_sub_field( 'badges_items' );
?>
<!-- Badges / Directories Section -->
<section class="badges">
    <div class="container">
        <p class="badges__label"><?php echo esc_html( $badges_label ); ?></p>
        <div class="badges__list">
            <?php if ( $badges_items ) : ?>
                <?php foreach ( $badges_items as $badge ) :
                    $badge_url  = $badge['badge_url'] ?? '';
                    $badge_icon = $badge['badge_icon'] ?? '';
                    $badge_name = $badge['badge_name'] ?? '';
                    // Empty alt: the badge name is already the visible label and aria-label.
                    $logo_id    = is_numeric( $badge['badge_logo'] ?? '' ) ? (int) $badge['badge_logo'] : 0;
                    $badge_logo = $logo_id > 0
                        ? wp_get_attachment_image( $logo_id, 'medium', false, array(
                            'class'    => 'badges__logo',
                            'alt'      => '',
                            'loading'  => 'lazy',
                            'decoding' => 'async',
                        ) )
                        : '';
                    ?>
                    <?php if ( ! empty( $badge_url ) ) : ?>
                        <a class="badges__item" href="<?php echo esc_url( $badge_url ); ?>" target="_blank" rel="noopener noreferrer" aria-label="<?php echo esc_attr( $badge_name ); ?>">
                    <?php else : ?>
                        <div class="badges__item">
                    <?php endif; ?>
                        <span class="<?php echo $badge_logo ? 'badges__icon badges__icon--logo' : 'badges__icon'; ?>"><?php echo $badge_logo ? $badge_logo : esc_html( $badge_icon ); // phpcs:ignore WordPress.Security.EscapeOutput -- core-escaped <img>. ?></span>
                        <span class="badges__name"><?php echo esc_html( $badge_name ); ?></span>
                    <?php if ( ! empty( $badge_url ) ) : ?>
                        </a>
                    <?php else : ?>
                        </div>
                    <?php endif; ?>
                <?php endforeach; ?>
            <?php else : ?>
                <a class="badges__item" href="#" target="_blank" rel="noopener noreferrer" aria-label="Home Advisor">
                    <span class="badges__icon">HA</span>
                    <span class="badges__name">Home Advisor</span>
                </a>

                <a class="badges__item" href="#" target="_blank" rel="noopener noreferrer" aria-label="Yellow Pages">
                    <span class="badges__icon">YP</span>
                    <span class="badges__name">Yellow Pages</span>
                </a>

                <a class="badges__item" href="#" target="_blank" rel="noopener noreferrer" aria-label="Cilex">
                    <span class="badges__icon">CL</span>
                    <span class="badges__name">Cilex</span>
                </a>

                <a class="badges__item" href="#" target="_blank" rel="noopener noreferrer" aria-label="Yelp">
                    <span class="badges__icon">YL</span>
                    <span class="badges__name">Yelp</span>
                </a>

                <a class="badges__item" href="#" target="_blank" rel="noopener noreferrer" aria-label="BBB Accredited">
                    <span class="badges__icon">BBB</span>
                    <span class="badges__name">BBB Accredited</span>
                </a>
            <?php endif; ?>
        </div>
    </div>
</section>
