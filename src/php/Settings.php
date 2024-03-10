<?php
/**
 * Settings class file.
 *
 * @package kagg/generator
 */

// phpcs:ignore Generic.Commenting.DocComment.MissingShort
/** @noinspection PhpUnnecessaryCurlyVarSyntaxInspection */

namespace KAGG\Generator;

use KAGG\Generator\Generator\Generator;
use KAGG\Generator\Generator\Item;

/**
 * Class Settings.
 */
class Settings {

	/**
	 * The plugin settings option name.
	 */
	const OPTION_GROUP = 'kagg_generator_group';

	/**
	 * The plugin settings page slug.
	 */
	const PAGE = 'kagg-generator';

	/**
	 * The plugin style and script handle.
	 */
	const HANDLE = 'kagg-generator-admin';

	/**
	 * The plugin 'generate' action.
	 */
	const GENERATE_ACTION = 'kagg-generator-generate';

	/**
	 * The plugin cache flush action.
	 */
	const CACHE_FLUSH_ACTION = 'kagg-generator-cache-flush';

	/**
	 * The plugin download SQL file action.
	 */
	const DOWNLOAD_SQL_ACTION = 'kagg-generator-download-sql';

	/**
	 * The plugin update comment counts action.
	 */
	const UPDATE_COMMENT_COUNTS_ACTION = 'kagg-generator-update-comment-counts';

	/**
	 * The plugin 'delete' action.
	 */
	const DELETE_ACTION = 'kagg-generator-delete';

	/**
	 * The name of the option to store plugin settings.
	 */
	const OPTION_KEY = 'kagg_generator_settings';

	/**
	 * The first part of the generated marker for added items.
	 */
	const MARKER = 'https://generator.kagg.eu/';

	/**
	 * Plugin prefix.
	 */
	const PREFIX = 'kagg-generator-';

	/**
	 * Generation ID.
	 */
	const GENERATION_ID = 'generation_id';

	/**
	 * Generator class instance.
	 *
	 * @var Generator
	 */
	private $generator;

	/**
	 * Form fields.
	 *
	 * @var array
	 */
	private $form_fields;

	/**
	 * Plugin settings.
	 *
	 * @var array
	 */
	private $settings;

	/**
	 * Init class.
	 *
	 * @return void
	 */
	public function init() {
		$this->generator = new Generator();

		$this->init_form_fields();
		$this->init_settings();
		$this->hooks();
	}

	/**
	 * Init hooks.
	 *
	 * @return void
	 */
	public function hooks() {
		add_filter(
			'plugin_action_links_' . plugin_basename( KAGG_GENERATOR_FILE ),
			[ $this, 'add_settings_link' ],
			10,
			2
		);

		add_action( 'admin_menu', [ $this, 'add_settings_page' ] );
		add_action( 'current_screen', [ $this, 'requirements' ] );
		add_action( 'current_screen', [ $this, 'setup_fields' ] );
		add_action( 'current_screen', [ $this, 'setup_sections' ], 11 );
		add_filter( 'pre_update_option_' . self::OPTION_KEY, [ $this, 'pre_update_option_filter' ], 10, 3 );
		add_action( 'plugins_loaded', [ $this, 'load_textdomain' ], 100 );
		add_action( 'admin_enqueue_scripts', [ $this, 'admin_enqueue_scripts' ] );
		add_action( 'wp_ajax_' . self::UPDATE_COMMENT_COUNTS_ACTION, [ $this, 'update_comment_counts' ] );
		add_action( 'wp_ajax_' . self::CACHE_FLUSH_ACTION, [ $this, 'cache_flush' ] );
		add_action( 'wp_ajax_' . self::DOWNLOAD_SQL_ACTION, [ $this->generator, 'download_sql' ] );
		add_action( 'wp_ajax_' . self::DELETE_ACTION, [ $this, 'delete' ] );
		add_action( 'wp_ajax_' . self::GENERATE_ACTION, [ $this, 'generate' ] );
	}

