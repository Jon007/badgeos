<?php

if ( ! defined( 'ABSPATH' ) )
	exit; // Exit if accessed directly

// Various utilities

class scbUtil {

	// Force script enqueue
	static function do_scripts( $handles ) {
		global $wp_scripts;

		if ( ! is_a( $wp_scripts, 'WP_Scripts' ) )
			$wp_scripts = new WP_Scripts();

		$wp_scripts->do_items( ( array ) $handles );
	}

	// Force style enqueue
	static function do_styles( $handles ) {
		self::do_scripts( 'jquery' );

		global $wp_styles;

		if ( ! is_a( $wp_styles, 'WP_Styles' ) )
			$wp_styles = new WP_Styles();

		ob_start();
		$wp_styles->do_items( ( array ) $handles );
		$content = str_replace( array( "'", "\n" ), array( '"', '' ), ob_get_clean() );

		echo "<script type='text/javascript'>\n";
		echo "jQuery(function ($) { $('head').prepend('$content'); });\n";
		echo "</script>";
	}

	// Enable delayed activation; to be used with scb_init()
	static function add_activation_hook( $plugin, $callback ) {
		if ( defined( 'SCB_LOAD_MU' ) )
			register_activation_hook( $plugin, $callback );
		else
			add_action( 'scb_activation_' . plugin_basename( $plugin ), $callback );
	}

	// For debugging
	static function do_activation( $plugin ) {
		do_action( 'scb_activation_' . plugin_basename( $plugin ) );
	}

	// Allows more than one uninstall hooks.
	// Also prevents an UPDATE query on each page load.
	static function add_uninstall_hook( $plugin, $callback ) {
		if ( !is_admin() )
			return;

		register_uninstall_hook( $plugin, '__return_false' );	// dummy

		add_action( 'uninstall_' . plugin_basename( $plugin ), $callback );
	}

	// For debugging
	static function do_uninstall( $plugin ) {
		do_action( 'uninstall_' . plugin_basename( $plugin ) );
	}

	// Get the current, full URL
	static function get_current_url() {
		return ( is_ssl() ? 'https://' : 'http://' ) . $_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI'];
	}

	// Apply a function to each element of a ( nested ) array recursively
	static function array_map_recursive( $callback, $array ) {
		array_walk_recursive( $array, array( __CLASS__, 'array_map_recursive_helper' ), $callback );

		return $array;
	}

	static function array_map_recursive_helper( &$val, $key, $callback ) {
		$val = call_user_func( $callback, $val );
	}

	// Extract certain $keys from $array
	static function array_extract( $array, $keys ) {
		_deprecated_function( __CLASS__ . '::' . __FUNCTION__, 'WP 3.1', 'wp_array_slice_assoc()' );
		return wp_array_slice_assoc( $array, $keys );
	}

	// Extract a certain value from a list of arrays
	static function array_pluck( $array, $key ) {
		_deprecated_function( __CLASS__ . '::' . __FUNCTION__, 'WP 3.1', 'wp_list_pluck()' );
		return wp_list_pluck( $array, $key );
	}

	// Transform a list of objects into an associative array
	static function objects_to_assoc( $objects, $key, $value ) {
		_deprecated_function( __CLASS__ . '::' . __FUNCTION__, 'r41', 'scb_list_fold()' );
		return scb_list_fold( $objects, $key, $value );
	}

	// Prepare an array for an IN statement
	static function array_to_sql( $values ) {
		foreach ( $values as &$val )
			$val = "'" . esc_sql( trim( $val ) ) . "'";

		return implode( ',', $values );
	}

	// Example: split_at( '</', '<a></a>' ) => array( '<a>', '</a>' )
	static function split_at( $delim, $str ) {
		$i = strpos( $str, $delim );

		if ( false === $i )
			return false;

		$start = substr( $str, 0, $i );
		$finish = substr( $str, $i );

		return array( $start, $finish );
	}
}

// Return a standard admin notice
function scb_admin_notice( $msg, $class = 'updated' ) {
	return html( "div class='$class fade'", html( "p", $msg ) );
}

// Transform a list of objects into an associative array
function scb_list_fold( $list, $key, $value ) {
	$r = array();

	if ( is_array( reset( $list ) ) ) {
		foreach ( $list as $item )
			$r[ $item[ $key ] ] = $item[ $value ];
	} else {
		foreach ( $list as $item )
			$r[ $item->$key ] = $item->$value;
	}

	return $r;
}

/**
 * Splits a list into sets, grouped by the result of running each value through $fn.
 *
 * @param array List of items to be partitioned
 * @param callback Function that takes an element and returns a string key
 */
