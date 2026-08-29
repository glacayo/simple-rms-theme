<?php
/**
 * Legacy identity, GET preview, and mapping proofs.
 *
 * Usage: php tests/wizard-internal-page-identity-harness.php
 */
if ( PHP_SAPI !== 'cli' ) {
	fwrite( STDERR, "CLI only.\n" );
	exit( 1 );
}

require_once __DIR__ . '/wizard-internal-page-activation-bootstrap.php';

use Inc\Wizard\Internal_Page_Identity;
use Inc\Wizard\Logger;
use Inc\Wizard\State_Manager;
use Inc\Wizard\Step_Controller;
use Inc\Wizard\Step_Internal_Page_Builder;
use Inc\Wizard\Wizard_Mutation_Fence;

$passed = 0;
function rms_id_assert( $c, string $m ): void {
	if ( ! $c ) {
		fwrite( STDERR, $m . "\n" );
		exit( 1 );
	}
}

rms_ipa_reset();
$GLOBALS['_posts'][21] = new WP_Post( 21 );
$GLOBALS['_posts'][21]->post_name = 'our-story';
$sm = new State_Manager();
$st = $sm->get_state();
$st['generated_pages'] = array(
	array( 'id' => 21, 'slug' => 'our-story', 'title' => 'Our Story', 'role' => '' ),
);
$sm->save_state( $st );
$before = $sm->get_state();
$preview = Internal_Page_Identity::preview_plan( $before );
$after_get = ( new Step_Controller() )->get_resume_state();
rms_id_assert( $before === $sm->get_state(), 'GET preview does not write wizard state' );
rms_id_assert( empty( $preview['types']['about']['available'] ), 'custom slug is not guessed as about' );
rms_id_assert( 1 === count( $preview['unmapped'] ) && true === $preview['unmapped'][0]['mapping_needed'], 'untyped custom slug is mapping_needed' );
rms_id_assert( isset( $after_get['internal_page_preview']['unmapped'][0] ), 'resume state exposes unmapped shells' );
echo "PASS get-preview-no-write-custom-slug-unmapped\n";
++$passed;

rms_ipa_reset();
$GLOBALS['_posts'][12] = new WP_Post( 12 );
$GLOBALS['_posts'][12]->post_name = 'about-us';
$sm = new State_Manager();
$st = $sm->get_state();
$st['generated_pages'] = array( array( 'id' => 12, 'slug' => 'about-us', 'role' => '' ) );
$sm->save_state( $st );
$options_before = $GLOBALS['_options'];
Internal_Page_Identity::preview_plan( $sm->get_state() );
( new Step_Controller() )->get_resume_state();
rms_id_assert( $options_before === $GLOBALS['_options'], 'GET hydration writes no options' );
$builder = new Step_Internal_Page_Builder();
$fence = new Wizard_Mutation_Fence();
$owner = $fence->acquire();
$builder->accept_mutation_owner( (string) $owner );
$fence->authorize_agent( $builder, (string) $owner );
$builder->run( array( 'action' => 'start' ) );
$pages = ( new State_Manager() )->get_state()['generated_pages'];
rms_id_assert( 'about' === ( $pages[0]['type'] ?? '' ) && 12 === (int) $pages[0]['id'], 'legacy alias persists type and post ID on mutation' );
$fence->clear_agent( (string) $owner );
$fence->release( (string) $owner );
echo "PASS legacy-alias-persists-on-mutation-only\n";
++$passed;

rms_ipa_reset();
$GLOBALS['_posts'][31] = new WP_Post( 31 );
$GLOBALS['_posts'][32] = new WP_Post( 32 );
$GLOBALS['_post_meta'][31]['_wp_page_template'] = 'pages/services.php';
$sm = new State_Manager();
$st = $sm->get_state();
$st['generated_pages'] = array(
	array( 'id' => 31, 'slug' => 'what-we-offer', 'role' => '' ),
	array( 'id' => 32, 'slug' => 'news', 'role' => 'blog' ),
);
$sm->save_state( $st );
$preview = Internal_Page_Identity::preview_plan( $sm->get_state() );
rms_id_assert( true === $preview['types']['services']['available'] && 'template' === $preview['types']['services']['identity_source'], 'template meta identifies services' );
rms_id_assert( true === $preview['types']['blog']['available'] && 'role' === $preview['types']['blog']['identity_source'], 'blog role identifies blog' );
echo "PASS template-and-role-identity\n";
++$passed;

