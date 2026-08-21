<?php
/**
 * Plugin Name: AI News Assistant
 * Description: Workflow redaksi untuk membuat, memeriksa, dan menyimpan draft berita berbantuan AI.
 * Version: 3.1.0
 * Requires at least: 6.2
 * Requires PHP: 7.4
 * Author: AI News Assistant
 * Text Domain: ai-news-assistant
 */

defined( 'ABSPATH' ) || exit;

define( 'AINA_VERSION', '3.1.0' );
define( 'AINA_FILE', __FILE__ );
define( 'AINA_DIR', plugin_dir_path( __FILE__ ) );
define( 'AINA_URL', plugin_dir_url( __FILE__ ) );

require_once AINA_DIR . 'includes/class-ai-news-assistant-provider.php';
require_once AINA_DIR . 'includes/class-ai-news-assistant-backend-provider.php';
require_once AINA_DIR . 'includes/class-ai-news-assistant-post-handler.php';
require_once AINA_DIR . 'includes/class-ai-news-assistant-admin.php';
require_once AINA_DIR . 'includes/class-ai-news-assistant.php';

register_activation_hook( __FILE__, array( 'AI_News_Assistant', 'activate' ) );

function aina_run_plugin() {
	return AI_News_Assistant::instance();
}
add_action( 'plugins_loaded', 'aina_run_plugin' );
