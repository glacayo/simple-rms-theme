<?php
$eyebrow  = get_sub_field('vm_v1_eyebrow') ?: 'Who We Are';
$headline = get_sub_field('vm_v1_headline') ?: 'Our Vision, Mission and Values';
$intro    = get_sub_field('vm_v1_intro') ?: 'These principles guide how we work with our clients every day.';
$cards    = get_sub_field('vm_v1_cards');
$cta_text = get_sub_field('vm_v1_cta_text') ?: 'Contact Us';

// An empty CTA URL resolves through the Contact-page helper; if that helper is
// unexpectedly unavailable, fall back to an absolute home contact anchor and
// never to a bare on-page anchor.
$cta_url = trim( (string) get_sub_field('vm_v1_cta_url') );

if ( '' === $cta_url && function_exists( 'rms_get_contact_page_url' ) ) {
    $cta_url = trim( (string) rms_get_contact_page_url() );
}

if ( '' === $cta_url ) {
    $cta_url = home_url( '/#contact' );
}

// The layout contract is exactly three ordered, grounded cards. The template
// owns this neutral fallback set and its local three-row validator so the
// section renders safely when the wizard autoloader or AI harness class is
// unavailable. The harness stays the generation boundary, never a render
// dependency; drifted stored rows fall back to the same neutral set.
$vm_v1_card_titles   = array( 'Our Vision', 'Our Mission', 'Why Choose Us' );
$vm_v1_neutral_cards = array(
    array( 'card_title' => 'Our Vision', 'card_text' => 'To be a company clients can rely on for careful work and honest communication.' ),
    array( 'card_title' => 'Our Mission', 'card_text' => 'To deliver dependable service and keep every client informed at each step.' ),
    array( 'card_title' => 'Why Choose Us', 'card_text' => 'Clients choose us for straightforward communication and attention to detail.' ),
);

$vm_v1_has_valid_cards = static function ( $rows ) use ( $vm_v1_card_titles ) {
    if ( ! is_array( $rows ) || count( $rows ) !== count( $vm_v1_card_titles ) ) {
        return false;
    }

    foreach ( array_values( $rows ) as $index => $row ) {
        if ( ! is_array( $row ) || trim( (string) ( $row['card_title'] ?? '' ) ) !== $vm_v1_card_titles[ $index ] ) {
            return false;
        }

        if ( '' === trim( strip_tags( (string) ( $row['card_text'] ?? '' ) ) ) ) {
            return false;
        }
    }

    return true;
};

if ( ! $vm_v1_has_valid_cards( $cards ) ) {
    $cards = $vm_v1_neutral_cards;
}
?>
<!-- Vision Mission V1 — Three-Card Trust Section -->
<section class="vision-mission-v1" aria-labelledby="vision-mission-v1-heading">
    <div class="container">
        <p class="vision-mission-v1__eyebrow"><?php echo esc_html( $eyebrow ); ?></p>
        <h2 id="vision-mission-v1-heading" class="vision-mission-v1__headline"><?php echo esc_html( $headline ); ?></h2>
        <p class="vision-mission-v1__intro"><?php echo wp_kses_post( $intro ); ?></p>

        <div class="vision-mission-v1__grid">
            <?php foreach ( $cards as $card ) : ?>
                <article class="vision-mission-v1__card vision-mission-v1__card--highlight">
                    <h3 class="vision-mission-v1__title"><?php echo esc_html( $card['card_title'] ?? '' ); ?></h3>
                    <p class="vision-mission-v1__text"><?php echo wp_kses_post( $card['card_text'] ?? '' ); ?></p>
                </article>
            <?php endforeach; ?>
        </div>

        <div class="vision-mission-v1__cta-wrap">
            <a class="btn vision-mission-v1__cta" href="<?php echo esc_url( $cta_url ); ?>" aria-label="<?php echo esc_attr( $cta_text . ' from our team' ); ?>" data-raven-cta><?php echo esc_html( $cta_text ); ?></a>
        </div>
    </div>
</section>
