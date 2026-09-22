<?php
/**
 * Admin page: settings tab and submissions tab.
 *
 * @package ClickIdReferrerCaptureCf7
 */

defined( 'ABSPATH' ) || exit;

/**
 * Builds the Settings screen under Settings, Click ID Capture.
 */
class CIDRC_Admin extends CIDRC_Admin_Fields {

	/**
	 * Menu slug.
	 *
	 * @var string
	 */
	const SLUG = 'cidrc';

	/**
	 * Registers the admin hooks.
	 *
	 * @return void
	 */
	public function init() {
		add_action( 'admin_menu', array( $this, 'add_page' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue' ) );
		add_action( 'admin_notices', array( $this, 'cf7_notice' ) );
		add_filter( 'plugin_action_links_' . CIDRC_BASENAME, array( $this, 'action_links' ) );
	}

	/**
	 * Adds the settings page.
	 *
	 * @return void
	 */
	public function add_page() {
		add_options_page(
			__( 'Click ID Capture', 'click-id-referrer-capture-cf7' ),
			__( 'Click ID Capture', 'click-id-referrer-capture-cf7' ),
			'manage_options',
			self::SLUG,
			array( $this, 'render' )
		);
	}

	/**
	 * Adds a settings link on the plugins screen.
	 *
	 * @param array $links Existing links.
	 * @return array Links.
	 */
	public function action_links( $links ) {
		if ( ! is_array( $links ) ) {
			$links = array();
		}

		$url = admin_url( 'options-general.php?page=' . self::SLUG );

		array_unshift( $links, '<a href="' . esc_url( $url ) . '">' . esc_html__( 'Settings', 'click-id-referrer-capture-cf7' ) . '</a>' );

		return $links;
	}

	/**
	 * Loads the admin stylesheet on the plugin screen only.
	 *
	 * @param string $hook Current admin page hook.
	 * @return void
	 */
	public function enqueue( $hook ) {
		if ( 'settings_page_' . self::SLUG !== $hook ) {
			return;
		}

		wp_enqueue_style( 'cidrc-admin', CIDRC_URL . 'assets/css/cidrc-admin.css', array(), CIDRC_VERSION );
	}

	/**
	 * Shows a notice when Contact Form 7 is not active.
	 *
	 * @return void
	 */
	public function cf7_notice() {
		if ( CIDRC_CF7::is_active() || ! current_user_can( 'manage_options' ) ) {
			return;
		}

		$screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;

		if ( $screen && ! in_array( $screen->id, array( 'plugins', 'settings_page_' . self::SLUG ), true ) ) {
			return;
		}

		echo '<div class="notice notice-warning"><p>';
		echo esc_html__( 'Click ID & Referrer Capture is running and is storing click identifiers in a first-party cookie, but Contact Form 7 is not active, so no form fields are being filled and no submissions are being logged.', 'click-id-referrer-capture-cf7' );
		echo '</p></div>';
	}

	/**
	 * Reads the current filter arguments from the request.
	 *
	 * @return array Filter arguments.
	 */
	protected function current_filters() {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read only list filtering.
		$form_id = isset( $_GET['cidrc_form_id'] ) ? absint( wp_unslash( $_GET['cidrc_form_id'] ) ) : 0;
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read only list filtering.
		$from = isset( $_GET['cidrc_date_from'] ) ? sanitize_text_field( wp_unslash( $_GET['cidrc_date_from'] ) ) : '';
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read only list filtering.
		$to = isset( $_GET['cidrc_date_to'] ) ? sanitize_text_field( wp_unslash( $_GET['cidrc_date_to'] ) ) : '';

		return array(
			'form_id'   => $form_id,
			'date_from' => CIDRC_Export::clean_date( $from ),
			'date_to'   => CIDRC_Export::clean_date( $to ),
		);
	}

	/**
	 * Renders the page.
	 *
	 * @return void
	 */
	public function render() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You do not have permission to view this page.', 'click-id-referrer-capture-cf7' ) );
		}

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Tab selection only.
		$tab = isset( $_GET['tab'] ) ? sanitize_key( wp_unslash( $_GET['tab'] ) ) : 'settings';
		$tab = in_array( $tab, array( 'settings', 'submissions' ), true ) ? $tab : 'settings';

		echo '<div class="wrap cidrc-wrap">';
		echo '<h1>' . esc_html__( 'Click ID & Referrer Capture', 'click-id-referrer-capture-cf7' ) . '</h1>';
		echo '<h2 class="nav-tab-wrapper">';

		foreach ( array(
			'settings'    => __( 'Settings', 'click-id-referrer-capture-cf7' ),
			'submissions' => __( 'Submissions', 'click-id-referrer-capture-cf7' ),
		) as $slug => $label ) {
			$class = ( $slug === $tab ) ? 'nav-tab nav-tab-active' : 'nav-tab';
			$url   = admin_url( 'options-general.php?page=' . self::SLUG . '&tab=' . $slug );

			echo '<a class="' . esc_attr( $class ) . '" href="' . esc_url( $url ) . '">' . esc_html( $label ) . '</a>';
		}

		echo '</h2>';

		if ( 'submissions' === $tab ) {
			$this->render_submissions();
		} else {
			$this->render_settings();
		}

		echo '</div>';
	}