rms_ipa_reset();
$GLOBALS['_posts'][40] = new WP_Post( 40 );
$sm = new State_Manager();
$st = $sm->get_state();
$st['generated_pages'] = array( array( 'id' => 40, 'slug' => 'history', 'role' => '' ) );
$sm->save_state( $st );
$missing = Internal_Page_Identity::apply_map(
	$st['generated_pages'],
	array( array( 'post_id' => 40, 'type' => 'about' ) )
);
rms_id_assert( is_wp_error( $missing ) && 'rms_wizard_internal_map_confirmation_required' === $missing->get_error_code(), 'mapping without confirmation is rejected' );
rms_id_assert( '' === (string) ( ( new State_Manager() )->get_state()['generated_pages'][0]['type'] ?? '' ), 'missing confirmation writes no type' );
echo "PASS mapping-missing-confirmation\n";
++$passed;

$confirm = array( 'confirm_map' => true, 'confirm_map_types' => array( 'about' ) );
$mapped = Internal_Page_Identity::apply_map(
	$st['generated_pages'],
	array( array( 'post_id' => 40, 'type' => 'about' ) ),
	array(),
	$confirm
);
rms_id_assert( ! is_wp_error( $mapped ) && 'about' === ( $mapped[0]['type'] ?? '' ), 'confirmed map assigns type' );
echo "PASS mapping-confirmed-success\n";
++$passed;

$GLOBALS['_posts'][41] = new WP_Post( 41 );
$dup_type = Internal_Page_Identity::apply_map(
	array(
		array( 'id' => 40, 'slug' => 'history', 'role' => '' ),
		array( 'id' => 41, 'slug' => 'story', 'role' => '' ),
	),
	array(
		array( 'post_id' => 40, 'type' => 'about' ),
		array( 'post_id' => 41, 'type' => 'about' ),
	),
	array(),
	array( 'confirm_map' => true, 'confirm_map_types' => array( 'about' ) )
);
rms_id_assert( is_wp_error( $dup_type ) && 'rms_wizard_internal_map_conflict' === $dup_type->get_error_code(), 'duplicate type rejected' );
echo "PASS mapping-duplicate-type\n";
++$passed;

$dup_id = Internal_Page_Identity::apply_map(
	array( array( 'id' => 40, 'slug' => 'history', 'role' => '' ) ),
	array(
		array( 'post_id' => 40, 'type' => 'about' ),
		array( 'post_id' => 40, 'type' => 'services' ),
	),
	array(),
	array( 'confirm_map' => true, 'confirm_map_types' => array( 'about', 'services' ) )
);
rms_id_assert( is_wp_error( $dup_id ) && 'rms_wizard_internal_map_conflict' === $dup_id->get_error_code(), 'duplicate post ID rejected' );
echo "PASS mapping-duplicate-post-id\n";
++$passed;

$plan_conflict = Internal_Page_Identity::apply_map(
	array( array( 'id' => 40, 'slug' => 'history', 'role' => '' ) ),
	array( array( 'post_id' => 40, 'type' => 'about' ) ),
	array( 'about' => array( 'post_id' => 12, 'status' => 'pending' ) ),
	$confirm
);
rms_id_assert( is_wp_error( $plan_conflict ) && 'rms_wizard_internal_map_conflict' === $plan_conflict->get_error_code(), 'persisted plan conflict rejected' );
echo "PASS mapping-conflicting-persisted-plan\n";
++$passed;

$typed = Internal_Page_Identity::apply_map(
	array( array( 'id' => 40, 'slug' => 'history', 'type' => 'services' ) ),
	array( array( 'post_id' => 40, 'type' => 'about' ) ),
	array(),
	$confirm
);
rms_id_assert( is_wp_error( $typed ) && 'rms_wizard_internal_map_conflict' === $typed->get_error_code(), 'different stored type rejected' );
echo "PASS mapping-existing-type-conflict\n";
++$passed;