	/**
	 * Add link to plugin setting page on plugins page.
	 *
	 * @param array|mixed $links Plugin links.
	 * @param string      $file  Filename.
	 *
	 * @return array Plugin links
	 * @noinspection PhpUnusedParameterInspection
	 */
	public function add_settings_link( $links, string $file ): array {
		$action_links = [
			'settings' =>
				'<a href="' . admin_url( 'tools.php?page=' . self::PAGE ) .
				'" aria-label="' .
				esc_attr__( 'View KAGG Fast Post Generator settings', 'kagg-generator' ) .
				'">' .
				esc_html__( 'Settings', 'kagg-generator' ) . '</a>',
		];

		return array_merge( $action_links, (array) $links );
	}

	/**
	 * Add the settings page to the menu.
	 */
	public function add_settings_page() {
		$page_title = __( 'KAGG Fast Post Generator', 'kagg-generator' );
		$menu_title = __( 'KAGG Generator', 'kagg-generator' );
		$capability = 'manage_options';
		$slug       = self::PAGE;
		$callback   = [ $this, 'settings_page' ];
		$icon       = KAGG_GENERATOR_URL . '/assets/images/icon-16x16.png';
		$icon       = '<img class="kagg-generator-menu-image" src="' . $icon . '">';
		$menu_title = $icon . '<span class="kagg-generator-menu-title">' . $menu_title . '</span>';

		add_submenu_page( 'tools.php', $page_title, $menu_title, $capability, $slug, $callback );
	}

	/**
	 * Settings page.
	 */
	public function settings_page() {
		?>
		<div class="wrap">
			<h2 id="title">
				<?php
				// Admin panel title.
				echo( esc_html( __( 'KAGG Fast Post Generator', 'kagg-generator' ) ) );
				?>
			</h2>

			<form
				id="kagg-generator-settings" action="<?php echo esc_url( admin_url( 'options.php' ) ); ?>"
				method="POST">

				<input
					type="hidden" name="<?php echo esc_attr( self::GENERATION_ID ); ?>"
					value="<?php echo esc_attr( uniqid( self::PREFIX, true ) ); ?>" />
				<?php
				settings_fields( self::OPTION_GROUP ); // Hidden protection fields.
				do_settings_sections( self::PAGE ); // Sections with options.
				submit_button();
				?>
			</form>

			<?php
			submit_button( __( 'Generate', 'kagg-generate' ), 'secondary', 'kagg-generate-button' );
			?>

			<?php
			submit_button( __( 'Delete', 'kagg-generate' ), 'secondary', 'kagg-delete-button' );
			?>

			<div id="kagg-generator-log"></div>
		</div>
		<?php
	}

	/**
	 * Setup settings sections.
	 */
	public function setup_sections() {
		if ( ! $this->is_options_screen() ) {
			return;
		}

		add_settings_section(
			'first_section',
			__( 'Options', 'kagg-generator' ),
			[ $this, 'first_section' ],
			self::PAGE
		);
	}

	/**
	 * Section callback.
	 *
	 * @param array $arguments Section arguments.
	 */
	public function first_section( array $arguments ) {
	}

	/**
	 * Get plugin option.
	 *
	 * @param string $key         Key.
	 * @param mixed  $empty_value Empty value.
	 *
	 * @return mixed The value specified for the option or a default value for the option.
	 */
	public function get_option( string $key, $empty_value = null ) {
		if ( empty( $this->settings ) ) {
			$this->init_settings();
		}

		// Get option default if unset.
		if ( ! isset( $this->settings[ $key ] ) ) {
			$form_fields            = $this->get_form_fields();
			$this->settings[ $key ] = isset( $form_fields[ $key ] ) ? $this->get_field_default( $form_fields[ $key ] ) : '';
		}

		if ( '' === $this->settings[ $key ] && ! is_null( $empty_value ) ) {
			$this->settings[ $key ] = $empty_value;
		}

		return $this->settings[ $key ];
	}

	/**
	 * Show requirements' notice.
	 *
	 * @return void
	 */
	public function requirements() {
		if ( ! $this->is_options_screen() ) {
			return;
		}

		if ( $this->generator->use_local_infile() && ! ini_get( 'mysqli.allow_local_infile' ) ) {
			// Show notice.
			( new AdminNotices() )->add_notice(
				__( 'To work properly on your server, the KAGG Fast Post Generator plugin needs `mysqli.allow_local_infile = On` set in the php.ini file.', 'kagg-generator' ) .
				'<br>' .
				__( 'Ask your hosting provider to set this configuration option.', 'kagg-generator' ),
				'notice notice-error'
			);
		}

		if ( preg_match( '/[^a-zA-Z0-9 _-]/', pathinfo( str_replace( '\\', '/', sys_get_temp_dir() ), PATHINFO_BASENAME ) ) ) {
			// Show notice.
			( new AdminNotices() )->add_notice(
				__( 'The temporary directory path contains invalid characters. Please check the path and make sure it contains only ASCII letters, numbers, spaces, underscores, and hyphens.', 'kagg-generator' ) .
				'<br>' .
				__( "You may need to modify the 'sys_temp_dir' setting in the php.ini file.", 'kagg-generator' ),
				'notice notice-error'
			);
		}
	}

