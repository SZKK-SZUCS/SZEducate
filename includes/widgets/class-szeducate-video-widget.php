<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// Egy "url" (vagy "text") típusú séma-mezőben tárolt videó-hivatkozás megjelenítése
// beágyazott lejátszóként. YouTube / Vimeo / stb. a WordPress beépített oEmbed
// szolgáltatóin keresztül (nem kell hozzá API-kulcs), közvetlen .mp4/.webm fájl
// natív <video>-ként, minden más nyers hivatkozásként. A képarány / max. szélesség /
// igazítás / lekerekítés / árnyék Elementor-vezérlőkből állítható; az arány-doboz
// reszponzív (a lejátszó mindig kitölti).
class SZEducate_Video_Widget extends \Elementor\Widget_Base {

	public function get_name() { return 'szeducate_video'; }
	public function get_title() { return 'SZEducate Képzés Videó'; }
	public function get_icon() { return 'eicon-youtube'; }
	public function get_categories() { return [ 'general' ]; }

	private function get_url_fields() {
		$schema = json_decode( get_option( 'szeducate_local_schema', '[]' ), true );
		$out = array();
		if ( is_array( $schema ) ) {
			foreach ( $schema as $group ) {
				if ( empty( $group['fields'] ) || ! is_array( $group['fields'] ) ) continue;
				foreach ( $group['fields'] as $field ) {
					if ( ! isset( $field['key'], $field['type'] ) ) continue;
					if ( in_array( $field['type'], array( 'url', 'text' ), true ) ) {
						$out[ $field['key'] ] = ( isset( $field['label'] ) ? $field['label'] : $field['key'] ) . ' [' . $field['key'] . ']';
					}
				}
			}
		}
		return $out;
	}

	protected function register_controls() {

		$this->start_controls_section(
			'content_section',
			[ 'label' => 'Adat Forrás', 'tab' => \Elementor\Controls_Manager::TAB_CONTENT ]
		);

		$options = array_merge( array( '' => '-- Válassz mezőt --' ), $this->get_url_fields() );
		$this->add_control(
			'video_key',
			[
				'label'       => 'Videó Mező',
				'type'        => \Elementor\Controls_Manager::SELECT,
				'options'     => $options,
				'default'     => isset( $options['video'] ) ? 'video' : '',
				'description' => 'A Séma Tervezőben "Link" (url) típusúra állított mezők. A képzés adatlapján ide kerül a YouTube / Vimeo / közvetlen videó hivatkozás.',
			]
		);

		$this->add_control(
			'empty_text',
			[
				'label'       => 'Szöveg, ha nincs videó',
				'type'        => \Elementor\Controls_Manager::TEXT,
				'default'     => '',
				'description' => 'Üresen hagyva a widget nem jelenik meg, ha a képzéshez nincs videó megadva.',
			]
		);

		$this->end_controls_section();

		// ------------------------------------------------------------------
		// Stílus
		// ------------------------------------------------------------------
		$this->start_controls_section(
			'style_section',
			[ 'label' => 'Videó', 'tab' => \Elementor\Controls_Manager::TAB_STYLE ]
		);

		$this->add_control(
			'aspect_ratio',
			[
				'label'     => 'Képarány',
				'type'      => \Elementor\Controls_Manager::SELECT,
				'default'   => '56.25',
				'options'   => [
					'56.25'   => '16:9 (szélesvásznú)',
					'75'      => '4:3',
					'42.8571' => '21:9 (cinema)',
					'100'     => '1:1 (négyzet)',
					'177.7778'=> '9:16 (álló / shorts)',
				],
				'selectors' => [ '{{WRAPPER}} .sz-video-embed' => 'padding-bottom: {{VALUE}}%;' ],
			]
		);

		$this->add_responsive_control(
			'max_width',
			[
				'label'      => 'Maximális szélesség',
				'type'       => \Elementor\Controls_Manager::SLIDER,
				'size_units' => [ 'px', '%' ],
				'range'      => [ 'px' => [ 'min' => 160, 'max' => 1600 ], '%' => [ 'min' => 20, 'max' => 100 ] ],
				'default'    => [ 'unit' => '%', 'size' => 100 ],
				'selectors'  => [ '{{WRAPPER}} .sz-video-outer' => 'max-width: {{SIZE}}{{UNIT}};' ],
			]
		);

		$this->add_responsive_control(
			'align',
			[
				'label'     => 'Igazítás',
				'type'      => \Elementor\Controls_Manager::CHOOSE,
				'default'   => 'center',
				'options'   => [
					'flex-start' => [ 'title' => 'Balra', 'icon' => 'eicon-text-align-left' ],
					'center'     => [ 'title' => 'Középre', 'icon' => 'eicon-text-align-center' ],
					'flex-end'   => [ 'title' => 'Jobbra', 'icon' => 'eicon-text-align-right' ],
				],
				'selectors' => [ '{{WRAPPER}} .sz-video-wrap' => 'display: flex; justify-content: {{VALUE}};' ],
			]
		);

		$this->add_responsive_control(
			'border_radius',
			[
				'label'      => 'Lekerekítés',
				'type'       => \Elementor\Controls_Manager::DIMENSIONS,
				'size_units' => [ 'px', '%' ],
				'default'    => [ 'top' => 8, 'right' => 8, 'bottom' => 8, 'left' => 8, 'unit' => 'px' ],
				'selectors'  => [ '{{WRAPPER}} .sz-video-outer' => 'border-radius: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};' ],
			]
		);

		$this->add_group_control(
			\Elementor\Group_Control_Box_Shadow::get_type(),
			[ 'name' => 'video_shadow', 'selector' => '{{WRAPPER}} .sz-video-outer' ]
		);

		$this->end_controls_section();
	}