rms_ipa_reset();
$GLOBALS['_posts'][40] = new WP_Post( 40 );
$GLOBALS['_posts'][41] = new WP_Post( 41 );
$sm = new State_Manager();
$st = $sm->get_state();
$st['generated_pages'] = array(
	array( 'id' => 40, 'slug' => 'history', 'role' => '' ),
	array( 'id' => 41, 'slug' => 'story', 'role' => '' ),
);
$sm->save_state( $st );
$before_state = $sm->get_state();
$meta_before  = $GLOBALS['_post_meta'];
$builder      = new Step_Internal_Page_Builder();
$fence        = new Wizard_Mutation_Fence();
$owner        = $fence->acquire();
$builder->accept_mutation_owner( (string) $owner );
$fence->authorize_agent( $builder, (string) $owner );
$mixed = $builder->run(
	array(
		'action'            => 'start',
		'map_pages'         => array(
			array( 'post_id' => 40, 'type' => 'about' ),
			array( 'post_id' => 41, 'type' => 'about' ),
		),
		'confirm_map'       => true,
		'confirm_map_types' => array( 'about' ),
	)
);
rms_id_assert( is_wp_error( $mixed ) && 'rms_wizard_internal_map_conflict' === $mixed->get_error_code(), 'mixed batch is rejected' );
rms_id_assert( $before_state === ( new State_Manager() )->get_state(), 'mixed invalid batch writes no state' );
rms_id_assert( $meta_before === $GLOBALS['_post_meta'], 'mixed invalid batch writes no ACF' );
$fence->clear_agent( (string) $owner );
$fence->release( (string) $owner );
echo "PASS mapping-mixed-batch-atomic-rollback\n";
++$passed;

rms_ipa_reset();
$GLOBALS['_posts'][40] = new WP_Post( 40 );
$sm = new State_Manager();
$st = $sm->get_state();
$st['generated_pages'] = array( array( 'id' => 40, 'slug' => 'history', 'role' => '' ) );
$sm->save_state( $st );
$builder = new Step_Internal_Page_Builder();
$fence   = new Wizard_Mutation_Fence();
$owner   = $fence->acquire();
$builder->accept_mutation_owner( (string) $owner );
$fence->authorize_agent( $builder, (string) $owner );
$ok = $builder->run(
	array(
		'action'            => 'start',
		'map_pages'         => array( array( 'post_id' => 40, 'type' => 'about' ) ),
		'confirm_map'       => true,
		'confirm_map_types' => array( 'about' ),
	)
);
rms_id_assert( ! is_wp_error( $ok ), 'confirmed builder mapping succeeds' );
rms_id_assert( 'about' === ( ( new State_Manager() )->get_state()['generated_pages'][0]['type'] ?? '' ), 'confirmed mapping persisted type' );
$fence->clear_agent( (string) $owner );
$fence->release( (string) $owner );
echo "PASS mapping-builder-confirmed-success\n";
++$passed;

rms_ipa_reset();
$GLOBALS['_posts'][40] = new WP_Post( 40 );
$GLOBALS['_posts'][40]->post_name = 'home';
$sm = new State_Manager();
$st = $sm->get_state();
$st['generated_pages'] = array( array( 'id' => 40, 'slug' => 'home', 'role' => 'home' ) );
$sm->save_state( $st );
$before_state = $sm->get_state();
$meta_before  = $GLOBALS['_post_meta'];
$options_before = $GLOBALS['_options'];
$GLOBALS['_posts_count'] = 4;
$builder = new Step_Internal_Page_Builder();
$fence   = new Wizard_Mutation_Fence();
$owner   = $fence->acquire();
$builder->accept_mutation_owner( (string) $owner );
$fence->authorize_agent( $builder, (string) $owner );
$remap = $builder->run(
	array(
		'action'            => 'start',
		'map_pages'         => array( array( 'post_id' => 40, 'type' => 'about' ) ),
		'confirm_map'       => true,
		'confirm_map_types' => array( 'about' ),
	)
);
rms_id_assert( is_wp_error( $remap ) && 'rms_wizard_internal_map_invalid' === $remap->get_error_code(), 'home role remap rejected' );
rms_id_assert( $before_state === ( new State_Manager() )->get_state(), 'home remap writes no state' );
rms_id_assert( $meta_before === $GLOBALS['_post_meta'], 'home remap writes no ACF or template meta' );
rms_id_assert( $options_before === $GLOBALS['_options'], 'home remap writes no options or canonical' );
rms_id_assert( 0 === $GLOBALS['_page_writes'], 'home remap writes no pages' );
rms_id_assert( 4 === (int) wp_count_posts( 'post' )->publish, 'home remap does not change post counts' );
rms_id_assert( array() === ( new Logger() )->all(), 'home remap writes no logs' );
$fence->clear_agent( (string) $owner );
$fence->release( (string) $owner );
echo "PASS mapping-home-role-remap-rejected-zero-writes\n";
++$passed;