	/**
	 * Setup options fields.
	 *
	 * @return void
	 */
	public function setup_fields() {
		if ( ! $this->is_options_screen() ) {
			return;
		}

		register_setting( self::OPTION_GROUP, self::OPTION_KEY );

		foreach ( $this->form_fields as $key => $field ) {
			$field['field_id'] = $key;

			add_settings_field(
				$key,
				$field['label'],
				[ $this, 'field_callback' ],
				self::PAGE,
				$field['section'],
				$field
			);
		}
	}

	// phpcs:disable Generic.Metrics.CyclomaticComplexity.MaxExceeded, Generic.Metrics.NestingLevel.MaxExceeded
	/**
	 * Output settings field.
	 *
	 * @param array $arguments Field arguments.
	 *
	 * @noinspection HtmlWrongAttributeValue
	 * @noinspection HtmlUnknownAttribute
	 */
	public function field_callback( array $arguments ) {
		$value = $this->get_option( $arguments['field_id'] );

		// Check which type of field we want.
		switch ( $arguments['type'] ) {
			case 'text':
			case 'password':
			case 'number':
				printf(
					'<input name="%1$s[%2$s]" id="%2$s" type="%3$s" placeholder="%4$s" value="%5$s" class="regular-text" />',
					esc_attr( self::OPTION_KEY ),
					esc_attr( $arguments['field_id'] ),
					esc_attr( $arguments['type'] ),
					esc_attr( $arguments['placeholder'] ),
					esc_html( $value )
				);
				break;
			case 'textarea':
				printf(
					'<textarea name="%1$s[%2$s]" id="%2$s" placeholder="%3$s" rows="5" cols="50">%4$s</textarea>',
					esc_attr( self::OPTION_KEY ),
					esc_attr( $arguments['field_id'] ),
					esc_attr( $arguments['placeholder'] ),
					wp_kses_post( $value )
				);
				break;
			case 'checkbox':
			case 'radio':
				if ( 'checkbox' === $arguments['type'] ) {
					$arguments['options'] = [ 'yes' => '' ];
				}

				if ( ! empty( $arguments['options'] ) && is_array( $arguments['options'] ) ) {
					$options_markup = '';
					$iterator       = 0;
					foreach ( $arguments['options'] as $key => $label ) {
						++$iterator;
						$options_markup .= sprintf(
							'<label for="%2$s_%7$s"><input id="%2$s_%7$s" name="%1$s[%2$s]" type="%3$s" value="%4$s" %5$s /> %6$s</label><br/>',
							esc_attr( self::OPTION_KEY ),
							$arguments['field_id'],
							$arguments['type'],
							$key,
							checked( $value, $key, false ),
							$label,
							$iterator
						);
					}
					printf(
						'<fieldset>%s</fieldset>',
						wp_kses(
							$options_markup,
							[
								'label' => [
									'for' => [],
								],
								'input' => [
									'id'      => [],
									'name'    => [],
									'type'    => [],
									'value'   => [],
									'checked' => [],
								],
								'br'    => [],
							]
						)
					);
				}
				break;
			case 'select': // If it is a select dropdown.
				if ( ! empty( $arguments['options'] ) && is_array( $arguments['options'] ) ) {
					$options_markup = '';
					foreach ( $arguments['options'] as $key => $label ) {
						$options_markup .= sprintf(
							'<option value="%s" %s>%s</option>',
							$key,
							selected( $value, $key, false ),
							$label
						);
					}
					printf(
						'<select name="%1$s[%2$s]">%3$s</select>',
						esc_attr( self::OPTION_KEY ),
						esc_html( $arguments['field_id'] ),
						wp_kses(
							$options_markup,
							[
								'option' => [
									'value'    => [],
									'selected' => [],
								],
							]
						)
					);
				}
				break;
			case 'multiple': // If it is a multiple select dropdown.
				if ( ! empty( $arguments['options'] ) && is_array( $arguments['options'] ) ) {
					$options_markup = '';
					foreach ( $arguments['options'] as $key => $label ) {
						$selected = '';
						if ( is_array( $value ) && in_array( $key, $value, true ) ) {
							$selected = selected( $key, $key, false );
						}
						$options_markup .= sprintf(
							'<option value="%s" %s>%s</option>',
							$key,
							$selected,
							$label
						);
					}
					printf(
						'<select multiple="multiple" name="%1$s[%2$s][]">%3$s</select>',
						esc_attr( self::OPTION_KEY ),
						esc_html( $arguments['field_id'] ),
						wp_kses(
							$options_markup,
							[
								'option' => [
									'value'    => [],
									'selected' => [],
								],
							]
						)
					);
				}
				break;
			default:
		}

		// If there is a help text.
		$helper = $arguments['helper'];
		if ( $helper ) {
			printf( '<span class="helper"> %s</span>', esc_html( $helper ) );
		}

		// If there is a supplemental text.
		$supplemental = $arguments['supplemental'];
		if ( $supplemental ) {
			printf( '<p class="description">%s</p>', esc_html( $supplemental ) );
		}
	}
	// phpcs:enable Generic.Metrics.CyclomaticComplexity.MaxExceeded, Generic.Metrics.NestingLevel.MaxExceeded

