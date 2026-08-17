<?php
defined( 'ABSPATH' ) || exit;

class AI_News_Assistant_Mock_Provider extends AI_News_Assistant_Provider_Base {
	public function is_configured() { return true; }
	public function detect_news_type( $title ) {
		$lower = strtolower( remove_accents( $title ) );
		$type = 'explainer'; $subtype = 'Informasi / Analisis'; $confidence = 68;
		if ( preg_match( '/laka|kecelakaan|tabrak|korban|celurit|diamuk|polisi|warga|kebakaran|banjir|longsor|gempa|pencurian|begal|kriminal/', $lower ) ) {
			$type = 'incident'; $confidence = 91;
			if ( preg_match( '/banjir|longsor|gempa|kebakaran/', $lower ) ) $subtype = 'Bencana / Keadaan Darurat';
			elseif ( preg_match( '/laka|kecelakaan|tabrak/', $lower ) ) $subtype = 'Laka Lantas';
			else $subtype = 'Kriminal / Keamanan Warga';
		} elseif ( preg_match( '/pemkot|pemkab|pemerintah|dinas|menteri|program|kebijakan|anggaran|bupati|wali kota|gubernur/', $lower ) ) { $type = 'government'; $subtype = 'Program Publik / Kebijakan'; $confidence = 88;
		} elseif ( preg_match( '/umkm|bisnis|usaha|ekonomi|pasar|harga|investasi|perusahaan/', $lower ) ) { $type = 'business'; $subtype = 'Ekonomi / Bisnis / UMKM'; $confidence = 86;
		} elseif ( preg_match( '/festival|agenda|event|lomba|konser|pameran|seminar|digelar/', $lower ) ) { $type = 'event'; $subtype = 'Event / Agenda'; $confidence = 84;
		} elseif ( preg_match( '/profil|sosok|kisah|inspiratif|perjalanan hidup/', $lower ) ) { $type = 'feature'; $subtype = 'Feature / Profil Tokoh'; $confidence = 82;
		} elseif ( preg_match( '/advertorial|promo|peluncuran produk|sponsored/', $lower ) ) { $type = 'advertorial'; $subtype = 'Advertorial / Konten Mitra'; $confidence = 90; }
		$labels = array( 'incident' => 'Peristiwa', 'government' => 'Pemerintahan', 'business' => 'Bisnis', 'feature' => 'Feature', 'event' => 'Event', 'advertorial' => 'Advertorial', 'explainer' => 'Explainer' );
		$needed = array(
			'incident' => array( 'Lokasi dan waktu kejadian', 'Kronologi dari lebih dari satu sumber', 'Keterangan polisi/aparat', 'Kondisi korban atau pihak terlibat' ),
			'government' => array( 'Nama program dan instansi', 'Target penerima', 'Jadwal dan lokasi', 'Sumber resmi atau dokumen kebijakan' ),
			'business' => array( 'Pelaku usaha', 'Data transaksi/omzet', 'Konteks pasar', 'Sumber data ekonomi' ),
			'feature' => array( 'Identitas dan latar tokoh', 'Kutipan langsung', 'Tonggak perjalanan', 'Verifikasi pihak lain' ),
			'event' => array( 'Penyelenggara', 'Waktu dan lokasi', 'Susunan agenda', 'Kontak atau sumber resmi' ),
			'advertorial' => array( 'Nama brand/produk', 'Klaim yang dapat dibuktikan', 'Informasi sponsor', 'Call to action' ),
			'explainer' => array( 'Pertanyaan utama', 'Latar belakang', 'Data pendukung', 'Pendapat ahli/sumber primer' ),
		);
		$warnings = array( 'Pastikan seluruh nama, angka, waktu, dan kutipan diverifikasi sebelum publikasi.' );
		if ( 'incident' === $type ) { $warnings[] = 'Hindari menyebut identitas anak di bawah umur, korban, atau terduga pelaku jika belum terverifikasi dan tidak berkepentingan publik.'; $warnings[] = 'Gunakan asas praduga tak bersalah dan minta konfirmasi aparat.'; }
		return array( 'news_type' => $type, 'news_type_label' => $labels[ $type ], 'subtype' => $subtype, 'confidence' => $confidence, 'required_data' => $needed[ $type ], 'warnings' => $warnings, 'review_status' => 'Needs Verification' );
	}