rms_ipa_reset();
$GLOBALS['_posts'][40] = new WP_Post( 40 );
$GLOBALS['_posts'][40]->post_name = 'blog';
$sm = new State_Manager();
$st = $sm->get_state();
$st['generated_pages'] = array( array( 'id' => 40, 'slug' => 'blog', 'role' => 'blog' ) );
$sm->save_state( $st );
$before_state = $sm->get_state();
$meta_before  = $GLOBALS['_post_meta'];
$options_before = $GLOBALS['_options'];
$GLOBALS['_posts_count'] = 4;
$builder = new Step_Internal_Page_Builder();
$fence   = new Wizard_Mutation_Fence();
$owner   = $fence->acquire();
$builder->accept_mutation_owner( (string) $owner );
$fence->authorize_agent( $builder, (string) $owner );
$remap = $builder->run(
	array(
		'action'            => 'start',
		'map_pages'         => array( array( 'post_id' => 40, 'type' => 'services' ) ),
		'confirm_map'       => true,
		'confirm_map_types' => array( 'services' ),
	)
);
rms_id_assert( is_wp_error( $remap ) && 'rms_wizard_internal_map_invalid' === $remap->get_error_code(), 'blog role remap rejected' );
rms_id_assert( $before_state === ( new State_Manager() )->get_state(), 'blog remap writes no state' );
rms_id_assert( $meta_before === $GLOBALS['_post_meta'], 'blog remap writes no ACF or template meta' );
rms_id_assert( $options_before === $GLOBALS['_options'], 'blog remap writes no options or canonical' );
rms_id_assert( 0 === $GLOBALS['_page_writes'], 'blog remap writes no pages' );
rms_id_assert( 4 === (int) wp_count_posts( 'post' )->publish, 'blog remap does not change post counts' );
rms_id_assert( array() === ( new Logger() )->all(), 'blog remap writes no logs' );
$fence->clear_agent( (string) $owner );
$fence->release( (string) $owner );
echo "PASS mapping-blog-role-remap-rejected-zero-writes\n";
++$passed;

rms_ipa_reset();
$sm = new State_Manager();
$st = $sm->get_state();
$st['generated_pages'] = array( array( 'id' => 40, 'slug' => 'history', 'role' => '' ) );
$sm->save_state( $st );
$before_state = $sm->get_state();
$meta_before  = $GLOBALS['_post_meta'];
$options_before = $GLOBALS['_options'];
$GLOBALS['_posts_count'] = 4;
$builder = new Step_Internal_Page_Builder();
$fence   = new Wizard_Mutation_Fence();
$owner   = $fence->acquire();
$builder->accept_mutation_owner( (string) $owner );
$fence->authorize_agent( $builder, (string) $owner );
$missing = $builder->run(
	array(
		'action'            => 'start',
		'map_pages'         => array( array( 'post_id' => 40, 'type' => 'about' ) ),
		'confirm_map'       => true,
		'confirm_map_types' => array( 'about' ),
	)
);
rms_id_assert( is_wp_error( $missing ) && 'rms_wizard_internal_map_invalid' === $missing->get_error_code(), 'missing live object rejected' );
rms_id_assert( $before_state === ( new State_Manager() )->get_state(), 'missing object writes no state' );
rms_id_assert( $meta_before === $GLOBALS['_post_meta'], 'missing object writes no ACF or template meta' );
rms_id_assert( $options_before === $GLOBALS['_options'], 'missing object writes no options or canonical' );
rms_id_assert( 0 === $GLOBALS['_page_writes'], 'missing object writes no pages' );
rms_id_assert( 4 === (int) wp_count_posts( 'post' )->publish, 'missing object does not change post counts' );
rms_id_assert( array() === ( new Logger() )->all(), 'missing object writes no logs' );
$fence->clear_agent( (string) $owner );
$fence->release( (string) $owner );
echo "PASS mapping-missing-live-object-rejected-zero-writes\n";
++$passed;

