<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class SZEducate_Schema {

	private $option_name = 'szeducate_schema';

	public function init() {
		add_action( 'admin_menu', array( $this, 'add_schema_page' ) );
		add_action( 'admin_post_szeducate_migrate_variansok', array( $this, 'handle_migrate_variansok' ) );
	}

	// A munkarend / nyelv / finanszírozási forma mezők tárolhatnak JSON tömböt (checkbox,
	// multiszelekt) VAGY pontosvesszővel elválasztott sima szöveget is - ez mindkettőt
	// egységes, tisztított string-tömbbé alakítja.
	private function migrate_to_list( $val ) {
		$out = array();
		if ( is_array( $val ) ) {
			foreach ( $val as $v ) {
				$v = trim( (string) $v );
				if ( $v !== '' && ! in_array( $v, $out, true ) ) $out[] = $v;
			}
			return $out;
		}
		$val = trim( (string) $val );
		if ( $val === '' ) return $out;
		foreach ( explode( ';', $val ) as $v ) {
			$v = trim( $v );
			if ( $v !== '' && ! in_array( $v, $out, true ) ) $out[] = $v;
		}
		return $out;
	}

	private function find_schema_field( $schema, $key ) {
		if ( ! is_array( $schema ) ) return null;
		foreach ( $schema as $group ) {
			if ( empty( $group['fields'] ) ) continue;
			foreach ( $group['fields'] as $field ) {
				if ( $field['key'] === $key ) return $field;
			}
		}
		return null;
	}

	private function find_schema_subfield( $sub_fields, $key ) {
		if ( ! is_array( $sub_fields ) ) return null;
		foreach ( $sub_fields as $sf ) {
			if ( $sf['key'] === $key ) return $sf;
		}
		return null;
	}

	private function schema_field_options( $field_def ) {
		if ( empty( $field_def['options'] ) ) return array();
		return array_map( 'trim', explode( ';', $field_def['options'] ) );
	}

	// A WordPress SelectControl csak KARAKTERRE PONTOS egyezésnél jelöli ki az opciót -
	// ez az érték szövegét a séma ténylegesen deklarált Dropdown-opciói közül a
	// legközelebbihez igazítja (kis-nagybetűtől és résztartalmazástól függetlenül), hogy a
	// szerkesztőben tényleg kiválasztva jelenjen meg, ne "Válassz..."-on ragadjon.
	private function align_to_option( $raw_value, $options ) {
		$raw_value = trim( (string) $raw_value );
		if ( $raw_value === '' || empty( $options ) ) return $raw_value;
		foreach ( $options as $opt ) {
			$opt = trim( (string) $opt );
			if ( $opt === '' ) continue;
			if ( mb_strtolower( $opt, 'UTF-8' ) === mb_strtolower( $raw_value, 'UTF-8' )
				|| mb_stripos( $raw_value, $opt, 0, 'UTF-8' ) !== false
				|| mb_stripos( $opt, $raw_value, 0, 'UTF-8' ) !== false
			) {
				return $opt;
			}
		}
		return $raw_value;
	}

	// EGYSZERI MIGRÁCIÓS ESZKÖZ: a régi lapos munkarend/nyelv/finanszírozási forma/önköltség
	// mezőkből felépíti az új "munkarend_csoportok" beágyazott struktúrát, a jelenlegi ÉLŐ
	// adatbázison (nem egy régi mentési fájlon), hogy elkerüljük az elavult adatból eredő
	// hibákat. Nem tudja visszaadni azt a részletet, hogy pl. Nappalin csak angolul indul-e
	// a képzés (ez a régi adatszerkezetben sosem volt eltárolva) - ezért minden munkarendhez
	// az összes rögzített nyelv × finanszírozási forma kombinációt felveszi, ezeket utólag
	// kézzel kell finomítani a Kliens szerkesztőben, ahol ez tényleg eltér.
	//
	// Ugyanez a lefutás minden Képzésnél (akár most épült a "munkarend_csoportok", akár
	// korábban) megtisztítja az Állami finanszírozású sorok Ár típusa / Összeg mezőit, ÉS
	// a séma ténylegesen mentett Dropdown-opcióihoz igazítja a Finanszírozási forma / Ár
	// típusa szövegét - így egy korábbi futásból származó, el nem találó szöveg is
	// automatikusan javításra kerül újrafuttatáskor. A gomb emiatt bátran újra futtatható.
	public function handle_migrate_variansok() {
		if ( ! current_user_can( 'manage_options' ) || ! wp_verify_nonce( $_POST['_wpnonce'] ?? '', 'szeducate_migrate_variansok' ) ) {
			wp_die( 'Nincs jogosultságod.' );
		}

		$groups_key         = 'munkarend_csoportok';
		$sub_munkarend_key  = 'munkarend';
		$sub_variants_key   = 'variansok';
		$nested_nyelv_key   = 'nyelv';
		$nested_finance_key = 'finanszirozasi_forma';
		$nested_price_key   = 'ar_tipus';
		$nested_amount_key  = 'osszeg';
		$state_value        = 'Állami';

		// FONTOS: a Hub saját, ténylegesen szerkesztett sémája a "szeducate_schema" opció alatt
		// van (lásd $this->option_name), NEM a "szeducate_local_schema" alatt - az utóbbi a
		// Kliens oldali szinkronizált másolat neve, ami a Hub-on rendszerint üresen áll.
		$schema_json = get_option( $this->option_name, '[]' );
		$schema = json_decode( $schema_json, true );

		$top_field_def     = $this->find_schema_field( $schema, $groups_key );
		$top_sub_fields    = ! empty( $top_field_def['sub_fields'] ) ? $top_field_def['sub_fields'] : array();
		$variants_def      = $this->find_schema_subfield( $top_sub_fields, $sub_variants_key );
		$nested_sub_fields = ! empty( $variants_def['sub_fields'] ) ? $variants_def['sub_fields'] : array();

		$finance_field_def = $this->find_schema_subfield( $nested_sub_fields, $nested_finance_key );
		$finance_options   = $finance_field_def ? $this->schema_field_options( $finance_field_def ) : array();

		$price_field_def   = $this->find_schema_subfield( $nested_sub_fields, $nested_price_key );
		$price_options     = $price_field_def ? $this->schema_field_options( $price_field_def ) : array();

		// A "Félév"/"Teljes" jelentésű opciót a deklarált sorrend alapján azonosítjuk (ahogy
		// az útmutatóban is szerepelt: "Félév; Teljes") - ha a séma üres/hiányos, marad a szöveges alapértelmezés.
		$felev_label  = isset( $price_options[0] ) ? $price_options[0] : 'Félév';
		$teljes_label = isset( $price_options[1] ) ? $price_options[1] : 'Teljes';

		global $wpdb;
		$table = $wpdb->prefix . 'szeducate_courses_data';
		$rows = $wpdb->get_results( "SELECT id, course_data FROM $table", ARRAY_A );

		$migrated = 0;
		$skipped_existing = 0;
		$skipped_empty = 0;
		$normalized = 0;
		$synced_ids = array();

		foreach ( $rows as $row ) {
			$data = json_decode( $row['course_data'], true );
			if ( ! is_array( $data ) ) continue;

			$changed = false;
			$already_had_groups = ! empty( $data[ $groups_key ] );

			if ( ! $already_had_groups ) {
				$munkarend_list = $this->migrate_to_list( $data['munkarend'] ?? '' );
				if ( empty( $munkarend_list ) ) { $skipped_empty++; continue; }

				$nyelv_list = $this->migrate_to_list( $data['nyelv'] ?? '' );
				if ( empty( $nyelv_list ) ) $nyelv_list = array( '' );

				$finance_list = $this->migrate_to_list( $data['finanszirozasi_forma'] ?? '' );
				if ( empty( $finance_list ) ) $finance_list = array( '' );
				$finance_list = array_map( function( $f ) use ( $finance_options ) {
					return $this->align_to_option( $f, $finance_options );
				}, $finance_list );

				// A régi rendszerben soha nem volt egyszerre kitöltve mindkettő - ha mégis
				// (régi, tisztázatlan adat), a félévenkénti önköltséget vesszük irányadónak.
				$onkoltseg = $data['onkoltseg'] ?? '';
				$teljes = $data['kepzes-teljes-koltsege'] ?? '';

				if ( $onkoltseg !== '' && $onkoltseg !== null ) {
					$price_type = $felev_label;
					$amount = $onkoltseg;
				} elseif ( $teljes !== '' && $teljes !== null ) {
					$price_type = $teljes_label;
					$amount = $teljes;
				} else {
					$price_type = '';
					$amount = '';
				}

				$groups = array();
				foreach ( $munkarend_list as $m ) {
					$variansok = array();
					foreach ( $nyelv_list as $ny ) {
						foreach ( $finance_list as $ff ) {
							$variansok[] = array(
								$nested_nyelv_key   => $ny,
								$nested_finance_key => $ff,
								$nested_price_key   => $price_type,
								$nested_amount_key  => $amount,
							);
						}
					}
					$groups[] = array(
						$sub_munkarend_key => $m,
						$sub_variants_key  => $variansok,
					);
				}

				$data[ $groups_key ] = $groups;
				$migrated++;
				$changed = true;
			} else {
				$skipped_existing++;
			}

			// Normalizálás: Finanszírozási forma / Ár típusa szövegének igazítása a séma
			// tényleges opcióihoz, és az Állami sorok ár-mezőinek törlése - az imént épített,
			// VAGY a korábban már meglévő "munkarend_csoportok" adatban egyaránt.
			if ( is_array( $data[ $groups_key ] ) ) {
				foreach ( $data[ $groups_key ] as &$group ) {
					if ( ! is_array( $group ) || empty( $group[ $sub_variants_key ] ) || ! is_array( $group[ $sub_variants_key ] ) ) continue;
					foreach ( $group[ $sub_variants_key ] as &$variant ) {
						if ( ! is_array( $variant ) ) continue;

						if ( isset( $variant[ $nested_finance_key ] ) && $variant[ $nested_finance_key ] !== '' ) {
							$aligned = $this->align_to_option( $variant[ $nested_finance_key ], $finance_options );
							if ( $aligned !== $variant[ $nested_finance_key ] ) {
								$variant[ $nested_finance_key ] = $aligned;
								$changed = true;
								if ( $already_had_groups ) $normalized++;
							}
						}

						$fv = isset( $variant[ $nested_finance_key ] ) ? (string) $variant[ $nested_finance_key ] : '';
						$is_state_row = ( $fv !== '' && mb_stripos( $fv, $state_value, 0, 'UTF-8' ) !== false );
						$has_price = ! empty( $variant[ $nested_price_key ] ) || ( isset( $variant[ $nested_amount_key ] ) && $variant[ $nested_amount_key ] !== '' );

						if ( $is_state_row && $has_price ) {
							$variant[ $nested_price_key ] = '';
							$variant[ $nested_amount_key ] = '';
							$changed = true;
							if ( $already_had_groups ) $normalized++;
						} elseif ( ! $is_state_row && isset( $variant[ $nested_price_key ] ) && $variant[ $nested_price_key ] !== '' ) {
							$aligned_pt = $this->align_to_option( $variant[ $nested_price_key ], $price_options );
							if ( $aligned_pt !== $variant[ $nested_price_key ] ) {
								$variant[ $nested_price_key ] = $aligned_pt;
								$changed = true;
								if ( $already_had_groups ) $normalized++;
							}
						}
					}
					unset( $variant );
				}
				unset( $group );
			}

			if ( ! $changed ) continue;

			$wpdb->update( $table, array( 'course_data' => wp_json_encode( $data, JSON_UNESCAPED_UNICODE ) ), array( 'id' => $row['id'] ) );
			$synced_ids[] = intval( $row['id'] );
		}

		// A Hub saját másolatának frissítése után a Klienseket is szinkronizálni kell - EGYETLEN
		// kötegelt eseménybe csomagolva, hogy ne 1 db képzésenkénti wp-cron esemény terhelje le
		// egyszerre a szervert, hanem egy rendezett, egymás utáni feldolgozás fusson le.
		if ( ! empty( $synced_ids ) ) {
			wp_schedule_single_event( time(), 'szeducate_dispatch_course_webhook_batch', array( $synced_ids ) );
		}

		wp_redirect( add_query_arg( array(
			'page'             => 'szeducate-schema',
			'migrate_msg'      => 'done',
			'migrated'         => $migrated,
			'skipped_existing' => $skipped_existing,
			'skipped_empty'    => $skipped_empty,
			'normalized'       => $normalized,
		), admin_url( 'admin.php' ) ) );
		exit;
	}

	public function add_schema_page() {
		add_submenu_page(
			'szeducate-settings',
			'Séma Tervező',
			'Séma Tervező',
			'manage_options',
			'szeducate-schema',
			array( $this, 'render_page' )
		);
	}

	public function render_page() {
		if ( isset( $_POST['szeducate_schema'] ) && isset( $_POST['szeducate_schema_nonce'] ) ) {
			if ( wp_verify_nonce( $_POST['szeducate_schema_nonce'], 'save_szeducate_schema_action' ) ) {
				$new_schema = wp_unslash( $_POST['szeducate_schema'] );
				update_option( $this->option_name, $new_schema );

				require_once SZEDUCATE_PLUGIN_DIR . 'includes/class-szeducate-activator.php';
				SZEducate_Activator::update_database_schema();

				global $wpdb;
				$table_name = $wpdb->prefix . 'szeducate_clients';
				
				if ( $wpdb->get_var( "SHOW TABLES LIKE '{$table_name}'" ) == $table_name ) {
					$clients = $wpdb->get_results( "SELECT client_url, api_token FROM {$table_name} WHERE client_url != '' AND enabled = 1" );
					foreach ( $clients as $client ) {
						$webhook_url = rtrim( $client->client_url, '/' ) . '/wp-json/szeducate/v1/client/sync';
						wp_remote_post( $webhook_url, array(
							'blocking' => false,
							'timeout'  => 5,
							'headers'  => array( 'X-SZEducate-Auth' => $client->api_token ),
						) );
					}
				}
				echo '<div class="notice notice-success is-dismissible" style="margin-top:20px;"><p><strong>A Séma sikeresen elmentve a Hub-on, és a Kliensek automatikusan szinkronizálva lettek a háttérben!</strong></p></div>';
			} else {
				echo '<div class="notice notice-error is-dismissible" style="margin-top:20px;"><p>Biztonsági hiba (lejárt session). Kérjük frissítse az oldalt!</p></div>';
			}
		}

		if ( isset( $_GET['migrate_msg'] ) && $_GET['migrate_msg'] === 'done' ) {
			$m = intval( $_GET['migrated'] ?? 0 );
			$se = intval( $_GET['skipped_existing'] ?? 0 );
			$sz = intval( $_GET['skipped_empty'] ?? 0 );
			echo '<div class="notice notice-success is-dismissible" style="margin-top:20px;"><p><strong>Migráció kész:</strong> ' . $m . ' Képzés frissítve és a Kliensekhez szinkronizálva. ' . $se . ' kihagyva (már volt "munkarend_csoportok" adata). ' . $sz . ' kihagyva (nem volt kitöltve a régi "munkarend" mező).</p></div>';
		}

		$schema = get_option( $this->option_name, '[]' );
		if ( empty( json_decode( $schema, true ) ) ) {
			$schema = wp_json_encode( [
				[
					'group_id'    => 'alap_adatok',
					'group_label' => 'Alap adatok',
					'is_locked'   => true,
					'fields'      => [
						[ 'key' => 'kepzesi_forma', 'label' => 'Képzési Forma', 'type' => 'select', 'options' => 'BSc, MSc, Osztatlan, Felsőoktatási szakképzés, Szakirányú továbbképzés, Mikroképzés, Előkészítő', 'is_required' => true, 'is_filterable' => true, 'is_locked' => true, 'help_text' => 'Válassza ki a képzés típusát.' ],
					]
				]
			] );
		}
		?>
		<div class="wrap" style="max-width: 1600px; padding-right: 20px;">
			<h1>SZEducate Séma Tervező</h1>
			<p>Tervezd meg a képzések adatszerkezetét! Az itt létrehozott mezőket a Kliensek (karok) tölthetik ki.</p>
			
			<div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 15px;">
				<div class="notice notice-info" style="margin: 0; padding: 15px; border-left-color: #007cba; background: #fff; box-shadow: 0 1px 1px rgba(0,0,0,.04); flex: 1; margin-right: 20px;">
					<p style="margin-top: 0;"><strong>Tipp: Közös mezők szinkronja</strong></p>
					<p style="margin-bottom: 0;">Ha egy mezőt több különböző fülön (csoportban) is meg szeretnél jeleníteni, állítsd be nekik pontosan ugyanazt az "Azonosítót"! A rendszer automatikusan kék "Közös mező" jelvénnyel látja el őket. <strong>Ha az egyiket módosítod, a rendszer valós időben frissíti a többi ugyanilyen azonosítójú mezőt is!</strong></p>
				</div>
				<div style="display: flex; gap: 10px;">
					<input type="file" id="import-schema-file" accept=".json" style="display: none;">
					<button type="button" id="import-schema-btn" class="button button-secondary">Séma Importálása (.json)</button>
					<button type="button" id="export-schema-btn" class="button button-secondary">Séma Exportálása (.json)</button>
				</div>
			</div>
			
			<form method="post" id="szeducate-schema-form">
				<?php wp_nonce_field( 'save_szeducate_schema_action', 'szeducate_schema_nonce' ); ?>
				<input type="hidden" name="szeducate_schema" id="szeducate_schema_data" value="<?php echo esc_attr( $schema ); ?>">
				<div id="schema-builder-container" style="width: 100%;"></div>
				<button type="button" id="add-group-btn" class="button button-secondary" style="margin-top: 20px;">+ Új Csoport (Fül) Hozzáadása</button>
				<div style="margin-top: 30px; padding-bottom: 50px;">
					<?php submit_button( 'Séma Mentése és Véglegesítése', 'primary', 'submit', false, array('id' => 'save-schema-btn', 'style' => 'padding: 5px 30px; font-size: 15px;') ); ?>
				</div>
			</form>

			<div class="notice notice-warning" style="margin: 30px 0 0 0; padding: 15px; max-width: 900px;">
				<p style="margin-top: 0;"><strong>Egyszeri migráció: "Munkarend csoportok" feltöltése a régi mezőkből</strong></p>
				<p>Csak azután futtasd, hogy a fenti sémában már felvetted a <code>munkarend_csoportok</code> Lista mezőt (benne a <code>munkarend</code> és a beágyazott <code>variansok</code> al-mezővel, azon belül <code>nyelv</code>, <code>finanszirozasi_forma</code>, <code>ar_tipus</code>, <code>osszeg</code>). Minden olyan Képzésnél, ahol a régi <code>munkarend</code> mező ki van töltve és az új mező még üres, az összes rögzített nyelv × finanszírozási forma kombinációt felveszi minden munkarendhez - ez <strong>nem tudja</strong> visszaadni, ha pl. csak Nappalin indul angolul, azt utólag kézzel kell finomítani a Kliens szerkesztőben. Már kitöltött Képzéseket nem írja felül, és a Klienseket is automatikusan szinkronizálja utána.</p>
				<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" onsubmit="return confirm('Biztosan futtatod a migrációt az élő adatbázison? Ez minden olyan Képzést módosít, ahol a régi munkarend mező ki van töltve, de az új munkarend_csoportok még üres.');">
					<input type="hidden" name="action" value="szeducate_migrate_variansok">
					<?php wp_nonce_field( 'szeducate_migrate_variansok' ); ?>
					<button type="submit" class="button">Migráció futtatása most</button>
				</form>
			</div>
		</div>

		<script src="https://cdn.jsdelivr.net/npm/sortablejs@latest/Sortable.min.js"></script>
		
		<style>
			#schema-builder-container { font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Oxygen-Sans, Ubuntu, Cantarell, "Helvetica Neue", sans-serif; }
			
			.sz-input, .sz-select { 
				width: 100%; 
				box-sizing: border-box; 
				border: 1px solid #8c8f94; 
				border-radius: 4px; 
				padding: 6px 10px; 
				font-size: 13px; 
				line-height: 1.4; 
				box-shadow: none; 
				transition: all 0.2s; 
				min-height: 32px;
			}
			.sz-input:focus, .sz-select:focus { border-color: #2271b1; outline: none; box-shadow: 0 0 0 1px #2271b1; }
			.sz-select { padding: 4px 30px 4px 10px; }
			
			.drag-handle { cursor: grab; font-size: 18px; color: #a7aaad; user-select: none; padding: 5px; margin-right: 5px; display: flex; align-items: center; letter-spacing: -2px; font-weight: bold; }
			.drag-handle:active { cursor: grabbing; color: #2271b1; }

			.szeducate-group { background: #fff; border: 1px solid #c3c4c7; border-radius: 6px; margin-bottom: 24px; box-shadow: 0 1px 3px rgba(0,0,0,0.04); overflow: hidden; transition: box-shadow 0.2s; }
			.szeducate-group:hover { box-shadow: 0 4px 8px rgba(0,0,0,0.05); }
			
			.szeducate-group-header { display: flex; align-items: flex-start; padding: 15px 20px; background: #f6f7f7; border-bottom: 1px solid #c3c4c7; }
			.szeducate-group.locked .szeducate-group-header { background: #f0f6fc; border-bottom-color: #c8d8e8; }
			.szeducate-group-header .drag-handle { margin-top: 15px; }

			.sz-field-col { display: flex; flex-direction: column; gap: 4px; min-width: 0; }
			.sz-field-col label { font-size: 11px; font-weight: 600; color: #646970; text-transform: uppercase; letter-spacing: 0.5px; }
			
			.g-inputs { display: flex; gap: 20px; flex: 1; margin-left: 10px; }
			
			.group-conditions { padding: 15px 20px; background: #fafafa; border-bottom: 1px solid #e2e4e7; display: flex; gap: 20px; align-items: flex-end; }
			
			.szeducate-fields-container { padding: 20px; background: #f0f0f1; min-height: 20px; }
			.szeducate-field { background: #fff; border: 1px solid #dcdde1; border-radius: 4px; padding: 15px 20px; margin-bottom: 15px; transition: all 0.2s; }
			.szeducate-field:hover { border-color: #a7aaad; box-shadow: 0 2px 5px rgba(0,0,0,0.05); }
			.szeducate-field.locked { background: #f9f9f9; border-color: #e2e4e7; }
			.szeducate-field.archived-field { opacity: 0.6; background: #e5e5e5; filter: grayscale(100%); }
			
			.sz-field-main { display: flex; gap: 20px; align-items: flex-start; }
			.sz-field-main .drag-handle { margin-top: 15px; }
			
			.sz-field-settings { margin-top: 15px; padding-top: 15px; border-top: 1px dashed #dcdde1; padding-left: 35px; }
			.sz-field-settings-checkboxes { display: flex; flex-wrap: wrap; gap: 25px; align-items: center; margin-bottom: 12px; font-size: 13px; color: #50575e; }
			.sz-field-settings-checkboxes label { display: flex; align-items: center; gap: 6px; cursor: pointer; }
			.sz-field-settings-checkboxes input[type="checkbox"] { margin: 0; cursor: pointer; }

			.subfields-container { background: #f8f9f9; border: 1px dashed #a7aaad; border-radius: 4px; padding: 15px; margin-top: 15px; margin-left: 35px; }
			.szeducate-subfield { background: #fff; border: 1px solid #dcdde1; padding: 10px; margin-bottom: 8px; border-radius: 3px; }
			.szeducate-subfield .sf-row { display: flex; gap: 15px; align-items: center; }
			.nested-subfields-container { background: #eef1f3; border: 1px dashed #b8bcc0; border-radius: 4px; padding: 12px 12px 12px 30px; margin-top: 10px; }

			.btn-icon { background: none; border: none; cursor: pointer; color: #d63638; font-size: 16px; font-weight: bold; padding: 5px 10px; display: flex; align-items: center; justify-content: center; border-radius: 3px; transition: background 0.2s; }
			.btn-icon:hover { background: #fbeaea; }
			.badge-locked { background: #e5f5fa; color: #007cba; padding: 4px 8px; border-radius: 12px; font-size: 12px; font-weight: 600; display: inline-block; }
			.badge-duplicate { background: #e6f0fa; color: #005a9e; padding: 2px 6px; border-radius: 4px; font-size: 10px; font-weight: 600; display: inline-block; border: 1px solid #b3d4fc; float: right; margin-top: -2px; }
		</style>

		<script>
		document.addEventListener('DOMContentLoaded', function() {
			const container = document.getElementById('schema-builder-container');
			const addGroupBtn = document.getElementById('add-group-btn');
			const form = document.getElementById('szeducate-schema-form');
			const dataInput = document.getElementById('szeducate_schema_data');

			document.getElementById('export-schema-btn').addEventListener('click', () => {
				const data = dataInput.value;
				if (!data || data === '[]') { alert('Üres a séma, nincs mit exportálni.'); return; }
				
				const blob = new Blob([data], { type: 'application/json' });
				const url = URL.createObjectURL(blob);
				const a = document.createElement('a');
				a.href = url;
				a.download = 'szeducate_schema_export_' + new Date().toISOString().slice(0,10) + '.json';
				document.body.appendChild(a);
				a.click();
				document.body.removeChild(a);
				URL.revokeObjectURL(url);
			});

			document.getElementById('import-schema-btn').addEventListener('click', () => {
				document.getElementById('import-schema-file').click();
			});

			document.getElementById('import-schema-file').addEventListener('change', (e) => {
				const file = e.target.files[0];
				if (!file) return;
				
				const reader = new FileReader();
				reader.onload = (ev) => {
					try {
						const json = JSON.parse(ev.target.result);
						if (!Array.isArray(json)) throw new Error("A JSON struktúra nem megfelelő (nem tömb).");
						
						dataInput.value = JSON.stringify(json);
						if (confirm("Az importált fájl betöltése azonnal felülírja a jelenlegi sémát. Véglegesítjük a mentést?")) {
							HTMLFormElement.prototype.submit.call(form);
						} else {
							e.target.value = '';
						}
					} catch (err) {
						alert('Hiba történt az importálás során! Ellenőrizze, hogy a fájl érvényes SZEducate JSON séma-e. Részletek: ' + err.message);
					}
				};
				reader.readAsText(file);
			});

			let schemaData = [];
			try { schemaData = JSON.parse(dataInput.value); } catch(e) {}

			const originalTypes = {};
			schemaData.forEach(g => {
				if(g.fields) g.fields.forEach(f => originalTypes[f.key] = f.type);
			});

			const fieldTypes = [
				{ val: 'text', text: 'Rövidszöveg' },
				{ val: 'textarea', text: 'Hosszúszöveg' },
				{ val: 'wysiwyg', text: 'Formázott szöveg (WYSIWYG)' },
				{ val: 'email', text: 'Email cím' },
				{ val: 'checkbox', text: 'Jelölőnégyzet (Több opció)' },
				{ val: 'radio', text: 'Rádiógomb' },
				{ val: 'select', text: 'Dropdown' },
				{ val: 'boolean', text: 'Kapcsoló (Igen/Nem)' },
				{ val: 'number', text: 'Szám' },
				{ val: 'date', text: 'Dátum' },
				{ val: 'url', text: 'Link (1 db URL)' },
				{ val: 'links', text: 'Többszörös Link (URL+Szöveg)' },
				{ val: 'image', text: 'Kép (Médiatár)' },
				{ val: 'repeater', text: 'Táblázatos Ismétlődő' }
			];

			const baseSubFieldTypes = [
				{ val: 'text', text: 'Rövidszöveg' },
				{ val: 'textarea', text: 'Hosszúszöveg' },
				// "richtext": hosszúszöveg, amibe a Kliens szerkesztőben szövegközi
				// hiperhivatkozás szúrható; a tárolt érték korlátozott HTML (a, strong, em, br),
				// a frontend Repeater widget wp_kses-szel engedi át.
				{ val: 'richtext', text: 'Hosszúszöveg (linkelhető)' },
				{ val: 'number', text: 'Szám' },
				{ val: 'select', text: 'Dropdown' },
				{ val: 'boolean', text: 'Kapcsoló' },
				{ val: 'url', text: 'Link' }
			];
			// Egy beágyazott lista al-mezője már NEM lehet maga is lista (max. 1 szint mélységig
			// engedjük az egymásba ágyazást), hogy a szerkesztő felület és az adatszerkezet is átlátható maradjon.
			const subFieldTypes = [ ...baseSubFieldTypes, { val: 'repeater', text: 'Beágyazott lista' } ];

			function generateId() { return Math.random().toString(36).substr(2, 9); }
			function initSortable(el, options) { if (typeof Sortable !== 'undefined' && el) new Sortable(el, options); }
			function slugify(text) {
				const a = 'àáäâãåăæąçćčđďèéěėëêęğǵḧìíïîįłḿǹńňñòóöôœøṕŕřßşśšșťțùúüûǘůűūųẃẍÿýźžż·/_,:;';
				const b = 'aaaaaaaaacccddeeeeeeegghiiiiilmnnnnooooooprrsssssttuuuuuuuuuwxyyzzz------';
				const p = new RegExp(a.split('').join('|'), 'g');
				return text.toString().toLowerCase().replace(/\s+/g, '_').replace(p, c => b.charAt(a.indexOf(c))).replace(/&/g, '_and_').replace(/[^\w\-]+/g, '').replace(/\-\-+/g, '_').replace(/^-+/, '').replace(/-+$/, '');
			}

			function generateUniqueKey(baseKey, currentInput) {
				let key = baseKey;
				let counter = 1;
				let isUnique = false;
				while (!isUnique) {
					isUnique = true;
					document.querySelectorAll('.f-key, .sf-key').forEach(input => {
						if (input !== currentInput && input.value === key) isUnique = false;
					});
					if (!isUnique) { counter++; key = baseKey + '_' + counter; }
				}
				return key;
			}

			function checkDuplicateKeys() {
				const inputs = document.querySelectorAll('.f-key');
				const keys = Array.from(inputs).map(i => i.value.trim()).filter(v => v !== '');
				const counts = {};
				keys.forEach(k => counts[k] = (counts[k] || 0) + 1);
				document.querySelectorAll('.szeducate-field').forEach(field => {
					const keyInput = field.querySelector('.f-key');
					const badge = field.querySelector('.badge-duplicate');
					if (keyInput && badge) badge.style.display = (keyInput.value.trim() && counts[keyInput.value.trim()] > 1) ? 'inline-block' : 'none';
				});
			}

			let isSyncing = false;
			function syncCommonFields(sourceField) {
				if (isSyncing) return;
				
				const sourceKey = sourceField.querySelector('.f-key').value.trim();
				if (!sourceKey) return;

				const badge = sourceField.querySelector('.badge-duplicate');
				if (!badge || badge.style.display === 'none') return;

				isSyncing = true;
				
				const sourceLabel = sourceField.querySelector('.f-label').value;
				const sourceType = sourceField.querySelector('.f-type').value;
				const sourceOptions = sourceField.querySelector('.f-options').value;
				const sourceHelp = sourceField.querySelector('.f-help').value;
				const sourceReq = sourceField.querySelector('.f-required').checked;
				const sourceFilter = sourceField.querySelector('.f-filter').checked;
				const sourceTax = sourceField.querySelector('.f-taxonomy').checked;

				const allFields = document.querySelectorAll('.szeducate-field');
				allFields.forEach(fDiv => {
					if (fDiv === sourceField || fDiv.classList.contains('locked')) return;
					
					const currentKey = fDiv.querySelector('.f-key').value.trim();
					if (currentKey === sourceKey) {
						fDiv.querySelector('.f-label').value = sourceLabel;
						
						const typeEl = fDiv.querySelector('.f-type');
						if (typeEl.value !== sourceType) {
							typeEl.value = sourceType;
							typeEl.dispatchEvent(new Event('change'));
						}
						
						fDiv.querySelector('.f-options').value = sourceOptions;
						fDiv.querySelector('.f-help').value = sourceHelp;
						fDiv.querySelector('.f-required').checked = sourceReq;
						fDiv.querySelector('.f-filter').checked = sourceFilter;
						fDiv.querySelector('.f-taxonomy').checked = sourceTax;
					}
				});
				
				isSyncing = false;
			}

			// allowNested: csak a legfelső szintű Lista mező saját al-mezői ajánlhatnak fel
			// "Beágyazott lista" típust - egy már beágyazott al-mező nem ágyazhat tovább.
			function createSubFieldRow(subField, allowNested) {
				const sfDiv = document.createElement('div');
				sfDiv.className = 'szeducate-subfield';
				const typeList = allowNested ? subFieldTypes : baseSubFieldTypes;
				let sfOptions = typeList.map(t => `<option value="${t.val}" ${subField.type === t.val ? 'selected' : ''}>${t.text}</option>`).join('');
				const showOpts = subField.type === 'select' ? 'flex' : 'none';
				const showNested = allowNested && subField.type === 'repeater' ? 'block' : 'none';

				sfDiv.innerHTML = `
					<div class="sf-row">
						<div class="drag-handle subfield-drag-handle" title="Mozgatás">::</div>
						<div class="sz-field-col" style="flex: 2;">
							<input type="text" class="sz-input sf-label" placeholder="Oszlop neve" value="${subField.label || ''}">
						</div>
						<div class="sz-field-col" style="flex: 1.5;">
							<input type="text" class="sz-input sf-key" placeholder="Azonosító" value="${subField.key || ''}">
						</div>
						<div class="sz-field-col" style="flex: 1.5;">
							<select class="sz-select sf-type">${sfOptions}</select>
						</div>
						<div class="sz-field-col sf-options-wrapper" style="flex: 2; display: ${showOpts};">
							<input type="text" class="sz-input sf-options" placeholder="Opciók (pontosvesszővel)" value="${subField.options || ''}">
						</div>
						<button type="button" class="btn-icon delete-sf-btn" title="Oszlop törlése">X</button>
					</div>
					${allowNested ? `
					<div class="nested-subfields-container" style="display: ${showNested};">
						<div style="font-weight: 600; font-size: 12px; margin-bottom: 8px; color: #1d2327;">Beágyazott lista oszlopai</div>
						<div class="sf-list-nested"></div>
						<button type="button" class="button button-small add-nested-sf-btn" style="margin-top: 8px;">+ Új beágyazott oszlop</button>
					</div>` : ''}
				`;
				sfDiv.querySelector('.sf-label').addEventListener('blur', function() {
					const ki = sfDiv.querySelector('.sf-key');
					if (!ki.value && this.value) { ki.value = generateUniqueKey(slugify(this.value), ki); checkDuplicateKeys(); }
				});
				sfDiv.querySelector('.sf-type').addEventListener('change', function(e) {
					sfDiv.querySelector('.sf-options-wrapper').style.display = e.target.value === 'select' ? 'flex' : 'none';
					const nestedContainer = sfDiv.querySelector('.nested-subfields-container');
					if (nestedContainer) nestedContainer.style.display = (e.target.value === 'repeater') ? 'block' : 'none';
				});
				sfDiv.querySelector('.delete-sf-btn').addEventListener('click', () => { sfDiv.remove(); checkDuplicateKeys(); });

				if (allowNested) {
					const nestedList = sfDiv.querySelector('.sf-list-nested');
					if (subField.type === 'repeater' && subField.sub_fields) subField.sub_fields.forEach(nsf => nestedList.appendChild(createSubFieldRow(nsf, false)));
					initSortable(nestedList, { handle: '.subfield-drag-handle', animation: 150 });
					sfDiv.querySelector('.add-nested-sf-btn').addEventListener('click', () => nestedList.appendChild(createSubFieldRow({ type: 'text' }, false)));
				}

				return sfDiv;
			}

			// Csak a KÖZVETLEN gyerek al-mezőket gyűjti egy adott listából - egy beágyazott
			// lista saját al-mezői ne kerüljenek bele kétszer (egyszer a szülőbe, egyszer sajátjukba).
			function collectSubFields(sfListEl, allowNested) {
				const result = [];
				if (!sfListEl) return result;
				Array.from(sfListEl.children).forEach(sfDiv => {
					if (!sfDiv.classList || !sfDiv.classList.contains('szeducate-subfield')) return;
					const type = sfDiv.querySelector('.sf-type').value;
					const item = {
						key: sfDiv.querySelector('.sf-key').value.trim(),
						label: sfDiv.querySelector('.sf-label').value.trim(),
						type: type,
						options: sfDiv.querySelector('.sf-options').value.trim()
					};
					if (allowNested && type === 'repeater') {
						item.sub_fields = collectSubFields(sfDiv.querySelector('.sf-list-nested'), false);
					}
					result.push(item);
				});
				return result;
			}

			function createFieldRow(field) {
				const isArchived = field.is_archived || false;
				const fDiv = document.createElement('div');
				fDiv.className = `szeducate-field ${field.is_locked ? 'locked' : ''} ${isArchived ? 'archived-field' : ''}`;
				fDiv.dataset.type = 'field';

				let typeOptions = fieldTypes.map(t => `<option value="${t.val}" ${field.type === t.val ? 'selected' : ''}>${t.text}</option>`).join('');
				const showOptions = ['select', 'radio', 'checkbox'].includes(field.type) ? 'flex' : 'none';
				const isReadonly = field.is_locked ? 'readonly' : '';

				fDiv.innerHTML = `
					<input type="hidden" class="f-archived" value="${isArchived ? 'true' : ''}">
					<div class="sz-field-main">
						<div class="drag-handle field-drag-handle" title="Mozgatás">::</div>
						
						<div class="sz-field-col" style="flex: 2.5;">
							<label>Mező neve (Címke)</label>
							<input type="text" class="sz-input f-label" placeholder="pl. Képzés helye" value="${field.label || ''}" ${isReadonly}>
						</div>
						
						<div class="sz-field-col" style="flex: 1.5;">
							<label>Azonosító (Key) <span class="badge-duplicate" style="display:none;" title="Közös mező!">Közös mező</span></label>
							<input type="text" class="sz-input f-key" placeholder="pl. kepzes_helye" value="${field.key || ''}" ${isReadonly}>
						</div>
						
						<div class="sz-field-col" style="flex: 1.5;">
							<label>Mező Típusa</label>
							<select class="sz-select f-type" ${field.is_locked ? 'disabled' : ''}>${typeOptions}</select>
							${field.is_locked ? `<input type="hidden" class="f-type-hidden" value="${field.type}">` : ''}
						</div>
						
						<div class="sz-field-col f-options-wrapper" style="flex: 3; display: ${showOptions};">
							<label>Opciók (Pontosvesszővel elválasztva)</label>
							<input type="text" class="sz-input f-options" placeholder="pl. Nappali; Levelező" value="${field.options || ''}" ${isReadonly}>
						</div>
						
						<div class="sz-field-col" style="flex: 0 0 auto; padding-top: 18px;">
							<div style="display: flex; gap: 8px;">
								${!field.is_locked ? `
									<button type="button" class="button toggle-archive-btn">${isArchived ? 'Visszaállítás' : 'Archiválás'}</button>
									<button type="button" class="button button-link-delete delete-field-btn" style="color:#d63638; text-decoration:none;" title="Végleges törlés a felületről">Törlés</button>
								` : `<span class="badge-locked">Rendszermező</span>`}
							</div>
						</div>
					</div>
					
					<div class="sz-field-settings">
						<div class="sz-field-settings-checkboxes">
							<label><input type="checkbox" class="f-required" ${field.is_required ? 'checked' : ''} ${field.is_locked ? 'disabled' : ''}> Kötelező kitölteni</label>
							<label><input type="checkbox" class="f-filter" ${field.is_filterable ? 'checked' : ''} ${field.is_locked ? 'disabled' : ''}> Kiemelt Szűrő (Indexelt)</label>
							<label><input type="checkbox" class="f-taxonomy" ${field.is_taxonomy ? 'checked' : ''} ${field.is_locked ? 'disabled' : ''}> SEO URL (Archívum)</label>
						</div>
						<div class="sz-field-col" style="width: 100%;">
							<label style="font-size: 10px;">Súgó szöveg a szerkesztőbe (Útmutató a Klienseknek)</label>
							<input type="text" class="sz-input f-help" placeholder="pl. Add meg a hivatalos e-mail címet a @sze.hu végződéssel..." value="${field.help_text || ''}" ${field.is_locked ? 'readonly' : ''}>
						</div>
					</div>
					
					<div class="subfields-container" style="display: ${field.type === 'repeater' ? 'block' : 'none'};">
						<div style="font-weight: 600; font-size: 13px; margin-bottom: 12px; color: #1d2327;">[Lista] Táblázat Oszlopai (Al-mezők)</div>
						<div class="sf-list"></div>
						<button type="button" class="button button-small add-sf-btn" style="margin-top: 12px;">+ Új oszlop</button>
					</div>
				`;

				if (!field.is_locked) {
					const triggerSync = () => syncCommonFields(fDiv);
					
					fDiv.querySelector('.f-label').addEventListener('input', triggerSync);
					fDiv.querySelector('.f-options').addEventListener('input', triggerSync);
					fDiv.querySelector('.f-help').addEventListener('input', triggerSync);
					fDiv.querySelector('.f-required').addEventListener('change', triggerSync);
					fDiv.querySelector('.f-filter').addEventListener('change', triggerSync);
					fDiv.querySelector('.f-taxonomy').addEventListener('change', triggerSync);

					fDiv.querySelector('.f-type').addEventListener('change', function(e) {
						fDiv.querySelector('.f-options-wrapper').style.display = ['select', 'radio', 'checkbox'].includes(e.target.value) ? 'flex' : 'none';
						fDiv.querySelector('.subfields-container').style.display = e.target.value === 'repeater' ? 'block' : 'none';
						triggerSync();
					});
					
					fDiv.querySelector('.f-label').addEventListener('blur', function() {
						const ki = fDiv.querySelector('.f-key');
						if (!ki.value && this.value) { ki.value = generateUniqueKey(slugify(this.value), ki); checkDuplicateKeys(); }
					});
					fDiv.querySelector('.f-key').addEventListener('input', checkDuplicateKeys);
				}

				const sfList = fDiv.querySelector('.sf-list');
				if (field.type === 'repeater' && field.sub_fields) field.sub_fields.forEach(sf => sfList.appendChild(createSubFieldRow(sf, true)));
				initSortable(sfList, { handle: '.subfield-drag-handle', animation: 150 });
				fDiv.querySelector('.add-sf-btn').addEventListener('click', () => sfList.appendChild(createSubFieldRow({ type: 'text' }, true)));

				if (!field.is_locked) {
					fDiv.querySelector('.toggle-archive-btn').addEventListener('click', function() {
						const archInput = fDiv.querySelector('.f-archived');
						const willBeArchived = archInput.value !== 'true';
						archInput.value = willBeArchived ? 'true' : '';
						fDiv.classList.toggle('archived-field', willBeArchived);
						this.innerText = willBeArchived ? 'Visszaállítás' : 'Archiválás';
					});

					fDiv.querySelector('.delete-field-btn').addEventListener('click', () => {
						if(confirm('A törléssel a mező eltűnik a felületről. Biztonságosabb az Archiválás használata. Biztosan véglegesen törlöd?')) {
							fDiv.remove(); checkDuplicateKeys();
						}
					});
				}
				return fDiv;
			}

			function createGroupBlock(group) {
				const gDiv = document.createElement('div');
				gDiv.className = `szeducate-group ${group.is_locked ? 'locked' : ''}`;
				gDiv.dataset.type = 'group';

				const isReadonly = group.is_locked ? 'readonly' : '';
				const cond = group.condition || {};
				const conditionHtml = !group.is_locked ? `
					<div class="group-conditions">
						<div class="sz-field-col" style="flex: 0 0 auto;">
							<label style="color: #2271b1;">[Feltétel] Láthatóság:</label>
							<div style="font-size: 13px; font-weight: 600; line-height: 32px;">A fül megjelenik, ha</div>
						</div>
						<div class="sz-field-col" style="flex: 2;">
							<label>Mező (Azonosító)</label>
							<input type="text" class="sz-input c-field" placeholder="pl. kepzesi_forma" value="${cond.field || ''}">
						</div>
						<div class="sz-field-col" style="flex: 2;">
							<label>Operátor</label>
							<select class="sz-select c-operator">
								<option value="">Nincs feltétel (Mindig látszik)</option>
								<option value="==" ${cond.operator === '==' ? 'selected' : ''}>Egyenlő</option>
								<option value="!=" ${cond.operator === '!=' ? 'selected' : ''}>Nem egyenlő</option>
								<option value="not_empty" ${cond.operator === 'not_empty' ? 'selected' : ''}>Nincs üresen</option>
								<option value="empty" ${cond.operator === 'empty' ? 'selected' : ''}>Üres</option>
								<option value="contains" ${cond.operator === 'contains' ? 'selected' : ''}>Tartalmazza</option>
							</select>
						</div>
						<div class="sz-field-col" style="flex: 2;">
							<label>Érték</label>
							<input type="text" class="sz-input c-value" placeholder="pl. BSc" value="${cond.value || ''}">
						</div>
					</div>` : '';

				gDiv.innerHTML = `
					<div class="szeducate-group-header">
						<div class="drag-handle group-drag-handle" title="Mozgatás">::</div>
						<div class="g-inputs" style="max-width: 600px;">
							<div class="sz-field-col" style="flex: 3;">
								<label>Csoport (Fül) Megnevezése</label>
								<input type="text" class="sz-input g-label" placeholder="pl. Szakirányú Továbbképzés" value="${group.group_label || ''}" style="font-weight: 600;" ${isReadonly}>
							</div>
							<div class="sz-field-col" style="flex: 2;">
								<label>Rendszer Azonosító</label>
								<input type="text" class="sz-input g-id" placeholder="pl. sztk_ful" value="${group.group_id || generateId()}" ${isReadonly}>
							</div>
						</div>
						<div style="display: flex; align-items: center; gap: 15px; margin-top: 18px; margin-left: auto;">
							${!group.is_locked ? `<button type="button" class="button button-link-delete delete-group-btn" style="color:#d63638; text-decoration:none; white-space: nowrap;">Csoport Törlése</button>` : `<span class="badge-locked" style="white-space: nowrap;">Zárt Rendszerfül</span>`}
							<button type="button" class="button toggle-group-btn" style="min-width: 40px;">&lt;</button>
						</div>
					</div>
					<div class="group-inside" style="display: none;">
						${conditionHtml}
						<div class="szeducate-fields-container"></div>
						<div style="padding: 0 20px 20px 20px; background: #f0f0f1;">
							<button type="button" class="button add-field-btn">+ Új Mező Hozzáadása a csoporthoz</button>
						</div>
					</div>
				`;

				const fieldsContainer = gDiv.querySelector('.szeducate-fields-container');
				if(group.fields && group.fields.length > 0) group.fields.forEach(f => fieldsContainer.appendChild(createFieldRow(f)));
				initSortable(fieldsContainer, { handle: '.field-drag-handle', animation: 150 });

				gDiv.querySelector('.toggle-group-btn').addEventListener('click', function() {
					const inside = gDiv.querySelector('.group-inside');
					const isHidden = inside.style.display === 'none';
					inside.style.display = isHidden ? 'block' : 'none';
					this.innerText = isHidden ? 'v' : '<';
				});

				gDiv.querySelector('.add-field-btn').addEventListener('click', () => { fieldsContainer.appendChild(createFieldRow({ type: 'text' })); checkDuplicateKeys(); });
				if (!group.is_locked) gDiv.querySelector('.delete-group-btn').addEventListener('click', () => { if(confirm('Biztosan törlöd a teljes csoportot az összes mezővel együtt?')) { gDiv.remove(); checkDuplicateKeys(); } });
				return gDiv;
			}

			initSortable(container, { handle: '.group-drag-handle', animation: 150 });
			schemaData.forEach(group => container.appendChild(createGroupBlock(group)));
			addGroupBtn.addEventListener('click', () => container.appendChild(createGroupBlock({})));
			setTimeout(checkDuplicateKeys, 500);

			form.addEventListener('submit', function(e) {
				const newSchema = [];
				let typeWarnings = [];
				const groups = container.querySelectorAll('.szeducate-group');
				
				groups.forEach(g => {
					const gData = {
						group_id: g.querySelector('.g-id').value.trim(),
						group_label: g.querySelector('.g-label').value.trim(),
						is_locked: g.querySelector('.g-id').readOnly,
						fields: []
					};
					const opSelect = g.querySelector('.c-operator');
					if (opSelect && opSelect.value !== '') gData.condition = { field: g.querySelector('.c-field').value.trim(), operator: opSelect.value, value: g.querySelector('.c-value').value.trim() };

					const fields = g.querySelectorAll('.szeducate-field');
					fields.forEach(f => {
						const key = f.querySelector('.f-key').value.trim();
						const typeEl = f.querySelector('.f-type');
						const typeHiddenEl = f.querySelector('.f-type-hidden');
						const fieldType = typeHiddenEl ? typeHiddenEl.value : typeEl.value;
						const isArchived = f.querySelector('.f-archived').value === 'true';

						if (originalTypes[key] && originalTypes[key] !== fieldType && !isArchived) {
							typeWarnings.push(`- ${f.querySelector('.f-label').value.trim()} (${key}): ${originalTypes[key]} -> ${fieldType}`);
						}

						let fieldData = {
							key: key,
							label: f.querySelector('.f-label').value.trim(),
							type: fieldType,
							options: f.querySelector('.f-options').value.trim(),
							is_filterable: f.querySelector('.f-filter').checked,
							is_taxonomy: f.querySelector('.f-taxonomy').checked,
							is_required: f.querySelector('.f-required').checked,
							is_locked: f.querySelector('.f-key').readOnly,
							is_archived: isArchived,
							help_text: f.querySelector('.f-help').value.trim()
						};

						if (fieldType === 'repeater') {
							fieldData.sub_fields = collectSubFields(f.querySelector('.sf-list'), true);
						}
						gData.fields.push(fieldData);
					});
					newSchema.push(gData);
				});

				if (typeWarnings.length > 0) {
					const msg = "FIGYELEM! Az alábbi mezők típusa megváltozott:\n\n" + typeWarnings.join("\n") + "\n\nAdatbázis szinten az adatok nem vesznek el, a Kliens rendszer (React) pedig futásidőben megpróbálja automatikusan konvertálni az inkompatibilis típusokat (pl. szövegből lista). Biztosan véglegesíted?";
					if (!confirm(msg)) {
						e.preventDefault();
						return;
					}
				}

				dataInput.value = JSON.stringify(newSchema);
			});
		});
		</script>
		<?php
	}
}