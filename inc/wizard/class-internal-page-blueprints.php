<?php
/**
 * Fixed internal page blueprint registry.
 *
 * @package Simple_RMS_Theme
 */

namespace Inc\Wizard;

defined( 'ABSPATH' ) || exit;

/**
 * Maps each internal page type to template, layouts, harness page type, and canonical policy.
 */
final class Internal_Page_Blueprints {
	/**
	 * Page types whose `_wp_page_template` may be assigned at Generate Pages shell creation.
	 *
	 * @return string[]
	 */
	public static function shell_ready_types(): array {
		return [ 'about', 'services', 'contact', 'projects', 'testimonials', 'blog' ];
	}

	/**
	 * Return the fixed blueprint map keyed by page type.
	 *
	 * PAGE_PROJECTS and PAGE_TESTIMONIALS are string identifiers until the harness
	 * defines those constants. Existing types use AI_Content_Harness page-type constants.
	 *
	 * @return array<string,array{template:string,layouts:array<int,string>,page_type:string,canonical:string}>
	 */
	public static function all(): array {
		return [
			'about'         => [
				'template'  => 'pages/about-us.php',
				'layouts'   => [ 'about-us', 'vision-mission-v2' ],
				'page_type' => AI_Content_Harness::PAGE_ABOUT,
				'canonical' => 'copy',
			],
			'services'      => [
				'template'  => 'pages/services.php',
				'layouts'   => [ 'services-v1', 'cta-v2' ],
				'page_type' => AI_Content_Harness::PAGE_SERVICE,
				'canonical' => 'copy',
			],
			'contact'       => [
				'template'  => 'pages/contact-us.php',
				'layouts'   => [ 'contact-info' ],
				'page_type' => AI_Content_Harness::PAGE_CONTACT,
				'canonical' => 'copy',
			],
			'projects'      => [
				'template'  => 'pages/projects.php',
				'layouts'   => [ 'gallery-grid' ],
				'page_type' => 'PAGE_PROJECTS',
				'canonical' => 'copy',
			],
			'testimonials'  => [
				'template'  => 'pages/testimonials.php',
				'layouts'   => [ 'testimonials-v1' ],
				'page_type' => 'PAGE_TESTIMONIALS',
				'canonical' => 'copy',
			],
			'blog'          => [
				'template'  => 'home.php',
				'layouts'   => [ 'blog-v1' ],
				'page_type' => AI_Content_Harness::PAGE_BLOG,
				'canonical' => 'copy',
			],
		];
	}
}
