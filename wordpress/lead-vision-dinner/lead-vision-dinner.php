<?php
/**
 * Plugin Name: LEAD Vision Dinner Registration
 * Description: Registration for the LEAD Africa Vision Dinner at leadcma.org/dinner. Guests and Table Leaders register here; every registration writes to Monday.com. Shortcodes: [lead_vision_dinner] and [lead_vision_dinner_table].
 * Version:     1.0.0
 * Author:      LEAD
 * Requires PHP: 7.4
 * Requires at least: 5.8
 *
 * Source: theleadprogram/vision-dinner-countdown, wordpress/lead-vision-dinner.
 *
 * @package LEAD\VisionDinner
 */

namespace LEAD\VisionDinner;

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Block direct browser access.
}

const VERSION = '1.0.0';
const SLUG    = 'lead-vision-dinner';

define( 'LEAD_VD_FILE', __FILE__ );
define( 'LEAD_VD_DIR', plugin_dir_path( __FILE__ ) );
define( 'LEAD_VD_URL', plugin_dir_url( __FILE__ ) );

require_once LEAD_VD_DIR . 'inc/config.php';
require_once LEAD_VD_DIR . 'inc/settings.php';
require_once LEAD_VD_DIR . 'inc/class-monday.php';
require_once LEAD_VD_DIR . 'inc/class-registry.php';
require_once LEAD_VD_DIR . 'inc/rest.php';
require_once LEAD_VD_DIR . 'inc/shortcodes.php';
require_once LEAD_VD_DIR . 'inc/site-health.php';
