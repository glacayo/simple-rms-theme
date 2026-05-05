<!-- Portfolio V3 — Masonry-Style with Category Filter Tabs -->
<?php
$headline    = get_sub_field('portfolio_v3_headline') ?: 'Project Gallery';
$subheadline = get_sub_field('portfolio_v3_subheadline') ?: 'Explore Our Work by Category';
$filters     = get_sub_field('portfolio_v3_filters');
$projects    = get_sub_field('portfolio_v3_projects');
?>
<section class="portfolio-v3">
    <div class="container">
        <h2 class="portfolio-v3__headline"><?php echo esc_html($headline); ?></h2>
        <h3 class="portfolio-v3__subheadline"><?php echo esc_html($subheadline); ?></h3>

        <div class="portfolio-v3__filters">
            <button class="portfolio-v3__filter portfolio-v3__filter--active" type="button" data-filter="all">All</button>
            <?php if (!empty($filters)) : ?>
                <?php foreach ($filters as $filter) :
                    $filter_label = $filter['filter_label'] ?? '';
                    $filter_value = sanitize_title($filter_label);
                ?>
                    <button class="portfolio-v3__filter" type="button" data-filter="<?php echo esc_attr($filter_value); ?>"><?php echo esc_html($filter_label); ?></button>
                <?php endforeach; ?>
            <?php else : ?>
                <button class="portfolio-v3__filter" type="button" data-filter="residential">Residential</button>
                <button class="portfolio-v3__filter" type="button" data-filter="commercial">Commercial</button>
                <button class="portfolio-v3__filter" type="button" data-filter="emergency">Emergency</button>
            <?php endif; ?>
        </div>

        <div class="portfolio-v3__grid">
            <?php if (!empty($projects)) : ?>
                <?php foreach ($projects as $project) :
                    $image    = $project['project_image'] ?? '';
                    $label    = $project['project_label'] ?? '';
                    $category = $project['project_category'] ?? '';
                ?>
                    <div class="portfolio-v3__item" data-category="<?php echo esc_attr($category); ?>" data-lightbox="<?php echo esc_url($image); ?>">
                        <img src="<?php echo esc_url($image); ?>" alt="<?php echo esc_attr($label); ?> project" width="400" height="300" loading="lazy">
                        <div class="portfolio-v3__overlay">
                            <span class="portfolio-v3__icon"><svg xmlns="http://www.w3.org/2000/svg" width="32" height="32" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/><line x1="11" y1="8" x2="11" y2="14"/><line x1="8" y1="11" x2="14" y2="11"/></svg></span>
                            <span class="portfolio-v3__label"><?php echo esc_html($label); ?></span>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php else : ?>
                <div class="portfolio-v3__item" data-category="residential" data-lightbox="https://placehold.co/800x600">
                    <img src="https://placehold.co/400x300" alt="Residential Roofing project" width="400" height="300" loading="lazy">
                    <div class="portfolio-v3__overlay">
                        <span class="portfolio-v3__icon"><svg xmlns="http://www.w3.org/2000/svg" width="32" height="32" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/><line x1="11" y1="8" x2="11" y2="14"/><line x1="8" y1="11" x2="14" y2="11"/></svg></span>
                        <span class="portfolio-v3__label">Residential Roofing</span>
                    </div>
                </div>
                <div class="portfolio-v3__item" data-category="commercial" data-lightbox="https://placehold.co/800x600">
                    <img src="https://placehold.co/400x400" alt="Commercial Painting project" width="400" height="400" loading="lazy">
                    <div class="portfolio-v3__overlay">
                        <span class="portfolio-v3__icon"><svg xmlns="http://www.w3.org/2000/svg" width="32" height="32" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/><line x1="11" y1="8" x2="11" y2="14"/><line x1="8" y1="11" x2="14" y2="11"/></svg></span>
                        <span class="portfolio-v3__label">Commercial Painting</span>
                    </div>
                </div>
                <div class="portfolio-v3__item" data-category="emergency" data-lightbox="https://placehold.co/800x600">
                    <img src="https://placehold.co/400x350" alt="Storm Repair project" width="400" height="350" loading="lazy">
                    <div class="portfolio-v3__overlay">
                        <span class="portfolio-v3__icon"><svg xmlns="http://www.w3.org/2000/svg" width="32" height="32" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/><line x1="11" y1="8" x2="11" y2="14"/><line x1="8" y1="11" x2="14" y2="11"/></svg></span>
                        <span class="portfolio-v3__label">Storm Repair</span>
                    </div>
                </div>
                <div class="portfolio-v3__item" data-category="residential" data-lightbox="https://placehold.co/800x600">
                    <img src="https://placehold.co/400x300" alt="New Construction project" width="400" height="300" loading="lazy">
                    <div class="portfolio-v3__overlay">
                        <span class="portfolio-v3__icon"><svg xmlns="http://www.w3.org/2000/svg" width="32" height="32" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/><line x1="11" y1="8" x2="11" y2="14"/><line x1="8" y1="11" x2="14" y2="11"/></svg></span>
                        <span class="portfolio-v3__label">New Construction</span>
                    </div>
                </div>
                <div class="portfolio-v3__item" data-category="emergency" data-lightbox="https://placehold.co/800x600">
                    <img src="https://placehold.co/400x400" alt="Emergency Services project" width="400" height="400" loading="lazy">
                    <div class="portfolio-v3__overlay">
                        <span class="portfolio-v3__icon"><svg xmlns="http://www.w3.org/2000/svg" width="32" height="32" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/><line x1="11" y1="8" x2="11" y2="14"/><line x1="8" y1="11" x2="14" y2="11"/></svg></span>
                        <span class="portfolio-v3__label">Emergency Services</span>
                    </div>
                </div>
                <div class="portfolio-v3__item" data-category="commercial" data-lightbox="https://placehold.co/800x600">
                    <img src="https://placehold.co/400x350" alt="Metal Roofing project" width="400" height="350" loading="lazy">
                    <div class="portfolio-v3__overlay">
                        <span class="portfolio-v3__icon"><svg xmlns="http://www.w3.org/2000/svg" width="32" height="32" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/><line x1="11" y1="8" x2="11" y2="14"/><line x1="8" y1="11" x2="14" y2="11"/></svg></span>
                        <span class="portfolio-v3__label">Metal Roofing</span>
                    </div>
                </div>
            <?php endif; ?>
        </div>
    </div>
</section>
