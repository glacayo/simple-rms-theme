<?php
/**
 * Wizard state manager.
 *
 * @package Simple_RMS_Theme
 */

namespace Inc\Wizard;

defined( 'ABSPATH' ) || exit;

/**
 * Stores wizard progress, generated data references, and locks in wp_options.
 */
class State_Manager {
    public const STATE_OPTION     = 'rms_wizard_state';
    public const LOG_OPTION       = 'rms_wizard_log';
    public const COMPLETED_OPTION = 'rms_wizard_completed';

    /**
     * Return the default state shape.
     *
     * @return array<string,mixed>
     */
    public function defaults(): array {
        return [
            'current_step'           => 'dependencies',
            'step_status'            => [],
            'client_data'            => [],
            'generated'              => [],
            'created_posts'          => [],
            'generated_pages'        => [],
            'home_page_slug'         => '',
            'blog_page_slug'         => '',
            'ai_config'              => [],
            'menu_config'            => [],
            'selected_home_sections' => [],
            'home_sections'          => [],
            'logs'                   => self::LOG_OPTION,
            'locks'                  => [],
            'updated_at'             => '',
        ];
    }

    /**
     * Load the complete wizard state.
     *
     * @return array<string,mixed>
     */
    public function get_state(): array {
        $state = \get_option( self::STATE_OPTION, [] );

        if ( ! is_array( $state ) ) {
            $state = [];
        }

        return array_replace_recursive( $this->defaults(), $state );
    }

    /**
     * Persist the complete wizard state.
     *
     * @param array $state State values to merge over defaults.
     *
     * @return bool
     */
    public function save_state( array $state ): bool {
        $state               = array_replace_recursive( $this->defaults(), $state );
        $state['updated_at'] = \current_time( 'mysql', true );

        return \update_option( self::STATE_OPTION, $state, false );
    }

    /**
     * Merge partial state into the stored state.
     *
     * @param array $partial_state Partial state values.
     *
     * @return bool
     */
    public function merge_state( array $partial_state ): bool {
        return $this->save_state( array_replace_recursive( $this->get_state(), $partial_state ) );
    }

    /**
     * Set the active wizard step.
     *
     * @param string $step Step slug.
     *
     * @return bool
     */
    public function set_current_step( string $step ): bool {
        return $this->merge_state( [ 'current_step' => \sanitize_key( $step ) ] );
    }

    /**
     * Store a step status value.
     *
     * @param string $step   Step slug.
     * @param string $status pending, running, complete, or failed.
     *
     * @return bool
     */
    public function set_step_status( string $step, string $status ): bool {
        $allowed_statuses = [ 'pending', 'running', 'complete', 'failed' ];
        $status           = in_array( $status, $allowed_statuses, true ) ? $status : 'pending';

        $state = $this->get_state();
        $state['step_status'][ \sanitize_key( $step ) ] = $status;

        return $this->save_state( $state );
    }

    /**
     * Determine whether the wizard is complete and locked.
     *
     * @return bool
     */
    public function is_completed(): bool {
        if ( \defined( 'RMS_WIZARD_FORCE' ) && true === \RMS_WIZARD_FORCE ) {
            return false;
        }

        return (bool) \get_option( self::COMPLETED_OPTION, false );
    }

    /**
     * Persist the completion lock.
     *
     * @return bool
     */
    public function mark_completed(): bool {
        return \update_option( self::COMPLETED_OPTION, true, false );
    }

    /**
     * Remove the completion lock.
     *
     * @return bool
     */
    public function clear_completed(): bool {
        return \delete_option( self::COMPLETED_OPTION );
    }

    /**
     * Acquire a named lock stored inside the state option.
     *
     * @param string $lock_name Lock name.
     * @param int    $ttl       Lock time to live in seconds.
     *
     * @return bool True when the lock was acquired.
     */
    public function acquire_lock( string $lock_name, int $ttl = 300 ): bool {
        $state     = $this->get_state();
        $lock_name = \sanitize_key( $lock_name );
        $locks     = is_array( $state['locks'] ) ? $state['locks'] : [];
        $now       = time();

        if ( isset( $locks[ $lock_name ]['expires_at'] ) && (int) $locks[ $lock_name ]['expires_at'] > $now ) {
            return false;
        }

        $locks[ $lock_name ] = [
            'acquired_at' => $now,
            'expires_at'  => $now + max( 1, $ttl ),
        ];

        $state['locks'] = $locks;

        return $this->save_state( $state );
    }

    /**
     * Release a named lock.
     *
     * @param string $lock_name Lock name.
     *
     * @return bool
     */
    public function release_lock( string $lock_name ): bool {
        $state     = $this->get_state();
        $lock_name = \sanitize_key( $lock_name );

        if ( isset( $state['locks'][ $lock_name ] ) ) {
            unset( $state['locks'][ $lock_name ] );
        }

        return $this->save_state( $state );
    }
}
