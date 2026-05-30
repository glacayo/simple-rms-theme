<?php
/**
 * Persistent wizard logger.
 *
 * @package Simple_RMS_Theme
 */

namespace Inc\Wizard;

defined( 'ABSPATH' ) || exit;

/**
 * Writes structured wizard log entries to wp_options.
 */
class Logger {
    public const OPTION_KEY = 'rms_wizard_log';

    private const MAX_ENTRIES = 500;

    /**
     * Append a structured log entry.
     *
     * @param string $level   Log level.
     * @param string $message Human-readable message.
     * @param array  $context Optional context values.
     *
     * @return array The stored log entry.
     */
    public function log( string $level, string $message, array $context = [] ): array {
        $entry = [
            'timestamp' => \current_time( 'mysql', true ),
            'level'     => \sanitize_key( $level ),
            'message'   => \sanitize_text_field( $message ),
            'context'   => $this->sanitize_context( $context ),
        ];

        $entries   = $this->all();
        $entries[] = $entry;

        if ( count( $entries ) > self::MAX_ENTRIES ) {
            $entries = array_slice( $entries, - self::MAX_ENTRIES );
        }

        \update_option( self::OPTION_KEY, $entries, false );

        return $entry;
    }

    /**
     * Return all stored entries.
     *
     * @return array<int,array<string,mixed>>
     */
    public function all(): array {
        $entries = \get_option( self::OPTION_KEY, [] );

        return is_array( $entries ) ? $entries : [];
    }

    /**
     * Remove all stored log entries.
     *
     * @return void
     */
    public function clear(): void {
        \update_option( self::OPTION_KEY, [], false );
    }

    /**
     * Sanitize nested log context without losing scalar structure.
     *
     * @param array $context Raw context.
     *
     * @return array
     */
    private function sanitize_context( array $context ): array {
        $sanitized = [];

        foreach ( $context as $key => $value ) {
            $safe_key = \sanitize_key( (string) $key );

            if ( is_array( $value ) ) {
                $sanitized[ $safe_key ] = $this->sanitize_context( $value );
                continue;
            }

            if ( is_bool( $value ) || is_int( $value ) || is_float( $value ) || null === $value ) {
                $sanitized[ $safe_key ] = $value;
                continue;
            }

            $sanitized[ $safe_key ] = \sanitize_text_field( (string) $value );
        }

        return $sanitized;
    }
}
