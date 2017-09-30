<?php
/*
Plugin Name: Toolbar Cached
Description: Caches the Toolbar to speedup your site
Version: 0.1.0
Author: Fabian Schlieper
Author URI: https://fabi.me/
License:      GPL2
License URI:  https://www.gnu.org/licenses/gpl-2.0.html
GitHub Plugin URI: f4bsch/admin-bar-cached
*/

defined( 'ABSPATH' ) or exit;

class AdminBarCachePlugin {
	/**
	 * With trailing slash!
	 * @var string
	 */
	static $path;

	static function main() {
		self::$path = dirname( __FILE__ ) . '/';
		// right before WP_Scripts::init()
		add_action( 'init', array( __CLASS__, 'init' ), - 1 );
	}

	static function init() {
		// action=do-translation-upgrade
		if ( ! empty( $_GET['action'] ) || ! empty( $_GET['force-check'] ) ) {
			self::flush();
		}


		// replace original hook
		remove_action( 'wp_footer', 'wp_admin_bar_render', 1000 );
		remove_action( 'in_admin_header', 'wp_admin_bar_render', 0 );
		add_action( 'wp_footer', array( __CLASS__, 'wp_admin_bar_render' ), 1000 );
		add_action( 'in_admin_header', array( __CLASS__, 'wp_admin_bar_render' ), 0 );


		// flush on ...
		add_action( 'save_post', array( __CLASS__, 'flushAfterSavePost' ), 10, 3 );
		add_action( 'pre_comment_on_post', array( __CLASS__, 'flush' ) );
		add_filter( 'pre_update_option_active_sitewide_plugins', array( __CLASS__, 'flush' ), 10, 3 );
		add_filter( 'pre_update_option_active_plugins', array( __CLASS__, 'flush' ), 10, 3 );


		// caching the admin menu is experimental!
		if ( defined('ADMIN_BAR_CACHED_EXPERIMENTAL_MENU') ) {
			require_once self::$path . 'admin-menu.php';
			add_action( '_admin_menu', array( 'AdminMenuCache', 'adminMenuPre' ), 1e9 );
		}

		// caching scripts is experimental!
		if ( defined('ADMIN_BAR_CACHED_EXPERIMENTAL_SCRIPTS') ) {

			remove_action( 'wp_default_scripts', 'wp_default_scripts' );
			add_action( 'wp_default_scripts', array( __CLASS__, 'wpDefaultScripts' ) );


			remove_action( 'admin_print_scripts', 'print_head_scripts', 20 );
			//add_action( 'admin_print_scripts', array( __CLASS__, 'adminPrintScripts' ), 20 );
			add_action( 'admin_print_scripts', function () {
				return self::printCached( 'print_head_scripts' );
			}, 20 );


			remove_action( 'admin_print_footer_scripts', '_wp_footer_scripts' );
			add_action( 'admin_print_footer_scripts', function () {
				return self::printCached( '_wp_footer_scripts' );
			} );

			remove_action( 'admin_print_styles', 'print_admin_styles', 20 );
			//add_action( 'admin_print_styles', array( __CLASS__, 'adminPrintStyles' ), 20 );
			add_action( 'admin_print_styles', function () {
				return self::printCached( 'print_admin_styles' );
			}, 20 );
		}
	}

	static function wp_admin_bar_render() {
		require_once self::$path . 'admin-bar.php';
		AdminBarCached::render();
	}


	static function getCacheKey( $for = 'bar', $appendRequestUrl = false ) {
		$userId = wp_get_current_user()->ID;

		return "admin_bar_cached_{$for}_u{$userId}_a" . is_admin() . "_na" . is_network_admin() . ( $appendRequestUrl ? $_SERVER['PHP_SELF'] : '' );
	}

	static function flush( $valueFromFilter = null ) {
		// we use arbitrary keys
		global $wpdb;
		$tns = $wpdb->get_col( "SELECT option_name FROM {$wpdb->options} WHERE option_name LIKE '_transient_admin_bar_cached_%'" );
		$p   = strlen( '_transient_' );
		foreach ( $tns as $tn ) {
			delete_transient( substr( $tn, $p ) );
		}

		return $valueFromFilter;
	}

	static function flushAfterSavePost( $post_ID, $post, $update ) {
		if ( $post->post_status !== 'auto-draft' ) {
			self::flush();
		}
	}


	static function url( $sub ) {
		return plugin_dir_url( __FILE__ ) . '/' . $sub;
	}


	private static $cachedScriptsAndStyles = array();
	private static $cachedScriptsAndStylesSetCache = false;

	static function wpDefaultScripts( &$scripts ) {
		self::$cachedScriptsAndStyles = get_transient( self::getCacheKey( 'scripts_styles', true ) );
		if ( ! is_array( self::$cachedScriptsAndStyles ) ) {
			self::$cachedScriptsAndStyles = array();
		}

		if ( empty( self::$cachedScriptsAndStyles ) ) {
			wp_default_scripts( $scripts );
		}
	}

	static function printCached( $tag ) {
		if ( empty( self::$cachedScriptsAndStyles["{$tag}_out"] ) ) {
			ob_start();
			self::$cachedScriptsAndStyles["{$tag}_ret"] = call_user_func( $tag );
			self::$cachedScriptsAndStyles["{$tag}_out"] = ob_get_clean();
			if ( ! self::$cachedScriptsAndStylesSetCache ) {
				self::$cachedScriptsAndStylesSetCache = true;
				add_action( 'shutdown', array( __CLASS__, 'shutdown' ) );
			}
			echo "<!--gen:$tag -->";
		}

		echo self::$cachedScriptsAndStyles["{$tag}_out"];

		return self::$cachedScriptsAndStyles["{$tag}_ret"];
	}


	static function shutdown() {
		if ( self::$cachedScriptsAndStylesSetCache ) {
			$key = self::getCacheKey( 'scripts_styles', true );
			set_transient( $key, self::$cachedScriptsAndStyles, 4 * DAY_IN_SECONDS );
			echo "<script>console.log('scripts/styles regenerated', '" . esc_js( $key ) . "');</script>";
		}
	}
}

AdminBarCachePlugin::main();
