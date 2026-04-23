<!-- Hero Section -->
<section class="hero" style="--hero-bg: url('https://images.unsplash.com/photo-1486406146926-c627a92ad1ab?w=1920&q=80');">
    <div class="hero__overlay hero__overlay--dark"></div>
    <div class="container">
        <div class="grid-2">

            <!-- Left Column -->
            <div class="hero__col-left">
                <div class="hero__reviews">
                    <span class="hero__reviews-stars" aria-label="5 out of 5 stars"><svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="currentColor" stroke="none" aria-hidden="true"><path d="M12 2l3.09 6.26L22 9.27l-5 4.87 1.18 6.88L12 17.77l-6.18 3.25L7 14.14 2 9.27l6.91-1.01L12 2z"/></svg><svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="currentColor" stroke="none" aria-hidden="true"><path d="M12 2l3.09 6.26L22 9.27l-5 4.87 1.18 6.88L12 17.77l-6.18 3.25L7 14.14 2 9.27l6.91-1.01L12 2z"/></svg><svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="currentColor" stroke="none" aria-hidden="true"><path d="M12 2l3.09 6.26L22 9.27l-5 4.87 1.18 6.88L12 17.77l-6.18 3.25L7 14.14 2 9.27l6.91-1.01L12 2z"/></svg><svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="currentColor" stroke="none" aria-hidden="true"><path d="M12 2l3.09 6.26L22 9.27l-5 4.87 1.18 6.88L12 17.77l-6.18 3.25L7 14.14 2 9.27l6.91-1.01L12 2z"/></svg><svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="currentColor" stroke="none" aria-hidden="true"><path d="M12 2l3.09 6.26L22 9.27l-5 4.87 1.18 6.88L12 17.77l-6.18 3.25L7 14.14 2 9.27l6.91-1.01L12 2z"/></svg></span>
                    <span class="hero__reviews-label">5.0 Google Reviews</span>
                </div>
                <h1 class="hero__title">Professional Roofing Solutions You Can Trust</h1>
                <p class="hero__text">We deliver high-quality roofing services for residential and commercial properties. Our experienced team ensures durable, weather-resistant installations backed by industry-leading warranties. From inspections to full replacements, we handle every project with precision and care.</p>
            </div>

            <!-- Right Column — Free Estimate Form -->
            <div class="hero__col-right">
                <div class="hero__form-card">
                    <h2 class="hero__form-title">FREE Estimate</h2>
                    <form class="hero__form" method="post">
                        <div class="hero__form-group">
                            <label for="hero-name">Full Name</label>
                            <input type="text" id="hero-name" name="name" placeholder="Your name" required>
                        </div>
                        <div class="hero__form-group">
                            <label for="hero-email">Email</label>
                            <input type="email" id="hero-email" name="email" placeholder="your@email.com" required>
                        </div>
                        <div class="hero__form-row">
                            <div class="hero__form-group">
                                <label for="hero-phone">Phone</label>
                                <input type="tel" id="hero-phone" name="phone" placeholder="(555) 123-4567" required>
                            </div>
                            <div class="hero__form-group">
                                <label for="hero-zip">Zip Code</label>
                                <input type="text" id="hero-zip" name="zip" placeholder="00000" required>
                            </div>
                        </div>
                        <div class="hero__form-group">
                            <label for="hero-service">Service Interested In</label>
                            <select id="hero-service" name="service" required>
                                <option value="" disabled selected>Select a service</option>
                                <option value="roof-installation">Roof Installation</option>
                                <option value="roof-repair">Roof Repair</option>
                                <option value="roof-replacement">Roof Replacement</option>
                                <option value="inspection">Roof Inspection</option>
                                <option value="gutters">Gutter Installation</option>
                                <option value="other">Other</option>
                            </select>
                        </div>
                        <div class="hero__form-group">
                            <label for="hero-summary">Project Summary</label>
                            <textarea id="hero-summary" name="summary" rows="4" placeholder="Briefly describe your project..."></textarea>
                        </div>
                        <button type="submit" class="btn hero__form-submit">Get My Free Estimate</button>
                    </form>
                </div>
            </div>

        </div>
    </div>
</section>
