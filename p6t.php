<?php

// TODO: Style all the things.
// TODO: Better post-save UI.

class p6t {
	private $translations = array();
	private $glotpress_project_id = 1;
	private $translation_idx_for_js = 1;
	private $locale;

	static $instance;

	static function &init() {
		if ( ! $instance ) {
			$instance = new p6t;
		}

		return $instance;
	}

	function __construct() {
		global $wp_locale;
		// Needed so we can check the nplurals value for each language
		require_once( ABSPATH . '/glotpress.dir/gp/locales/locales.php');

		if ( isset( $_GET['sa-locale'] ) && is_super_admin() ) {
			$this->locale = GP_Locales::by_slug( $_GET['sa-locale'] );
		} else {
			$this->locale = GP_Locales::by_slug( get_locale() );
			if ( 'en' === $this->locale->slug ) {
				return;
			}
		}

		if ( ! $this->locale ) {
			return;
		}

		$this->run_hooks();
	}

	function run_hooks() {
		add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_scripts_and_styles' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_scripts_and_styles' ) );

		add_action( 'admin_bar_menu', array( $this, 'admin_bar_menu' ), 1110 );

		add_action( 'wp_head', array( $this, 'start_catching_translations' ), 100 );
		add_action( 'admin_head', array( $this, 'start_catching_translations' ), 100 );

		add_action( 'wp_footer', array( $this, 'stop_catching_translations' ) );
		add_action( 'admin_footer', array( $this, 'stop_catching_translations' ) );

		add_action( 'wp_footer', array( $this, 'render_editor' ) );
		add_action( 'admin_footer', array( $this, 'render_editor' ) );

		add_filter( 'body_class', array( $this, 'body_class') );
		add_filter( 'admin_body_class', array( $this, 'admin_body_class') );

		add_action( 'wp_ajax_p6t_save', array( $this, 'handle_ajax_save' ) );
	}

	function start_catching_translations() {
		add_filter( 'gettext', array( $this, 'gettext' ), 10, 2 );
		add_filter( 'gettext_with_context', array( $this, 'gettext_with_context' ), 10, 3 );
		add_filter( 'ngettext', array( $this, 'ngettext' ), 10, 4 );
		add_filter( 'ngettext_with_context', array( $this, 'ngettext_with_context' ), 10, 5 );
	}

	function stop_catching_translations() {
		remove_filter( 'gettext', array( $this, 'gettext' ), 10, 2 );
		remove_filter( 'gettext_with_context', array( $this, 'gettext_with_context' ), 10, 3 );
		remove_filter( 'ngettext', array( $this, 'ngettext' ), 10, 4 );
		remove_filter( 'ngettext_with_context', array( $this, 'ngettext_with_context' ), 10, 5 );
	}

	function admin_bar_menu( $wp_admin_bar ) {
		$globe_image = staticize_subdomain( plugins_url( 'img/globe.png', __FILE__ ) );


		if ( $this->is_polyglot_editor_request() ) {
			$url = remove_query_arg( 'poly' );
		} else {
			$url = add_query_arg( 'poly', 'glot', http() . '://' . $_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI'] );
			$url = bar_get_redirect_url( 'portobello_click', 'admin_bar', $url );
		}

		$wp_admin_bar->add_menu(
			array(
				'title' => "<img src='$globe_image' />",
				'href' => $url,
				'parent' => 'top-secondary'
			)
		);
	}

	function render_editor() {
		if ( ! $this->is_polyglot_editor_request() )
			return;

		?>

		<div id="p6t-editor">
			<div class="p6t-header">
				<div id="p6t-close-button"><a href="<?php echo esc_url( remove_query_arg( 'poly' ) ); ?>"><span class="noticon noticon-close" title="x"></span></a></div>
				<h1><?php esc_html_e( 'Translate' ); ?></h1>
				<p class="intro">
					<p>Help us translate WordPress.com!</p>

					<p>Your suggestions will be moderated by our translation volunteers. To learn more about our translation process and community, please read <a href="http://en.support.wordpress.com/translation-faq/">our translation FAQ</a>.
					</p>
				</p>
<?php		if ( is_super_admin() ) : ?>
				<div id="p6t-filters">
					<select id="p6t-locale">
						<option value="en"<?php selected( $this->locale->slug, 'en' ); ?>>English</option>
						<?php foreach ( $this->get_locale_list() as $locale ) : ?>
							<option value="<?php echo esc_attr( $locale->locale ); ?>"<?php selected( $this->locale->slug, $locale->locale ); ?>><?php echo esc_html( $locale->name ); ?></option>
						<?php endforeach; ?>
					</select>
				</div>
<?php		endif; ?>
			</div>

			<div id="p6t-editor-form">
				<p class="p6t-error" style="color: red; display: none;">Sorry, there was an error saving your translation!</p>
				<?php foreach( range( 0, $this->locale->nplurals - 1 ) as $plural_index ) : ?>
					<p class="p6t-translation">
						<label for="p6t-translation_<?php echo (int) $plural_index; ?>"><?php
							printf(
								esc_html__( 'This plural form is used for numbers like: %s' ),
								'<span class="numbers">' . implode( ', ', $this->locale->numbers_for_index( $plural_index ) ) . '</span>'
							);
						?></label>
						<textarea id="p6t-translation_<?php echo (int) $plural_index; ?>"></textarea>
						<a href="#" class="p6t-paste-original" title="<?php esc_attr_e( 'Copy original' ) ?>">
							<span class="noticon noticon-summary" title="<?php esc_attr_e( 'Copy original' ) ?>"></span>
						</a>
					</p>
				<?php endforeach; ?>
				<input type="submit" name="save" id="p6t-save" class="button" value="<?php esc_attr_e( 'Save' ); ?>">
				<span class="p6t-translation-success" style="font-size: 20px; color: green; display: none;">&#10003;</span>
			</div>

			<?php if ( count( $this->translations ) > 0 ) : ?>
				<span id="p6t-loading">Loading...</span>
				<ul class="translation-list">
				<?php
					foreach ( $this->translations as $glotpress_id => $translation ) :
						$context = empty( $translation['context'] ) ? '' : '<span class="p6t-context">' . esc_html( $translation['context'] ) . '</span>';
						$type = isset( $translation['plural'] ) ? 'plural' : 'singular' ;
						$original_singular = isset( $translation['singular'] ) ? ' data-original-singular="' . esc_attr( $translation['singular'] ) . '"' : '';
						$original_plural = isset( $translation['plural'] ) ? ' data-original-plural="' . esc_attr( $translation['plural'] ) . '"' : '';
				?>
					<li id="translation-<?php echo (int) $glotpress_id; ?>" data-glotpress-id="<?php echo (int) $glotpress_id; ?>" data-type="<?php echo $type; ?>"<?php echo $original_singular . $original_plural ;?>><?php echo esc_html( $translation['singular'] ) . $context; ?></li>
				<?php endforeach; ?>
				</ul>
			<?php endif; ?>
		</div>
		<?php
	}

	function admin_body_class( $classes ) {
		if ( $this->is_polyglot_editor_request() )
			$classes .= 'p6t ';

		return $classes;
	}

	function body_class( $classes ) {
		if ( $this->is_polyglot_editor_request() )
			$classes[] = 'p6t';

		return $classes;
	}

	function enqueue_scripts_and_styles() {
		if ( !$this->is_polyglot_editor_request() )
			return;

		wp_enqueue_style( 'p6t', plugins_url( 'p6t.css', __FILE__ ) );
		wp_enqueue_style( 'select2.css', '/wp-content/js/select2/select2.css', array(), '20120723' );
		wp_enqueue_style( 'noticons', '/i/noticons/noticons.css', array(), '20120723' );

		wp_enqueue_script( 'p6t', plugins_url( 'p6t.js', __FILE__ ), 'jquery', '20120723' );
		wp_enqueue_script( 'select2.js', '/wp-content/js/select2/select2.js', array( 'jquery', 'jquery-ui-core', 'jquery-color' ), '20120723', true );

	}

	function is_polyglot_editor_request() {
		return ( !empty( $_GET['poly'] ) && 'glot' == $_GET['poly'] );
	}

	function translation( $singular, $plural = NULL, $number = 0, $context = NULL ) {
		$glotpress_id = $this->find_glotpress_original_for( $singular, $plural, $context );

		if ( !$glotpress_id ) {
			return;
		}

		$this->translations[$glotpress_id] = compact( 'singular', 'plural', 'context' );
	}

	function gettext( $translation, $original ) {
		if ( $translation !== $original ) {
			return $translation;
		}

		$this->translation( $original );
		return $translation;
	}

	function gettext_with_context( $translation, $original, $context ) {
		if ( $translation !== $original ) {
			return $translation;
		}

		$this->translation( $original, NULL, 0, $context );
		return $translation;
	}

	function ngettext( $translation, $singular, $plural, $count ) {
		if ( $translation !== $singular && $translation !== $plural ) {
			return $translation;
		}

		$this->translation( $singular, $plural, $count );
		return $translation;
	}

	function ngettext_with_context( $translation, $singular, $plural, $count, $context ) {
		if ( $translation !== $singular && $translation !== $plural ) {
			return $translation;
		}

		$this->translation( $singular, $plural, $count, $context );
		return $translation;
	}

	function handle_ajax_save() {
		if ( empty( $_POST['is_plural'] ) || empty( $_POST['locale'] ) ) {
		}

		if ( empty( $_POST['translations'] ) || !is_array( $_POST['translations'] ) ) {
			status_header( 400 );
			die( __( 'Missing parameter.' ) );
		}

		if ( empty( $_POST['glotpress_id'] ) ) {
			status_header( 400 );
			die( __( 'Missing parameter.' ) );
		}

		if ( count( $_POST['translations'] ) == 0 || ( true === $_POST['is_plural'] && count( $_POST['translations'] < 2 ) ) ) {
			status_header( 400 );
			die( __( 'Missing translation.' ) );
		}

		$this->save_translation(
			stripslashes( $_POST['locale'] ),
			(int) $_POST['glotpress_id'],
			array_map( 'stripslashes', $_POST['translations'] )
		);
		exit;
	}

	function save_translation( $locale, $glotpress_id, $translations ) {
		global $wpdb, $current_user;

		$glotpress_id = (int) $this->verify_glotpress_id( $glotpress_id );
		$translation_set_id = (int) $this->get_translation_set_id( $locale );

		if ( !$glotpress_id || ! $translation_set_id )
			return false;

		$fields_to_insert = array(
			'original_id'        => $glotpress_id,
			'translation_set_id' => $translation_set_id,
			'user_id'            => $current_user->ID,
			'status'             => 'waiting',
			'date_added'         => gmdate( 'Y-m-d H:i:s'),
			'date_modified'      => gmdate( 'Y-m-d H:i:s'),
		);

		$field_formats = array( '%d', '%d', '%d', '%s', '%s', '%s' );
		foreach ( $translations as $idx => $translated_string ) {
			if ( ! empty( $translated_string ) ) {
				$fields_to_insert['translation_' . $idx] = $translated_string;
				$field_formats[] = '%s';
			}
		}

		$wpdb->insert( 'gp_translations', $fields_to_insert, $field_formats );
		bump_stats_extras( 'portobello_insert', $locale );
	}

	function verify_glotpress_id( $id ) {
		global $wpdb;
		return (int) $wpdb->get_var( $wpdb->prepare( "SELECT `id` FROM `gp_originals` WHERE `id` = %d", $id ) );
	}

	function get_translation_set_id( $locale ) {
		global $wpdb;
		return (int) $wpdb->get_var( $wpdb->prepare( 'SELECT id FROM gp_translation_sets WHERE project_id = %d AND locale = %s', $this->glotpress_project_id, $locale ) );
	}

	function find_glotpress_original_for( $singular, $plural = null, $context = null ) {
		global $wpdb;

		$cache_key = md5( $singular . '|' . $plural . '|' . $context );

		$glotpress_id = wp_cache_get( $cache_key, 'gp_originals' );

		if ( $glotpress_id ) {
			return $glotpress_id;
		}

		$query = $wpdb->prepare( "SELECT `id` FROM `gp_originals` WHERE `project_id` = %d AND `status` = %s AND `singular` = %s", $this->glotpress_project_id, '+active', $singular );

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

	function get_locale_list() {
		global $wpdb;

		return $wpdb->get_results( $wpdb->prepare( 'SELECT name, locale FROM gp_translation_sets WHERE project_id = %d ORDER BY name ASC', $this->glotpress_project_id ) );
	}
}

add_action( 'init', array( 'p6t', 'init' ), 100 );
