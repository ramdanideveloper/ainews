<?php
defined( 'ABSPATH' ) || exit;

interface AI_News_Assistant_Provider {
	/** @return array|WP_Error */
	public function generate( array $input );
	/** @return array|WP_Error */
	public function detect_news_type( $title );
	public function is_configured();
}

abstract class AI_News_Assistant_Provider_Base implements AI_News_Assistant_Provider {
	protected function normalize( $data, array $input ) {
		$defaults = array(
			'main_title' => '', 'alternative_titles' => array(), 'lead' => '', 'content' => '',
			'summary_points' => array(), 'verification_notes' => array(),
			'fact_checklist' => array(), 'seo' => array(), 'social_captions' => array(),
			'review_status' => empty( $input['source'] ) ? 'Needs Verification' : 'Ready',
		);
		$data = is_array( $data ) ? wp_parse_args( $data, $defaults ) : $defaults;
		$data['content'] = wp_kses_post( $data['content'] );
		$data['alternative_titles'] = array_slice( array_pad( $this->normalize_list( $data['alternative_titles'] ), 3, $data['main_title'] ), 0, 5 );
		$data['summary_points'] = $this->normalize_list( $data['summary_points'] );
		$data['verification_notes'] = $this->normalize_list( $data['verification_notes'] );
		$data['fact_checklist'] = wp_parse_args( (array) $data['fact_checklist'], array(
			'who' => '', 'what' => '', 'when' => '', 'where' => '', 'why' => '', 'how' => '',
			'source_available' => ! empty( $input['source'] ),
			'needs_verification' => empty( $input['source'] ),
		) );
		$data['fact_checklist'] = $this->complete_fact_checklist( $data['fact_checklist'], $input, $data );
		$data['seo'] = wp_parse_args( (array) $data['seo'], array(
			'seo_title' => $data['main_title'], 'meta_description' => '', 'slug' => sanitize_title( $data['main_title'] ),
			'focus_keyword' => '', 'tags' => array(), 'category_suggestion' => 'Berita',
		) );
		$data['seo']['tags'] = $this->normalize_list( $data['seo']['tags'] );
		$data = $this->optimize_rank_math_seo( $data, $input );
		$data['social_captions'] = wp_parse_args( (array) $data['social_captions'], array(
			'instagram_facebook' => '', 'twitter_x' => '', 'whatsapp_telegram' => '',
		) );
		if ( ! empty( $input['source_analysis'] ) ) {
			$data['review_status'] = 'Needs Verification';
			$data['fact_checklist']['needs_verification'] = true;
			$source = (array) $input['source_analysis'];
			$url = isset( $source['source_url'] ) ? esc_url( $source['source_url'] ) : '';
			$media = sanitize_text_field( $source['source_media'] ?? $source['source_domain'] ?? '' );
			if ( $url && false === strpos( $data['content'], $url ) ) $data['content'] .= '<p class="aina-source-attribution"><em>Dilansir dari <a href="' . $url . '" target="_blank" rel="noopener">' . esc_html( $media ?: $url ) . '</a>. Seluruh informasi wajib dibandingkan kembali dengan sumber asli sebelum publikasi.</em></p>';
		}
		return $data;
	}
	private function complete_fact_checklist( array $facts, array $input, array $data ) {
		$editorial = isset( $input['editorial_data'] ) ? (array) $input['editorial_data'] : array();
		$map = array(
			'who' => array( 'involved_parties', 'party_condition', 'official', 'profile_name', 'business_actor', 'participants', 'spokesperson', 'speaker' ),
			'when' => array( 'event_time', 'schedule', 'timeline' ),
			'where' => array( 'location' ),
			'why' => array( 'temporary_cause', 'background', 'market_context', 'key_message' ),
			'how' => array( 'chronology', 'handling_status', 'main_story', 'agenda' ),
		);
		if ( empty( $facts['what'] ) ) $facts['what'] = sanitize_text_field( isset( $input['title'] ) ? $input['title'] : $data['main_title'] );
		foreach ( $map as $fact => $keys ) {
			if ( ! empty( $facts[ $fact ] ) ) continue;
			foreach ( $keys as $key ) {
				if ( ! empty( $editorial[ $key ] ) ) { $facts[ $fact ] = sanitize_textarea_field( $editorial[ $key ] ); break; }
			}
		}
		$source_keys = array( 'source_information', 'official_source', 'authority_statement', 'resident_statement' );
		$has_source = ! empty( $input['source'] );
		foreach ( $source_keys as $key ) if ( ! empty( $editorial[ $key ] ) ) $has_source = true;
		$facts['source_available'] = $has_source;
		$required_missing = array_filter( array( 'who', 'what', 'when', 'where', 'why', 'how' ), function ( $key ) use ( $facts ) { return empty( $facts[ $key ] ); } );
		$facts['needs_verification'] = ! empty( $required_missing ) || ! empty( $editorial['unconfirmed_data'] ) || 'Ready' !== ( isset( $data['review_status'] ) ? $data['review_status'] : '' );
		return $facts;
	}
	private function normalize_list( $value ) {
		if ( is_string( $value ) ) {
			$value = preg_split( '/[\r\n]+/', $value );
		} elseif ( ! is_array( $value ) ) {
			$value = empty( $value ) ? array() : array( $value );
		}
		$list = array();
		foreach ( $value as $item ) {
			if ( is_array( $item ) ) $item = implode( ': ', array_map( 'strval', array_values( $item ) ) );
			$item = trim( preg_replace( '/^[\s\-*•]+/u', '', (string) $item ) );
			if ( '' !== $item ) $list[] = $item;
		}
		return array_values( $list );
	}
	private function optimize_rank_math_seo( array $data, array $input ) {
		$title = sanitize_text_field( $data['main_title'] );
		$words = preg_split( '/\s+/u', trim( $title ) );
		$keyword_words = array_slice( array_filter( $words ), 0, 4 );
		$keyword = ! empty( $input['focus_keyword'] ) ? sanitize_text_field( $input['focus_keyword'] ) : trim( implode( ' ', $keyword_words ), " \t\n\r\0\x0B,.:;!?-" );
		if ( function_exists( 'mb_strtolower' ) ) $keyword = mb_strtolower( $keyword, 'UTF-8' );
		else $keyword = strtolower( $keyword );
		$keyword = $this->limit_text( $keyword, 45, '' );
		if ( '' === $keyword ) $keyword = sanitize_text_field( $data['seo']['focus_keyword'] );

		$seo_title = sanitize_text_field( $data['seo']['seo_title'] );
		if ( false === stripos( $seo_title, $keyword ) ) $seo_title = $keyword . ': ' . $seo_title;
		if ( 'hard_news' !== ( $input['style'] ?? '' ) && ! preg_match( '/\b(terbaik|ampuh|mudah|efektif|unggulan|penting)\b/iu', $seo_title ) ) $seo_title = $keyword . ': Panduan Terbaik dan Efektif';
		$data['seo']['seo_title'] = $this->limit_text( $seo_title, 60 );
		$data['seo']['focus_keyword'] = $keyword;

		$description = sanitize_text_field( $data['seo']['meta_description'] );
		if ( false === stripos( $description, $keyword ) ) $description = ucfirst( $keyword ) . '. ' . $description;
		$data['seo']['meta_description'] = $this->limit_text( $description, 155 );

		$slug = sanitize_title( $data['seo']['slug'] );
		if ( false === strpos( $slug, sanitize_title( $keyword ) ) ) $slug = sanitize_title( $keyword . ' ' . $slug );
		$data['seo']['slug'] = trim( $this->limit_text( $slug, 55, '' ), '-' );

		$data['content'] = $this->format_editorial_content( $data['content'], $data['lead'], $input, $keyword );
		$backend_audit = isset( $data['seo_audit'] ) ? (array) $data['seo_audit'] : array();
		$data['seo_audit'] = array_merge( $backend_audit, $this->seo_audit( $data, $keyword ) );
		return $data;
	}
	private function format_editorial_content( $content, $lead, array $input, $keyword ) {
		$content = (string) $content;
		$content = preg_replace( '/<h[1-3][^>]*>\s*[^<]*(?:fakta utama|ringkasan utama|poin utama)[^<]*<\/h[1-3]>\s*/iu', '', $content );
		$host = (string) wp_parse_url( home_url( '/' ), PHP_URL_HOST );
		$host = preg_replace( '/^www\./i', '', $host );
		$location = $this->editorial_dateline( isset( $input['editorial_data'] ) ? (array) $input['editorial_data'] : array() );
		$prefix = '<a href="' . esc_url( home_url( '/' ) ) . '">' . esc_html( $host ) . '</a>';
		if ( '' !== $location ) $prefix .= ' | ' . esc_html( $location );
		$prefix .= ' – ';
		$lead_text = trim( wp_strip_all_tags( (string) $lead ) );
		$input_title = trim( wp_strip_all_tags( (string) ( $input['title'] ?? '' ) ) );
		if ( '' !== $input_title ) $lead_text = preg_replace( '/^' . preg_quote( $input_title, '/' ) . '\s*(?:—|–|-|:)\s*/iu', '', $lead_text );
		if ( '' !== $keyword && false === stripos( $lead_text, $keyword ) && 'hard_news' !== ( $input['style'] ?? '' ) ) $lead_text = ucfirst( $keyword ) . '. ' . $lead_text;
		if ( '' !== $keyword && ! preg_match( '/<h[2-4][^>]*>[^<]*' . preg_quote( $keyword, '/' ) . '/iu', $content ) ) {
			if ( preg_match( '/<h2([^>]*)>/i', $content ) ) $content = preg_replace( '/<h2([^>]*)>/i', '<h2$1>' . esc_html( ucfirst( $keyword ) ) . ': ', $content, 1 );
			else $content = '<h2>' . esc_html( ucfirst( $keyword ) ) . '</h2>' . $content;
		}
		$content = $this->add_table_of_contents( $content );
		$source_url = isset( $input['source_url'] ) ? esc_url( $input['source_url'] ) : '';
		if ( '' !== $source_url && false === strpos( $content, $source_url ) ) $content .= '<p><strong>Sumber rujukan:</strong> <a href="' . $source_url . '" target="_blank" rel="noopener">Lihat sumber resmi</a>.</p>';
		$opening = '<p>' . $prefix . esc_html( $lead_text ) . '</p>';
		if ( '' !== $host && false !== stripos( wp_strip_all_tags( $content ), $host ) ) return $content;
		return $opening . $content;
	}
	private function add_table_of_contents( $content ) {
		$items = array();
		$index = 0;
		$updated = preg_replace_callback( '/<h2([^>]*)>(.*?)<\/h2>/isu', function ( $match ) use ( &$items, &$index ) {
			$index++;
			$title = trim( wp_strip_all_tags( $match[2] ) );
			$id = 'bagian-' . $index . '-' . sanitize_title( $title );
			$items[] = '<li><a href="#' . esc_attr( $id ) . '">' . esc_html( $title ) . '</a></li>';
			$attributes = preg_replace( '/\s+id=("[^"]*"|\'[^\']*\')/i', '', $match[1] );
			return '<h2' . $attributes . ' id="' . esc_attr( $id ) . '">' . $match[2] . '</h2>';
		}, $content );
		if ( count( $items ) < 3 ) return $updated;
		$toc = '<!-- wp:rank-math/toc-block {"title":"Daftar Isi"} --><div class="wp-block-rank-math-toc-block" id="rank-math-table-of-contents"><h2>Daftar Isi</h2><nav><ul>' . implode( '', $items ) . '</ul></nav></div><!-- /wp:rank-math/toc-block -->';
		return $toc . $updated;
	}
	private function seo_audit( array $data, $keyword ) {
		$content_text = wp_strip_all_tags( $data['content'] );
		$checks = array(
			'keyword_in_title' => false !== stripos( $data['seo']['seo_title'], $keyword ),
			'keyword_in_description' => false !== stripos( $data['seo']['meta_description'], $keyword ),
			'keyword_in_slug' => false !== strpos( $data['seo']['slug'], sanitize_title( $keyword ) ),
			'keyword_in_content' => false !== stripos( $content_text, $keyword ),
			'keyword_in_heading' => (bool) preg_match( '/<h[2-4][^>]*>[^<]*' . preg_quote( $keyword, '/' ) . '/iu', $data['content'] ),
			'title_length' => strlen( $data['seo']['seo_title'] ) <= 60,
			'description_length' => strlen( $data['seo']['meta_description'] ) >= 120 && strlen( $data['seo']['meta_description'] ) <= 160,
			'slug_length' => strlen( $data['seo']['slug'] ) <= 60,
			'outbound_source_link' => (bool) preg_match( '/<a\b[^>]*href=["\']https?:\/\//i', $data['content'] ),
			'table_of_contents' => false !== strpos( $data['content'], 'wp-block-rank-math-toc-block' ),
			'sentiment_word_in_title' => (bool) preg_match( '/\b(terbaik|mudah|efektif|unggulan|penting|buruk|berbahaya)\b/iu', $data['seo']['seo_title'] ),
			'power_word_in_title' => (bool) preg_match( '/\b(ampuh|lengkap|praktis|rahasia|terbukti|wajib|panduan)\b/iu', $data['seo']['seo_title'] ),
		);
		$score = (int) round( count( array_filter( $checks ) ) / count( $checks ) * 100 );
		return array( 'score' => $score, 'target' => 85, 'passed' => $score >= 85, 'checks' => $checks, 'note' => __( 'Estimasi internal. Skor final tetap dihitung oleh Rank Math pada editor WordPress.', 'ai-news-assistant' ) );
	}
	private function editorial_dateline( array $editorial_data ) {
		$location = isset( $editorial_data['location'] ) ? sanitize_text_field( $editorial_data['location'] ) : '';
		if ( '' === $location ) return '';
		if ( preg_match( '/(?:Kecamatan|Kec\.?|Kelurahan|Desa)\s+([^,]+)/iu', $location, $match ) ) $location = $match[1];
		elseif ( preg_match( '/(?:Kabupaten|Kota)\s+([^,]+)/iu', $location, $match ) ) $location = $match[1];
		else $location = trim( explode( ',', $location )[0] );
		return function_exists( 'mb_convert_case' ) ? mb_convert_case( trim( $location ), MB_CASE_TITLE, 'UTF-8' ) : ucwords( strtolower( trim( $location ) ) );
	}
	private function limit_text( $text, $limit, $suffix = '' ) {
		$text = trim( (string) $text );
		$length = function_exists( 'mb_strlen' ) ? mb_strlen( $text, 'UTF-8' ) : strlen( $text );
		if ( $length <= $limit ) return $text;
		$cut = function_exists( 'mb_substr' ) ? mb_substr( $text, 0, $limit, 'UTF-8' ) : substr( $text, 0, $limit );
		$cut = preg_replace( '/\s+\S*$/u', '', $cut );
		return rtrim( $cut, " \t\n\r\0\x0B,.:;-" ) . $suffix;
	}
}
