<!-- Portfolio V2 — Grid with Gaps on Solid Background -->
<?php
$headline    = get_sub_field('portfolio_v2_headline') ?: 'Featured Work';
$subheadline = get_sub_field('portfolio_v2_subheadline') ?: 'Browse Our Best Projects';
$projects    = get_sub_field('portfolio_v2_projects');
?>
<section class="portfolio-v2">
    <div class="container">
        <h2 class="portfolio-v2__headline"><?php echo esc_html($headline); ?></h2>
        <h3 class="portfolio-v2__subheadline"><?php echo esc_html($subheadline); ?></h3>

        <div class="portfolio-v2__grid">
            <?php if (!empty($projects)) : ?>
                <?php foreach ($projects as $project) :
                    $image = $project['project_image'] ?? '';
                    $label = $project['project_label'] ?? '';
                ?>
                    <div class="portfolio-v2__item" data-lightbox="<?php echo esc_url($image); ?>">
                        <img src="<?php echo esc_url($image); ?>" alt="<?php echo esc_attr($label); ?> project" width="400" height="300" loading="lazy">
                        <div class="portfolio-v2__overlay">
                            <span class="portfolio-v2__icon"><svg xmlns="http://www.w3.org/2000/svg" width="32" height="32" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/><line x1="11" y1="8" x2="11" y2="14"/><line x1="8" y1="11" x2="14" y2="11"/></svg></span>
                            <span class="portfolio-v2__label"><?php echo esc_html($label); ?></span>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php else : ?>
                <div class="portfolio-v2__item" data-lightbox="https://placehold.co/800x600">
                    <img src="https://placehold.co/400x300" alt="Commercial Painting project" width="400" height="300" loading="lazy">
                    <div class="portfolio-v2__overlay">
                        <span class="portfolio-v2__icon"><svg xmlns="http://www.w3.org/2000/svg" width="32" height="32" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/><line x1="11" y1="8" x2="11" y2="14"/><line x1="8" y1="11" x2="14" y2="11"/></svg></span>
                        <span class="portfolio-v2__label">Commercial Painting</span>
                    </div>
                </div>
                <div class="portfolio-v2__item" data-lightbox="https://placehold.co/800x600">
                    <img src="https://placehold.co/400x300" alt="Residential Roofing project" width="400" height="300" loading="lazy">
                    <div class="portfolio-v2__overlay">
                        <span class="portfolio-v2__icon"><svg xmlns="http://www.w3.org/2000/svg" width="32" height="32" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/><line x1="11" y1="8" x2="11" y2="14"/><line x1="8" y1="11" x2="14" y2="11"/></svg></span>
                        <span class="portfolio-v2__label">Residential Roofing</span>
                    </div>
                </div>
                <div class="portfolio-v2__item" data-lightbox="https://placehold.co/800x600">
                    <img src="https://placehold.co/400x300" alt="Storm Repair project" width="400" height="300" loading="lazy">
                    <div class="portfolio-v2__overlay">
                        <span class="portfolio-v2__icon"><svg xmlns="http://www.w3.org/2000/svg" width="32" height="32" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/><line x1="11" y1="8" x2="11" y2="14"/><line x1="8" y1="11" x2="14" y2="11"/></svg></span>
                        <span class="portfolio-v2__label">Storm Repair</span>
                    </div>
                </div>
                <div class="portfolio-v2__item" data-lightbox="https://placehold.co/800x600">
                    <img src="https://placehold.co/400x300" alt="New Construction project" width="400" height="300" loading="lazy">
                    <div class="portfolio-v2__overlay">
                        <span class="portfolio-v2__icon"><svg xmlns="http://www.w3.org/2000/svg" width="32" height="32" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/><line x1="11" y1="8" x2="11" y2="14"/><line x1="8" y1="11" x2="14" y2="11"/></svg></span>
                        <span class="portfolio-v2__label">New Construction</span>
                    </div>
                </div>
                <div class="portfolio-v2__item" data-lightbox="https://placehold.co/800x600">
                    <img src="https://placehold.co/400x300" alt="Gutter Installation project" width="400" height="300" loading="lazy">
                    <div class="portfolio-v2__overlay">
                        <span class="portfolio-v2__icon"><svg xmlns="http://www.w3.org/2000/svg" width="32" height="32" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/><line x1="11" y1="8" x2="11" y2="14"/><line x1="8" y1="11" x2="14" y2="11"/></svg></span>
                        <span class="portfolio-v2__label">Gutter Installation</span>
                    </div>
                </div>
                <div class="portfolio-v2__item" data-lightbox="https://placehold.co/800x600">
                    <img src="https://placehold.co/400x300" alt="Roof Inspection project" width="400" height="300" loading="lazy">
                    <div class="portfolio-v2__overlay">
                        <span class="portfolio-v2__icon"><svg xmlns="http://www.w3.org/2000/svg" width="32" height="32" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/><line x1="11" y1="8" x2="11" y2="14"/><line x1="8" y1="11" x2="14" y2="11"/></svg></span>
                        <span class="portfolio-v2__label">Roof Inspection</span>
                    </div>
                </div>
                <div class="portfolio-v2__item" data-lightbox="https://placehold.co/800x600">
                    <img src="https://placehold.co/400x300" alt="Emergency Services project" width="400" height="300" loading="lazy">
                    <div class="portfolio-v2__overlay">
                        <span class="portfolio-v2__icon"><svg xmlns="http://www.w3.org/2000/svg" width="32" height="32" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/><line x1="11" y1="8" x2="11" y2="14"/><line x1="8" y1="11" x2="14" y2="11"/></svg></span>
                        <span class="portfolio-v2__label">Emergency Services</span>
                    </div>
                </div>
                <div class="portfolio-v2__item" data-lightbox="https://placehold.co/800x600">
                    <img src="https://placehold.co/400x300" alt="Metal Roofing project" width="400" height="300" loading="lazy">
                    <div class="portfolio-v2__overlay">
                        <span class="portfolio-v2__icon"><svg xmlns="http://www.w3.org/2000/svg" width="32" height="32" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/><line x1="11" y1="8" x2="11" y2="14"/><line x1="8" y1="11" x2="14" y2="11"/></svg></span>
                        <span class="portfolio-v2__label">Metal Roofing</span>
                    </div>
                </div>
            <?php endif; ?>
        </div>
    </div>
</section>
