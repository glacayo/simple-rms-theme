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
	 * @return array<string,array<string,mixed>>
	 */
	public function install_missing(): array {
		$tgmpa  = $this->get_tgmpa();
		$result = [];

		if ( ! $tgmpa ) {
			return $result;
		}

		foreach ( $this->get_status() as $slug => $plugin_status ) {
			$result[ $slug ] = $plugin_status;

			if ( empty( $plugin_status['installed'] ) ) {
				$installed = $this->install_plugin( $slug );
				$result[ $slug ]['installed'] = $installed;
			}

			if ( ! empty( $result[ $slug ]['installed'] ) && empty( $plugin_status['active'] ) ) {
				$result[ $slug ]['active'] = $this->activate_plugin( $slug );
			}
		}

		$complete = $this->all_active( $result );
		$this->state_manager->set_step_status( self::STEP, $complete ? 'complete' : 'failed' );

		return $result;
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

		$plugin = $tgmpa->plugins[ $slug ];
		$source = $plugin['source'] ?? '';

		if ( '' === $source ) {
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
			return false;
		}

		require_once \ABSPATH . 'wp-admin/includes/plugin.php';

		$result  = \activate_plugin( $tgmpa->plugins[ $slug ]['file_path'] );
		$success = ! \is_wp_error( $result );

		$this->logger->log( $success ? 'info' : 'error', $success ? 'Dependency activated.' : 'Dependency activation failed.', [ 'slug' => $slug ] );

		return $success;
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
