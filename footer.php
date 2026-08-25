<?php
$footer_version = rms_get_footer_version();
if ($footer_version !== '') {
    get_template_part("templates/{$footer_version}");
}
wp_footer();
?>
</body>
</html>
