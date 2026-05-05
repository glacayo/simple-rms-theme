<!-- CTA V3 — Stats + CTA Combined -->
<?php
$headline    = get_sub_field('cta_v3_headline') ?: 'Join Our Growing List of Happy Homeowners';
$button_text = get_sub_field('cta_v3_button_text') ?: 'Start Your Project Today';
$button_url  = get_sub_field('cta_v3_button_url') ?: '#contact';
$stats       = get_sub_field('cta_v3_stats');

$fallback_stats = [
    ['stat_number' => '500+', 'stat_label' => 'Projects Completed'],
    ['stat_number' => '25',   'stat_label' => 'Years Experience'],
    ['stat_number' => '100%', 'stat_label' => 'Satisfaction Rate'],
    ['stat_number' => '5.0',  'stat_label' => 'Google Rating'],
];
?>
<section class="cta-v3">
    <div class="container">
        <div class="cta-v3__stats">
            <?php
            $display_stats = (!empty($stats) && is_array($stats)) ? $stats : $fallback_stats;
            foreach ($display_stats as $stat) :
                $stat_number = $stat['stat_number'] ?? '';
                $stat_label  = $stat['stat_label'] ?? '';
                if (empty($stat_number) && empty($stat_label)) continue;
            ?>
            <div class="cta-v3__stat">
                <span class="cta-v3__stat-number"><?php echo esc_html($stat_number); ?></span>
                <span class="cta-v3__stat-label"><?php echo esc_html($stat_label); ?></span>
            </div>
            <?php endforeach; ?>
        </div>
        <div class="cta-v3__action">
            <h2 class="cta-v3__headline"><?php echo esc_html($headline); ?></h2>
            <a href="<?php echo esc_url($button_url); ?>" class="btn cta-v3__button"><?php echo esc_html($button_text); ?></a>
        </div>
    </div>
</section>
