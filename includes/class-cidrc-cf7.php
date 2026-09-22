<?php
/**
 * Contact Form 7 integration: hidden fields, mail tags and submission handling.
 *
 * @package ClickIdReferrerCaptureCf7
 */

defined( 'ABSPATH' ) || exit;

/**
 * Wires the plugin into Contact Form 7.
 */
class CIDRC_CF7 {

	/**
	 * Registers the Contact Form 7 hooks.
	 *
	 * @return void
	 */
	public function init() {
		add_filter( 'wpcf7_form_hidden_fields', array( $this, 'hidden_fields' ) );
		add_filter( 'wpcf7_special_mail_tags', array( $this, 'special_mail_tag' ), 10, 4 );
		add_filter( 'wpcf7_mail_components', array( $this, 'mail_components' ), 10, 3 );
		add_action( 'wpcf7_mail_sent', array( $this, 'mail_sent' ) );
	}

	/**
	 * Reports whether Contact Form 7 is active.
	 *
	 * @return bool True when Contact Form 7 is loaded.
	 */
	public static function is_active() {
		return defined( 'WPCF7_VERSION' ) || class_exists( 'WPCF7_ContactForm' );
	}

	/**
	 * Adds the empty hidden fields Contact Form 7 renders inside every form.
	 *
	 * The values are left empty on purpose. Filling them server side would bake one
	 * visitor's attribution into a cached page, so the front end script fills them.
	 *
	 * @param array $fields Existing hidden fields.
	 * @return array Hidden fields with the plugin fields added.
	 */
	public function hidden_fields( $fields ) {
		if ( ! is_array( $fields ) ) {
			$fields = array();
		}

		foreach ( array_keys( cidrc_hidden_fields() ) as $name ) {
			$fields[ $name ] = '';
		}

		return $fields;
	}

	/**
	 * Expands the [cidrc_summary] mail tag.
	 *
	 * @param string|null $output   Existing replacement, or null.
	 * @param string      $name     Mail tag name.
	 * @param bool        $html     Whether the mail body is HTML.
	 * @param mixed       $mail_tag Mail tag object.
	 * @return string|null Replacement text, or the untouched output.
	 */
	public function special_mail_tag( $output, $name, $html = false, $mail_tag = null ) {
		unset( $mail_tag );

		$name = ltrim( (string) $name, '_' );

		if ( 'cidrc_summary' !== $name ) {
			return $output;
		}

		$summary = $this->summary_from_submission();

		if ( $html ) {
			return nl2br( esc_html( $summary ) );
		}

		return $summary;
	}

	/**
	 * Appends the summary to every mail body when the setting is on.
	 *
	 * @param array $components   Mail components.
	 * @param mixed $contact_form Contact form object.
	 * @param mixed $mail         Mail object.
	 * @return array Mail components.
	 */
	public function mail_components( $components, $contact_form = null, $mail = null ) {
		unset( $contact_form, $mail );

		if ( ! cidrc_get_setting( 'auto_append_summary', 0 ) || ! is_array( $components ) ) {
			return $components;
		}

		if ( ! isset( $components['body'] ) ) {
			return $components;
		}

		$summary = $this->summary_from_submission();
		$heading = __( 'Attribution captured by Click ID & Referrer Capture', 'click-id-referrer-capture-cf7' );

		if ( false !== strpos( (string) $components['body'], '<' ) && false !== strpos( (string) $components['body'], '</' ) ) {
			$components['body'] .= "\n<hr />\n<p><strong>" . esc_html( $heading ) . "</strong><br />\n" . nl2br( esc_html( $summary ) ) . "</p>\n";

			return $components;
		}

		$components['body'] .= "\n\n-- " . $heading . " --\n" . $summary . "\n";

		return $components;
	}

	/**
	 * Builds the summary from the current Contact Form 7 submission.
	 *
	 * @return string Summary text.
	 */
	protected function summary_from_submission() {
		$posted = $this->get_posted_data();
		$values = $this->values_from_posted( $posted );

		return cidrc_build_summary( $values );
	}

	/**
	 * Returns the posted data for the current submission.
	 *
	 * @return array Posted data.
	 */
	protected function get_posted_data() {
		if ( ! class_exists( 'WPCF7_Submission' ) ) {
			return array();
		}

		$submission = WPCF7_Submission::get_instance();

		if ( ! $submission ) {
			return array();
		}

		$posted = $submission->get_posted_data();

		return is_array( $posted ) ? $posted : array();
	}

