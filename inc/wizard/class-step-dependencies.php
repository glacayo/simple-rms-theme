<?php
/**
 * Wizard dependency step service.
 *
 * @package Simple_RMS_Theme
 */

namespace Inc\Wizard;

defined( 'ABSPATH' ) || exit;

/**
 * Checks, installs, and activates required TGMPA plugins for the wizard.
 */
class Step_Dependencies {
	private const STEP = 'dependencies';

	/**
	 * @var Logger
	 */
	private $logger;

	/**
	 * @var State_Manager
	 */
	private $state_manager;

	public function __construct( ?Logger $logger = null, ?State_Manager $state_manager = null ) {
		$this->logger        = $logger ?? new Logger();
		$this->state_manager = $state_manager ?? new State_Manager();
	}

	/**
	 * Return per-plugin dependency status from TGMPA.
	 *
	 * @return array<string,array<string,mixed>>
	 */
	public function get_status(): array {
		$tgmpa = $this->get_tgmpa();

		if ( ! $tgmpa ) {
			$this->logger->log( 'error', 'TGMPA is not available for dependency checks.' );

			return [];
		}

		$tgmpa->populate_file_path();
		$status = [];

		foreach ( $tgmpa->plugins as $slug => $plugin ) {
			if ( empty( $plugin['required'] ) ) {
				continue;
			}

			$status[ $slug ] = [
				'name'      => $plugin['name'] ?? $slug,
				'slug'      => $slug,
				'required'  => true,
				'installed' => $tgmpa->is_plugin_installed( $slug ),
				'active'    => $tgmpa->is_plugin_active( $slug ),
			];
		}

		return $status;
	}

	/**
	 * Install and activate missing required plugins.
	 *
	 * Per-plugin installed/active flags always reflect an authoritative
	 * post-action re-check via TGMPA — never optimistic booleans. The `action`
	 * diagnostic string distinguishes install/activation outcomes for the UI
	 * and log without introducing a parallel lifecycle state.
	 *
	 * @return array<string,array<string,mixed>>
	 */
	public function install_missing(): array {
		$tgmpa = $this->get_tgmpa();

		if ( ! $tgmpa ) {
			/*
			 * TGMPA is unavailable — the Dependencies step cannot proceed.
			 * get_tgmpa() does not log here, so log explicitly and persist the
			 * step as failed so the wizard stays in a retryable state instead
			 * of lingering as running. The controller derives top-level
			 * success:false from this final status.
			 */
			$this->logger->log( 'error', 'Dependencies step failed: TGMPA is not available.', [ 'step' => self::STEP ] );
			$this->state_manager->set_step_status( self::STEP, 'failed' );

			return [];
		}

		// Snapshot the pre-action status once so we can decide which action to take.
		$pre_status = $this->get_status();

		foreach ( $pre_status as $slug => $plugin_status ) {
			if ( empty( $plugin_status['installed'] ) ) {
				$this->install_plugin( $slug );
			}
		}

		/*
		 * After every install attempt, refresh TGMPA file paths and clear the
		 * WordPress plugin cache so subsequent is_plugin_installed/active checks
		 * reflect the real filesystem state instead of a stale snapshot.
		 */
		$tgmpa->populate_file_path();

		if ( function_exists( 'wp_clean_plugins_cache' ) ) {
			\wp_clean_plugins_cache();
		}

		foreach ( $pre_status as $slug => $plugin_status ) {
			if ( empty( $plugin_status['active'] ) && $tgmpa->is_plugin_installed( $slug ) ) {
				$this->activate_plugin( $slug );
			}
		}

		/*
		 * Final per-plugin status is an authoritative re-read from TGMPA after
		 * every install/activate action has completed. This prevents optimistic
		 * booleans from masking a failed install or activation.
		 */
		return $this->get_status_with_diagnostics( $pre_status );
	}

	private function get_tgmpa(): ?\TGM_Plugin_Activation {
		if ( ! class_exists( '\TGM_Plugin_Activation' ) ) {
			return null;
		}

		\do_action( 'tgmpa_register' );

		return \TGM_Plugin_Activation::get_instance();
	}

