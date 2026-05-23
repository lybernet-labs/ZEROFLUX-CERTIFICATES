<?php
/**
 * Plugin Name: ZeroFlux Certificate Verification
 * Plugin URI: https://github.com/zfln-rehan0520
 * Description: Lightweight internship & employee certificate verification system.
 * Version: 2.1
 * Author: Mohammed Rehan {Founder of Lybernet}
 * Text Domain: zf-certificates
 */

if ( ! defined( 'ABSPATH' ) ) exit;

define( 'ZF_CERT_VERSION', '2.1' );
define( 'ZF_CERT_DIR', plugin_dir_path( __FILE__ ) );
define( 'ZF_CERT_URL', plugin_dir_url( __FILE__ ) );

/* ─── Autoload sub-files ─────────────────────────────────────────── */
require_once ZF_CERT_DIR . 'includes/class-db.php';
require_once ZF_CERT_DIR . 'includes/class-admin.php';
require_once ZF_CERT_DIR . 'includes/class-shortcode.php';

/* ─── Boot ───────────────────────────────────────────────────────── */
function zf_cert_init() {
    $db        = new ZF_Cert_DB();
    $admin     = new ZF_Cert_Admin( $db );
    $shortcode = new ZF_Cert_Shortcode( $db );
}
add_action( 'plugins_loaded', 'zf_cert_init' );

register_activation_hook( __FILE__, array( 'ZF_Cert_DB', 'create_table' ) );