	/**
	 * Filter plugin option update.
	 *
	 * @param mixed  $value     New option value.
	 * @param mixed  $old_value Old option value.
	 * @param string $option    Option name.
	 *
	 * @return mixed
	 * @noinspection PhpUnusedParameterInspection
	 */
	public function pre_update_option_filter( $value, $old_value, string $option ) {
		if ( $value === $old_value ) {
			return $value;
		}

		$form_fields = $this->get_form_fields();
		foreach ( $form_fields as $key => $form_field ) {
			$value[ $key ] = $value[ $key ] ?? $form_field;

			if ( 'checkbox' === $form_field['type'] ) {
				$value[ $key ] = '1' === $value[ $key ] || 'yes' === $value[ $key ] ? 'yes' : 'no';
			}
		}

		return $value;
	}

	/**
	 * Load plugin text domain.
	 */
	public function load_textdomain() {
		load_plugin_textdomain(
			'kagg-generator',
			false,
			plugin_basename( KAGG_GENERATOR_PATH ) . '/languages/'
		);
	}

	/**
	 * Enqueue plugin scripts.
	 */
	public function admin_enqueue_scripts() {
		wp_enqueue_style(
			self::HANDLE,
			KAGG_GENERATOR_URL . '/assets/css/admin.css',
			[],
			KAGG_GENERATOR_VERSION
		);

		wp_enqueue_script(
			self::HANDLE,
			KAGG_GENERATOR_URL . '/assets/js/admin.js',
			[],
			KAGG_GENERATOR_VERSION,
			true
		);

		$generate_ajax_url = admin_url( 'admin-ajax.php' );

		if ( defined( 'KAGG_GENERATOR_SHORTINIT' ) && KAGG_GENERATOR_SHORTINIT ) {
			$generate_ajax_url = KAGG_GENERATOR_URL . '/src/php/ajax.php';
		}

		wp_localize_script(
			self::HANDLE,
			'GeneratorObject',
			[
				'generateAjaxUrl'           => $generate_ajax_url,
				'generateAction'            => self::GENERATE_ACTION,
				'generateNonce'             => wp_create_nonce( self::GENERATE_ACTION ),
				'adminAjaxUrl'              => admin_url( 'admin-ajax.php' ),
				'updateCommentCountsAction' => self::UPDATE_COMMENT_COUNTS_ACTION,
				'updateCommentCountsNonce'  => wp_create_nonce( self::UPDATE_COMMENT_COUNTS_ACTION ),
				'cacheFlushAction'          => self::CACHE_FLUSH_ACTION,
				'cacheFlushNonce'           => wp_create_nonce( self::CACHE_FLUSH_ACTION ),
				'downloadSQLAction'         => self::DOWNLOAD_SQL_ACTION,
				'downloadSQLNonce'          => wp_create_nonce( self::DOWNLOAD_SQL_ACTION ),
				'deleteAction'              => self::DELETE_ACTION,
				'deleteNonce'               => wp_create_nonce( self::DELETE_ACTION ),
				'nothingToDo'               => esc_html__( 'Nothing to do.', 'kagg-generate' ),
				'deleteConfirmation'        => esc_html__( 'Are you sure to delete all the generated items?', 'kagg-generate' ),
				'generating'                => esc_html__( 'Generating items...', 'kagg-generate' ),
				'deleting'                  => esc_html__( 'Deleting generated items...', 'kagg-generate' ),
				'updatingCommentCounts'     => esc_html__( 'Updating comment counts...', 'kagg-generate' ),
				// translators: 1: Time.
				'totalTimeUsed'             => esc_html__( 'Total time used: %s sec.', 'kagg-generate' ),
			]
		);
	}

