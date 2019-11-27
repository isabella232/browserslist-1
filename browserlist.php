<?php
/*
Plugin Name: Browserlist
Plugin URI: http://yoast.com/wordpress/plugins/browserlist/
Description: Display a list of browsers based on a browserlist json file.
Version: 1.0
Author: joostdevalk
Author URI: https://yoast.com/
License: GPL2
*/

class Yoast_Browserlist {
	/**
	 * Used to translate shortnames to human readable names.
	 *
	 * @var array
	 */
	private $browser_names = [
		'and_chr' => 'Chrome for Android',
		'and_ff'  => 'Firefox for Android',
		'and_uc'  => 'UC Browser for Android',
		'and_qq'  => 'QQ Browser',
		'android' => 'Android Browser',
		'baidu'   => 'Baidu Browser',
		'chrome'  => 'Chrome',
		'edge'    => 'Edge',
		'firefox' => 'Firefox',
		'ie'      => 'Internet Explorer',
		'ie_mob'  => 'IE Mobile',
		'ios_saf' => 'iOS Safari',
		'op_mini' => 'Opera Mini',
		'op_mob'  => 'Opera Mobile',
		'opera'   => 'Opera',
		'safari'  => 'Safari',
		'samsung' => 'Samsung Internet',
	];

	/**
	 * Used to classify browsers.
	 *
	 * @var array
	 */
	private $desktop_browsers = [
		'chrome',
		'edge',
		'firefox',
		'ie',
		'opera',
		'safari',
	];

	/**
	 * Configuration URL.
	 *
	 * @var string
	 */
	private $config_url = '';

	/**
	 * The transient used to store our browserlist config.
	 *
	 * @var string
	 */
	private $config_transient = 'browserlist_config_remote';

	/**
	 * Yoast_Browserlist constructor.
	 */
	public function __construct() {
		$this->plugin_dir_url = plugin_dir_url( __FILE__ );

		add_shortcode( 'browserlist', [ $this, 'shortcode' ] );
		add_shortcode( 'browserslist', [ $this, 'shortcode' ] );
	}

	/**
	 * Outputs the browserlist HTML.
	 *
	 * @param array $attributes
	 *
	 * @return string
	 */
	public function shortcode( $attributes ) {
		$parsed_attributes = shortcode_atts(
			[
				'config'  => 'https://raw.githubusercontent.com/Yoast/javascript/develop/packages/browserslist-config/src/index.js',
				'heading' => 'h2',
			],
			$attributes
		);

		$this->config_url = $parsed_attributes['config'];

		return $this->get_browserlist_html( $parsed_attributes['heading'] );
	}

	/**
	 * Retrieves the browserlist config, either from cache or remote.
	 *
	 * @return array|bool|mixed
	 */
	private function get_browserlist_config() {
		$browserlist_config = get_transient( $this->config_transient );
		if ( empty( $browserlist_config ) ) {
			$browserlist_config = $this->get_browserlist_config_from_remote();
		}

		return $browserlist_config;
	}

	/**
	 * Retrieves the browserlist config from our remote server.
	 *
	 * @return bool|array False on failure, an array with browsers on success.
	 */
	private function get_browserlist_config_from_remote() {
		$config_request = wp_remote_get( $this->config_url );
		$config         = wp_remote_retrieve_body( $config_request );
		if ( empty( $config ) ) {
			return false;
		}
		preg_match_all( '/"([^"]+)"/', $config, $matches );

		set_transient( $this->config_transient, $matches[1], 24 * HOUR_IN_SECONDS );

		return $matches[1];
	}

	/**
	 * Retrieves the browserlist HTML.
	 *
	 * @param string $heading The heading to use for the lists.
	 *
	 * @return string
	 */
	private function get_browserlist_html( $heading ) {
		$config        = $this->get_browserlist_config();
		$browsers      = $this->get_browsers( $config );
		$browsers_list = $this->translate_browsers( $browsers );

		$html = '<div class="browserslist">';
		foreach ( [ 'mobile' => 'Mobile', 'desktop' => 'Desktop' ] as $platform => $heading_text ) {
			$html .= '<div class="browserslist_' . $platform . '">';
			$html .= sprintf( '<%1$s>%2$s</%1$s>', $heading, $heading_text );
			$html .= '<ul>';
			foreach ( $browsers_list[ $platform ] as $browser ) {
				$html .= sprintf( '<li><img src="%1$s" alt="%2$s" /> %2$s %3$s</li>', $browser['image'], $browser['name'], $browser['version'] );
			}
			$html .= '</ul></div>';
		}
		$html .= '</div>';

		return $html;
	}

	/**
	 * Retrieves the browsers you should support based on given config.
	 *
	 * @param array $config Array of browsers you want to support.
	 *
	 * @return array The output of the npx command.
	 */
	private function get_browsers( $config ) {
		if ( ! is_array( $config ) ) {
			return [];
		}
		$url_config = '"' . implode( $config, ', ' ) . '"';

		$command = 'npx browserslist ' . $url_config;

		exec( $command, $output );

		return $output;
	}

	/**
	 * Translate the non-human readable npx output into an array with human readable names and logo's.
	 *
	 * @param array $browsers Browsers returned by npx.
	 *
	 * @return array Browsers, each with version, image, name, grouped by type.
	 */
	private function translate_browsers( $browsers ) {
		$browsers_list = [
			'mobile'  => [],
			'desktop' => [],
		];

		foreach ( $browsers as $browser ) {
			preg_match( '/([a-z_]+) ([\d-\.]+)/', $browser, $match );
			$browser_arr['version'] = $match[2];
			$browser_arr['image']   = $this->plugin_dir_url . 'images/' . $match[1] . '.png';
			$browser_arr['name']    = $this->browser_names[ $match[1] ];

			$type = 'mobile';
			if ( in_array( $match[1], $this->desktop_browsers ) ) {
				$type = 'desktop';
			}

			$browsers_list[ $type ][] = $browser_arr;
		}

		return $browsers_list;
	}
}

new Yoast_Browserlist();
