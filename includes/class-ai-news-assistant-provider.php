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

		$plain_content = wp_strip_all_tags( $data['content'] );
		if ( false === stripos( substr( $plain_content, 0, 250 ), $keyword ) ) {
			$replacement = '<p$1><strong>' . esc_html( ucfirst( $keyword ) ) . '</strong> — ';
			$data['content'] = preg_replace( '/<p([^>]*)>/i', $replacement, $data['content'], 1, $replaced );
			if ( empty( $replaced ) ) $data['content'] = '<p><strong>' . esc_html( ucfirst( $keyword ) ) . '</strong></p>' . $data['content'];
		}
		if ( ! preg_match( '/<h2[^>]*>[^<]*' . preg_quote( $keyword, '/' ) . '[^<]*<\/h2>/iu', $data['content'] ) ) {
			$heading = '<h2>' . esc_html( ucfirst( $keyword ) ) . ': fakta utama</h2>';
			$data['content'] = preg_replace( '/<\/p>/i', '</p>' . $heading, $data['content'], 1, $heading_added );
			if ( empty( $heading_added ) ) $data['content'] .= $heading;
		}
		return $data;
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
