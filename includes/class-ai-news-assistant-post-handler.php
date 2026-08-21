<?php
defined( 'ABSPATH' ) || exit;

class AI_News_Assistant_Post_Handler {
	const GENERATED_META = '_aina_generated';
	public function __construct() {
		add_action( 'wp_ajax_aina_detect_news_type', array( $this, 'ajax_detect_news_type' ) );
		add_action( 'wp_ajax_aina_generate', array( $this, 'ajax_generate' ) );
		add_action( 'wp_ajax_aina_generate_article', array( $this, 'ajax_generate_article' ) );
		add_action( 'wp_ajax_aina_generate_image', array( $this, 'ajax_generate_image' ) );
		add_action( 'wp_ajax_aina_save_draft', array( $this, 'ajax_save_draft' ) );
		add_action( 'add_meta_boxes', array( $this, 'add_meta_box' ) );
	}
	private function check_ajax() {
		check_ajax_referer( 'aina_admin', 'nonce' );
		if ( ! current_user_can( 'edit_posts' ) ) wp_send_json_error( array( 'message' => __( 'Anda tidak memiliki izin.', 'ai-news-assistant' ) ), 403 );
	}
	private function input() {
		$editorial_json = isset( $_POST['editorial_data'] ) ? wp_unslash( $_POST['editorial_data'] ) : '';
		$editorial_data = json_decode( $editorial_json, true );
		$editorial_data = is_array( $editorial_data ) ? $this->clean_array( $editorial_data ) : array();
		return array(
			'title' => isset( $_POST['title'] ) ? sanitize_text_field( wp_unslash( $_POST['title'] ) ) : '',
			'news_type' => isset( $_POST['news_type'] ) ? sanitize_key( $_POST['news_type'] ) : '',
			'editorial_data' => $editorial_data,
			'source' => isset( $_POST['source'] ) ? esc_url_raw( wp_unslash( $_POST['source'] ) ) : '',
			'facts' => isset( $_POST['facts'] ) ? sanitize_textarea_field( wp_unslash( $_POST['facts'] ) ) : '',
			'raw_text' => isset( $_POST['raw_text'] ) ? sanitize_textarea_field( wp_unslash( $_POST['raw_text'] ) ) : '',
			'style' => isset( $_POST['style'] ) ? sanitize_key( $_POST['style'] ) : 'hard_news',
			'length' => isset( $_POST['length'] ) ? sanitize_key( $_POST['length'] ) : 'medium',
		);
	}
	public function ajax_detect_news_type() {
		$this->check_ajax();
		$title = isset( $_POST['title'] ) ? sanitize_text_field( wp_unslash( $_POST['title'] ) ) : '';
		if ( '' === $title ) wp_send_json_error( array( 'message' => __( 'Isi judul sementara terlebih dahulu.', 'ai-news-assistant' ) ), 400 );
		$result = $this->provider()->detect_news_type( $title );
		if ( is_wp_error( $result ) ) wp_send_json_error( array( 'message' => $result->get_error_message() ), 500 );
		wp_send_json_success( array( 'detection' => $this->clean_array( $result ), 'demo' => empty( AI_News_Assistant::settings()['api_key'] ) ) );
	}
	public function provider() {
		$settings = AI_News_Assistant::settings();
		return new AI_News_Assistant_Backend_Provider( $settings );
	}
	public function ajax_generate() {
		$this->check_ajax();
		$input = $this->input();
		if ( '' === $input['title'] || '' === $input['news_type'] || empty( $input['editorial_data'] ) ) wp_send_json_error( array( 'message' => __( 'Deteksi jenis berita dan isi form redaksi sebelum membuat draft.', 'ai-news-assistant' ) ), 400 );
		$result = $this->provider()->generate( $input );
		if ( is_wp_error( $result ) ) wp_send_json_error( array( 'message' => $result->get_error_message() ), 500 );
		wp_send_json_success( array( 'draft' => $result, 'demo' => empty( AI_News_Assistant::settings()['site_token'] ) ) );
	}
	public function ajax_generate_article() {
		$this->check_ajax();
		$input = array(
			'title' => isset( $_POST['title'] ) ? sanitize_text_field( wp_unslash( $_POST['title'] ) ) : '',
			'focus_keyword' => isset( $_POST['focus_keyword'] ) ? sanitize_text_field( wp_unslash( $_POST['focus_keyword'] ) ) : '',
			'brief' => isset( $_POST['brief'] ) ? sanitize_textarea_field( wp_unslash( $_POST['brief'] ) ) : '',
			'outline' => isset( $_POST['outline'] ) ? sanitize_textarea_field( wp_unslash( $_POST['outline'] ) ) : '',
			'style' => isset( $_POST['style'] ) ? sanitize_key( $_POST['style'] ) : 'seo_news',
			'length' => isset( $_POST['length'] ) ? min( 900, max( 200, absint( $_POST['length'] ) ) ) : 500,
			'audience' => isset( $_POST['audience'] ) ? sanitize_text_field( wp_unslash( $_POST['audience'] ) ) : '',
			'editorial_data' => array(),
		);
		if ( '' === $input['title'] || '' === $input['brief'] ) wp_send_json_error( array( 'message' => __( 'Judul dan bahan artikel wajib diisi.', 'ai-news-assistant' ) ), 400 );
		$result = $this->provider()->generate_article( $input );
		if ( is_wp_error( $result ) ) wp_send_json_error( array( 'message' => $result->get_error_message() ), 500 );
		wp_send_json_success( array( 'draft' => $result ) );
	}
	public function ajax_generate_image() {
		$this->check_ajax();
		if ( empty( AI_News_Assistant::settings()['site_token'] ) ) wp_send_json_error( array( 'message' => __( 'Hubungkan akun sebelum membuat gambar.', 'ai-news-assistant' ) ), 403 );
		$payload = array(
			'title' => isset( $_POST['title'] ) ? sanitize_text_field( wp_unslash( $_POST['title'] ) ) : '',
			'content_summary' => isset( $_POST['content_summary'] ) ? sanitize_textarea_field( wp_unslash( $_POST['content_summary'] ) ) : '',
			'keyword' => isset( $_POST['keyword'] ) ? sanitize_text_field( wp_unslash( $_POST['keyword'] ) ) : '',
			'style' => isset( $_POST['image_style'] ) ? sanitize_text_field( wp_unslash( $_POST['image_style'] ) ) : 'editorial photojournalism',
			'aspect_ratio' => isset( $_POST['aspect_ratio'] ) && in_array( $_POST['aspect_ratio'], array( '1:1', '16:9', '9:16' ), true ) ? $_POST['aspect_ratio'] : '16:9',
			'custom_prompt' => isset( $_POST['image_prompt'] ) ? sanitize_textarea_field( wp_unslash( $_POST['image_prompt'] ) ) : '',
			'use_as_thumbnail' => true,
		);
		if ( '' === $payload['title'] ) wp_send_json_error( array( 'message' => __( 'Judul artikel diperlukan untuk membuat gambar.', 'ai-news-assistant' ) ), 400 );
		$result = $this->provider()->generate_image( $payload );
		if ( is_wp_error( $result ) ) wp_send_json_error( array( 'message' => $result->get_error_message() ), 500 );
		$attachment = $this->store_generated_image( $result, $payload );
		if ( is_wp_error( $attachment ) ) wp_send_json_error( array( 'message' => $attachment->get_error_message() ), 500 );
		update_post_meta( $attachment, '_aina_image_usage', $this->clean_array( array( 'charged_amount' => $result['charged_amount'] ?? 0, 'balance_after' => $result['balance_after'] ?? '', 'generated_at' => current_time( 'mysql' ) ) ) );
		wp_send_json_success( array( 'attachment_id' => $attachment, 'image_url' => wp_get_attachment_url( $attachment ), 'charged_amount' => $result['charged_amount'] ?? 0, 'balance_after' => $result['balance_after'] ?? null ) );
	}
	public function ajax_save_draft() {
		$this->check_ajax();
		$json = isset( $_POST['draft'] ) ? wp_unslash( $_POST['draft'] ) : '';
		$data = json_decode( $json, true );
		if ( ! is_array( $data ) || empty( $data['main_title'] ) ) wp_send_json_error( array( 'message' => __( 'Data draft tidak valid.', 'ai-news-assistant' ) ), 400 );
		$settings = AI_News_Assistant::settings();
		$facts = isset( $data['fact_checklist'] ) ? (array) $data['fact_checklist'] : array();
		if ( ! empty( $settings['require_checklist'] ) && empty( $facts ) ) wp_send_json_error( array( 'message' => __( 'Fact checklist wajib tersedia sebelum menyimpan.', 'ai-news-assistant' ) ), 400 );
		$status = in_array( $settings['post_status'], array( 'draft', 'pending' ), true ) ? $settings['post_status'] : 'draft';
		$post_id = wp_insert_post( array( 'post_type' => 'post', 'post_title' => sanitize_text_field( $data['main_title'] ), 'post_content' => wp_kses_post( $data['content'] ), 'post_excerpt' => sanitize_textarea_field( isset( $data['lead'] ) ? $data['lead'] : '' ), 'post_status' => $status, 'meta_input' => array( self::GENERATED_META => 1 ) ), true );
		if ( is_wp_error( $post_id ) ) wp_send_json_error( array( 'message' => $post_id->get_error_message() ), 500 );
		$this->save_meta( $post_id, $data );
		$thumbnail_id = isset( $_POST['thumbnail_id'] ) ? absint( $_POST['thumbnail_id'] ) : 0;
		if ( $thumbnail_id && wp_attachment_is_image( $thumbnail_id ) ) { set_post_thumbnail( $post_id, $thumbnail_id ); wp_update_post( array( 'ID' => $thumbnail_id, 'post_parent' => $post_id ) ); update_post_meta( $post_id, '_aina_generated_image_id', $thumbnail_id ); }
		wp_send_json_success( array( 'message' => __( 'Draft berhasil disimpan.', 'ai-news-assistant' ), 'post_id' => $post_id, 'edit_url' => get_edit_post_link( $post_id, 'raw' ) ) );
	}
	private function store_generated_image( array $result, array $payload ) {
		if ( empty( $result['image_base64'] ) ) return new WP_Error( 'aina_image_empty', __( 'Provider tidak mengembalikan data gambar.', 'ai-news-assistant' ) );
		$bytes = base64_decode( $result['image_base64'], true );
		if ( false === $bytes ) return new WP_Error( 'aina_image_decode', __( 'Data gambar dari provider tidak valid.', 'ai-news-assistant' ) );
		$mime = isset( $result['mime_type'] ) ? sanitize_mime_type( $result['mime_type'] ) : 'image/png';
		$extension = 'image/jpeg' === $mime ? 'jpg' : ( 'image/webp' === $mime ? 'webp' : 'png' );
		$filename = sanitize_file_name( sanitize_title( $payload['title'] ) . '-' . wp_generate_password( 6, false, false ) . '.' . $extension );
		$upload = wp_upload_bits( $filename, null, $bytes );
		if ( ! empty( $upload['error'] ) ) return new WP_Error( 'aina_image_upload', $upload['error'] );
		require_once ABSPATH . 'wp-admin/includes/image.php';
		$attachment_id = wp_insert_attachment( array( 'post_mime_type' => $mime, 'post_title' => sanitize_text_field( $payload['title'] ), 'post_excerpt' => sanitize_text_field( $result['caption'] ?? $payload['title'] ), 'post_status' => 'inherit' ), $upload['file'] );
		if ( is_wp_error( $attachment_id ) ) return $attachment_id;
		wp_update_attachment_metadata( $attachment_id, wp_generate_attachment_metadata( $attachment_id, $upload['file'] ) );
		update_post_meta( $attachment_id, '_wp_attachment_image_alt', sanitize_text_field( $result['alt_text'] ?? $payload['keyword'] ?? $payload['title'] ) );
		update_post_meta( $attachment_id, '_aina_generated_image', 1 );
		return $attachment_id;
	}
	private function save_meta( $post_id, array $data ) {
		$seo = isset( $data['seo'] ) ? (array) $data['seo'] : array();
		update_post_meta( $post_id, '_aina_review_status', sanitize_text_field( isset( $data['review_status'] ) ? $data['review_status'] : '' ) );
		update_post_meta( $post_id, '_aina_fact_checklist', $this->clean_array( isset( $data['fact_checklist'] ) ? $data['fact_checklist'] : array() ) );
		update_post_meta( $post_id, '_aina_seo', $this->clean_array( $seo ) );
		update_post_meta( $post_id, '_aina_social_captions', $this->clean_array( isset( $data['social_captions'] ) ? $data['social_captions'] : array() ) );
		update_post_meta( $post_id, '_aina_usage', $this->clean_array( isset( $data['usage'] ) ? $data['usage'] : array() ) );
		update_post_meta( $post_id, '_aina_generation_notes', $this->clean_array( array( 'alternative_titles' => isset( $data['alternative_titles'] ) ? $data['alternative_titles'] : array(), 'summary_points' => isset( $data['summary_points'] ) ? $data['summary_points'] : array(), 'verification_notes' => isset( $data['verification_notes'] ) ? $data['verification_notes'] : array(), 'generated_at' => current_time( 'mysql' ) ) ) );
		$this->sync_rank_math_meta( $post_id, $seo );
		if ( ! empty( $seo['tags'] ) ) wp_set_post_tags( $post_id, array_map( 'sanitize_text_field', (array) $seo['tags'] ), false );
		if ( ! empty( $seo['category_suggestion'] ) ) { $category_id = get_cat_ID( sanitize_text_field( $seo['category_suggestion'] ) ); if ( $category_id ) wp_set_post_categories( $post_id, array( $category_id ), false ); }
		if ( ! empty( $seo['slug'] ) ) wp_update_post( array( 'ID' => $post_id, 'post_name' => sanitize_title( $seo['slug'] ) ) );
	}
	private function sync_rank_math_meta( $post_id, array $seo ) {
		$settings = AI_News_Assistant::settings();
		if ( empty( $settings['sync_rank_math'] ) || ! AI_News_Assistant::is_rank_math_active() ) return;
		$map = array( 'seo_title' => 'rank_math_title', 'meta_description' => 'rank_math_description', 'focus_keyword' => 'rank_math_focus_keyword' );
		foreach ( $map as $source_key => $meta_key ) {
			if ( empty( $seo[ $source_key ] ) ) continue;
			if ( empty( $settings['overwrite_rank_math'] ) && '' !== (string) get_post_meta( $post_id, $meta_key, true ) ) continue;
			update_post_meta( $post_id, $meta_key, sanitize_text_field( $seo[ $source_key ] ) );
		}
	}
	private function clean_array( $value ) {
		if ( ! is_array( $value ) ) return sanitize_textarea_field( (string) $value );
		$clean = array();
		foreach ( $value as $key => $item ) $clean[ sanitize_key( $key ) ] = is_array( $item ) ? $this->clean_array( $item ) : ( is_bool( $item ) ? $item : sanitize_textarea_field( (string) $item ) );
		return $clean;
	}
	public function add_meta_box() { add_meta_box( 'aina-review', __( 'AI News Assistant Review', 'ai-news-assistant' ), array( $this, 'render_meta_box' ), 'post', 'normal', 'default' ); }
	public function render_meta_box( $post ) {
		$status = get_post_meta( $post->ID, '_aina_review_status', true );
		if ( ! $status ) { echo '<p>' . esc_html__( 'Post ini belum memiliki data AI News Assistant.', 'ai-news-assistant' ) . '</p>'; return; }
		$facts = (array) get_post_meta( $post->ID, '_aina_fact_checklist', true );
		$seo = (array) get_post_meta( $post->ID, '_aina_seo', true );
		$captions = (array) get_post_meta( $post->ID, '_aina_social_captions', true );
		$notes = (array) get_post_meta( $post->ID, '_aina_generation_notes', true );
		echo '<div class="aina-metabox"><p><strong>' . esc_html__( 'Review status:', 'ai-news-assistant' ) . '</strong> ' . esc_html( $status ) . '</p>';
		$this->meta_table( __( 'Fact checklist', 'ai-news-assistant' ), $facts ); $this->meta_table( __( 'SEO suggestion', 'ai-news-assistant' ), $seo ); $this->meta_table( __( 'Social captions', 'ai-news-assistant' ), $captions ); $this->meta_table( __( 'Catatan generate', 'ai-news-assistant' ), $notes );
		echo '</div>';
	}
	private function meta_table( $title, array $items ) {
		echo '<h4>' . esc_html( $title ) . '</h4><table class="widefat striped"><tbody>';
		foreach ( $items as $key => $value ) { if ( is_array( $value ) ) $value = implode( ', ', array_map( 'sanitize_text_field', $value ) ); elseif ( is_bool( $value ) ) $value = $value ? __( 'Ya', 'ai-news-assistant' ) : __( 'Tidak', 'ai-news-assistant' ); echo '<tr><th>' . esc_html( ucwords( str_replace( '_', ' ', $key ) ) ) . '</th><td>' . nl2br( esc_html( (string) $value ) ) . '</td></tr>'; }
		echo '</tbody></table>';
	}
}
