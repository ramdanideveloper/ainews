<?php
defined( 'ABSPATH' ) || exit;

class AI_News_Assistant_OpenAI_Provider extends AI_News_Assistant_Provider_Base {
	private $settings;
	public function __construct( array $settings ) { $this->settings = $settings; }
	public function is_configured() { return ! empty( $this->settings['api_key'] ); }
	public function detect_news_type( $title ) {
		$prompt = 'Klasifikasikan judul berita Indonesia berikut. Keluarkan JSON valid saja: news_type (incident/government/business/feature/event/advertorial/explainer), news_type_label, subtype, confidence (0-100), required_data (array), warnings (array), review_status. Judul: ' . $title;
		$result = $this->request( array( array( 'role' => 'system', 'content' => 'Anda adalah editor berita Indonesia yang berhati-hati terhadap isu sensitif.' ), array( 'role' => 'user', 'content' => $prompt ) ) );
		if ( is_wp_error( $result ) ) return $result;
		$allowed = array( 'incident', 'government', 'business', 'feature', 'event', 'advertorial', 'explainer' );
		if ( empty( $result['news_type'] ) || ! in_array( $result['news_type'], $allowed, true ) ) return new WP_Error( 'aina_invalid_detection', __( 'Respons deteksi jenis berita tidak valid.', 'ai-news-assistant' ) );
		return $result;
	}

	public function generate( array $input ) {
		if ( ! $this->is_configured() ) return new WP_Error( 'aina_missing_key', __( 'API key belum dikonfigurasi.', 'ai-news-assistant' ) );
		$endpoint = ! empty( $this->settings['endpoint'] ) ? $this->settings['endpoint'] : 'https://api.openai.com/v1/chat/completions';
		$system = 'Anda adalah asisten redaksi Indonesia. Gunakan editorial_data sebagai sumber utama naskah, jangan hanya mengembangkan judul dan jangan mengarang fakta. Field kosong harus dicatat dalam verification_notes. Jika sumber, waktu, lokasi, atau konfirmasi aparat yang relevan kosong, set review_status Needs Verification dan needs_verification true. Keluarkan JSON valid saja. Struktur wajib: main_title, alternative_titles (min 3), lead, content (HTML paragraf aman), summary_points, verification_notes, fact_checklist {who,what,when,where,why,how,source_available,needs_verification}, seo {seo_title,meta_description,slug,focus_keyword,tags,category_suggestion}, social_captions {instagram_facebook,twitter_x,whatsapp_telegram}, review_status (Ready/Needs Verification/Missing Source).';
		$data = $this->request( array( array( 'role' => 'system', 'content' => $system ), array( 'role' => 'user', 'content' => wp_json_encode( $input ) ) ) );
		if ( is_wp_error( $data ) ) return $data;
		return $this->normalize( $data, $input );
	}
	private function request( array $messages ) {
		$endpoint = ! empty( $this->settings['endpoint'] ) ? $this->settings['endpoint'] : 'https://api.openai.com/v1/chat/completions';
		$response = wp_remote_post( esc_url_raw( $endpoint ), array(
			'timeout' => 45,
			'headers' => array( 'Authorization' => 'Bearer ' . $this->settings['api_key'], 'Content-Type' => 'application/json' ),
			'body' => wp_json_encode( array( 'model' => $this->settings['model'], 'temperature' => 0.3, 'response_format' => array( 'type' => 'json_object' ), 'messages' => $messages ) ),
		) );
		if ( is_wp_error( $response ) ) return $response;
		$code = wp_remote_retrieve_response_code( $response );
		$body = json_decode( wp_remote_retrieve_body( $response ), true );
		if ( $code < 200 || $code >= 300 ) return new WP_Error( 'aina_api_error', isset( $body['error']['message'] ) ? sanitize_text_field( $body['error']['message'] ) : sprintf( __( 'API merespons dengan kode %d.', 'ai-news-assistant' ), $code ) );
		$content = isset( $body['choices'][0]['message']['content'] ) ? $body['choices'][0]['message']['content'] : '';
		$data = json_decode( $content, true );
		if ( ! is_array( $data ) ) return new WP_Error( 'aina_invalid_response', __( 'Respons AI bukan JSON yang valid.', 'ai-news-assistant' ) );
		return $data;
	}
}