rms_ipa_reset();
$GLOBALS['_posts'][41] = new WP_Post( 41 );
$GLOBALS['_posts'][41]->post_type = 'post';
$sm = new State_Manager();
$st = $sm->get_state();
$st['generated_pages'] = array( array( 'id' => 41, 'slug' => 'news', 'role' => '' ) );
$sm->save_state( $st );
$before_state = $sm->get_state();
$meta_before  = $GLOBALS['_post_meta'];
$options_before = $GLOBALS['_options'];
$GLOBALS['_posts_count'] = 4;
$builder = new Step_Internal_Page_Builder();
$fence   = new Wizard_Mutation_Fence();
$owner   = $fence->acquire();
$builder->accept_mutation_owner( (string) $owner );
$fence->authorize_agent( $builder, (string) $owner );
$nonpage = $builder->run(
	array(
		'action'            => 'start',
		'map_pages'         => array( array( 'post_id' => 41, 'type' => 'about' ) ),
		'confirm_map'       => true,
		'confirm_map_types' => array( 'about' ),
	)
);
rms_id_assert( is_wp_error( $nonpage ) && 'rms_wizard_internal_map_invalid' === $nonpage->get_error_code(), 'non-page live object rejected' );
rms_id_assert( $before_state === ( new State_Manager() )->get_state(), 'non-page writes no state' );
rms_id_assert( $meta_before === $GLOBALS['_post_meta'], 'non-page writes no ACF or template meta' );
rms_id_assert( $options_before === $GLOBALS['_options'], 'non-page writes no options or canonical' );
rms_id_assert( 0 === $GLOBALS['_page_writes'], 'non-page writes no pages' );
rms_id_assert( 4 === (int) wp_count_posts( 'post' )->publish, 'non-page does not change post counts' );
rms_id_assert( array() === ( new Logger() )->all(), 'non-page writes no logs' );
$fence->clear_agent( (string) $owner );
$fence->release( (string) $owner );
echo "PASS mapping-non-page-object-rejected-zero-writes\n";
++$passed;

rms_ipa_reset();
$GLOBALS['_posts'][40] = new WP_Post( 40 );
$sm = new State_Manager();
$st = $sm->get_state();
$st['generated_pages'] = array( array( 'id' => 40, 'slug' => 'history', 'role' => '' ) );
$sm->save_state( $st );
$before_state = $sm->get_state();
$meta_before  = $GLOBALS['_post_meta'];
$options_before = $GLOBALS['_options'];
$GLOBALS['_posts_count'] = 4;
$builder = new Step_Internal_Page_Builder();
$fence   = new Wizard_Mutation_Fence();
$owner   = $fence->acquire();
$builder->accept_mutation_owner( (string) $owner );
$fence->authorize_agent( $builder, (string) $owner );
$subset = $builder->run(
	array(
		'action'            => 'start',
		'map_pages'         => array( array( 'post_id' => 40, 'type' => 'about' ) ),
		'confirm_map'       => true,
		'confirm_map_types' => array(),
	)
);
rms_id_assert( is_wp_error( $subset ) && 'rms_wizard_internal_map_confirmation_required' === $subset->get_error_code(), 'empty confirmation subset rejected' );
rms_id_assert( $before_state === ( new State_Manager() )->get_state(), 'subset confirmation writes no state' );
rms_id_assert( $meta_before === $GLOBALS['_post_meta'], 'subset confirmation writes no ACF or template meta' );
rms_id_assert( $options_before === $GLOBALS['_options'], 'subset confirmation writes no options or canonical' );
rms_id_assert( 0 === $GLOBALS['_page_writes'], 'subset confirmation writes no pages' );
rms_id_assert( 4 === (int) wp_count_posts( 'post' )->publish, 'subset confirmation does not change post counts' );
rms_id_assert( array() === ( new Logger() )->all(), 'subset confirmation writes no logs' );
$fence->clear_agent( (string) $owner );
$fence->release( (string) $owner );
echo "PASS mapping-subset-confirmation-rejected-zero-writes\n";
++$passed;

