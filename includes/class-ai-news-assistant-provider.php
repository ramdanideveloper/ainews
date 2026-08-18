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
		$data['seo'] = wp_parse_args( (array) $data['seo'], array(
			'seo_title' => $data['main_title'], 'meta_description' => '', 'slug' => sanitize_title( $data['main_title'] ),
			'focus_keyword' => '', 'tags' => array(), 'category_suggestion' => 'Berita',
		) );
		$data['seo']['tags'] = $this->normalize_list( $data['seo']['tags'] );
		$data = $this->optimize_rank_math_seo( $data );
		$data['social_captions'] = wp_parse_args( (array) $data['social_captions'], array(
			'instagram_facebook' => '', 'twitter_x' => '', 'whatsapp_telegram' => '',
		) );
		return $data;
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
	private function optimize_rank_math_seo( array $data ) {
		$title = sanitize_text_field( $data['main_title'] );
		$words = preg_split( '/\s+/u', trim( $title ) );
		$keyword_words = array_slice( array_filter( $words ), 0, 4 );
		$keyword = trim( implode( ' ', $keyword_words ), " \t\n\r\0\x0B,.:;!?-" );
		if ( function_exists( 'mb_strtolower' ) ) $keyword = mb_strtolower( $keyword, 'UTF-8' );
		else $keyword = strtolower( $keyword );
		$keyword = $this->limit_text( $keyword, 45, '' );
		if ( '' === $keyword ) $keyword = sanitize_text_field( $data['seo']['focus_keyword'] );

		$seo_title = sanitize_text_field( $data['seo']['seo_title'] );
		if ( false === stripos( $seo_title, $keyword ) ) $seo_title = $keyword . ': ' . $seo_title;
		$data['seo']['seo_title'] = $this->limit_text( $seo_title, 60 );
		$data['seo']['focus_keyword'] = $keyword;

		$description = sanitize_text_field( $data['seo']['meta_description'] );
		if ( false === stripos( $description, $keyword ) ) $description = ucfirst( $keyword ) . '. ' . $description;
		$data['seo']['meta_description'] = $this->limit_text( $description, 155 );

		$slug = sanitize_title( $keyword . ' ' . $data['seo']['slug'] );
		$data['seo']['slug'] = trim( $this->limit_text( $slug, 60, '' ), '-' );

		$data['content'] = $this->format_editorial_content( $data['content'], $data['lead'], $input );
		return $data;
	}
	private function format_editorial_content( $content, $lead, array $input ) {
		$content = (string) $content;
		$content = preg_replace( '/<h[1-3][^>]*>\s*[^<]*(?:fakta utama|ringkasan utama|poin utama)[^<]*<\/h[1-3]>\s*/iu', '', $content );
		$host = (string) wp_parse_url( home_url( '/' ), PHP_URL_HOST );
		$host = preg_replace( '/^www\./i', '', $host );
		$location = $this->editorial_dateline( isset( $input['editorial_data'] ) ? (array) $input['editorial_data'] : array() );
		$prefix = '<strong><a href="' . esc_url( home_url( '/' ) ) . '">' . esc_html( $host ) . '</a>';
		if ( '' !== $location ) $prefix .= ' | ' . esc_html( $location );
		$prefix .= '</strong> – ';
		$lead_text = trim( wp_strip_all_tags( (string) $lead ) );
		$opening = '<p>' . $prefix . esc_html( $lead_text ) . '</p>';
		if ( '' !== $host && false !== stripos( wp_strip_all_tags( $content ), $host ) ) return $content;
		return $opening . $content;
	}
	private function editorial_dateline( array $editorial_data ) {
		$location = isset( $editorial_data['location'] ) ? sanitize_text_field( $editorial_data['location'] ) : '';
		if ( '' === $location ) return '';
		if ( preg_match( '/(?:Kecamatan|Kec\.?|Kelurahan|Desa)\s+([^,]+)/iu', $location, $match ) ) $location = $match[1];
		elseif ( preg_match( '/(?:Kabupaten|Kota)\s+([^,]+)/iu', $location, $match ) ) $location = $match[1];
		else $location = trim( explode( ',', $location )[0] );
		return function_exists( 'mb_strtoupper' ) ? mb_strtoupper( trim( $location ), 'UTF-8' ) : strtoupper( trim( $location ) );
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
