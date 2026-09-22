<?php
/**
 * Plain PHP standards checker for the plugin. It does not load WordPress.
 *
 * Usage: php test/check-standards.php
 *
 * Checks performed on every PHP file in the plugin:
 *  1. echo or print of a variable without an escaping function on the same line.
 *  2. Superglobal reads that are not wrapped in a sanitiser on the same line.
 *  3. $wpdb->query / get_results / get_var / get_row without prepare().
 *  4. Translation function calls without the plugin text domain.
 *  5. Missing direct file access guard.
 *  6. Files longer than 400 lines.
 *
 * @package ClickIdReferrerCaptureCf7
 */

$root        = dirname( __DIR__ );
$text_domain = 'click-id-referrer-capture-cf7';
$findings    = array();

/**
 * Returns every PHP file in the plugin, excluding the test directory.
 *
 * @param string $dir Root directory.
 * @return array List of file paths.
 */
function cidrc_check_files( $dir ) {
	$files    = array();
	$iterator = new RecursiveIteratorIterator( new RecursiveDirectoryIterator( $dir, FilesystemIterator::SKIP_DOTS ) );

	foreach ( $iterator as $file ) {
		$path = $file->getPathname();

		if ( 'php' !== strtolower( pathinfo( $path, PATHINFO_EXTENSION ) ) ) {
			continue;
		}

		if ( false !== strpos( $path, DIRECTORY_SEPARATOR . 'test' . DIRECTORY_SEPARATOR ) ) {
			continue;
		}

		if ( false !== strpos( $path, DIRECTORY_SEPARATOR . 'dist' . DIRECTORY_SEPARATOR ) ) {
			continue;
		}

		$files[] = $path;
	}

	sort( $files );

	return $files;
}

/**
 * Records a finding.
 *
 * @param array  $findings Findings, by reference.
 * @param string $rule     Rule name.
 * @param string $file     File path.
 * @param int    $line     Line number.
 * @param string $text     Offending line.
 * @return void
 */
function cidrc_add( &$findings, $rule, $file, $line, $text ) {
	$findings[] = array(
		'rule' => $rule,
		'file' => $file,
		'line' => $line,
		'text' => trim( $text ),
	);
}

$escapers  = '/(\besc_[a-z_]+\s*\(|\bwp_kses|\babsint\s*\(|\bintval\s*\(|\bnumber_format|\bchecked\s*\(|\bselected\s*\(|\bsubmit_button\s*\(|\bwp_json_encode\s*\(|_esc_)/';
$sanitisers = '/(\bsanitize_[a-z_]+\s*\(|\bwp_unslash\s*\(|\babsint\s*\(|\bintval\s*\(|\besc_url_raw\s*\(|\bwp_kses)/';
$i18n      = '/\b(__|_e|_x|_ex|_n|_nx|esc_html__|esc_html_e|esc_html_x|esc_attr__|esc_attr_e|esc_attr_x)\s*\(/';
$db_calls  = '/\$wpdb->(query|get_results|get_var|get_row|get_col)\s*\(/';

foreach ( cidrc_check_files( $root ) as $file ) {
	$relative = ltrim( str_replace( $root, '', $file ), DIRECTORY_SEPARATOR );
	$contents = file_get_contents( $file );
	$lines    = preg_split( '/\r\n|\r|\n/', $contents );

	if ( false === strpos( $contents, "defined( 'ABSPATH' ) || exit" ) && false === strpos( $contents, "defined( 'WP_UNINSTALL_PLUGIN' ) || exit" ) ) {
		cidrc_add( $findings, 'no-direct-access-guard', $relative, 1, 'File has no direct access guard.' );
	}

	if ( count( $lines ) > 400 ) {
		cidrc_add( $findings, 'file-too-long', $relative, count( $lines ), count( $lines ) . ' lines, the limit is 400.' );
	}

	foreach ( $lines as $index => $line ) {
		$number  = $index + 1;
		$trimmed = ltrim( $line );

		if ( 0 === strpos( $trimmed, '*' ) || 0 === strpos( $trimmed, '//' ) || 0 === strpos( $trimmed, '/*' ) ) {
			continue;
		}

		// Rule 1: unescaped output.
		if ( preg_match( '/(^|[\s;{}])(echo|print)\s/', $line ) && false !== strpos( $line, '$' ) ) {
			if ( ! preg_match( $escapers, $line ) ) {
				cidrc_add( $findings, 'unescaped-output', $relative, $number, $line );
			}
		}

		// Rule 2: unsanitised superglobal reads.
		if ( preg_match( '/\$_(GET|POST|REQUEST|COOKIE|SERVER)\s*\[/', $line ) ) {
			$only_check = preg_match( '/^\s*(if|\}?\s*elseif)?\s*\(?\s*(!\s*)?(isset|empty)\s*\(\s*\$_(GET|POST|REQUEST|COOKIE|SERVER)\s*\[[^\]]+\]\s*\)\s*\)?\s*[{:]?\s*$/', $line );

			if ( ! $only_check && ! preg_match( $sanitisers, $line ) ) {
				cidrc_add( $findings, 'unsanitised-input', $relative, $number, $line );
			}
		}

		// Rule 3: database calls without prepare.
		if ( preg_match( $db_calls, $line ) && false === strpos( $line, '$wpdb->prepare' ) ) {
			cidrc_add( $findings, 'query-without-prepare', $relative, $number, $line );
		}

		// Rule 4: translation calls without the text domain.
		if ( preg_match( $i18n, $line ) && false === strpos( $line, $text_domain ) ) {
			cidrc_add( $findings, 'missing-text-domain', $relative, $number, $line );
		}
	}
}

if ( empty( $findings ) ) {
	echo "check-standards: no findings. " . count( cidrc_check_files( $root ) ) . " PHP files checked.\n";
	exit( 0 );
}

echo "check-standards: " . count( $findings ) . " finding(s).\n\n";

foreach ( $findings as $finding ) {
	printf( "[%s] %s:%d\n    %s\n", $finding['rule'], $finding['file'], $finding['line'], $finding['text'] );
}

exit( 1 );