	public function generate( array $input ) {
		$topic  = ! empty( $input['title'] ) ? $input['title'] : ( ! empty( $input['topic'] ) ? $input['topic'] : __( 'Perkembangan Terkini', 'ai-news-assistant' ) );
		$editorial = ! empty( $input['editorial_data'] ) && is_array( $input['editorial_data'] ) ? array_filter( $input['editorial_data'] ) : array();
		$style  = ! empty( $input['style'] ) ? $input['style'] : 'hard_news';
		$source = ! empty( $input['source'] ) || ! empty( $editorial['source_information'] ) || ! empty( $editorial['official_source'] );
		$labels = array( 'hard_news' => 'Hard News', 'soft_news' => 'Soft News', 'feature' => 'Feature', 'press_release' => 'Press Release Rewrite', 'seo_news' => 'SEO News' );
		$tone   = isset( $labels[ $style ] ) ? $labels[ $style ] : 'Hard News';
		$count  = array( 'short' => 3, 'medium' => 5, 'long' => 8 );
		$total  = isset( $count[ $input['length'] ] ) ? $count[ $input['length'] ] : 5;
		$paragraphs = array(
			sprintf( 'Perkembangan mengenai %s menjadi perhatian setelah informasi terbaru disampaikan kepada publik. Redaksi merangkum informasi awal ini dengan pendekatan %s.', $topic, $tone ),
			sprintf( 'Berdasarkan bahan yang tersedia, %s memiliki sejumlah aspek penting yang perlu dilihat secara utuh. Detail waktu, lokasi, dan pihak terkait harus dicocokkan kembali dengan sumber resmi.', $topic ),
			'Pihak yang berkepentingan diharapkan memberikan data pendukung agar konteks berita tidak menimbulkan tafsir keliru. Editor juga perlu meminta konfirmasi dari pihak utama sebelum publikasi.',
			'Dampak perkembangan ini akan bergantung pada tindak lanjut dan keterangan resmi berikutnya. Informasi tambahan dapat mengubah sudut pandang maupun kesimpulan awal.',
			'Redaksi akan memperbarui naskah ketika fakta baru yang terverifikasi tersedia. Pembaca disarankan merujuk pada kanal resmi untuk perkembangan lanjutan.',
			'Dalam proses editorial, angka, nama, jabatan, serta kutipan langsung wajib diperiksa silang. Langkah tersebut penting untuk menjaga akurasi dan keberimbangan pemberitaan.',
			'Konteks historis juga perlu ditambahkan apabila relevan agar pembaca memahami posisi perkembangan terbaru dibandingkan peristiwa sebelumnya.',
			'Naskah ini merupakan draft awal berbantuan AI dan belum ditujukan untuk publikasi tanpa peninjauan manusia.',
		);
		if ( $editorial ) {
			$fact_lines = array();
			foreach ( $editorial as $key => $value ) { if ( ! in_array( $key, array( 'unconfirmed_data', 'source_information', 'official_source' ), true ) ) $fact_lines[] = ucwords( str_replace( '_', ' ', $key ) ) . ': ' . $value; }
			if ( $fact_lines ) $paragraphs[1] = 'Berdasarkan data yang dihimpun redaksi, ' . implode( '. ', array_slice( $fact_lines, 0, 4 ) ) . '.';
		}
		$content = '';
		foreach ( array_slice( $paragraphs, 0, $total ) as $paragraph ) { $content .= '<p>' . esc_html( $paragraph ) . '</p>'; }
		$missing_authority = 'incident' === $input['news_type'] && empty( $editorial['authority_statement'] );
		$status = $source && count( $editorial ) >= 3 && ! $missing_authority ? 'Ready' : 'Needs Verification';
		return $this->normalize( array(
			'main_title' => sprintf( '%s: Fakta dan Perkembangan Terbaru', $topic ),
			'alternative_titles' => array( sprintf( 'Sorotan Utama tentang %s', $topic ), sprintf( 'Apa yang Perlu Diketahui dari %s', $topic ), sprintf( 'Update %s dan Langkah Selanjutnya', $topic ) ),
			'lead' => $paragraphs[0], 'content' => $content,
			'summary_points' => array( 'Informasi terbaru telah menjadi perhatian publik.', 'Konfirmasi pihak terkait masih diperlukan.', 'Editor wajib memeriksa data sebelum publikasi.' ),
			'verification_notes' => 'Ready' === $status ? array( 'Cocokkan kutipan dan angka dengan sumber.' ) : array_values( array_filter( array( ! $source ? 'Sumber belum tersedia atau belum jelas.' : '', $missing_authority ? 'Perlu konfirmasi polisi/aparat.' : '', empty( $editorial['location'] ) ? 'Lokasi belum lengkap.' : '', empty( $editorial['event_time'] ) && empty( $editorial['schedule'] ) ? 'Waktu belum lengkap.' : '' ) ) ),
			'fact_checklist' => array( 'who' => ! empty( $editorial['involved_parties'] ) ? $editorial['involved_parties'] : ( ! empty( $editorial['official'] ) ? $editorial['official'] : 'Pihak terkait perlu dikonfirmasi' ), 'what' => $topic, 'when' => ! empty( $editorial['event_time'] ) ? $editorial['event_time'] : ( ! empty( $editorial['schedule'] ) ? $editorial['schedule'] : 'Perlu diverifikasi' ), 'where' => ! empty( $editorial['location'] ) ? $editorial['location'] : 'Perlu diverifikasi', 'why' => ! empty( $editorial['temporary_cause'] ) ? $editorial['temporary_cause'] : 'Perlu keterangan sumber', 'how' => ! empty( $editorial['chronology'] ) ? $editorial['chronology'] : 'Perlu data pendukung', 'source_available' => $source, 'needs_verification' => 'Ready' !== $status ),
			'seo' => array( 'seo_title' => sprintf( '%s: Perkembangan Terbaru', $topic ), 'meta_description' => sprintf( 'Simak fakta, konteks, dan perkembangan terbaru mengenai %s yang perlu diketahui.', $topic ), 'slug' => sanitize_title( $topic . '-perkembangan-terbaru' ), 'focus_keyword' => $topic, 'tags' => array( $topic, 'berita terbaru', 'update' ), 'category_suggestion' => 'Berita Terkini' ),
			'social_captions' => array( 'instagram_facebook' => sprintf( 'Perkembangan terbaru tentang %s. Simak rangkuman fakta dan konteksnya. #BeritaTerkini', $topic ), 'twitter_x' => sprintf( 'Update %s: fakta utama dan hal yang masih perlu diverifikasi.', $topic ), 'whatsapp_telegram' => sprintf( 'Berita terbaru: %s. Baca rangkuman dan perkembangan selengkapnya.', $topic ) ),
			'review_status' => $status,
		), $input );
	}
}
