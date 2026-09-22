<?php
/**
 * Reusable settings field renderers for the admin page.
 *
 * @package ClickIdReferrerCaptureCf7
 */

defined( 'ABSPATH' ) || exit;

/**
 * Renders the individual rows of the settings form table.
 */
class CIDRC_Admin_Fields {

	/**
	 * Renders a text input row.
	 *
	 * @param string $name  Field name.
	 * @param string $label Field label.
	 * @param string $value Current value.
	 * @param string $help  Help text.
	 * @return void
	 */
	protected function row_text( $name, $label, $value, $help = '' ) {
		$id = $this->field_id( $name );

		echo '<tr><th scope="row"><label for="' . esc_attr( $id ) . '">' . esc_html( $label ) . '</label></th><td>';
		echo '<input type="text" class="regular-text" id="' . esc_attr( $id ) . '" name="' . esc_attr( $name ) . '" value="' . esc_attr( (string) $value ) . '" />';
		$this->help( $help );
		echo '</td></tr>';
	}

	/**
	 * Renders a number input row.
	 *
	 * @param string $name  Field name.
	 * @param string $label Field label.
	 * @param int    $value Current value.
	 * @param string $help  Help text.
	 * @param int    $min   Minimum value.
	 * @param int    $max   Maximum value.
	 * @return void
	 */
	protected function row_number( $name, $label, $value, $help = '', $min = 0, $max = 400 ) {
		$id = $this->field_id( $name );

		echo '<tr><th scope="row"><label for="' . esc_attr( $id ) . '">' . esc_html( $label ) . '</label></th><td>';
		echo '<input type="number" class="small-text" id="' . esc_attr( $id ) . '" name="' . esc_attr( $name ) . '" value="' . esc_attr( (string) (int) $value ) . '" min="' . esc_attr( (string) (int) $min ) . '" max="' . esc_attr( (string) (int) $max ) . '" step="1" />';
		$this->help( $help );
		echo '</td></tr>';
	}

	/**
	 * Renders a checkbox row.
	 *
	 * @param string $name  Field name.
	 * @param string $label Field label.
	 * @param mixed  $value Current value.
	 * @param string $help  Help text.
	 * @return void
	 */
	protected function row_checkbox( $name, $label, $value, $help = '' ) {
		$id = $this->field_id( $name );

		echo '<tr><th scope="row">' . esc_html( $label ) . '</th><td>';
		echo '<label for="' . esc_attr( $id ) . '"><input type="checkbox" id="' . esc_attr( $id ) . '" name="' . esc_attr( $name ) . '" value="1"' . checked( 1, (int) $value, false ) . ' /> ';
		echo esc_html__( 'Enabled', 'click-id-referrer-capture-cf7' ) . '</label>';
		$this->help( $help );
		echo '</td></tr>';
	}

	/**
	 * Renders a select row.
	 *
	 * @param string $name    Field name.
	 * @param string $label   Field label.
	 * @param array  $choices Choices.
	 * @param string $value   Current value.
	 * @param string $help    Help text.
	 * @return void
	 */
	protected function row_select( $name, $label, array $choices, $value, $help = '' ) {
		$id = $this->field_id( $name );

		echo '<tr><th scope="row"><label for="' . esc_attr( $id ) . '">' . esc_html( $label ) . '</label></th><td>';
		echo '<select id="' . esc_attr( $id ) . '" name="' . esc_attr( $name ) . '">';

		foreach ( $choices as $choice_value => $choice_label ) {
			echo '<option value="' . esc_attr( (string) $choice_value ) . '"' . selected( $value, $choice_value, false ) . '>' . esc_html( $choice_label ) . '</option>';
		}

		echo '</select>';
		$this->help( $help );
		echo '</td></tr>';
	}

	/**
	 * Renders a textarea row.
	 *
	 * @param string $name  Field name.
	 * @param string $label Field label.
	 * @param string $value Current value.
	 * @param string $help  Help text.
	 * @return void
	 */
	protected function row_textarea( $name, $label, $value, $help = '' ) {
		$id = $this->field_id( $name );

		echo '<tr><th scope="row"><label for="' . esc_attr( $id ) . '">' . esc_html( $label ) . '</label></th><td>';
		echo '<textarea class="large-text code" rows="4" id="' . esc_attr( $id ) . '" name="' . esc_attr( $name ) . '">' . esc_textarea( (string) $value ) . '</textarea>';
		$this->help( $help );
		echo '</td></tr>';
	}

	/**
	 * Renders help text.
	 *
	 * @param string $help Help text.
	 * @return void
	 */
	protected function help( $help ) {
		if ( '' === $help ) {
			return;
		}

		echo '<p class="description">' . esc_html( $help ) . '</p>';
	}

	/**
	 * Builds a DOM identifier from a field name.
	 *
	 * @param string $name Field name.
	 * @return string Identifier.
	 */
	protected function field_id( $name ) {
		return str_replace( array( '[', ']' ), array( '_', '' ), $name );
	}
}