	protected function render() {
		$settings  = $this->get_settings_for_display();
		$video_key = ! empty( $settings['video_key'] ) ? trim( $settings['video_key'] ) : '';
		$is_edit   = \Elementor\Plugin::$instance->editor->is_edit_mode();

		if ( $video_key === '' ) {
			if ( $is_edit ) {
				echo '<div style="background:#f0f0f1; padding:10px; border:1px dashed #ccc; text-align:center;">SZEducate Képzés Videó<br><small>Válassz egy videó (Link típusú) mezőt a beállításokban.</small></div>';
			}
			return;
		}

		$post_id = get_the_ID();
		if ( ! $post_id || get_post_type( $post_id ) !== 'sz_course' ) {
			if ( $is_edit ) {
				echo '<div style="background:#f0f0f1; padding:10px; border:1px dashed #ccc; text-align:center;">SZEducate Képzés Videó (csak egy Képzés oldalán jelenik meg)</div>';
			}
			return;
		}

		require_once SZEDUCATE_PLUGIN_DIR . 'includes/class-szeducate-client.php';
		$data = SZEducate_Client::get_course_data_for_post( $post_id );
		$url  = ( is_array( $data ) && isset( $data[ $video_key ] ) ) ? trim( (string) $data[ $video_key ] ) : '';

		if ( $url === '' ) {
			if ( ! empty( $settings['empty_text'] ) ) {
				echo '<div class="sz-video-empty">' . esc_html( $settings['empty_text'] ) . '</div>';
			} elseif ( $is_edit ) {
				echo '<div style="background:#f0f0f1; padding:10px; border:1px dashed #ccc; text-align:center;">Ehhez a Képzéshez nincs videó megadva.</div>';
			}
			return;
		}

		$widget_id = 'sz-video-' . $this->get_id();
		$player    = $this->build_player( $url );
		?>
		<div class="sz-video-wrap" id="<?php echo esc_attr( $widget_id ); ?>">
			<div class="sz-video-outer">
				<?php if ( $player !== '' ) : ?>
					<div class="sz-video-embed"><?php echo $player; // már szűrt / előállított markup ?></div>
				<?php else : ?>
					<a class="sz-video-fallback" href="<?php echo esc_url( $url ); ?>" target="_blank" rel="noopener noreferrer"><?php echo esc_html( $url ); ?></a>
				<?php endif; ?>
			</div>
		</div>
		<style>
			/* Alap képarány / igazítás: nem scope-olt, alacsony specificitású, hogy a
			   "Képarány" ill. "Igazítás" Stílus-vezérlő ({{WRAPPER}} …) felül tudja írni. */
			.sz-video-embed { padding-bottom: 56.25%; }
			.sz-video-wrap { display: flex; justify-content: center; }

			/* Csak szerkezet - a képarányt / méretet / igazítást a Stílus-vezérlők adják. */
			#<?php echo esc_attr( $widget_id ); ?> .sz-video-wrap { width: 100%; }
			#<?php echo esc_attr( $widget_id ); ?> .sz-video-outer { width: 100%; overflow: hidden; }
			#<?php echo esc_attr( $widget_id ); ?> .sz-video-embed { position: relative; width: 100%; height: 0; }
			#<?php echo esc_attr( $widget_id ); ?> .sz-video-embed iframe,
			#<?php echo esc_attr( $widget_id ); ?> .sz-video-embed video,
			#<?php echo esc_attr( $widget_id ); ?> .sz-video-embed object,
			#<?php echo esc_attr( $widget_id ); ?> .sz-video-embed embed {
				position: absolute; top: 0; left: 0; width: 100%; height: 100%; border: 0;
			}
		</style>
		<?php
	}

	// Az URL-ből lejátszható markupot állít elő: előbb a WP beépített oEmbed
	// szolgáltatói (YouTube, Vimeo, Dailymotion, ...), majd közvetlen videófájl
	// natív <video>-ként. Ha egyik sem, üres stringet ad (a render() ilyenkor
	// egyszerű hivatkozást tesz ki).
	private function build_player( $url ) {
		$oembed = wp_oembed_get( $url, array( 'width' => 1280 ) );
		if ( is_string( $oembed ) && $oembed !== '' ) {
			// Az oEmbed-kimenet megbízható szolgáltatói iframe - de a biztonság kedvéért
			// engedjük át egy szűk kses-en (iframe + a szokásos attribútumai).
			return wp_kses( $oembed, array(
				'iframe' => array(
					'src' => array(), 'width' => array(), 'height' => array(), 'title' => array(),
					'frameborder' => array(), 'allow' => array(), 'allowfullscreen' => array(),
					'loading' => array(), 'referrerpolicy' => array(), 'sandbox' => array(),
					'class' => array(), 'style' => array(),
				),
			) );
		}

		$path = (string) wp_parse_url( $url, PHP_URL_PATH );
		$ext  = strtolower( pathinfo( $path, PATHINFO_EXTENSION ) );
		if ( in_array( $ext, array( 'mp4', 'webm', 'ogv', 'ogg', 'mov', 'm4v' ), true ) ) {
			return '<video controls playsinline preload="metadata" src="' . esc_url( $url ) . '"></video>';
		}

		return '';
	}
}
