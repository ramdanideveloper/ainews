<?php
defined( 'ABSPATH' ) || exit;

final class AI_News_Assistant {
	private static $instance;
	public $admin;
	public $post_handler;
	public static function instance() {
		if ( null === self::$instance ) self::$instance = new self();
		return self::$instance;
	}
	private function __construct() {
		$this->post_handler = new AI_News_Assistant_Post_Handler();
		if ( is_admin() ) $this->admin = new AI_News_Assistant_Admin( $this->post_handler );
	}
	public static function defaults() {
		return array( 'provider' => 'openai', 'api_key' => '', 'endpoint' => 'https://api.openai.com/v1/chat/completions', 'model' => 'gpt-4o-mini', 'language' => 'Indonesian', 'tone' => 'Netral dan faktual', 'post_status' => 'draft', 'require_checklist' => 1 );
	}
	public static function settings() { return wp_parse_args( get_option( 'aina_settings', array() ), self::defaults() ); }
	public static function activate() { add_option( 'aina_settings', self::defaults() ); }
}