	/**
	 * Update comment counts after generation of comments.
	 *
	 * @return void
	 */
	public function update_comment_counts() {
		global $wpdb;

		$this->generator->run_checks( self::UPDATE_COMMENT_COUNTS_ACTION );

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
		$wpdb->query(
			"UPDATE $wpdb->posts AS p LEFT JOIN
					(SELECT comment_post_ID, COUNT(*) AS comment_count
						FROM $wpdb->comments
						GROUP BY comment_post_ID) AS t
					ON p.ID = t.comment_post_ID
					SET p.comment_count = COALESCE(t.comment_count, 0)
					WHERE p.comment_count != COALESCE( t.comment_count, 0 )"
		);

		wp_send_json_success( esc_html__( 'Comment counts updated.', 'kagg-generator' ) );
	}

	/**
	 * Flush object cache. This action is needed if a persistent object cache like Redis is active.
	 *
	 * @return void
	 */
	public function cache_flush() {
		$this->generator->run_checks( self::CACHE_FLUSH_ACTION );

		wp_cache_flush();

		wp_send_json_success( esc_html__( 'Cache flushed.', 'kagg-generator' ) );
	}

	/**
	 * Delete all generated items.
	 *
	 * @return void
	 * @noinspection SqlResolve
	 */
	public function delete() {
		$this->generator->run_checks( self::DELETE_ACTION );

		$registered_items = $this->generator->get_registered_items();
		$db_locations     = [];
		$result           = false;

		foreach ( $registered_items as $item_classname ) {

			/**
			 * Item handler.
			 *
			 * @var Item $item_handler
			 */
			$item_handler = new $item_classname();
			$db_location  = $item_handler->get_table() . '|' . $item_handler->get_marker_field();

			if ( in_array( $db_location, $db_locations, true ) ) {
				continue;
			}

			$result = $this->delete_items( $item_handler ) || $result;

			$db_locations[] = $db_location;
		}

		if ( $result ) {
			wp_send_json_success( esc_html__( 'All generated items have been deleted.', 'kagg-generator' ) );
		}

		wp_send_json_success( esc_html__( 'Nothing to delete.', 'kagg-generator' ) );
	}

	/**
	 * Delete all generated items.
	 *
	 * @param Item $item_handler Item handler.
	 *
	 * @return bool
	 * @noinspection SqlResolve
	 */
	private function delete_items( Item $item_handler ): bool {
		global $wpdb;

		$table        = $item_handler->get_table();
		$marker_field = $item_handler->get_marker_field();
		$marker       = self::MARKER . '%';

		// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		// phpcs:disable WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$result = $wpdb->query(
			$wpdb->prepare(
				"SELECT $marker_field FROM $table WHERE $marker_field LIKE %s LIMIT 1",
				$marker
			)
		);

		if ( ! $result ) {
			// Do not mess with tables when no items to delete.
			return false;
		}

		$queries = [
			'START TRANSACTION',
			"CREATE TABLE {$table}_copy LIKE $table",
			$wpdb->prepare(
				"INSERT INTO {$table}_copy
				SELECT *
					FROM $table p
					WHERE p.$marker_field NOT LIKE %s",
				$marker
			),
			"DROP TABLE $table",
			"RENAME TABLE {$table}_copy TO $table",
			'COMMIT',
		];

		ob_start();