	/**
	 * Renders the settings form.
	 *
	 * @return void
	 */
	protected function render_settings() {
		$settings = CIDRC_Settings::get_all();
		$option   = CIDRC_Settings::OPTION;

		echo '<form method="post" action="' . esc_url( admin_url( 'options.php' ) ) . '">';

		settings_fields( CIDRC_Settings::GROUP );

		echo '<h2>' . esc_html__( 'Capture and storage', 'click-id-referrer-capture-cf7' ) . '</h2>';
		echo '<table class="form-table" role="presentation"><tbody>';

		$this->row_select(
			$option . '[storage_mode]',
			__( 'Storage mode', 'click-id-referrer-capture-cf7' ),
			array(
				'always'  => __( 'Always store', 'click-id-referrer-capture-cf7' ),
				'consent' => __( 'Wait for consent', 'click-id-referrer-capture-cf7' ),
			),
			$settings['storage_mode'],
			__( 'In wait for consent mode the values are held in the browser session only, and the cookie is written after consent is granted.', 'click-id-referrer-capture-cf7' )
		);

		$this->row_number( $option . '[cookie_days]', __( 'Cookie lifetime in days', 'click-id-referrer-capture-cf7' ), $settings['cookie_days'], __( 'Between 1 and 400 days. Google Ads offline conversions accept a click up to 90 days old by default.', 'click-id-referrer-capture-cf7' ), 1, 400 );

		$this->row_text( $option . '[consent_cookie_name]', __( 'Consent cookie name', 'click-id-referrer-capture-cf7' ), $settings['consent_cookie_name'], __( 'Optional. Examples: cookieyes-consent for CookieYes, CookieConsent for Cookiebot, cmplz_marketing for Complianz.', 'click-id-referrer-capture-cf7' ) );

		$this->row_text( $option . '[consent_cookie_value]', __( 'Consent cookie contains', 'click-id-referrer-capture-cf7' ), $settings['consent_cookie_value'], __( 'Optional. The cookie is written once the consent cookie contains this text. Examples: advertisement:yes for CookieYes, marketing:true for Cookiebot, allow for Complianz.', 'click-id-referrer-capture-cf7' ) );

		echo '</tbody></table>';

		echo '<h2>' . esc_html__( 'Contact Form 7', 'click-id-referrer-capture-cf7' ) . '</h2>';
		echo '<table class="form-table" role="presentation"><tbody>';

		$this->row_checkbox( $option . '[auto_append_summary]', __( 'Append attribution summary to every Contact Form 7 mail body automatically', 'click-id-referrer-capture-cf7' ), $settings['auto_append_summary'], __( 'Leave this off if you prefer to place the [cidrc_summary] mail tag yourself.', 'click-id-referrer-capture-cf7' ) );

		$this->row_checkbox( $option . '[keep_log]', __( 'Keep a submission log', 'click-id-referrer-capture-cf7' ), $settings['keep_log'], __( 'Stores one row per sent form, with hashed contact details, so that you can export offline conversions later.', 'click-id-referrer-capture-cf7' ) );

		$this->row_number( $option . '[retention_days]', __( 'Log retention in days', 'click-id-referrer-capture-cf7' ), $settings['retention_days'], __( 'Rows older than this are deleted once a day. Set 0 to keep rows for ever.', 'click-id-referrer-capture-cf7' ), 0, 3650 );

		echo '</tbody></table>';

		echo '<h2>' . esc_html__( 'Offline conversions', 'click-id-referrer-capture-cf7' ) . '</h2>';
		echo '<table class="form-table" role="presentation"><tbody>';

		$this->row_text( $option . '[conversion_name]', __( 'Default conversion name', 'click-id-referrer-capture-cf7' ), $settings['conversion_name'], __( 'This has to match the conversion action name in Google Ads or Microsoft Advertising exactly.', 'click-id-referrer-capture-cf7' ) );

		$this->row_text( $option . '[conversion_value]', __( 'Default conversion value', 'click-id-referrer-capture-cf7' ), $settings['conversion_value'], __( 'A number, for example 250. Use 0 if you do not report a value.', 'click-id-referrer-capture-cf7' ) );

		$this->row_text( $option . '[currency]', __( 'Currency code', 'click-id-referrer-capture-cf7' ), $settings['currency'], __( 'Three letters, for example GBP, EUR or USD.', 'click-id-referrer-capture-cf7' ) );

		$this->row_textarea( $option . '[form_overrides]', __( 'Per form overrides', 'click-id-referrer-capture-cf7' ), $settings['form_overrides'], __( 'One line per form, in the format: form ID | conversion name | conversion value. The value is optional.', 'click-id-referrer-capture-cf7' ) );

		echo '</tbody></table>';

		echo '<h2>' . esc_html__( 'Webhook', 'click-id-referrer-capture-cf7' ) . '</h2>';
		echo '<table class="form-table" role="presentation"><tbody>';

		$this->row_text( $option . '[webhook_url]', __( 'Webhook URL', 'click-id-referrer-capture-cf7' ), $settings['webhook_url'], __( 'Optional. Each sent form is posted as JSON to this address, for example an n8n, Make or Zapier webhook.', 'click-id-referrer-capture-cf7' ) );

		$this->row_text( $option . '[webhook_secret]', __( 'Webhook secret', 'click-id-referrer-capture-cf7' ), $settings['webhook_secret'], __( 'Optional. Sent as the X-CIDRC-Secret request header so that your endpoint can reject anything else.', 'click-id-referrer-capture-cf7' ) );

		$this->row_checkbox( $option . '[webhook_raw_email]', __( 'Include the raw email address in the webhook', 'click-id-referrer-capture-cf7' ), $settings['webhook_raw_email'], __( 'Off by default. Only turn this on when your privacy notice covers sending personal data to that endpoint, and only when the endpoint is under your control.', 'click-id-referrer-capture-cf7' ) );

		echo '</tbody></table>';

		submit_button();

		echo '</form>';

		$this->render_help();
	}

