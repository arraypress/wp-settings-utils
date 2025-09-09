<?php
/**
 * WordPress Settings Manager
 *
 * A reusable settings management system for WordPress plugins with
 * caching, dot notation support, and flexible storage options.
 *
 * @package ArrayPress\Settings
 * @since   1.0.0
 * @author  David Sherlock
 * @license GPL-2.0-or-later
 */

declare( strict_types=1 );

namespace ArrayPress\SettingsUtils;

/**
 * Settings Manager Class
 *
 * Operations for working with settings.
 */
class Manager {

	/**
	 * The option name in the database.
	 *
	 * @var string
	 */
	private string $option_name;

	/**
	 * Cached settings array.
	 *
	 * @var array|null
	 */
	private ?array $cache = null;

	/**
	 * Default values for settings.
	 *
	 * @var array
	 */
	private array $defaults = [];

	/**
	 * Plugin prefix for filters.
	 *
	 * @var string
	 */
	private string $prefix;

	/**
	 * Constructor.
	 *
	 * @param string $option_name The option name in the database.
	 * @param array  $defaults    Default values for settings.
	 * @param string $prefix      Plugin prefix for filters (optional).
	 */
	public function __construct( string $option_name, array $defaults = [], string $prefix = '' ) {
		$this->option_name = $option_name;
		$this->defaults    = $defaults;
		$this->prefix      = $prefix ?: str_replace( '-', '_', $option_name );
	}

	/**
	 * Get a setting value.
	 *
	 * @param string     $key     Setting key (supports dot notation).
	 * @param mixed|null $default Default value if setting doesn't exist.
	 *
	 * @return mixed
	 */
	public function get( string $key, mixed $default = null ): mixed {
		$this->load_cache();

		// Support dot notation for nested arrays
		if ( str_contains( $key, '.' ) ) {
			$value = $this->get_nested( $key );
		} else {
			$value = $this->cache[ $key ] ?? null;
		}

		// Use provided default or fall back to configured defaults
		if ( $value === null ) {
			$value = $default ?? $this->defaults[ $key ] ?? null;
		}

		// Handle JSON strings
		if ( is_string( $value ) ) {
			if ( str_starts_with( $value, '{' ) || str_starts_with( $value, '[' ) ) {
				$decoded = json_decode( $value, true );
				if ( json_last_error() === JSON_ERROR_NONE ) {
					$value = $decoded;
				}
			}
		}

		// If it's a value/label pair, extract just the value
		if ( is_array( $value ) && isset( $value['value'] ) && count( $value ) === 2 ) {
			$value = $value['value'];
		}

		return apply_filters( "{$this->prefix}_get_setting", $value, $key, $default );
	}

	/**
	 * Update a setting value.
	 *
	 * @param string $key   Setting key (supports dot notation).
	 * @param mixed  $value Setting value.
	 *
	 * @return bool
	 */
	public function update( string $key, mixed $value ): bool {
		if ( empty( $key ) ) {
			return false;
		}

		$this->load_cache();

		$value = apply_filters( "{$this->prefix}_pre_update_setting", $value, $key );

		// If empty non-numeric value, delete the setting
		if ( ! is_numeric( $value ) && ! is_bool( $value ) && empty( $value ) ) {
			return $this->delete( $key );
		}

		// Support dot notation for nested arrays
		if ( str_contains( $key, '.' ) ) {
			$this->set_nested( $key, $value );
		} else {
			$this->cache[ $key ] = $value;
		}

		return $this->save();
	}

	/**
	 * Delete a setting.
	 *
	 * @param string $key Setting key (supports dot notation).
	 *
	 * @return bool
	 */
	public function delete( string $key ): bool {
		if ( empty( $key ) ) {
			return false;
		}

		$this->load_cache();

		if ( str_contains( $key, '.' ) ) {
			$this->delete_nested( $key );
		} else {
			unset( $this->cache[ $key ] );
		}

		return $this->save();
	}

	/**
	 * Get all settings.
	 *
	 * @return array
	 */
	public function all(): array {
		$this->load_cache();

		return apply_filters( "{$this->prefix}_get_all_settings", $this->cache );
	}

