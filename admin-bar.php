<?php

class AdminBarCached {

	public static function render() {
		$key = AdminBarCachePlugin::getCacheKey() ;

		$barHtml = get_transient($key);
		if($barHtml)
		{
			echo $barHtml;
			return;
		}

		$t = microtime(true);
		ob_start();
		wp_admin_bar_render();
		$barHtml = ob_get_clean();

		echo $barHtml;

		$t = round((microtime(true) - $t) * 1000,2);

		$insertAt = strrpos($barHtml, '</ul>');
		$insert = "<li id='' class='menupop'>$t ms</li>";
		$barHtml = substr($barHtml, 0, $insertAt).$insert.substr($barHtml, $insertAt);
		$barHtml .= "<!-- admin bar generated in $t ms -->";

		set_transient($key , $barHtml, 1 * HOUR_IN_SECONDS);
	}
}