rms_ipa_reset();
$GLOBALS['_posts'][40] = new WP_Post( 40 );
$sm = new State_Manager();
$st = $sm->get_state();
$st['generated_pages'] = array( array( 'id' => 40, 'slug' => 'history', 'role' => '' ) );
$sm->save_state( $st );
$before_state = $sm->get_state();
$meta_before  = $GLOBALS['_post_meta'];
$options_before = $GLOBALS['_options'];
$GLOBALS['_posts_count'] = 4;
$builder = new Step_Internal_Page_Builder();
$fence   = new Wizard_Mutation_Fence();
$owner   = $fence->acquire();
$builder->accept_mutation_owner( (string) $owner );
$fence->authorize_agent( $builder, (string) $owner );
$superset = $builder->run(
	array(
		'action'            => 'start',
		'map_pages'         => array( array( 'post_id' => 40, 'type' => 'about' ) ),
		'confirm_map'       => true,
		'confirm_map_types' => array( 'about', 'services' ),
	)
);
rms_id_assert( is_wp_error( $superset ) && 'rms_wizard_internal_map_confirmation_required' === $superset->get_error_code(), 'superset confirmation rejected' );
rms_id_assert( $before_state === ( new State_Manager() )->get_state(), 'superset confirmation writes no state' );
rms_id_assert( $meta_before === $GLOBALS['_post_meta'], 'superset confirmation writes no ACF or template meta' );
rms_id_assert( $options_before === $GLOBALS['_options'], 'superset confirmation writes no options or canonical' );
rms_id_assert( 0 === $GLOBALS['_page_writes'], 'superset confirmation writes no pages' );
rms_id_assert( 4 === (int) wp_count_posts( 'post' )->publish, 'superset confirmation does not change post counts' );
rms_id_assert( array() === ( new Logger() )->all(), 'superset confirmation writes no logs' );
$fence->clear_agent( (string) $owner );
$fence->release( (string) $owner );
echo "PASS mapping-superset-confirmation-rejected-zero-writes\n";
++$passed;