		foreach ( $queries as $query ) {
			$result = $wpdb->query( $query );

			if ( false === $result ) {
				$result = $wpdb->query( 'ROLLBACK' );
				break;
			}
		}
		// phpcs:enable WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		// phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching

		// We will have some messages here if WP_DEBUG_DISPLAY is on.
		$error_message = ob_get_clean();

		if ( false === $result ) {
			wp_send_json_error(
				sprintf(
				// translators: 1: Error message.
					esc_html__( 'Error deleting generated items of type %1$s: %2$s.', 'kagg-generator' ),
					$item_handler->get_type(),
					$error_message . $wpdb->last_error
				)
			);
		}

		return true;
	}

	/**
	 * Generate items.
	 *
	 * @return void
	 */
	public function generate() {
		$this->generator->run();
	}

	/**
	 * Init options form fields.
	 */
	private function init_form_fields() {
		$this->form_fields = [
			'post_type'  => [
				'label'        => __( 'Item type', 'kagg-generator' ),
				'section'      => 'first_section',
				'type'         => 'select',
				'options'      => [
					'post'    => __( 'Post', 'kagg-generator' ),
					'page'    => __( 'Page', 'kagg-generator' ),
					'comment' => __( 'Comment', 'kagg-generator' ),
					'user'    => __( 'User', 'kagg-generator' ),
				],
				'placeholder'  => '',
				'helper'       => '',
				'supplemental' => '',
				'default'      => 'post',
			],
			'number'     => [
				'label'        => __( 'Number of items to generate', 'kagg-generator' ),
				'section'      => 'first_section',
				'type'         => 'number',
				'placeholder'  => '',
				'helper'       => '',
				'supplemental' => '',
				'default'      => '0',
			],
			'chunk_size' => [
				'label'        => __( 'Chunk size', 'kagg-generator' ),
				'section'      => 'first_section',
				'type'         => 'number',
				'placeholder'  => '',
				'helper'       => '',
				'supplemental' => __( 'How many items to generate in one ajax request.', 'kagg-generator' ),
				'default'      => 50 * 1000,
			],
			'sql'        => [
				'label'        => __( 'Download SQL', 'kagg-generator' ),
				'section'      => 'first_section',
				'type'         => 'checkbox',
				'placeholder'  => '',
				'helper'       => '',
				'supplemental' => __( 'When selected, the plugin does not write to the database. Instead, it forces downloading of a SQL file.', 'kagg-generator' ),
				'default'      => 'no',
			],
		];
	}

	/**
	 * Initialise Settings.
	 *
	 * Store all settings in a single database entry
	 * and make sure the $settings array is either the default
	 * or the settings stored in the database.
	 */
	private function init_settings() {
		$this->settings = get_option( self::OPTION_KEY, null );

		// If there are no settings defined, use defaults.
		if ( ! is_array( $this->settings ) ) {
			$form_fields    = $this->get_form_fields();
			$this->settings = array_merge(
				array_fill_keys( array_keys( $form_fields ), '' ),
				wp_list_pluck( $form_fields, 'default' )
			);
		}
	}

	/**
	 * Get the form fields after they are initialized.
	 *
	 * @return array of options
	 */
	private function get_form_fields(): array {
		if ( empty( $this->form_fields ) ) {
			$this->init_form_fields();
		}

		return array_map( [ $this, 'set_defaults' ], $this->form_fields );
	}

	/**
	 * Set default required properties for each field.
	 *
	 * @param array $field Field.
	 *
	 * @return array
	 */
	private function set_defaults( array $field ): array {
		if ( ! isset( $field['default'] ) ) {
			$field['default'] = '';
		}

		return $field;
	}

	/**
	 * Get a fields default value. Defaults to '' if not set.
	 *
	 * @param array $field Field.
	 *
	 * @return string|mixed
	 */
	private function get_field_default( array $field ) {
		return empty( $field['default'] ) ? '' : $field['default'];
	}

	/**
	 * Is current admin screen the plugin options screen.
	 *
	 * @return bool
	 */
	private function is_options_screen(): bool {
		if ( ! function_exists( 'get_current_screen' ) ) {
			return false;
		}

		$current_screen = get_current_screen();

		if ( ! $current_screen ) {
			return false;
		}

		return 'options' === $current_screen->id || 'tools_page_kagg-generator' === $current_screen->id;
	}
}