	/**
	 * Rebuilds a values array from the posted hidden fields.
	 *
	 * @param array $posted Posted data.
	 * @return array Values in the shape returned by cidrc_get_values().
	 */
	public function values_from_posted( array $posted ) {
		$values = array(
			'first'              => array(),
			'last'               => array(),
			'first_referrer'     => '',
			'first_landing_page' => '',
			'first_seen'         => '',
			'last_seen'          => '',
		);

		foreach ( cidrc_hidden_fields() as $field => $key ) {
			if ( ! isset( $posted[ $field ] ) ) {
				continue;
			}

			$raw = is_array( $posted[ $field ] ) ? reset( $posted[ $field ] ) : $posted[ $field ];

			if ( in_array( $key, array( 'first_referrer', 'first_landing_page' ), true ) ) {
				$raw = cidrc_clean_url_value( $raw );
			} elseif ( 'last_touch' === $key ) {
				$raw = is_scalar( $raw ) ? trim( (string) $raw ) : '';
				$raw = ( strlen( $raw ) > 4096 ) ? '' : $raw;
			} else {
				$raw = cidrc_clean_value( $raw, 500 );
			}

			if ( '' === $raw ) {
				continue;
			}

			if ( 'last_touch' === $key ) {
				$decoded = json_decode( $raw, true );

				if ( is_array( $decoded ) ) {
					foreach ( $decoded as $decoded_key => $decoded_value ) {
						$decoded_key = sanitize_key( $decoded_key );

						if ( in_array( $decoded_key, cidrc_captured_params(), true ) ) {
							$values['last'][ $decoded_key ] = cidrc_clean_value( $decoded_value, 500 );
						}
					}
				}

				continue;
			}

			if ( in_array( $key, array( 'first_referrer', 'first_landing_page', 'first_seen' ), true ) ) {
				$values[ $key ] = $raw;
				continue;
			}

			$values['first'][ $key ] = $raw;
		}

		return $values;
	}

	/**
	 * Logs the submission and fires the webhook after Contact Form 7 sends mail.
	 *
	 * @param mixed $contact_form Contact form object.
	 * @return void
	 */
	public function mail_sent( $contact_form ) {
		$posted = $this->get_posted_data();
		$values = $this->values_from_posted( $posted );

		$form_id    = is_object( $contact_form ) && method_exists( $contact_form, 'id' ) ? absint( $contact_form->id() ) : 0;
		$form_title = is_object( $contact_form ) && method_exists( $contact_form, 'title' ) ? sanitize_text_field( $contact_form->title() ) : '';

		$identity   = $this->find_identity( $contact_form, $posted );
		$conversion = CIDRC_Settings::conversion_for_form( $form_id );
		$first      = $values['first'];
		$last       = $values['last'];

		$pick = static function ( $key ) use ( $first, $last ) {
			if ( ! empty( $first[ $key ] ) ) {
				return $first[ $key ];
			}

			return ! empty( $last[ $key ] ) ? $last[ $key ] : '';
		};

		$row = array(
			'created_at'       => gmdate( 'Y-m-d H:i:s' ),
			'form_id'          => $form_id,
			'form_title'       => $form_title,
			'gclid'            => $pick( 'gclid' ),
			'gbraid'           => $pick( 'gbraid' ),
			'wbraid'           => $pick( 'wbraid' ),
			'msclkid'          => $pick( 'msclkid' ),
			'fbclid'           => $pick( 'fbclid' ),
			'utm_source'       => $pick( 'utm_source' ),
			'utm_medium'       => $pick( 'utm_medium' ),
			'utm_campaign'     => $pick( 'utm_campaign' ),
			'landing_page'     => $values['first_landing_page'],
			'referrer'         => $values['first_referrer'],
			'email_hash'       => cidrc_hash_email( $identity['email'] ),
			'phone_hash'       => cidrc_hash_phone( $identity['phone'] ),
			'conversion_name'  => $conversion['name'],
			'conversion_value' => $conversion['value'],
			'currency'         => $conversion['currency'],
		);

		/**
		 * Filters whether a submission is written to the log.
		 *
		 * @param bool  $should_log Whether to log this submission.
		 * @param array $row        Row about to be written.
		 * @param mixed $contact_form Contact form object.
		 */
		$should_log = (bool) apply_filters( 'cidrc_should_log', (bool) cidrc_get_setting( 'keep_log', 1 ), $row, $contact_form );

		if ( $should_log ) {
			CIDRC_Log::insert( $row );
		}

		$webhook = new CIDRC_Webhook();
		$webhook->send( $row, $identity );
	}

	/**
	 * Finds the submitted email address and telephone number.
	 *
	 * @param mixed $contact_form Contact form object.
	 * @param array $posted       Posted data.
	 * @return array Array with the email and phone keys.
	 */
	protected function find_identity( $contact_form, array $posted ) {
		$identity = array(
			'email' => '',
			'phone' => '',
		);

		$map = array(
			'email' => 'email',
			'tel'   => 'phone',
		);

		if ( is_object( $contact_form ) && method_exists( $contact_form, 'scan_form_tags' ) ) {
			foreach ( $map as $basetype => $target ) {
				$tags = $contact_form->scan_form_tags( array( 'basetype' => $basetype ) );

				if ( ! is_array( $tags ) ) {
					continue;
				}

				foreach ( $tags as $tag ) {
					$name = is_object( $tag ) && isset( $tag->name ) ? (string) $tag->name : '';

					if ( '' === $name || empty( $posted[ $name ] ) ) {
						continue;
					}

					$value = is_array( $posted[ $name ] ) ? reset( $posted[ $name ] ) : $posted[ $name ];

					$identity[ $target ] = cidrc_clean_value( $value, 200 );

					break;
				}
			}
		}

		if ( '' === $identity['email'] ) {
			foreach ( $posted as $key => $value ) {
				if ( 0 === strpos( (string) $key, 'cidrc_' ) ) {
					continue;
				}

				$value = is_array( $value ) ? reset( $value ) : $value;

				if ( is_string( $value ) && is_email( trim( $value ) ) ) {
					$identity['email'] = cidrc_clean_value( $value, 200 );
					break;
				}
			}
		}

		return $identity;
	}
}
