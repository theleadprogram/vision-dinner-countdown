<?php
/**
 * Configuration: the Monday.com token, board and column IDs, and the
 * staging write guard.
 *
 * Board and column IDs are not secrets. They come from dinner.json, a copy of
 * dinners/YYYY-MM.json from the vision-dinner-countdown repo that build.sh
 * places in the plugin folder, so the repo stays the one source of truth.
 *
 * The write guard follows theleadprogram/lead-monday-integration: writes are
 * refused anywhere that is not production, so a staging copy of leadcma.org
 * can never register test guests on the live boards.
 *
 * @package LEAD\VisionDinner
 */

namespace LEAD\VisionDinner\Config;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Look up a secret: wp-config.php constant first, then the environment.
 *
 * getenv() returns false (not null) when a variable is unset, so the type is
 * checked explicitly rather than with ??.
 *
 * @param string $name    Name to look up.
 * @param string $default Returned when nothing is found.
 * @return string
 */
function get_secret( $name, $default = '' ) {
	if ( defined( $name ) ) {
		$value = constant( $name );
		if ( is_string( $value ) && '' !== $value ) {
			return $value;
		}
	}

	$value = getenv( $name );
	if ( is_string( $value ) && '' !== $value ) {
		return $value;
	}

	foreach ( array( $_ENV, $_SERVER ) as $bag ) {
		if ( isset( $bag[ $name ] ) && is_string( $bag[ $name ] ) && '' !== $bag[ $name ] ) {
			return $bag[ $name ];
		}
	}

	return $default;
}

/**
 * The Monday.com API token. Set it in wp-config.php:
 *
 *     define( 'LEAD_MONDAY_TOKEN', 'the-actual-token' );
 *
 * @return string Empty when not configured.
 */
function token() {
	return get_secret( 'LEAD_MONDAY_TOKEN' );
}

/**
 * @return string
 */
function api_url() {
	return 'https://api.monday.com/v2';
}

/**
 * Pinned so Monday's releases cannot silently change responses. Filterable
 * for when this version is retired.
 *
 * @return string
 */
function api_version() {
	return (string) apply_filters( 'lead_vd_monday_api_version', '2025-07' );
}

/**
 * The dinner file (dinners/YYYY-MM.json) bundled with the plugin.
 *
 * @return array Empty array when missing or unreadable.
 */
function dinner() {
	static $dinner = null;

	if ( null === $dinner ) {
		$dinner = array();
		$path   = LEAD_VD_DIR . 'dinner.json';
		if ( is_readable( $path ) ) {
			$decoded = json_decode( (string) file_get_contents( $path ), true );
			if ( is_array( $decoded ) ) {
				$dinner = $decoded;
			}
		}

		/**
		 * Lets staging point at duplicated boards without editing dinner.json.
		 *
		 * @param array $dinner The decoded dinner file.
		 */
		$dinner = apply_filters( 'lead_vd_dinner', $dinner );
	}

	return $dinner;
}

/**
 * One board's config: array( 'id' => …, 'group' => …, 'columns' => array( … ) ).
 *
 * @param string $key 'guests' or 'table_leaders'.
 * @return array|null
 */
function board( $key ) {
	$dinner = dinner();
	return isset( $dinner['monday']['boards'][ $key ] ) ? $dinner['monday']['boards'][ $key ] : null;
}

/**
 * A column ID on a board, or '' if unknown.
 *
 * @param string $board  'guests' or 'table_leaders'.
 * @param string $column Logical name, e.g. 'email'.
 * @return string
 */
function column( $board, $column ) {
	$config = board( $board );
	return isset( $config['columns'][ $column ] ) ? (string) $config['columns'][ $column ] : '';
}

/**
 * The dinner date (Y-m-d) from the dinner file.
 *
 * @return string
 */
function dinner_date() {
	$dinner = dinner();
	return isset( $dinner['dinner_date'] ) ? (string) $dinner['dinner_date'] : '';
}

/**
 * Whether the plugin has what it needs to talk to Monday.com.
 *
 * @return bool
 */
function is_configured() {
	return '' !== token() && null !== board( 'guests' ) && null !== board( 'table_leaders' );
}

/* -------------------------------------------------------------------------
 * Write protection (same rules as lead-monday-integration)
 * ---------------------------------------------------------------------- */

/**
 * Whether this site must refuse to write to Monday.com.
 *
 * First match wins:
 *   1. LEAD_MONDAY_READ_ONLY constant or environment variable.
 *   2. WP_ENVIRONMENT_TYPE other than 'production'.
 *   3. A staging-looking hostname.
 *
 * @return bool
 */
function is_read_only() {
	return null !== read_only_rule();
}

/**
 * Which rule made the site read-only, or null when writes are allowed.
 *
 * @return string|null
 */
function read_only_rule() {
	if ( defined( 'LEAD_MONDAY_READ_ONLY' ) ) {
		return constant( 'LEAD_MONDAY_READ_ONLY' ) ? 'LEAD_MONDAY_READ_ONLY constant is true' : null;
	}

	$env_flag = get_secret( 'LEAD_MONDAY_READ_ONLY' );
	if ( '' !== $env_flag ) {
		return in_array( strtolower( $env_flag ), array( '0', 'false', 'no', 'off' ), true )
			? null
			: 'LEAD_MONDAY_READ_ONLY environment variable is set';
	}

	if ( function_exists( 'wp_get_environment_type' ) && 'production' !== wp_get_environment_type() ) {
		return "WP_ENVIRONMENT_TYPE is '" . wp_get_environment_type() . "' (not production)";
	}

	$host = strtolower( (string) wp_parse_url( home_url(), PHP_URL_HOST ) );
	foreach ( array( '.stage.site', 'staging.', '.test', '.local', 'dev.' ) as $needle ) {
		if ( '' !== $host && false !== strpos( $host, $needle ) ) {
			return "hostname '{$host}' matches the staging pattern '{$needle}'";
		}
	}

	return null;
}

/**
 * Whether a GraphQL document opens with `mutation`.
 *
 * @param string $graphql The document.
 * @return bool
 */
function is_mutation( $graphql ) {
	$trimmed = preg_replace( '/\A(?:\s|#[^\n]*\n)+/', '', (string) $graphql );
	return 1 === preg_match( '/\Amutation\b/i', (string) $trimmed );
}
