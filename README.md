# WordPress Settings Manager

A flexible WordPress settings management library with intelligent caching, dot notation support, and type-safe operations for modern plugin development.

## Features

* 🚀 **Smart Caching**: Single database query per request with automatic cache management
* 📝 **Dot Notation**: Access nested settings with simple syntax (`api.keys.stripe`)
* 🎯 **Type Safety**: Automatic JSON decoding and value/label pair handling
* 🔧 **Defaults Management**: Register and merge defaults from multiple components
* 🪝 **Extensible**: WordPress filter hooks for all operations
* 💾 **Efficient Storage**: Only saves non-default values to database

## Requirements

* PHP 7.4 or later
* WordPress 5.0 or later

## Installation

```bash
composer require arraypress/wp-settings-utils
```

## Usage

### Basic Setup

```php
use ArrayPress\SettingsUtils\Manager;

// Initialize in your plugin
class MyPlugin {
	public Manager $settings;

	public function __construct() {
		$this->settings = new Manager(
			'myplugin_settings',
			[
				'api_key'        => '',
				'enable_feature' => true,
				'max_items'      => 10
			]
		);
	}
}

// Access settings
$plugin  = MyPlugin();
$api_key = $plugin->settings->get( 'api_key' );
```

### Getting & Setting Values

```php
// Get a setting with fallback
$value = $settings->get( 'api_key', 'default_value' );

// Update a setting
$settings->update( 'api_key', 'new_key_value' );

// Delete a setting
$settings->delete( 'api_key' );

// Check if setting exists
if ( $settings->has( 'api_key' ) ) {
	// Setting exists
}

// Get all settings
$all = $settings->all();

// Reset to defaults
$settings->reset();
```

### Dot Notation for Nested Settings

```php
// Set nested values
$settings->update( 'api.stripe.public_key', 'pk_live_...' );
$settings->update( 'api.stripe.secret_key', 'sk_live_...' );

// Get nested values
$public_key = $settings->get( 'api.stripe.public_key' );

// Check nested values
if ( $settings->has( 'api.stripe.secret_key' ) ) {
	// Secret key is set
}

// Delete nested values
$settings->delete( 'api.stripe.public_key' );
```

### Working with Defaults

```php
// Register additional defaults from components
$settings->register_defaults( [
	'module_enabled' => false,
	'module_config'  => [
		'option1' => 'value1',
		'option2' => 'value2'
	]
] );

// Defaults are automatically merged and available
$option1 = $settings->get( 'module_config.option1' ); // 'value1'
```

### JSON & Special Value Handling

```php
// Automatically decodes JSON strings
$settings->update( 'config', '{"key":"value"}' );
$config = $settings->get( 'config' ); // Returns array

// Handles value/label pairs (from select fields)
$settings->update( 'country', [ 'value' => 'US', 'label' => 'United States' ] );
$country = $settings->get( 'country' ); // Returns 'US'
```

### Cache Management

```php
// Force reload from database
$settings->clear_cache();

// Settings are automatically cached after first load
$value1 = $settings->get( 'key1' ); // Loads from database
$value2 = $settings->get( 'key2' ); // Uses cache
```

## Filter Hooks

The library provides filters for extending functionality:

```php
// Modify retrieved setting value
add_filter( 'myplugin_get_setting', function ( $value, $key, $default ) {
	// Modify value
	return $value;
}, 10, 3 );

// Modify value before saving
add_filter( 'myplugin_pre_update_setting', function ( $value, $key ) {
	// Validate or modify
	return $value;
}, 10, 2 );

// Modify all settings array
add_filter( 'myplugin_get_all_settings', function ( $settings ) {
	// Modify settings array
	return $settings;
} );
```

## Integration Patterns

### Static Accessor Pattern

```php
class MyPlugin {
	private static ?Manager $settings = null;

	public static function settings(): Manager {
		if ( self::$settings === null ) {
			self::$settings = new Manager( 'myplugin_settings' );
		}

		return self::$settings;
	}

	public static function get_setting( string $key, $default = null ) {
		return self::settings()->get( $key, $default );
	}
}

// Usage anywhere
$value = MyPlugin::get_setting( 'api_key' );
```

### Backward Compatibility

```php
// Wrapper functions for existing code
function myplugin_get_setting( string $key, $default = null ) {
	return MyPlugin::instance()->settings->get( $key, $default );
}

function myplugin_update_setting( string $key, $value ): bool {
	return MyPlugin::instance()->settings->update( $key, $value );
}
```

## Key Features

- **Efficient Database Usage**: Single query per request with intelligent caching
- **Flexible Storage**: Handles JSON, arrays, and value/label pairs automatically
- **Clean Database**: Only stores values that differ from defaults
- **Component-Friendly**: Register defaults from multiple sources
- **Developer-Friendly**: Intuitive API with dot notation support
- **WordPress Integration**: Proper use of options API and filter hooks

## Requirements

- PHP 7.4+
- WordPress 5.0+

## Contributing

Contributions are welcome! Please feel free to submit a Pull Request.

## License

This project is licensed under the GPL-2.0-or-later License.

## Support

- [Documentation](https://github.com/arraypress/wp-settings-utils)
- [Issue Tracker](https://github.com/arraypress/wp-settings-utils/issues)