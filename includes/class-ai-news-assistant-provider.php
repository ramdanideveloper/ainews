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
}
