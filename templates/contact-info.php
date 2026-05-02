<!-- Contact Info + Form — Two Column Layout -->
<section class="contact-info" id="contact">
    <div class="container">
        <div class="contact-info__grid">

            <!-- Left Column: Company Info -->
            <div class="contact-info__info">
                <h2 class="contact-info__headline">Get in Touch</h2>
                <p class="contact-info__intro">We're here to help with any roofing project. Reach out for a free estimate or to schedule a consultation.</p>

                <div class="contact-info__list">

                    <?php
                    $phones = get_field('company_phones', 'option');
                    if (is_array($phones) && !empty($phones)) :
                        $primary_phone = $phones[0]['phone_number'] ?? '';
                        $primary_phone_clean = preg_replace('/[^0-9+]/', '', $primary_phone);
                        ?>
                    <div class="contact-info__item">
                        <span class="contact-info__icon contact-info__icon--phone">
                            <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M22 16.92v3a2 2 0 0 1-2.18 2 19.79 19.79 0 0 1-8.63-3.07A19.5 19.5 0 0 1 4.82 12a19.79 19.79 0 0 1-3.07-8.67A2 2 0 0 1 3.73 1h3a2 2 0 0 1 2 1.72c.127.96.361 1.903.7 2.81a2 2 0 0 1-.45 2.11L8.09 8.91a16 16 0 0 0 5.78 5.78l.91-.91a2 2 0 0 1 2.11-.45c.907.339 1.85.573 2.81.7A2 2 0 0 1 22 16.92z"/></svg>
                        </span>
                        <div>
                            <strong class="contact-info__item-label">Phone</strong>
                            <?php if (count($phones) === 1) : ?>
                                <a href="tel:<?php echo esc_attr($primary_phone_clean); ?>" class="contact-info__item-value"><?php echo esc_html($primary_phone); ?></a>
                            <?php else : ?>
                                <?php foreach ($phones as $phone) : ?>
                                    <?php
                                    $phone_clean = preg_replace('/[^0-9+]/', '', $phone['phone_number'] ?? '');
                                    $phone_label = esc_html($phone['phone_label'] ?? '');
                                    ?>
                                    <a href="tel:<?php echo esc_attr($phone_clean); ?>" class="contact-info__item-value"><?php echo esc_html($phone['phone_number'] ?? ''); ?></a>
                                    <?php if (!empty($phone_label)) : ?>
                                        <span class="contact-info__item-meta"><?php echo esc_html($phone_label); ?></span>
                                    <?php endif; ?>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </div>
                    </div>
                    <?php endif; ?>

                    <?php
                    $emails = get_field('company_emails', 'option');
                    if (is_array($emails) && !empty($emails)) :
                        $primary_email = $emails[0]['email_address'] ?? '';
                        ?>
                    <div class="contact-info__item">
                        <span class="contact-info__icon contact-info__icon--email">
                            <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2z"/><polyline points="22,6 12,13 2,6"/></svg>
                        </span>
                        <div>
                            <strong class="contact-info__item-label">Email</strong>
                            <?php if (count($emails) === 1) : ?>
                                <a href="mailto:<?php echo esc_attr($primary_email); ?>" class="contact-info__item-value"><?php echo esc_html($primary_email); ?></a>
                            <?php else : ?>
                                <?php foreach ($emails as $email) : ?>
                                    <?php
                                    $email_addr = $email['email_address'] ?? '';
                                    $email_label = esc_html($email['email_label'] ?? '');
                                    ?>
                                    <a href="mailto:<?php echo esc_attr($email_addr); ?>" class="contact-info__item-value"><?php echo esc_html($email_addr); ?></a>
                                    <?php if (!empty($email_label)) : ?>
                                        <span class="contact-info__item-meta"><?php echo esc_html($email_label); ?></span>
                                    <?php endif; ?>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </div>
                    </div>
                    <?php endif; ?>

                    <?php
                    $addr_line1 = rms_get_option('company_address_line_1');
                    $addr_line2 = rms_get_option('company_address_line_2');
                    $addr_city  = rms_get_option('company_city');
                    $addr_state = rms_get_option('company_state');
                    $addr_zip   = rms_get_option('company_postal_code');
                    $addr_country = rms_get_option('company_country');

                    $address_parts = array_filter([
                        $addr_line1,
                        $addr_line2,
                        $addr_city,
                        $addr_state,
                        $addr_zip,
                        $addr_country,
                    ]);

                    if (!empty($address_parts)) :
                        $full_address = implode(', ', $address_parts);
                        ?>
                    <div class="contact-info__item">
                        <span class="contact-info__icon contact-info__icon--location">
                            <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0 1 18 0z"/><circle cx="12" cy="10" r="3"/></svg>
                        </span>
                        <div>
                            <strong class="contact-info__item-label">Address</strong>
                            <span class="contact-info__item-value"><?php echo esc_html($full_address); ?></span>
                        </div>
                    </div>
                    <?php endif; ?>

                    <?php
                    $estimate = rms_get_option('company_estimate_available');
                    if ($estimate) : ?>
                    <div class="contact-info__item contact-info__item--highlight">
                        <span class="contact-info__icon contact-info__icon--badge">
                            <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="20 6 9 17 4 12"/></svg>
                        </span>
                        <div>
                            <strong class="contact-info__item-label"><?php echo esc_html($estimate); ?></strong>
                            <span class="contact-info__item-value">No obligation quote within 24 hours</span>
                        </div>
                    </div>
                    <?php endif; ?>

                    <?php
                    $lang = rms_get_option('company_language');
                    if ($lang) : ?>
                    <div class="contact-info__item">
                        <span class="contact-info__icon contact-info__icon--lang">
                            <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><line x1="2" y1="12" x2="22" y2="12"/><path d="M12 2a15.3 15.3 0 0 1 4 10 15.3 15.3 0 0 1-4 10 15.3 15.3 0 0 1-4-10 15.3 15.3 0 0 1 4-10z"/></svg>
                        </span>
                        <div>
                            <strong class="contact-info__item-label">Languages</strong>
                            <span class="contact-info__item-value"><?php echo esc_html($lang); ?></span>
                        </div>
                    </div>
                    <?php endif; ?>

                    <?php
                    $schedule = get_field('company_schedule', 'option');
                    if (is_array($schedule) && !empty($schedule)) :
                        $day_labels = [
                            'monday'    => 'Mon',
                            'tuesday'   => 'Tue',
                            'wednesday' => 'Wed',
                            'thursday'  => 'Thu',
                            'friday'    => 'Fri',
                            'saturday'  => 'Sat',
                            'sunday'    => 'Sun',
                        ];
                        $schedule_lines = [];
                        foreach ($schedule as $row) {
                            $day = $row['schedule_day'] ?? '';
                            $open = $row['schedule_open_time'] ?? '';
                            $close = $row['schedule_close_time'] ?? '';
                            $is_closed = !empty($row['schedule_is_closed']);

                            if (empty($day)) continue;

                            $day_label = $day_labels[$day] ?? ucfirst($day);
                            if ($is_closed) {
                                $schedule_lines[] = $day_label . ': Closed';
                            } elseif (!empty($open) && !empty($close)) {
                                // Format time from 24h to 12h
                                $open_12 = date('g:i a', strtotime($open));
                                $close_12 = date('g:i a', strtotime($close));
                                $schedule_lines[] = $day_label . ': ' . $open_12 . ' - ' . $close_12;
                            }
                        }
                        if (!empty($schedule_lines)) :
                            ?>
                    <div class="contact-info__item">
                        <span class="contact-info__icon contact-info__icon--clock">
                            <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg>
                        </span>
                        <div>
                            <strong class="contact-info__item-label">Business Hours</strong>
                            <span class="contact-info__item-value"><?php echo implode('<br>', array_map('esc_html', $schedule_lines)); ?></span>
                        </div>
                    </div>
                        <?php endif; ?>
                    <?php endif; ?>

                    <?php
                    $covered = rms_get_option('company_covered_areas');
                    if ($covered) : ?>
                    <div class="contact-info__item">
                        <span class="contact-info__icon contact-info__icon--map">
                            <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polygon points="1 6 1 22 8 18 16 22 23 18 23 2 16 6 8 2 1 6"/><line x1="8" y1="2" x2="8" y2="18"/><line x1="16" y1="6" x2="16" y2="22"/></svg>
                        </span>
                        <div>
                            <strong class="contact-info__item-label">Service Area</strong>
                            <span class="contact-info__item-value"><?php echo esc_html($covered); ?></span>
                        </div>
                    </div>
                    <?php endif; ?>

                    <div class="contact-info__item">
                        <span class="contact-info__icon contact-info__icon--payment">
                            <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="1" y="4" width="22" height="16" rx="2" ry="2"/><line x1="1" y1="10" x2="23" y2="10"/></svg>
                        </span>
                        <div>
                            <strong class="contact-info__item-label">Payment Methods</strong>
                            <?php
                        $methods = get_field('company_payment_methods', 'option');
                        $method_labels = [];
                        if (!empty($methods) && is_array($methods)) {
                            foreach ($methods as $m) {
                                if (!empty($m['payment_method_name'])) {
                                    $method_labels[] = esc_html($m['payment_method_name']);
                                }
                            }
                        }
                        $methods_display = !empty($method_labels) ? implode(', ', $method_labels) : 'None specified';
                        ?>
                        <span class="contact-info__item-value"><?php echo $methods_display; ?></span>
                        </div>
                    </div>

                </div>
            </div>

            <!-- Right Column: Contact Form -->
            <div class="contact-info__form-wrapper">
                <h3 class="contact-info__form-headline">Request a Free Estimate</h3>
                <form class="contact-info__form" action="#" method="POST" novalidate>

                    <div class="contact-info__form-row contact-info__form-row--2col">
                        <div class="contact-info__field">
                            <label for="contact-name" class="contact-info__label">Full Name <span class="contact-info__required">*</span></label>
                            <input type="text" id="contact-name" name="contact_name" class="contact-info__input" placeholder="John Doe" required>
                        </div>
                        <div class="contact-info__field">
                            <label for="contact-email" class="contact-info__label">Email Address <span class="contact-info__required">*</span></label>
                            <input type="email" id="contact-email" name="contact_email" class="contact-info__input" placeholder="john@example.com" required>
                        </div>
                    </div>

                    <div class="contact-info__form-row contact-info__form-row--2col">
                        <div class="contact-info__field">
                            <label for="contact-phone" class="contact-info__label">Phone Number <span class="contact-info__required">*</span></label>
                            <input type="tel" id="contact-phone" name="contact_phone" class="contact-info__input" placeholder="(414) 000-0000" required>
                        </div>
                        <div class="contact-info__field">
                            <label for="contact-zip" class="contact-info__label">Zip Code <span class="contact-info__required">*</span></label>
                            <input type="text" id="contact-zip" name="contact_zip" class="contact-info__input" placeholder="53201" required>
                        </div>
                    </div>

                    <div class="contact-info__field">
                        <label for="contact-service" class="contact-info__label">Service of Interest</label>
                        <select id="contact-service" name="contact_service" class="contact-info__select">
                            <option value="">Select a service...</option>
                            <option value="roof-installation">Roof Installation</option>
                            <option value="roof-repair">Roof Repair</option>
                            <option value="roof-replacement">Roof Replacement</option>
                            <option value="roof-inspection">Roof Inspection</option>
                            <option value="gutter-installation">Gutter Installation</option>
                            <option value="emergency-services">Emergency Services</option>
                            <option value="storm-damage">Storm Damage Repair</option>
                            <option value="leak-detection">Leak Detection</option>
                            <option value="skylight">Skylight Installation</option>
                            <option value="maintenance">Preventive Maintenance</option>
                            <option value="other">Other</option>
                        </select>
                    </div>

                    <div class="contact-info__field">
                        <label for="contact-message" class="contact-info__label">Brief Description of Your Project <span class="contact-info__required">*</span></label>
                        <textarea id="contact-message" name="contact_message" class="contact-info__textarea" rows="5" placeholder="Tell us about your project, the type of property, and any specific needs or concerns..." required></textarea>
                    </div>

                    <div class="contact-info__form-actions">
                        <button type="submit" class="btn contact-info__submit">
                            <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="22" y1="2" x2="11" y2="13"/><polygon points="22 2 15 22 11 13 2 9 22 2"/></svg>
                            Send Message
                        </button>
                        <p class="contact-info__form-note">We typically respond within 24 hours.</p>
                    </div>

                </form>
            </div>

        </div>
    </div>
</section>