rms_ipa_reset();
$GLOBALS['_posts'][40] = new WP_Post( 40 );
$GLOBALS['_posts'][40]->post_content = 'Original body';
$GLOBALS['_posts'][40]->post_name = 'history';
$GLOBALS['_post_meta'][40]['page_sections'] = array( array( 'acf_fc_layout' => 'about-us', 'about_headline' => 'Existing ACF' ) );
$GLOBALS['_post_meta'][40]['_wp_page_template'] = 'page.php';
$sm = new State_Manager();
$st = $sm->get_state();
$st['generated_pages'] = array( array( 'id' => 40, 'slug' => 'history', 'role' => '' ) );
$sm->save_state( $st );
$meta_before  = $GLOBALS['_post_meta'];
$options_before = $GLOBALS['_options'];
$GLOBALS['_posts_count'] = 4;
$builder = new Step_Internal_Page_Builder();
$fence   = new Wizard_Mutation_Fence();
$owner   = $fence->acquire();
$builder->accept_mutation_owner( (string) $owner );
$fence->authorize_agent( $builder, (string) $owner );
$ok = $builder->run(
	array(
		'action'            => 'start',
		'map_pages'         => array( array( 'post_id' => 40, 'type' => 'about' ) ),
		'confirm_map'       => true,
		'confirm_map_types' => array( 'about' ),
	)
);
rms_id_assert( ! is_wp_error( $ok ), 'exact-set confirmed mapping succeeds' );
rms_id_assert( 'about' === ( ( new State_Manager() )->get_state()['generated_pages'][0]['type'] ?? '' ), 'exact-set mapping persists only the type' );
rms_id_assert( 'Original body' === ( $GLOBALS['_posts'][40]->post_content ?? '' ), 'mapping leaves post content unchanged' );
rms_id_assert( $meta_before === $GLOBALS['_post_meta'], 'mapping leaves template and ACF rows unchanged' );
$canonical_before = $options_before[ \Inc\Wizard\Canonical_Section_Store::OPTION_KEY ] ?? null;
$canonical_after  = $GLOBALS['_options'][ \Inc\Wizard\Canonical_Section_Store::OPTION_KEY ] ?? null;
rms_id_assert( $canonical_before === $canonical_after, 'mapping leaves canonical store unchanged' );
rms_id_assert( ( $options_before[ \Inc\Wizard\Logger::OPTION_KEY ] ?? null ) === ( $GLOBALS['_options'][ \Inc\Wizard\Logger::OPTION_KEY ] ?? null ), 'mapping leaves log option unchanged' );
rms_id_assert( 0 === $GLOBALS['_page_writes'], 'mapping writes no pages' );
rms_id_assert( 4 === (int) wp_count_posts( 'post' )->publish, 'mapping does not change post counts' );
rms_id_assert( array() === ( new Logger() )->all(), 'mapping writes no logs' );
$fence->clear_agent( (string) $owner );
$fence->release( (string) $owner );
echo "PASS mapping-exact-set-success-no-content-writes\n";
++$passed;

rms_ipa_reset();
$GLOBALS['_posts'][12] = new WP_Post( 12 );
$GLOBALS['_posts'][12]->post_name = 'about';
$GLOBALS['_posts'][18] = new WP_Post( 18 );
$GLOBALS['_posts'][18]->post_name = 'blog';
$GLOBALS['_posts'][40] = new WP_Post( 40 );
$GLOBALS['_posts'][40]->post_name = 'history';
$sm = new State_Manager();
$st = $sm->get_state();
$st['generated_pages'] = array(
	array( 'id' => 12, 'slug' => 'about', 'type' => 'about', 'role' => '' ),
	array( 'id' => 18, 'slug' => 'blog', 'type' => 'blog', 'role' => 'blog' ),
	array( 'id' => 40, 'slug' => 'history', 'role' => '' ),
);
$st['internal_pages']['about'] = array_merge( State_Manager::INTERNAL_PAGE_ENTRY, array( 'post_id' => 12, 'status' => 'complete' ) );
$st['internal_pages']['blog']  = array_merge( State_Manager::INTERNAL_PAGE_ENTRY, array( 'post_id' => 18, 'status' => 'pending' ) );
$sm->save_state( $st );
$preview = Internal_Page_Identity::preview_plan( $sm->get_state() );
rms_id_assert( isset( $preview['plan']['about'] ) && isset( $preview['plan']['blog'] ), 'preview payload carries the persisted plan' );
$resolved_ids = array();
foreach ( $preview['types'] as $type => $entry ) {
	if ( ! empty( $entry['available'] ) ) {
		$resolved_ids[] = (int) $entry['post_id'];
	}
}
sort( $resolved_ids );
rms_id_assert( array( 12, 18 ) === $resolved_ids, 'resolved types are keyed by unique post ids' );
$unmapped_ids = array();
foreach ( $preview['unmapped'] as $row ) {
	$unmapped_ids[] = (int) $row['post_id'];
}
rms_id_assert( array( 40 ) === $unmapped_ids, 'unmapped lists only the custom slug shell' );
rms_id_assert( array() === array_intersect( $resolved_ids, $unmapped_ids ), 'resolved and unmapped post ids are disjoint' );
rms_id_assert( 2 === count( $resolved_ids ) && 1 === count( $unmapped_ids ), 'five shells total: two resolved plus one unmapped, no unavailable duplicates' );
echo "PASS preview-payload-post-id-keyed-disjoint\n";
++$passed;

echo 'Harness passed: ' . $passed . " scenarios.\n";