	/**
	 * Renders the short reference block below the settings.
	 *
	 * @return void
	 */
	protected function render_help() {
		echo '<h2>' . esc_html__( 'Reference', 'click-id-referrer-capture-cf7' ) . '</h2>';
		echo '<p>' . esc_html__( 'Hidden fields added to every Contact Form 7 form:', 'click-id-referrer-capture-cf7' ) . '</p>';
		echo '<p>';

		foreach ( array_keys( cidrc_hidden_fields() ) as $field_name ) {
			echo '<code>' . esc_html( $field_name ) . '</code> ';
		}

		echo '</p>';
		echo '<p>' . esc_html__( 'Mail tag: [cidrc_summary]. Shortcode for testing: [cidrc_debug]. Javascript API: window.cidrc.getValues() and window.cidrc.consentGranted().', 'click-id-referrer-capture-cf7' ) . '</p>';
	}

	/**
	 * Renders the submissions tab.
	 *
	 * @return void
	 */
	protected function render_submissions() {
		$filters = $this->current_filters();
		$forms   = CIDRC_Log::get_forms();

		echo '<form method="get" action="' . esc_url( admin_url( 'options-general.php' ) ) . '" class="cidrc-filters">';
		echo '<input type="hidden" name="page" value="' . esc_attr( self::SLUG ) . '" />';
		echo '<input type="hidden" name="tab" value="submissions" />';

		echo '<label for="cidrc_date_from">' . esc_html__( 'From', 'click-id-referrer-capture-cf7' ) . '</label> ';
		echo '<input type="date" id="cidrc_date_from" name="cidrc_date_from" value="' . esc_attr( $filters['date_from'] ) . '" /> ';

		echo '<label for="cidrc_date_to">' . esc_html__( 'To', 'click-id-referrer-capture-cf7' ) . '</label> ';
		echo '<input type="date" id="cidrc_date_to" name="cidrc_date_to" value="' . esc_attr( $filters['date_to'] ) . '" /> ';

		echo '<label for="cidrc_form_id">' . esc_html__( 'Form', 'click-id-referrer-capture-cf7' ) . '</label> ';
		echo '<select id="cidrc_form_id" name="cidrc_form_id">';
		echo '<option value="0">' . esc_html__( 'All forms', 'click-id-referrer-capture-cf7' ) . '</option>';

		foreach ( $forms as $form_id => $form_title ) {
			echo '<option value="' . esc_attr( (string) $form_id ) . '"' . selected( $filters['form_id'], $form_id, false ) . '>' . esc_html( '' === $form_title ? (string) $form_id : $form_title ) . '</option>';
		}

		echo '</select> ';

		submit_button( __( 'Filter', 'click-id-referrer-capture-cf7' ), 'secondary', '', false );

		echo '</form>';

		echo '<p class="cidrc-exports">';
		echo '<a class="button button-primary" href="' . esc_url( CIDRC_Export::url( 'cidrc_export_google', $filters ) ) . '">' . esc_html__( 'Export Google Ads offline conversions CSV', 'click-id-referrer-capture-cf7' ) . '</a> ';
		echo '<a class="button" href="' . esc_url( CIDRC_Export::url( 'cidrc_export_microsoft', $filters ) ) . '">' . esc_html__( 'Export Microsoft Ads offline conversions CSV', 'click-id-referrer-capture-cf7' ) . '</a> ';
		echo '<a class="button" href="' . esc_url( CIDRC_Export::url( 'cidrc_export_full', $filters ) ) . '">' . esc_html__( 'Full log CSV', 'click-id-referrer-capture-cf7' ) . '</a>';
		echo '</p>';

		require_once CIDRC_DIR . 'includes/class-cidrc-list-table.php';

		$table = new CIDRC_List_Table( $filters );
		$table->prepare_items();
		$table->display();
	}
}
