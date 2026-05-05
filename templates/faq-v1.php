<!-- FAQ V1 — Classic Accordion -->
<?php
$headline    = get_sub_field('faq_v1_headline') ?: 'Frequently Asked Questions';
$subheadline = get_sub_field('faq_v1_subheadline') ?: 'Get Answers to Common Roofing Questions';
$faqs        = get_sub_field('faq_v1_faqs');
?>
<section class="faq-v1" aria-labelledby="faq-v1-heading">
    <div class="container">
        <h2 id="faq-v1-heading" class="faq-v1__headline"><?php echo esc_html($headline); ?></h2>
        <h3 class="faq-v1__subheadline"><?php echo esc_html($subheadline); ?></h3>

        <div class="faq-v1__list" role="list">
            <?php if (!empty($faqs)) : ?>
                <?php foreach ($faqs as $index => $faq) :
                    $i        = $index + 1;
                    $question = $faq['faq_question'] ?? '';
                    $answer   = $faq['faq_answer'] ?? '';
                ?>
                    <div class="faq-v1__item" role="listitem">
                        <button class="faq-v1__question" aria-expanded="false" aria-controls="faq-v1-answer-<?php echo $i; ?>" id="faq-v1-question-<?php echo $i; ?>">
                            <span class="faq-v1__question-text"><?php echo esc_html($question); ?></span>
                            <span class="faq-v1__icon" aria-hidden="true">
                                <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="6 9 12 15 18 9"/></svg>
                            </span>
                        </button>
                        <div class="faq-v1__answer" id="faq-v1-answer-<?php echo $i; ?>" role="region" aria-labelledby="faq-v1-question-<?php echo $i; ?>" hidden>
                            <div class="faq-v1__answer-content">
                                <?php echo wp_kses_post($answer); ?>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php else : ?>
                <div class="faq-v1__item" role="listitem">
                    <button class="faq-v1__question" aria-expanded="false" aria-controls="faq-v1-answer-1" id="faq-v1-question-1">
                        <span class="faq-v1__question-text">How much does a new roof cost?</span>
                        <span class="faq-v1__icon" aria-hidden="true">
                            <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="6 9 12 15 18 9"/></svg>
                        </span>
                    </button>
                    <div class="faq-v1__answer" id="faq-v1-answer-1" role="region" aria-labelledby="faq-v1-question-1" hidden>
                        <div class="faq-v1__answer-content">
                            <p>The cost of a new roof varies based on several factors including the size of your roof, the materials chosen, and the complexity of the installation. We offer free estimates and provide detailed quotes with no obligation. Most residential roof replacements in the Milwaukee area range from $8,000 to $25,000 depending on these factors. Contact us today for a personalized estimate.</p>
                        </div>
                    </div>
                </div>

                <div class="faq-v1__item" role="listitem">
                    <button class="faq-v1__question" aria-expanded="false" aria-controls="faq-v1-answer-2" id="faq-v1-question-2">
                        <span class="faq-v1__question-text">How long does a roof installation take?</span>
                        <span class="faq-v1__icon" aria-hidden="true">
                            <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="6 9 12 15 18 9"/></svg>
                        </span>
                    </button>
                    <div class="faq-v1__answer" id="faq-v1-answer-2" role="region" aria-labelledby="faq-v1-question-2" hidden>
                        <div class="faq-v1__answer-content">
                            <p>Most residential roof installations are completed in 1 to 3 days, depending on the size and complexity of your roof. Weather conditions and material availability can affect the timeline. We work efficiently while maintaining our high quality standards, and we always communicate any changes to the schedule promptly.</p>
                        </div>
                    </div>
                </div>

                <div class="faq-v1__item" role="listitem">
                    <button class="faq-v1__question" aria-expanded="false" aria-controls="faq-v1-answer-3" id="faq-v1-question-3">
                        <span class="faq-v1__question-text">Do you work with insurance companies?</span>
                        <span class="faq-v1__icon" aria-hidden="true">
                            <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="6 9 12 15 18 9"/></svg>
                        </span>
                    </button>
                    <div class="faq-v1__answer" id="faq-v1-answer-3" role="region" aria-labelledby="faq-v1-question-3" hidden>
                        <div class="faq-v1__answer-content">
                            <p>Yes, we have extensive experience working with insurance claims. Storm damage is often covered by your homeowner's insurance policy. We can help guide you through the claims process, provide detailed documentation for your insurer, and work directly with your insurance company to ensure you receive fair compensation for covered repairs or replacement.</p>
                        </div>
                    </div>
                </div>

                <div class="faq-v1__item" role="listitem">
                    <button class="faq-v1__question" aria-expanded="false" aria-controls="faq-v1-answer-4" id="faq-v1-question-4">
                        <span class="faq-v1__question-text">What warranty do you offer?</span>
                        <span class="faq-v1__icon" aria-hidden="true">
                            <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="6 9 12 15 18 9"/></svg>
                        </span>
                    </button>
                    <div class="faq-v1__answer" id="faq-v1-answer-4" role="region" aria-labelledby="faq-v1-question-4" hidden>
                        <div class="faq-v1__answer-content">
                            <p>We stand behind our work with a comprehensive craftsmanship warranty. Manufacturer warranties on materials vary by product, typically ranging from 25 to 50 years. We also provide a 5-year labor warranty on all installations. Ask us about our extended warranty options for additional peace of mind.</p>
                        </div>
                    </div>
                </div>

                <div class="faq-v1__item" role="listitem">
                    <button class="faq-v1__question" aria-expanded="false" aria-controls="faq-v1-answer-5" id="faq-v1-question-5">
                        <span class="faq-v1__question-text">Do you offer free inspections?</span>
                        <span class="faq-v1__icon" aria-hidden="true">
                            <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="6 9 12 15 18 9"/></svg>
                        </span>
                    </button>
                    <div class="faq-v1__answer" id="faq-v1-answer-5" role="region" aria-labelledby="faq-v1-question-5" hidden>
                        <div class="faq-v1__answer-content">
                            <p>Absolutely. We offer free, no-obligation roof inspections. Our thorough assessments check for visible damage, wear patterns, potential leak sources, and overall condition. You'll receive a detailed written report of our findings along with recommendations for maintenance or repair options.</p>
                        </div>
                    </div>
                </div>

                <div class="faq-v1__item" role="listitem">
                    <button class="faq-v1__question" aria-expanded="false" aria-controls="faq-v1-answer-6" id="faq-v1-question-6">
                        <span class="faq-v1__question-text">What areas do you serve in Milwaukee?</span>
                        <span class="faq-v1__icon" aria-hidden="true">
                            <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="6 9 12 15 18 9"/></svg>
                        </span>
                    </button>
                    <div class="faq-v1__answer" id="faq-v1-answer-6" role="region" aria-labelledby="faq-v1-question-6" hidden>
                        <div class="faq-v1__answer-content">
                            <p>We serve the greater Milwaukee metropolitan area and surrounding counties including Waukesha, Ozaukee, Washington, and Racine counties. If you're unsure whether your location is within our service area, give us a call. We're always happy to discuss your project regardless of location.</p>
                        </div>
                    </div>
                </div>
            <?php endif; ?>
        </div>
    </div>
</section>
