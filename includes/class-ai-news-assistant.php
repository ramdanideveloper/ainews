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
		$this->remove_legacy_provider_credentials();
		$this->post_handler = new AI_News_Assistant_Post_Handler();
		if ( is_admin() ) $this->admin = new AI_News_Assistant_Admin( $this->post_handler );
	}
	public static function defaults() {
		return array( 'backend_url' => 'https://dash.ramdani.web.id', 'account_token' => '', 'site_token' => '', 'provider' => 'backend', 'api_key' => '', 'endpoint' => '', 'model' => 'Backend managed', 'language' => 'Indonesian', 'tone' => 'Netral dan faktual', 'post_status' => 'draft', 'require_checklist' => 1, 'sync_rank_math' => 0, 'overwrite_rank_math' => 0 );
	}
	public static function settings() { $settings = wp_parse_args( get_option( 'aina_settings', array() ), self::defaults() ); if ( empty( $settings['backend_url'] ) ) $settings['backend_url'] = self::defaults()['backend_url']; return $settings; }
	public static function is_rank_math_active() { return defined( 'RANK_MATH_VERSION' ) || class_exists( 'RankMath' ) || class_exists( 'RankMath\\Helper' ); }
	public static function install_id() { $id = get_option( 'aina_install_id' ); if ( ! $id ) { $id = wp_generate_uuid4(); add_option( 'aina_install_id', $id, '', false ); } return $id; }
	public static function activate() { add_option( 'aina_settings', self::defaults() ); self::install_id(); }
	private function remove_legacy_provider_credentials() { $settings = get_option( 'aina_settings', array() ); if ( ! empty( $settings['api_key'] ) || ! empty( $settings['endpoint'] ) ) { $settings['api_key'] = ''; $settings['endpoint'] = ''; $settings['model'] = 'Backend managed'; $settings['provider'] = 'backend'; update_option( 'aina_settings', $settings ); } }
}