	/**
	 * Check if a setting exists.
	 *
	 * @param string $key Setting key (supports dot notation).
	 *
	 * @return bool
	 */
	public function has( string $key ): bool {
		$this->load_cache();

		if ( str_contains( $key, '.' ) ) {
			return $this->get_nested( $key ) !== null;
		}

		return isset( $this->cache[ $key ] );
	}

	/**
	 * Reset all settings to defaults.
	 *
	 * @return bool
	 */
	public function reset(): bool {
		$this->cache = $this->defaults;

		return $this->save();
	}

	/**
	 * Clear the settings cache.
	 *
	 * Forces reload from database on next access.
	 *
	 * @return void
	 */
	public function clear_cache(): void {
		$this->cache = null;
	}

	/**
	 * Merge new defaults with existing.
	 *
	 * Useful for registering settings from multiple components.
	 *
	 * @param array $defaults New default values to merge.
	 *
	 * @return void
	 */
	public function register_defaults( array $defaults ): void {
		$this->defaults = array_merge( $this->defaults, $defaults );

		// If cache is loaded and new defaults aren't in cache, add them
		if ( $this->cache !== null ) {
			foreach ( $defaults as $key => $value ) {
				if ( ! isset( $this->cache[ $key ] ) ) {
					$this->cache[ $key ] = $value;
				}
			}
		}
	}

	/**
	 * Get the option name.
	 *
	 * @return string
	 */
	public function get_option_name(): string {
		return $this->option_name;
	}

	/**
	 * Load settings from database into cache.
	 *
	 * @return void
	 */
	private function load_cache(): void {
		if ( $this->cache === null ) {
			$stored = get_option( $this->option_name, [] );

			if ( ! is_array( $stored ) ) {
				$stored = [];
			}

			// Merge with defaults
			$this->cache = array_merge( $this->defaults, $stored );
		}
	}

	/**
	 * Save cached settings to database.
	 *
	 * @return bool
	 */
	private function save(): bool {
		// Only save non-default values to keep database clean
		$to_save = [];
		foreach ( $this->cache as $key => $value ) {
			if ( ! isset( $this->defaults[ $key ] ) || $value !== $this->defaults[ $key ] ) {
				$to_save[ $key ] = $value;
			}
		}

		return update_option( $this->option_name, $to_save );
	}

	/**
	 * Get nested value using dot notation.
	 *
	 * @param string $key Dot-notated key.
	 *
	 * @return mixed|null
	 */
	private function get_nested( string $key ): mixed {
		$keys  = explode( '.', $key );
		$value = $this->cache;

		foreach ( $keys as $nested_key ) {
			if ( ! is_array( $value ) || ! isset( $value[ $nested_key ] ) ) {
				return null;
			}
			$value = $value[ $nested_key ];
		}

		return $value;
	}

	/**
	 * Set nested value using dot notation.
	 *
	 * @param string $key   Dot-notated key.
	 * @param mixed  $value Value to set.
	 *
	 * @return void
	 */
	private function set_nested( string $key, mixed $value ): void {
		$keys     = explode( '.', $key );
		$last_key = array_pop( $keys );
		$nested   = &$this->cache;

		foreach ( $keys as $nested_key ) {
			if ( ! isset( $nested[ $nested_key ] ) || ! is_array( $nested[ $nested_key ] ) ) {
				$nested[ $nested_key ] = [];
			}
			$nested = &$nested[ $nested_key ];
		}

		$nested[ $last_key ] = $value;
	}

	/**
	 * Delete nested value using dot notation.
	 *
	 * @param string $key Dot-notated key.
	 *
	 * @return void
	 */
	private function delete_nested( string $key ): void {
		$keys     = explode( '.', $key );
		$last_key = array_pop( $keys );
		$nested   = &$this->cache;

		foreach ( $keys as $nested_key ) {
			if ( ! isset( $nested[ $nested_key ] ) || ! is_array( $nested[ $nested_key ] ) ) {
				return;
			}
			$nested = &$nested[ $nested_key ];
		}

		unset( $nested[ $last_key ] );
	}

}