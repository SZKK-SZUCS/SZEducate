<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// A sima "Képzés Adat" Dynamic Tag (lásd class-szeducate-dynamic-tag.php) csak
// SZÖVEG kategóriás - Elementor Kép widgetjének dinamikus forrás mezője viszont
// KÉP kategóriás tag-et vár, ami egy {id, url} tömböt ad vissza, nem sima szöveget.
// E nélkül egy "image" típusú séma-mező URL-je csak nyers szövegként volt kiírható
// (pl. egy Szöveg widgetben), tényleges <img> elemként sehogy nem volt megjeleníthető.
// Ez a külön tag teszi lehetővé, hogy a beépített Kép widgetet (a maga teljes
// vágás/lightbox/stílus vezérlőivel) használjuk kép-mezőkhöz, egy saját, korlátozott
// "Kép Widget" újraírása helyett.
class SZEducate_Image_Dynamic_Tag extends \Elementor\Core\DynamicTags\Data_Tag {

	public function get_name() {
		return 'szeducate-course-image';
	}

	public function get_title() {
		return 'Képzés Kép (SZEducate)';
	}

	public function get_group() {
		return 'szeducate';
	}

	public function get_categories() {
		return array( \Elementor\Modules\DynamicTags\Module::IMAGE_CATEGORY );
	}

	protected function register_controls() {
		$schema_json = get_option( 'szeducate_local_schema', '[]' );
		$schema = json_decode( $schema_json, true );

		$options = array( '' => 'Válassz mezőt...' );

		if ( is_array( $schema ) ) {
			foreach ( $schema as $group ) {
				if ( empty( $group['fields'] ) ) continue;
				foreach ( $group['fields'] as $field ) {
					if ( isset( $field['type'] ) && $field['type'] === 'image' && isset( $field['key'] ) ) {
						$options[ $field['key'] ] = $field['label'];
					}
				}
			}
		}

		$this->add_control(
			'field_key',
			array(
				'label'       => 'Megjelenítendő Kép Mező',
				'type'        => \Elementor\Controls_Manager::SELECT,
				'options'     => $options,
				'default'     => '',
				'description' => 'Csak a Séma Tervezőben "Kép" (image) típusúra állított mezők jelennek meg itt.',
			)
		);
	}

	public function get_value( array $options = array() ) {
		$field_key = $this->get_settings( 'field_key' );
		if ( empty( $field_key ) ) return array( 'id' => 0, 'url' => '' );

		$post_id = get_the_ID();
		if ( ! $post_id || get_post_type( $post_id ) !== 'sz_course' ) return array( 'id' => 0, 'url' => '' );

		require_once SZEDUCATE_PLUGIN_DIR . 'includes/class-szeducate-client.php';
		$data = SZEducate_Client::get_course_data_for_post( $post_id );
		if ( ! is_array( $data ) || empty( $data[ $field_key ] ) || ! is_string( $data[ $field_key ] ) ) {
			return array( 'id' => 0, 'url' => '' );
		}

		// A kép-mezők a feltöltött médium URL-jét tárolják, nem a melléklet ID-ját (lásd
		// ImageUploadControl a szerkesztőben) - attachment ID hiányában az Elementor Kép
		// widget a regisztrált képméreteket (vágás stb.) nem tudja alkalmazni, az eredeti
		// fájlt jeleníti meg. Ez elfogadható korlát, cserébe nem kell duplikálni a médiumot.
		return array(
			'id'  => 0,
			'url' => esc_url_raw( $data[ $field_key ] ),
		);
	}
}
