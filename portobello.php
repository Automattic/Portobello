<?php

/*
 * Plugin Name: Portobello
 * Description: Inline translations
 */

class Portobello {
	static $instance;

	var $strings = array();

	public static function &init() {
		if ( ! self::$instance ) {
			self::$instance = new Portobello;
		}

		return self::$instance;
	}

	private function __construct() {
		$this->add_actions();
	}

	function start_catching_translations() {
		add_filter( 'gettext', array( $this, 'catch_translations' ), 100, 2 );
	}

	function stop_catching_translations() {
		remove_filter( 'gettext', array( $this, 'catch_translations' ), 100, 2 );
	}

	function add_actions() {
		add_action( 'wp_head', array( $this, 'start_catching_translations' ), 100 );
		add_action( 'admin_head', array( $this, 'start_catching_translations' ), 100 );

		add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_scripts' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_scripts' ) );

		add_action( 'wp_footer', array( $this, 'add_strings_to_script' ) );
		add_action( 'admin_footer', array( $this, 'add_strings_to_script' ) );
	}

	function catch_translations( $translated, $original ) {
		// @todo - this isn't right - some translations really are equal the original
		if ( $translated !== $original ) {
			return $translated;
		}

		$this->strings[] = $original;

		return $translated;
	}

	function enqueue_scripts() {
		wp_register_script( 'portobello', plugins_url( 'js/portobello.js', __FILE__ ), array(), '20130927', true );
		wp_enqueue_script( 'portobello' );
	}

	function add_strings_to_script() {
		$this->stop_catching_translations();
		wp_localize_script( 'portobello', 'portobelloData', array(
			'strings' => $this->strings,
		) );
	}
}

add_action( 'init', array( 'Portobello', 'init' ) );
