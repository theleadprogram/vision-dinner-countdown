<?php
/**
 * REST endpoints the page's JavaScript calls: /wp-json/lead-dinner/v1/…
 *
 * The Monday.com token never leaves the server. Public endpoints are
 * rate-limited per IP and the registration form carries a honeypot field.
 *
 * @package LEAD\VisionDinner
 */

namespace LEAD\VisionDinner\Rest;

use LEAD\VisionDinner\Monday_Exception;
use LEAD\VisionDinner\Registry;
use LEAD\VisionDinner\Token_Exception;
use LEAD\VisionDinner\Validation_Exception;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

const NS = 'lead-dinner/v1';

add_action(
	'rest_api_init',
	function () {
		$public = '__return_true';

		register_rest_route(
			NS,
			'/leaders',
			array(
				'methods'             => 'GET',
				'permission_callback' => $public,
				'callback'            => function () {
					return respond(
						function () {
							return array( 'leaders' => Registry::leaders() );
						}
					);
				},
			)
		);

		register_rest_route(
			NS,
			'/register',
			array(
				'methods'             => 'POST',
				'permission_callback' => $public,
				'callback'            => function ( \WP_REST_Request $request ) {
					$in = (array) $request->get_json_params();
					if ( ! empty( $in['website'] ) ) {
						// Honeypot filled: a bot. Pretend it worked.
						return new \WP_REST_Response( array( 'role' => 'guest', 'table' => '' ), 200 );
					}
					return respond(
						function () use ( $in ) {
							limit( 'register', 12, HOUR_IN_SECONDS );
							return Registry::register( $in );
						}
					);
				},
			)
		);

		register_rest_route(
			NS,
			'/resend-link',
			array(
				'methods'             => 'POST',
				'permission_callback' => $public,
				'callback'            => function ( \WP_REST_Request $request ) {
					$email = (string) $request->get_param( 'email' );
					return respond(
						function () use ( $email ) {
							limit( 'resend', 5, HOUR_IN_SECONDS );
							Registry::resend_link( $email );
							return array( 'ok' => true );
						}
					);
				},
			)
		);

		register_rest_route(
			NS,
			'/table',
			array(
				'methods'             => 'GET',
				'permission_callback' => $public,
				'callback'            => function ( \WP_REST_Request $request ) {
					$token = (string) $request->get_param( 't' );
					return respond(
						function () use ( $token ) {
							limit( 'table-read', 120, HOUR_IN_SECONDS );
							return Registry::table( $token );
						}
					);
				},
			)
		);

		register_rest_route(
			NS,
			'/table/guests',
			array(
				'methods'             => 'POST',
				'permission_callback' => $public,
				'callback'            => function ( \WP_REST_Request $request ) {
					$in = (array) $request->get_json_params();
					return respond(
						function () use ( $in ) {
							limit( 'table-write', 60, HOUR_IN_SECONDS );
							return Registry::add_guest( isset( $in['t'] ) ? (string) $in['t'] : '', $in );
						}
					);
				},
			)
		);

		register_rest_route(
			NS,
			'/table/guests/(?P<id>\d+)',
			array(
				'methods'             => 'POST',
				'permission_callback' => $public,
				'callback'            => function ( \WP_REST_Request $request ) {
					$in = (array) $request->get_json_params();
					$id = (string) $request['id'];
					return respond(
						function () use ( $in, $id ) {
							limit( 'table-write', 60, HOUR_IN_SECONDS );
							return Registry::update_guest( isset( $in['t'] ) ? (string) $in['t'] : '', $id, $in );
						}
					);
				},
			)
		);

		register_rest_route(
			NS,
			'/table/guests/(?P<id>\d+)/release',
			array(
				'methods'             => 'POST',
				'permission_callback' => $public,
				'callback'            => function ( \WP_REST_Request $request ) {
					$in = (array) $request->get_json_params();
					$id = (string) $request['id'];
					return respond(
						function () use ( $in, $id ) {
							limit( 'table-write', 60, HOUR_IN_SECONDS );
							return Registry::release_guest( isset( $in['t'] ) ? (string) $in['t'] : '', $id );
						}
					);
				},
			)
		);
	}
);

/**
 * Too many requests from one visitor.
 */
class Rate_Limit_Exception extends \RuntimeException {}

/**
 * Run a handler and turn its outcome into a response.
 *
 * @param callable $handler Returns the response data.
 * @return \WP_REST_Response
 */
function respond( callable $handler ) {
	try {
		$response = new \WP_REST_Response( $handler(), 200 );
	} catch ( Validation_Exception $e ) {
		$response = new \WP_REST_Response( array( 'errors' => $e->getErrors() ), 422 );
	} catch ( Token_Exception $e ) {
		$response = new \WP_REST_Response( array( 'code' => 'bad_link' ), 403 );
	} catch ( Rate_Limit_Exception $e ) {
		$response = new \WP_REST_Response( array( 'code' => 'slow_down' ), 429 );
	} catch ( Monday_Exception $e ) {
		error_log( '[lead-vision-dinner] ' . $e->getMessage() ); // phpcs:ignore WordPress.PHP.DevelopmentFunctions
		$response = new \WP_REST_Response( array( 'code' => 'server' ), 502 );
	}

	$response->header( 'Cache-Control', 'no-store' );
	return $response;
}

/**
 * Count a request against a per-IP budget.
 *
 * @param string $bucket Name.
 * @param int    $max    Requests allowed per window.
 * @param int    $window Seconds.
 * @throws Rate_Limit_Exception When over budget.
 */
function limit( $bucket, $max, $window ) {
	$ip  = isset( $_SERVER['REMOTE_ADDR'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) ) : 'unknown';
	$key = 'lead_vd_rl_' . $bucket . '_' . md5( $ip . wp_salt( 'nonce' ) );
	$hit = (int) get_transient( $key );

	if ( $hit >= $max ) {
		throw new Rate_Limit_Exception( 'Rate limited.' );
	}
	set_transient( $key, $hit + 1, $window );
}
