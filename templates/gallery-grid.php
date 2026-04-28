<!-- Gallery Grid — Photo Gallery with Pagination -->
<?php
/**
 * Gallery Grid Template
 *
 * 3 columns x 4 rows = 12 images per page
 * Hover overlay with zoom icon (lightbox)
 * Pagination above and below grid
 */

// Mock data — replace with ACF/CPT later
$total_images = 48; // Total images across all pages
$per_page = 12;
$total_pages = ceil($total_images / $per_page);
$current_page = isset($_GET['gallery_page']) ? max(1, intval($_GET['gallery_page'])) : 1;
$current_page = min($current_page, $total_pages);

// Sample gallery items (repeated to simulate 48 images)
$gallery_items = [];
$project_names = [
    'Commercial Roofing', 'Residential Repair', 'Storm Damage', 'New Construction',
    'Gutter Install', 'Skylight Setup', 'Metal Roofing', 'Tile Restoration',
    'Emergency Patch', 'Inspection Report', 'Leak Detection', 'Preventive Care',
];
for ($i = 0; $i < $per_page; $i++) {
    $index = (($current_page - 1) * $per_page) + $i;
    $gallery_items[] = [
        'thumb' => 'https://placehold.co/400x300',
        'full'  => 'https://placehold.co/1200x900',
        'label' => $project_names[$index % count($project_names)],
    ];
}
?>

<section class="gallery-grid">
    <div class="container">

        <!-- Pagination Top -->
        <nav class="gallery-pagination" aria-label="Gallery pagination">
            <a href="?gallery_page=1"
               class="gallery-pagination__arrow gallery-pagination__arrow--first <?php echo ($current_page <= 1) ? 'is-disabled' : ''; ?>"
               <?php echo ($current_page <= 1) ? 'aria-disabled="true"' : ''; ?>>
                &#171;
            </a>
            <a href="?gallery_page=<?php echo max(1, $current_page - 1); ?>"
               class="gallery-pagination__arrow gallery-pagination__arrow--prev <?php echo ($current_page <= 1) ? 'is-disabled' : ''; ?>"
               <?php echo ($current_page <= 1) ? 'aria-disabled="true"' : ''; ?>>
                &#8249;
            </a>

            <?php for ($p = 1; $p <= $total_pages; $p++) : ?>
                <a href="?gallery_page=<?php echo $p; ?>"
                   class="gallery-pagination__number <?php echo ($p === $current_page) ? 'is-active' : ''; ?>">
                    <?php echo $p; ?>
                </a>
            <?php endfor; ?>

            <a href="?gallery_page=<?php echo min($total_pages, $current_page + 1); ?>"
               class="gallery-pagination__arrow gallery-pagination__arrow--next <?php echo ($current_page >= $total_pages) ? 'is-disabled' : ''; ?>"
               <?php echo ($current_page >= $total_pages) ? 'aria-disabled="true"' : ''; ?>>
                &#8250;
            </a>
            <a href="?gallery_page=<?php echo $total_pages; ?>"
               class="gallery-pagination__arrow gallery-pagination__arrow--last <?php echo ($current_page >= $total_pages) ? 'is-disabled' : ''; ?>"
               <?php echo ($current_page >= $total_pages) ? 'aria-disabled="true"' : ''; ?>>
                &#187;
            </a>
        </nav>

        <!-- Gallery Grid -->
        <div class="gallery-grid__container">
            <?php foreach ($gallery_items as $item) : ?>
                <div class="gallery-grid__item" data-lightbox="<?php echo esc_attr($item['full']); ?>">
                    <img src="<?php echo esc_attr($item['thumb']); ?>"
                         alt="<?php echo esc_attr($item['label']); ?>"
                         width="400" height="300" loading="lazy">
                    <div class="gallery-grid__overlay">
                        <span class="gallery-grid__icon" aria-hidden="true"><svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/><line x1="11" y1="8" x2="11" y2="14"/><line x1="8" y1="11" x2="14" y2="11"/></svg></span>
                        <span class="gallery-grid__label"><?php echo esc_html($item['label']); ?></span>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>

        <!-- Pagination Bottom -->
        <nav class="gallery-pagination" aria-label="Gallery pagination">
            <a href="?gallery_page=1"
               class="gallery-pagination__arrow gallery-pagination__arrow--first <?php echo ($current_page <= 1) ? 'is-disabled' : ''; ?>"
               <?php echo ($current_page <= 1) ? 'aria-disabled="true"' : ''; ?>>
                &#171;
            </a>
            <a href="?gallery_page=<?php echo max(1, $current_page - 1); ?>"
               class="gallery-pagination__arrow gallery-pagination__arrow--prev <?php echo ($current_page <= 1) ? 'is-disabled' : ''; ?>"
               <?php echo ($current_page <= 1) ? 'aria-disabled="true"' : ''; ?>>
                &#8249;
            </a>

            <?php for ($p = 1; $p <= $total_pages; $p++) : ?>
                <a href="?gallery_page=<?php echo $p; ?>"
                   class="gallery-pagination__number <?php echo ($p === $current_page) ? 'is-active' : ''; ?>">
                    <?php echo $p; ?>
                </a>
            <?php endfor; ?>

            <a href="?gallery_page=<?php echo min($total_pages, $current_page + 1); ?>"
               class="gallery-pagination__arrow gallery-pagination__arrow--next <?php echo ($current_page >= $total_pages) ? 'is-disabled' : ''; ?>"
               <?php echo ($current_page >= $total_pages) ? 'aria-disabled="true"' : ''; ?>>
                &#8250;
            </a>
            <a href="?gallery_page=<?php echo $total_pages; ?>"
               class="gallery-pagination__arrow gallery-pagination__arrow--last <?php echo ($current_page >= $total_pages) ? 'is-disabled' : ''; ?>"
               <?php echo ($current_page >= $total_pages) ? 'aria-disabled="true"' : ''; ?>>
                &#187;
            </a>
        </nav>

    </div>
</section>
