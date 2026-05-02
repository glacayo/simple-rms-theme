<!-- Contact Map — Full-Width Google Maps Embed -->
<?php
$maps_url = rms_get_option('company_google_maps_url');
?>
<?php if (!empty($maps_url)) : ?>
<div class="contact-map__link">
    <a href="<?php echo esc_url($maps_url); ?>" target="_blank" rel="noopener noreferrer" class="btn contact-map__directions-btn">
        <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polygon points="1 6 1 22 8 18 16 22 23 18 23 2 16 6 8 2 1 6"/><line x1="8" y1="2" x2="8" y2="18"/><line x1="16" y1="6" x2="16" y2="22"/></svg>
        Get Directions
    </a>
</div>
<?php endif; ?>

<section class="contact-map">
    <div class="contact-map__embed">
        <?php if (!empty($maps_url)) : ?>
        <iframe
            src="<?php echo esc_url($maps_url); ?>"
            class="contact-map__iframe"
            style="border:0;"
            allowfullscreen=""
            loading="lazy"
            referrerpolicy="no-referrer-when-downgrade"
            title="Company Location Map"
        ></iframe>
        <?php else : ?>
        <div class="contact-map__placeholder">
            <svg xmlns="http://www.w3.org/2000/svg" width="48" height="48" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0 1 18 0z"/><circle cx="12" cy="10" r="3"/></svg>
            <p>Map unavailable</p>
        </div>
        <?php endif; ?>
    </div>
</section>