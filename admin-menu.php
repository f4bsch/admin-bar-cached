<?php

/**
* Caching the menu is experimental and does not work properly yet.
* Put define('ADMIN_BAR_CACHED_EXPERIMENTAL_MENU', true); in your wp-config.php to test it
*/

class AdminMenuCache {
	static function adminMenuPre() {
		global $wp_filter;
		if ( isset( $wp_filter['admin_menu'] ) ) {

			$menuCached = get_transient( 'mca' );
			$menuState  = get_transient( 'mca_s' );

			ob_start();

			static $menuGlobals = [
				'submenu',
				'menu',
				'_wp_real_parent_file',
				'_wp_submenu_nopriv',
				'_registered_pages',
				'_parent_pages',
				'admin_page_hooks'
			];


			if ( $menuCached && $menuState ) {
				foreach ( $menuGlobals as $mg ) {
					$GLOBALS[ $mg ] = $menuState[ $mg ];
				}

				print_r( $menuState );

				unset( $wp_filter['admin_menu'] );
				add_action( 'adminmenu', function () use ( $menuCached ) {
					ob_end_clean();
					echo $menuCached . "<script>console.log('menu cached')</script>";
				} );

			} else {
				add_action( 'adminmenu', function () use ( $menuGlobals ) {
					$menu = ob_get_clean();
					echo $menu;
					set_transient( 'mca', $menu, 10 );

					$menuState = [];
					foreach ( $menuGlobals as $mg ) {
						$menuState[ $mg ] = $GLOBALS[ $mg ];
					}

					global $wp_filter;
					foreach ( array_keys( $GLOBALS['_registered_pages'] ) as $rp ) {
						var_dump($wp_filter[ $rp ]->callbacks);
					}


					set_transient( 'mca_s', $menuState, 10 );

				} );
			}


			//$wp_filter['admin_menu']->do_action('');

			//foreach($wp_filter['admin_menu']['callbacks'] as $callback) {

			//}

			//print_r($wp_filter['admin_menu']);

			//exit;
		}
	}
}