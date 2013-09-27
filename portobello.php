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
		add_filter( 'gettext_with_context', array( $this, 'catch_translations' ), 100, 3 );

		add_filter( 'ngettext', array( $this, 'catch_plural_translations' ), 100, 4 );
		add_filter( 'ngettext_with_context', array( $this, 'catch_plural_translations' ), 100, 5 );
	}

	function stop_catching_translations() {
		remove_filter( 'gettext', array( $this, 'catch_translations' ), 100, 2 );
		remove_filter( 'gettext_with_context', array( $this, 'catch_translations' ), 100, 3 );

		remove_filter( 'ngettext', array( $this, 'catch_plural_translations' ), 100, 4 );
		remove_filter( 'ngettext_with_context', array( $this, 'catch_plural_translations' ), 100, 5 );
	}

	function add_actions() {
		add_action( 'wp_head', array( $this, 'start_catching_translations' ), 100 );
		add_action( 'admin_head', array( $this, 'start_catching_translations' ), 100 );

		add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_scripts' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_scripts' ) );

		add_action( 'wp_footer', array( $this, 'add_strings_to_script' ) );
		add_action( 'admin_footer', array( $this, 'add_strings_to_script' ) );
	}

	// @todo bulk queries and get_cache_multi
	function get_glotpress_id( $singular, $plural = '', $context = '' ) {
		global $wpdb;

		if ( ! defined( 'IS_WPCOM' ) || ! IS_WPCOM ) {
			return $this->fake_glotpress_id();
		}

		$cache_key = md5( $singular . '|' . $plural . '|' . $context );

		$glotpress_id = wp_cache_get( $cache_key, 'gp_originals' );

		if ( $glotpress_id ) {
			return $glotpress_id;
		}

		$query = $wpdb->prepare( "SELECT `id` FROM `gp_originals` WHERE `project_id` = 1 AND `singular` = %s", $singular );

		if ( $plural ) {
			$query .= $wpdb->prepare( " AND `plural` = %s", $plural );
		} else {
			$query .= " AND `plural` IS NULL";
		}

		if ( $context ) {
			$query .= $wpdb->prepare( " AND `context` = %s", $context );
		} else {
			$query .= " AND `context` IS NULL";
		}

		$glotpress_id = $wpdb->get_var( $query );

		wp_cache_add( $cache_key, $glotpress_id, 'gp_originals' );

		return $glotpress_id;
	}

	function fake_glotpress_id() {
		static $glotpress_id = 0;
		$glotpress_id += 10;
		return $glotpress_id;
	}

	function catch_translations( $translated, $original, $context = '' ) {
		return $this->catch_plural_translations( $translated, $original, '', 0, $context );
	}

	function catch_plural_translations( $translated, $singular, $plural, $number = 0, $context = '' ) {
		// @todo - this isn't right - some translations really are equal the original
		if ( $translated !== $singular && $translated !== $plural ) {
			return $translated;
		}

		$datum = array( $singular, $plural, $context );

		if ( in_array( $datum, $this->strings ) ) {
			return $translated;
		}

		$glotpress_id = $this->get_glotpress_id( $singular, $plural, $context );

		$this->strings[$glotpress_id] = $datum;

		return $translated;
	}

	function enqueue_scripts() {
		wp_register_script( 'portobello', plugins_url( 'js/portobello.js', __FILE__ ), array(), '20130927', true );
		wp_enqueue_script( 'portobello' );
	}

	function add_strings_to_script() {
		$this->stop_catching_translations();

		// @todo output singular and plural?
		wp_localize_script( 'portobello', 'portobelloData', array(
			'strings' => wp_list_pluck( $this->strings, 0 ),
		) );
	}
}

add_action( 'init', array( 'Portobello', 'init' ) );
