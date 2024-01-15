<?php
/**
 * Admin Notices.
 *
 * @package kagg/generator
 */

namespace KAGG\Generator;

/**
 * Class Admin_Notices
 */
class AdminNotices {

	/**
	 * Admin notices an array.
	 *
	 * @var array
	 */
	private $notices = [];

	/**
	 * AdminNotices constructor.
	 */
	public function __construct() {
		add_action( 'admin_notices', [ $this, 'show_notices' ] );
	}

	/**
	 * Add admin notice.
	 *
	 * @param string $message    Message to show.
	 * @param string $class_name Message class: notice notice-success notice-error notice-warning notice-info
	 *                           is-dismissible.
	 * @param array  $options    Notice options.
	 */
	public function add_notice( string $message, string $class_name = 'notice', array $options = [] ) {
		$this->notices[] = [
			'message' => $message,
			'class'   => $class_name,
			'options' => $options,
		];
	}

	/**
	 * Show all notices.
	 */
	public function show_notices() {
		foreach ( $this->notices as $notice ) {
			if ( ! $this->is_screen_allowed( $notice ) ) {
				continue;
			}

			?>
			<div class="<?php echo esc_attr( $notice['class'] ); ?>">
				<p>
					<?php echo wp_kses_post( $notice['message'] ); ?>
				</p>
			</div>
			<?php
		}
	}

	/**
	 * Whether the current admin screen allowed to show the notice.
	 *
	 * @param array $notice Notice.
	 *
	 * @return bool
	 */
	protected function is_screen_allowed( array $notice ): bool {
		$screen_ids = isset( $notice['options']['screen_ids'] ) ? (array) $notice['options']['screen_ids'] : null;
		if ( empty( $screen_ids ) ) {
			return true;
		}

		$current_screen = get_current_screen();

		foreach ( $screen_ids as $screen_id ) {
			if ( $current_screen && $screen_id === $current_screen->id ) {
				return true;
			}
		}

		return false;
	}
}
