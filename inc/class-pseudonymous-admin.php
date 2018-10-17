<?php
/**
 * Main file for Pseudonymous_Admin class.
 *
 * @package WordPress
 *
 * phpcs:disable WordPress.VIP.SuperGlobalInputUsage.AccessDetected
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * The primary class for Pseudonymous Admin.
 */
class Pseudonymous_Admin {
	/**
	 * The unique instance of the plugin.
	 *
	 * @var Pseudonymous_Admin
	 */
	private static $instance;

	/**
	 * Gets an instance of our plugin.
	 *
	 * @return Pseudonymous_Admin
	 */
	public static function get_instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	/**
	 * Initializes hooks for admin screen.
	 */
	public function init_hooks() {
		add_action( 'show_user_profile', array( $this, 'user_profile_fields' ) );
		add_action( 'edit_user_profile', array( $this, 'user_profile_fields' ) );
		add_action( 'personal_options_update', array( $this, 'save_user_profile_fields' ) );
		add_action( 'edit_user_profile_update', array( $this, 'save_user_profile_fields' ) );
	}

	/**
	 * Outputs user profile fields for the plugin.
	 *
	 * @param WP_User $user The user being edited.
	 */
	public function user_profile_fields( $user ) {
		printf( '<h3>%s</h3>', esc_html__( 'Pseudonymous Information', 'pseudonymous' ) );

		?>
		<table class="form-table">
			<tr>
				<th><label for="pseudonymous_user_login"><?php esc_html_e( 'Username' ); ?></label></th>
				<td>
					<input type="text" name="pseudonymous_user_login" id="pseudonymous_user_login" value="<?php echo esc_attr( get_user_meta( $user->ID, 'pseudonymous_user_login', true ) ); ?>" class="regular-text" /><br />
				</td>
			</tr>
			<tr>
			<th><label for="pseudonymous_user_nicename"><?php esc_html_e( 'Display name publicly as' ); ?></label></th>
				<td>
					<input type="text" name="pseudonymous_user_nicename" id="pseudonymous_user_nicename" value="<?php echo esc_attr( get_user_meta( $user->ID, 'pseudonymous_user_nicename', true ) ); ?>" class="regular-text" /><br />
				</td>
			</tr>
			<tr>
				<th><label for="pseudonymous_user_email"><?php esc_html_e( 'Email', 'pseudonymous' ); ?></label></th>
				<td>
					<input type="text" name="pseudonymous_user_email" id="pseudonymous_user_email" value="<?php echo esc_attr( get_user_meta( $user->ID, 'pseudonymous_user_email', true ) ); ?>" class="regular-text" /><br />
				</td>
			</tr>
		</table>
		<?php
		wp_nonce_field( 'edit_user_' . $user->ID, '_pseudonymous_nonce' );

	}

	/**
	 * Saves user profile fields for the plugin.
	 *
	 * @param Int $user_id The ID for the user being edited.
	 */
	public function save_user_profile_fields( $user_id ) {
		if ( ! current_user_can( 'edit_user', $user_id ) ) {
			return false;
		}

		if ( ! check_admin_referer( 'edit_user_' . $user_id, '_pseudonymous_nonce' ) ) {
			return false;
		}

		$user_login    = isset( $_POST['pseudonymous_user_login'] ) ? sanitize_key( wp_unslash( $_POST['pseudonymous_user_login'] ) ) : '';
		$user_nicename = isset( $_POST['pseudonymous_user_nicename'] ) ? sanitize_text_field( wp_unslash( $_POST['pseudonymous_user_nicename'] ) ) : '';
		$user_email    = isset( $_POST['pseudonymous_user_email'] ) ? sanitize_text_field( wp_unslash( $_POST['pseudonymous_user_email'] ) ) : '';

		update_user_meta( $user_id, 'pseudonymous_user_login', $user_login );
		update_user_meta( $user_id, 'pseudonymous_user_nicename', $user_nicename );
		update_user_meta( $user_id, 'pseudonymous_user_email', $user_email );
	}
}