	private function install_plugin( string $slug ): bool {
		$tgmpa = $this->get_tgmpa();

		if ( ! $tgmpa || empty( $tgmpa->plugins[ $slug ] ) ) {
			return false;
		}

		require_once \ABSPATH . 'wp-admin/includes/plugin-install.php';
		require_once \ABSPATH . 'wp-admin/includes/class-wp-upgrader.php';
		require_once \ABSPATH . 'wp-admin/includes/file.php';

		$plugin      = $tgmpa->plugins[ $slug ];
		$source      = (string) ( $plugin['source'] ?? '' );
		$source_type = (string) ( $plugin['source_type'] ?? '' );

		/*
		 * Repository plugins use TGMPA's canonical `source: repo` sentinel.
		 * Resolve the actual download link through the WordPress.org API so
		 * Plugin_Upgrader receives a real package URL, not the literal "repo".
		 * Bundled plugins (local ZIP path) and external URLs keep their source.
		 */
		if ( self::is_repo_source( $source, $source_type ) ) {
			$api = \plugins_api( 'plugin_information', [ 'slug' => $slug, 'fields' => [ 'sections' => false ] ] );

			if ( \is_wp_error( $api ) || empty( $api->download_link ) ) {
				$this->logger->log( 'error', 'Dependency install source could not be resolved.', [ 'slug' => $slug ] );

				return false;
			}

			$source = $api->download_link;
		}

		$upgrader = new \Plugin_Upgrader( new \Automatic_Upgrader_Skin() );
		$result   = $upgrader->install( $source );
		$success  = ! \is_wp_error( $result ) && false !== $result;

		$this->logger->log( $success ? 'info' : 'error', $success ? 'Dependency installed.' : 'Dependency install failed.', [ 'slug' => $slug ] );

		return $success;
	}

	private function activate_plugin( string $slug ): bool {
		$tgmpa = $this->get_tgmpa();

		if ( ! $tgmpa || empty( $tgmpa->plugins[ $slug ]['file_path'] ) ) {
			$this->logger->log(
				'error',
				'Dependency activation failed.',
				[
					'slug'   => $slug,
					'reason' => 'missing_file_path',
				]
			);

			return false;
		}

		require_once \ABSPATH . 'wp-admin/includes/plugin.php';

		$result  = \activate_plugin( $tgmpa->plugins[ $slug ]['file_path'] );
		$success = ! \is_wp_error( $result );

		$this->logger->log( $success ? 'info' : 'error', $success ? 'Dependency activated.' : 'Dependency activation failed.', [ 'slug' => $slug ] );

		return $success;
	}

	/**
	 * Whether TGMPA identified this plugin as a WordPress.org repo package.
	 *
	 * Empty source is treated as repo because that is TGMPA's default. The
	 * literal `repo` sentinel must never be handed to Plugin_Upgrader.
	 */
	public static function is_repo_source( string $source, string $source_type = '' ): bool {
		return 'repo' === $source_type || 'repo' === $source || '' === $source;
	}

	/**
	 * Map pre/post plugin state to a diagnostic action label.
	 *
	 * Installed/active flags stay authoritative. This label only explains
	 * what happened: already_active, installed, activated, install_failed,
	 * activation_failed, or not_installed.
	 */
	public static function diagnose_plugin_action( bool $was_installed, bool $was_active, bool $now_installed, bool $now_active ): string {
		if ( $now_active ) {
			if ( $was_active ) {
				return 'already_active';
			}

			return $was_installed ? 'activated' : 'installed';
		}

		if ( $now_installed ) {
			// On disk but inactive: install landed; activation did not.
			return 'activation_failed';
		}

		return $was_installed ? 'not_installed' : 'install_failed';
	}

	/**
	 * Build a truthful per-plugin status map with diagnostic action labels.
	 *
	 * The `action` field distinguishes install/activation outcomes for the UI
	 * and log without introducing a parallel lifecycle state. Installed/active
	 * flags come from an authoritative TGMPA re-check after every action.
	 *
	 * @param array<string,array<string,mixed>> $pre_status Snapshot before actions.
	 *
	 * @return array<string,array<string,mixed>>
	 */
	private function get_status_with_diagnostics( array $pre_status ): array {
		$final    = $this->get_status();
		$complete = $this->all_active( $final );

		$this->state_manager->set_step_status( self::STEP, $complete ? 'complete' : 'failed' );

		foreach ( $final as $slug => $plugin_status ) {
			$was_installed = ! empty( $pre_status[ $slug ]['installed'] );
			$was_active    = ! empty( $pre_status[ $slug ]['active'] );
			$now_installed = ! empty( $plugin_status['installed'] );
			$now_active    = ! empty( $plugin_status['active'] );
			$action        = self::diagnose_plugin_action( $was_installed, $was_active, $now_installed, $now_active );

			$final[ $slug ]['action'] = $action;

			$this->logger->log(
				$now_active ? 'info' : 'error',
				self::action_log_message( $action ),
				[
					'slug'      => $slug,
					'action'    => $action,
					'installed' => $now_installed,
					'active'    => $now_active,
				]
			);
		}

		return $final;
	}

	private static function action_log_message( string $action ): string {
		switch ( $action ) {
			case 'already_active':
				return 'Dependency already active.';
			case 'installed':
				return 'Dependency installed and activated.';
			case 'activated':
				return 'Dependency activated.';
			case 'activation_failed':
				return 'Dependency activation failed.';
			case 'not_installed':
				return 'Dependency is not installed.';
			case 'install_failed':
			default:
				return 'Dependency install failed.';
		}
	}

	private function all_active( array $status ): bool {
		foreach ( $status as $plugin_status ) {
			if ( empty( $plugin_status['active'] ) ) {
				return false;
			}
		}

		return [] !== $status;
	}
}
