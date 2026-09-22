<?php
/**
 * Optional outgoing webhook for automation tools such as n8n, Make or Zapier.
 *
 * @package ClickIdReferrerCaptureCf7
 */

defined( 'ABSPATH' ) || exit;

/**
 * Posts submission data to a configured URL.
 */
class CIDRC_Webhook {

	/**
	 * Sends the payload when a webhook URL is configured.
	 *
	 * @param array $row      Submission row.
	 * @param array $identity Array with the email and phone keys.
	 * @return bool True when a request was dispatched.
	 */
	public function send( array $row, array $identity = array() ) {
		$url = (string) cidrc_get_setting( 'webhook_url', '' );

		if ( '' === $url ) {
			return false;
		}

		$payload = $this->build_payload( $row, $identity );

		if ( empty( $payload ) ) {
			return false;
		}

		$headers = array( 'Content-Type' => 'application/json; charset=utf-8' );
		$secret  = (string) cidrc_get_setting( 'webhook_secret', '' );

		if ( '' !== $secret ) {
			$headers['X-CIDRC-Secret'] = $secret;
		}

		$response = wp_remote_post(
			$url,
			array(
				'timeout'  => 5,
				'blocking' => false,
				'headers'  => $headers,
				'body'     => wp_json_encode( $payload ),
			)
		);

		return ! is_wp_error( $response );
	}

	/**
	 * Builds the JSON payload.
	 *
	 * @param array $row      Submission row.
	 * @param array $identity Array with the email and phone keys.
	 * @return array Payload.
	 */
	protected function build_payload( array $row, array $identity = array() ) {
		$payload = array(
			'source'           => 'click-id-referrer-capture-cf7',
			'version'          => CIDRC_VERSION,
			'site_url'         => home_url( '/' ),
			'submitted_at'     => isset( $row['created_at'] ) ? $row['created_at'] . 'Z' : gmdate( 'Y-m-d\TH:i:s\Z' ),
			'form_id'          => isset( $row['form_id'] ) ? (int) $row['form_id'] : 0,
			'form_title'       => isset( $row['form_title'] ) ? (string) $row['form_title'] : '',
			'gclid'            => isset( $row['gclid'] ) ? (string) $row['gclid'] : '',
			'gbraid'           => isset( $row['gbraid'] ) ? (string) $row['gbraid'] : '',
			'wbraid'           => isset( $row['wbraid'] ) ? (string) $row['wbraid'] : '',
			'msclkid'          => isset( $row['msclkid'] ) ? (string) $row['msclkid'] : '',
			'fbclid'           => isset( $row['fbclid'] ) ? (string) $row['fbclid'] : '',
			'utm_source'       => isset( $row['utm_source'] ) ? (string) $row['utm_source'] : '',
			'utm_medium'       => isset( $row['utm_medium'] ) ? (string) $row['utm_medium'] : '',
			'utm_campaign'     => isset( $row['utm_campaign'] ) ? (string) $row['utm_campaign'] : '',
			'landing_page'     => isset( $row['landing_page'] ) ? (string) $row['landing_page'] : '',
			'referrer'         => isset( $row['referrer'] ) ? (string) $row['referrer'] : '',
			'email_hash'       => isset( $row['email_hash'] ) ? (string) $row['email_hash'] : '',
			'phone_hash'       => isset( $row['phone_hash'] ) ? (string) $row['phone_hash'] : '',
			'conversion_name'  => isset( $row['conversion_name'] ) ? (string) $row['conversion_name'] : '',
			'conversion_value' => isset( $row['conversion_value'] ) ? (float) $row['conversion_value'] : 0,
			'currency'         => isset( $row['currency'] ) ? (string) $row['currency'] : '',
		);

		if ( cidrc_get_setting( 'webhook_raw_email', 0 ) && ! empty( $identity['email'] ) ) {
			$payload['email'] = (string) $identity['email'];
		}

		/**
		 * Filters the webhook payload before it is sent.
		 *
		 * @param array $payload  Payload.
		 * @param array $row      Submission row.
		 * @param array $identity Array with the email and phone keys.
		 */
		$payload = apply_filters( 'cidrc_webhook_payload', $payload, $row, $identity );

		return is_array( $payload ) ? $payload : array();
	}
}
