<?php
defined( 'ABSPATH' ) || exit;
class AI_News_Assistant_Backend_Provider extends AI_News_Assistant_Provider_Base {
	private $settings;
	public function __construct( array $settings ) { $this->settings = $settings; }
	public function is_configured() { return ! empty( $this->settings['backend_url'] ); }
	public function detect_news_type( $title ) { return $this->request( 'detect_news_type', array( 'title' => $title ) ); }
	public function generate( array $input ) {
		$data = $this->request( 'generate_news', $input );
		if ( is_wp_error( $data ) ) return $data;
		$mapped = array( 'main_title' => $data['title'] ?? '', 'alternative_titles' => $data['alternative_titles'] ?? array(), 'lead' => $data['lead'] ?? '', 'content' => $data['content_html'] ?? '', 'summary_points' => $data['summary_points'] ?? array(), 'verification_notes' => $data['verification_notes'] ?? array(), 'fact_checklist' => $data['fact_checklist'] ?? array(), 'seo' => array( 'seo_title' => $data['seo_title'] ?? '', 'meta_description' => $data['meta_description'] ?? '', 'slug' => $data['slug'] ?? '', 'focus_keyword' => $data['focus_keyword'] ?? '', 'tags' => $data['tags'] ?? array(), 'category_suggestion' => $data['category_suggestion'] ?? '' ), 'social_captions' => $data['social_captions'] ?? array(), 'review_status' => $data['review_status'] ?? 'Needs Verification' );
		return $this->normalize( $mapped, $input );
	}
	private function request( $type, array $payload ) {
		if ( ! $this->is_configured() ) return new WP_Error( 'aina_backend_missing', __( 'Backend URL belum dikonfigurasi.', 'ai-news-assistant' ) );
		$base = untrailingslashit( $this->settings['backend_url'] ); $site_token = $this->settings['site_token'] ?? '';
		if ( $site_token ) { $paths = array( 'detect_news_type' => 'detect-news-type', 'generate_news' => 'generate-news' ); $url = $base . '/api/ai/' . $paths[ $type ]; $body = $payload; $headers = array( 'Authorization' => 'Bearer ' . $site_token, 'X-Site-URL' => home_url(), 'Content-Type' => 'application/json', 'Accept' => 'application/json' ); }
		else { $url = $base . '/api/guest/generate'; $body = array( 'install_id' => AI_News_Assistant::install_id(), 'site_url' => home_url(), 'plugin_version' => AINA_VERSION, 'request_type' => $type, 'payload' => $payload ); $headers = array( 'Content-Type' => 'application/json', 'Accept' => 'application/json' ); }
		$response = wp_remote_post( esc_url_raw( $url ), array( 'timeout' => 95, 'headers' => $headers, 'body' => wp_json_encode( $body ) ) );
		if ( is_wp_error( $response ) ) return $response; $json = json_decode( wp_remote_retrieve_body( $response ), true );
		if ( ! is_array( $json ) || empty( $json['success'] ) ) return new WP_Error( isset( $json['code'] ) ? sanitize_key( $json['code'] ) : 'aina_backend_error', isset( $json['message'] ) ? sanitize_text_field( $json['message'] ) : __( 'Backend tidak memberikan respons valid.', 'ai-news-assistant' ) );
		return isset( $json['data'] ) && is_array( $json['data'] ) ? $json['data'] : array();
	}
}
