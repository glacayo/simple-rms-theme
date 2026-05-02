<?php
$footer_version = sanitize_key(rms_get_option('company_footer_version') ?: 'footer-v2');
get_template_part("templates/{$footer_version}");
wp_footer();
?>
</body>
</html>