function scb_list_group_by( $list, $fn ) {
	$groups = array();

	foreach ( $list as $item ) {
		$key = call_user_func( $fn, $item );

		if ( null === $key )
			continue;

		$groups[ $key ][] = $item;
	}

	return $groups;
}

//_____Database Table Utilities_____

/**
 * Register a table with $wpdb
 *
 * @param string $key The key to be used on the $wpdb object
 * @param string $name The actual name of the table, without $wpdb->prefix
 */
function scb_register_table( $key, $name = false ) {
	global $wpdb;

	if ( !$name )
		$name = $key;

	$wpdb->tables[] = $name;
	$wpdb->$key = $wpdb->prefix . $name;
}

/**
 * Runs the SQL query for installing/upgrading a table
 *
 * @param string $key The key used in scb_register_table()
 * @param string $columns The SQL columns for the CREATE TABLE statement
 * @param array $opts Various other options
 */
function scb_install_table( $key, $columns, $opts = array() ) {
	global $wpdb;

	$full_table_name = $wpdb->$key;

	if ( is_string( $opts ) ) {
		$opts = array( 'upgrade_method' => $opts );
	}

	$opts = wp_parse_args( $opts, array(
		'upgrade_method' => 'dbDelta',
		'table_options' => '',
	) );

	$charset_collate = '';
	if ( $wpdb->has_cap( 'collation' ) ) {
		if ( ! empty( $wpdb->charset ) )
			$charset_collate = "DEFAULT CHARACTER SET $wpdb->charset";
		if ( ! empty( $wpdb->collate ) )
			$charset_collate .= " COLLATE $wpdb->collate";
	}

	$table_options = $charset_collate . ' ' . $opts['table_options'];

	if ( 'dbDelta' == $opts['upgrade_method'] ) {
		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		dbDelta( "CREATE TABLE $full_table_name ( $columns ) $table_options" );
		return;
	}

	if ( 'delete_first' == $opts['upgrade_method'] )
		$wpdb->query( "DROP TABLE IF EXISTS $full_table_name;" );

	$wpdb->query( "CREATE TABLE IF NOT EXISTS $full_table_name ( $columns ) $table_options;" );
}

function scb_uninstall_table( $key ) {
	global $wpdb;

	$wpdb->query( "DROP TABLE IF EXISTS " . $wpdb->$key );
}

//_____Minimalist HTML framework_____

/**
 * Generate an HTML tag. Atributes are escaped. Content is NOT escaped.
 */
if ( ! function_exists( 'html' ) ):
function html( $tag ) {
	static $SELF_CLOSING_TAGS = array( 'area', 'base', 'basefont', 'br', 'hr', 'input', 'img', 'link', 'meta' );

	$args = func_get_args();

	$tag = array_shift( $args );

	if ( is_array( $args[0] ) ) {
		$closing = $tag;
		$attributes = array_shift( $args );
		foreach ( $attributes as $key => $value ) {
			if ( false === $value )
				continue;

			if ( true === $value )
				$value = $key;

			$tag .= ' ' . $key . '="' . esc_attr( $value ) . '"';
		}
	} else {
		list( $closing ) = explode( ' ', $tag, 2 );
	}

	if ( in_array( $closing, $SELF_CLOSING_TAGS ) ) {
		return "<{$tag} />";
	}

	$content = implode( '', $args );

	return "<{$tag}>{$content}</{$closing}>";
}
endif;

// Generate an <a> tag
if ( ! function_exists( 'html_link' ) ):
function html_link( $url, $title = '' ) {
	if ( empty( $title ) )
		$title = $url;

	return html( 'a', array( 'href' => $url ), $title );
}
endif;

function scb_get_query_flags( $wp_query = null ) {
	if ( !$wp_query )
		$wp_query = $GLOBALS['wp_query'];

	$flags = array();
	foreach ( get_object_vars( $wp_query ) as $key => $val ) {
		if ( 'is_' == substr( $key, 0, 3 ) && $val )
			$flags[] = substr( $key, 3 );
	}

	return $flags;
}

//_____Compatibility layer_____

// WP < ?
if ( ! function_exists( 'set_post_field' ) ) :
function set_post_field( $field, $value, $post_id ) {
	global $wpdb;

	$post_id = absint( $post_id );
	$value = sanitize_post_field( $field, $value, $post_id, 'db' );

	return $wpdb->update( $wpdb->posts, array( $field => $value ), array( 'ID' => $post_id ) );
}
endif;

