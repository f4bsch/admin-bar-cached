<?php

class AdminBarCached {

	public static function render() {
		$key = AdminBarCachePlugin::getCacheKey();

		$barHtml = get_transient( $key );
		if ( $barHtml ) {
			echo self::fill($barHtml);

			return;
		}

		$t = microtime( true );
		ob_start();
		wp_admin_bar_render();
		$barHtml = ob_get_clean();

		echo $barHtml;

		$t = round( ( microtime( true ) - $t ) * 1000, 0 );

		$insertAt = strrpos( $barHtml, '</ul>' );
		if ( $insertAt !== false ) {
			$insert  = "<li id='' class='menupop'>$t ms</li>";
			$barHtml = substr( $barHtml, 0, $insertAt ) . $insert . substr( $barHtml, $insertAt );
			$barHtml .= "<!-- admin bar generated in $t ms -->";
			$barHtml = self::emplaceTplFields( $barHtml );

			$barHtml = str_replace('id="wp-admin-bar-wp-logo"', 'id="wp-admin-bar-wp-logo" style="display:none"', $barHtml);
		}

		set_transient( $key, $barHtml, 6 * HOUR_IN_SECONDS );
	}

	private static function emplaceTplFields( $barHtml ) {

		// wp-admin/term.php?taxonomy=category&tag_ID=1&post_type=post
		// wp-admin/post.php?post=607&action=edit

		/**
		 * @global WP_Query $wp_query
		 */
		global $wp_query;

		if ( $wp_query && ( $qobj = $wp_query->get_queried_object() ) ) {
			switch ( get_class( $qobj ) ) {
				case 'WP_Post': {
					/** @var $qobj WP_Post */
					$barHtml = preg_replace( "/={$qobj->ID}([&'\"])/", '={{POST_ID}}$1', $barHtml );
					break;
				}
				case 'WP_Term': {
					/** @var $qobj WP_Term */
					$barHtml = preg_replace( "/={$qobj->term_id}([&'\"])/", '={{TERM_ID}}$1', $barHtml );
					break;
				}
			}
		}

		return $barHtml;
	}

	private static function fill($barHtml) {
		/**
		 * @global WP_Query $wp_query
		 */
		global $wp_query;


		if ( $wp_query && ( $qobj = $wp_query->get_queried_object() ) ) {
			switch ( get_class( $qobj ) ) {
				case 'WP_Post': {
					/** @var $qobj WP_Post */
					$barHtml = str_replace('{{POST_ID}}', $qobj->ID, $barHtml);
					break;
				}
				case 'WP_Term': {
					/** @var $qobj WP_Term */
					$barHtml = str_replace('{{TERM_ID}}', $qobj->term_id, $barHtml);
					break;
				}
			}
		}

		return $barHtml;
	}
}