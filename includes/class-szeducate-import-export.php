<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Cell\DataValidation;

class SZEducate_Import_Export {

	public function init() {
		add_action( 'admin_menu', array( $this, 'add_menu_page' ) );
		
		add_action( 'admin_post_szeducate_download_csv_template', array( $this, 'handle_download_template' ) );
		add_action( 'admin_post_szeducate_export_all_csv', array( $this, 'handle_export_all' ) );

		add_filter( 'bulk_actions-edit-sz_course', array( $this, 'register_bulk_export_action' ) );
		add_action( 'load-edit.php', array( $this, 'process_bulk_export_action' ) );
	}

	public function add_menu_page() {
		add_submenu_page(
			'szeducate-settings',
			'Excel Import / Export',
			'Excel Import / Export',
			'manage_options',
			'szeducate-import',
			array( $this, 'render_page' )
		);
	}

	public function register_bulk_export_action( $bulk_actions ) {
		$bulk_actions['szeducate_export_selected'] = 'Exportálás formázott Excel-be';
		return $bulk_actions;
	}

	public function process_bulk_export_action() {
		$wp_list_table = _get_list_table( 'WP_Posts_List_Table' );
		$action = $wp_list_table->current_action();

		if ( $action === 'szeducate_export_selected' && isset( $_REQUEST['post'] ) && is_array( $_REQUEST['post'] ) ) {
			if ( ! current_user_can( 'edit_posts' ) ) wp_die( 'Nincs jogosultságod.' );
			
			$post_ids = array_map( 'intval', $_REQUEST['post'] );
			
			global $wpdb;
			$table_name = $wpdb->prefix . 'szeducate_courses_data';
			$ids_string = implode( ',', $post_ids );
			$results = $wpdb->get_results( "SELECT course_data FROM $table_name WHERE local_post_id IN ($ids_string)", ARRAY_A );
			
			$detected_formats = array();
			if ( $results ) {
				foreach ( $results as $row ) {
					$data = json_decode( $row['course_data'], true );
					if ( isset($data['kepzesi_forma']) && !empty($data['kepzesi_forma']) ) {
						$detected_formats[] = $data['kepzesi_forma'];
					}
				}
			}
			$detected_formats = array_unique($detected_formats);

			$this->generate_excel( $post_ids, false, $detected_formats );
		}
	}

	public function handle_download_template() {
		if ( ! current_user_can( 'manage_options' ) ) wp_die( 'Nincs jogosultságod.' );
		$formats = isset($_POST['formats']) && is_array($_POST['formats']) ? array_map('sanitize_text_field', $_POST['formats']) : array();
		$this->generate_excel( array(), true, $formats );
	}

	public function handle_export_all() {
		if ( ! current_user_can( 'manage_options' ) ) wp_die( 'Nincs jogosultságod.' );
		$formats = isset($_POST['formats']) && is_array($_POST['formats']) ? array_map('sanitize_text_field', $_POST['formats']) : array();
		$this->generate_excel( array(), false, $formats );
	}

	private function generate_excel( $post_ids = array(), $is_template_only = false, $requested_formats = array() ) {
		if ( ! class_exists( '\PhpOffice\PhpSpreadsheet\Spreadsheet' ) ) {
			wp_die('A PhpSpreadsheet könyvtár hiányzik!');
		}

		global $wpdb;
		$schema_json = get_option( 'szeducate_local_schema', '[]' );
		$schema = json_decode( $schema_json, true );

		$spreadsheet = new Spreadsheet();
		$sheet = $spreadsheet->getActiveSheet();
		$sheet->setTitle('Képzések');

		// Rejtett lap a szigorú legördülőkhöz (Egyválasztós)
		$hidden_sheet = $spreadsheet->createSheet();
		$hidden_sheet->setTitle('_opciok');
		$hidden_sheet->setSheetState(\PhpOffice\PhpSpreadsheet\Worksheet\Worksheet::SHEETSTATE_HIDDEN);
		$hidden_col_index = 1;

		// Látható Puska Lap a többválasztósokhoz
		$reference_sheet = $spreadsheet->createSheet();
		$reference_sheet->setTitle('Eddigi kifejezések (Puska)');
		$ref_col_index = 1;
		$has_refs = false;

		// Határozzuk meg a többválasztós mezőket a sémából
		$multi_select_keys = array();
		if ( is_array( $schema ) ) {
			foreach ( $schema as $group ) {
				if ( empty( $group['fields'] ) ) continue;
				foreach ( $group['fields'] as $field ) {
					if ( in_array( $field['type'], ['checkbox', 'multiselect'] ) || strpos($field['key'], 'kulcssz') !== false || strpos($field['key'], 'terulet') !== false ) {
						$multi_select_keys[] = $field['key'];
					}
				}
			}
		}

		// Referencia szótár kinyerése CSAK a többválasztós mezőkre
		$all_courses = $wpdb->get_results( "SELECT course_data FROM {$wpdb->prefix}szeducate_courses_data", ARRAY_A );
		$unique_values = array();
		
		if ( $all_courses ) {
			foreach ( $all_courses as $c ) {
				$data = json_decode( $c['course_data'], true );
				if ( ! is_array( $data ) ) continue;
				foreach ( $data as $k => $v ) {
					if ( empty( $v ) ) continue;
					if ( ! in_array( $k, $multi_select_keys ) ) continue; // Csak a Puska lapra szánt mezőket gyűjtjük
					
					if ( ! isset( $unique_values[$k] ) ) $unique_values[$k] = array();
					
					if ( is_string( $v ) ) {
						$parts = preg_split('/[,;]+/', $v);
						foreach ( $parts as $p ) {
							$p = trim($p);
							if ( $p !== '' && ! in_array( $p, $unique_values[$k] ) ) {
								$unique_values[$k][] = $p;
							}
						}
					} elseif ( is_array( $v ) ) {
						foreach ( $v as $p ) {
							if ( is_string($p) ) {
							    $p = trim($p);
							    if ( $p !== '' && ! in_array( $p, $unique_values[$k] ) ) {
								    $unique_values[$k][] = $p;
							    }
							}
						}
					}
				}
			}
		}

		foreach ($unique_values as $k => $arr) {
            sort($unique_values[$k]);
        }

		$col_index = 1;
		$group_borders = array(); 
		$keys_map = array(); 

		$fixed_formats = ["BSc", "MSc", "Osztatlan", "Felsőoktatási szakképzés", "Szakirányú továbbképzés", "Mikroképzés", "Előkészítő"];
		$is_all_formats = empty($requested_formats);

		// 1. ALAP OSZLOP: Cím
		$sheet->setCellValue([$col_index, 1], 'Rendszer adatok');
		$sheet->setCellValue([$col_index, 2], 'Cím (Szak megnevezése)');
		$sheet->setCellValue([$col_index, 3], 'title');
		$sheet->setCellValue([$col_index, 4], '[KÖTELEZŐ MEZŐ!] Szöveges mező');
		
		$keys_map[$col_index] = [
			'key' => 'title',
			'group_id' => 'alap_adatok',
			'group_label' => 'Alap adatok'
		];
		$group_borders[] = $col_index; 
		$col_index++;

		// 2. SÉMA OSZLOPOK ÉPÍTÉSE ÉS VALIDÁCIÓ
		if ( is_array( $schema ) ) {
			foreach ( $schema as $group ) {
				if ( empty( $group['fields'] ) || !is_array( $group['fields'] ) ) continue;

				$include_group = false;
				if ( $group['group_id'] === 'alap_adatok' ) {
					$include_group = true; 
				} elseif ( in_array($group['group_label'], $fixed_formats) ) {
					$include_group = $is_all_formats || in_array($group['group_label'], $requested_formats);
				} else {
					$include_group = $is_all_formats || in_array('Szakirányú továbbképzés', $requested_formats);
				}

				if ( ! $include_group ) continue;

				$start_col = $col_index;
				$sheet->setCellValue([$start_col, 1], $group['group_label']);

				foreach ( $group['fields'] as $field ) {
					$keys_map[$col_index] = [
						'key' => $field['key'],
						'group_id' => $group['group_id'],
						'group_label' => $group['group_label']
					];

					$sheet->setCellValue([$col_index, 2], $field['label']);
					$sheet->setCellValue([$col_index, 3], $field['key']);

					$is_single_select = in_array( $field['type'], ['select', 'radio'] );
					$is_multi_select  = in_array( $field['key'], $multi_select_keys );

					$help_text = '';
					if ( !empty($field['is_required']) ) {
						$help_text = '[KÖTELEZŐ MEZŐ!] ';
					}

					if ( $is_single_select ) {
						$help_text .= '[Csak egy választható]';
					} elseif ( in_array( $field['type'], ['checkbox', 'multiselect'] ) ) {
						$help_text .= '[Több érték esetén PONTOSVESSZŐVEL (;) válaszd el]';
					} else {
						switch ( $field['type'] ) {
							case 'boolean': $help_text .= '[Kapcsoló: "true" vagy "false"]'; break;
							case 'number': $help_text .= '[Csak szám]'; break;
							case 'date': $help_text .= '[Dátum: ÉÉÉÉ-HH-NN]'; break;
							case 'email': $help_text .= '[Email cím @sze.hu]'; break;
							case 'wysiwyg': $help_text .= '[Formázott szöveg. Sima szöveget is fogad.]'; break;
							case 'repeater': $help_text .= '[Táblázat. Sima szöveget beírva automatikusan az 1. oszlopba kerül.]'; break;
							case 'links': $help_text .= '[Linkek. Sima URL-t megadva a felület gombbá alakítja.]'; break;
							default: 
								if ( $is_multi_select ) {
									$help_text .= '[Több érték esetén PONTOSVESSZŐVEL (;) válaszd el]';
								} else {
									$help_text .= '[Szöveges mező]'; 
								}
								break;
						}
					}

					if ( !empty($field['help_text']) ) {
						$help_text .= "\n\n Súgó: " . $field['help_text'];
					}
					$sheet->setCellValue([$col_index, 4], $help_text);

					// A) EGYVÁLASZTÓS: Rejtett lapra mentés és Szigorú Validáció beállítása
					if ( $is_single_select && !empty($field['options']) ) {
						$opts = array_map('trim', explode(',', $field['options']));
						$hidden_row = 1;
						foreach($opts as $opt) {
							if ($opt !== '') {
								$hidden_sheet->setCellValue([$hidden_col_index, $hidden_row], $opt);
								$hidden_row++;
							}
						}
						
						if ($hidden_row > 1) {
							$col_letter = Coordinate::stringFromColumnIndex($col_index);
							$hidden_col_letter = Coordinate::stringFromColumnIndex($hidden_col_index);

							$validation = $sheet->getCell("{$col_letter}5")->getDataValidation();
							$validation->setType( DataValidation::TYPE_LIST );
							$validation->setErrorStyle( DataValidation::STYLE_STOP ); // Szigorú megállítás
							$validation->setAllowBlank(true);
							$validation->setShowInputMessage(true);
							$validation->setShowErrorMessage(true);
							$validation->setShowDropDown(true);
							$validation->setErrorTitle('Érvénytelen érték');
							$validation->setError('Kérlek, kizárólag a legördülő listából válassz!');
							$validation->setFormula1("'_opciok'!\${$hidden_col_letter}\$1:\${$hidden_col_letter}\$" . ($hidden_row - 1));
							
							$sheet->setDataValidation("{$col_letter}5:{$col_letter}1000", $validation);
							$hidden_col_index++;
						}
					}

					// B) TÖBBVÁLASZTÓS: Szabad szöveg, de kigyűjtjük a Puska lapra
					if ( $is_multi_select ) {
						$k = $field['key'];
						if ( isset($unique_values[$k]) && count($unique_values[$k]) > 0 ) {
							$has_refs = true;
							$reference_sheet->setCellValue([$ref_col_index, 1], $field['label'] . ' (' . $k . ')');
							$reference_sheet->getStyle([$ref_col_index, 1])->getFont()->setBold(true);
							$ref_row = 2;
							foreach ( $unique_values[$k] as $val ) {
								$reference_sheet->setCellValue([$ref_col_index, $ref_row], $val);
								$ref_row++;
							}
							$reference_sheet->getColumnDimensionByColumn($ref_col_index)->setAutoSize(true);
							$ref_col_index++;
						}
					}

					$col_index++;
				}

				$end_col = $col_index - 1;
				if ( $end_col > $start_col ) {
					$sheet->mergeCells([$start_col, 1, $end_col, 1]);
				}
				$group_borders[] = $end_col;
			}
		}

		if ( ! $has_refs ) {
			$reference_sheet->setCellValue([1, 1], 'Még nincs egyetlen kitöltött többválasztós adat sem az adatbázisban, amiből referenciát lehetne építeni.');
		} else {
			$reference_sheet->getStyle('A1:' . Coordinate::stringFromColumnIndex($ref_col_index - 1) . '1')->applyFromArray([
				'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['argb' => 'FFE0E0E0']],
			]);
		}

		// 3. EXCEL STÍLUSOZÁSA
		$last_col_letter = Coordinate::stringFromColumnIndex($col_index - 1);

		$sheet->getStyle("A1:{$last_col_letter}1")->applyFromArray([
			'font' => ['bold' => true, 'size' => 13, 'color' => ['argb' => 'FFFFFFFF']],
			'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['argb' => 'FF007CBA']],
			'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER],
		]);
		$sheet->getRowDimension(1)->setRowHeight(25);

		$sheet->getStyle("A2:{$last_col_letter}2")->applyFromArray([
			'font' => ['bold' => true, 'color' => ['argb' => 'FF333333']],
			'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['argb' => 'FFF0F6FC']],
			'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER, 'wrapText' => true],
		]);

		$sheet->getStyle("A3:{$last_col_letter}3")->applyFromArray([
			'font' => ['color' => ['argb' => 'FFAAAAAA'], 'italic' => true, 'size' => 9],
			'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['argb' => 'FFF9F9F9']],
		]);

		$sheet->getStyle("A4:{$last_col_letter}4")->applyFromArray([
			'font' => ['color' => ['argb' => 'FF646970'], 'size' => 10],
			'alignment' => ['wrapText' => true, 'vertical' => Alignment::VERTICAL_TOP],
		]);
		$sheet->getRowDimension(4)->setRowHeight(-1);

		foreach ($group_borders as $col_num) {
			$letter = Coordinate::stringFromColumnIndex($col_num);
			$sheet->getStyle("{$letter}1:{$letter}1000")->applyFromArray([
				'borders' => [
					'right' => ['borderStyle' => Border::BORDER_MEDIUM, 'color' => ['argb' => 'FF000000']],
				],
			]);
		}

		for ($i = 1; $i < $col_index; $i++) {
			$sheet->getColumnDimensionByColumn($i)->setWidth(25); 
		}
		$sheet->freezePane('B5');

		// 4. ADATOK BETÖLTÉSE
		if ( ! $is_template_only ) {
			$table_name = $wpdb->prefix . 'szeducate_courses_data';
			
			if ( empty( $post_ids ) ) {
				$results = $wpdb->get_results( "SELECT * FROM $table_name", ARRAY_A );
			} else {
				$ids_string = implode( ',', $post_ids );
				$results = $wpdb->get_results( "SELECT * FROM $table_name WHERE local_post_id IN ($ids_string)", ARRAY_A );
			}

			if ( $results ) {
				$current_row = 5;
				foreach ( $results as $row ) {
					$course_data = json_decode( $row['course_data'], true );
					if ( ! is_array( $course_data ) ) $course_data = array();

					$actual_format = isset( $course_data['kepzesi_forma'] ) ? $course_data['kepzesi_forma'] : '';
					if ( ! $is_all_formats && ! in_array( $actual_format, $requested_formats ) ) {
						continue;
					}

					foreach ( $keys_map as $col_idx => $map_info ) {
						$key = $map_info['key'];
						$group_id = $map_info['group_id'];
						$group_label = $map_info['group_label'];

						if ( $key === 'title' ) {
							$sheet->setCellValue([$col_idx, $current_row], $row['title']);
							continue;
						} 

						$is_relevant = false;
						if ( $group_id === 'alap_adatok' ) {
							$is_relevant = true;
						} elseif ( in_array( $group_label, $fixed_formats ) ) {
							if ( $actual_format === $group_label ) {
								$is_relevant = true;
							}
						} else {
							if ( $actual_format === 'Szakirányú továbbképzés' ) {
								$is_relevant = true;
							}
						}

						if ( $is_relevant ) {
							$val = isset( $course_data[$key] ) ? $course_data[$key] : '';
							
							if ( is_bool( $val ) ) {
								$sheet->setCellValue([$col_idx, $current_row], $val ? 'true' : 'false');
							} elseif ( is_array( $val ) ) {
								$sheet->setCellValueExplicit([$col_idx, $current_row], implode( '; ', $val ), \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
							} else {
								$sheet->setCellValue([$col_idx, $current_row], $val);
							}
						}
					}
					$current_row++;
				}
			}
		}

		$spreadsheet->setActiveSheetIndex(0);

		// 5. FÁJLNÉV GENERÁLÁSA
		if ( $is_template_only ) {
			$formats_str = empty($requested_formats) ? 'MINDEN' : implode('_', $requested_formats);
			$formats_str = preg_replace( '/[^A-Za-z0-9_áéíóöőúüűÁÉÍÓÖŐÚÜŰ]/', '_', $formats_str );
			$formats_str = preg_replace( '/_+/', '_', $formats_str );
			$filename = 'SZAKSABLON_-_' . $formats_str . '_-_' . date('Y-m-d') . '.xlsx';
		} else {
			$filename = 'szeducate_export_' . date('Ymd_His') . '.xlsx';
		}

		header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
		header('Content-Disposition: attachment;filename="' . $filename . '"');
		header('Cache-Control: max-age=0');
		
		$writer = new Xlsx($spreadsheet);
		$writer->save('php://output');
		exit;
	}

	// IMPORTÁLÓ ÉS UI
	public function render_page() {
		$parsed_data = null;
		$error_msg = '';

		// EXCEL FELDOLGOZÁSA
		if ( isset( $_POST['submit_excel'] ) && isset( $_FILES['excel_file'] ) ) {
			if ( $_FILES['excel_file']['error'] === UPLOAD_ERR_OK ) {
				$file_tmp = $_FILES['excel_file']['tmp_name'];

				if ( ! class_exists( '\PhpOffice\PhpSpreadsheet\IOFactory' ) ) {
					$error_msg = 'Kritikus hiba: A PhpSpreadsheet könyvtár hiányzik a szerverről!';
				} else {
					try {
						$spreadsheet = \PhpOffice\PhpSpreadsheet\IOFactory::load($file_tmp);
						$sheet = $spreadsheet->getSheetByName('Képzések');
						if ( ! $sheet ) {
							$sheet = $spreadsheet->getActiveSheet(); 
						}

						$rows = $sheet->toArray(); 
						
						$is_valid_format = (isset($rows[0][0]) && $rows[0][0] === 'Rendszer adatok');
						$keys = isset($rows[2]) ? $rows[2] : array();
						
						if ( $is_valid_format && !empty($keys) && in_array( 'title', $keys ) ) {
							$parsed_data = array();
							
							for ( $i = 4; $i < count($rows); $i++ ) {
								$row = $rows[$i];
								if ( empty( array_filter( $row ) ) ) continue; 
								
								$course = array( 'course_data' => array() );
								foreach ( $row as $index => $value ) {
									$key = $keys[$index] ?? null;
									if ( ! $key ) continue;
									
									if ( $key === 'title' ) {
										$course['title'] = $value;
									} else {
										$val_to_set = $value;
										if ( strtolower(trim((string)$value)) === 'true' ) {
											$val_to_set = true;
										} elseif ( strtolower(trim((string)$value)) === 'false' ) {
											$val_to_set = false;
										} 
										
										if ( isset($course['course_data'][$key]) && $course['course_data'][$key] !== '' && $val_to_set === '' ) {
											continue;
										}

										$course['course_data'][$key] = $val_to_set;
									}
								}

								if ( ! empty( $course['title'] ) ) {
									global $wpdb;
									$existing_id = $wpdb->get_var( $wpdb->prepare( 
										"SELECT ID FROM $wpdb->posts WHERE post_title = %s AND post_type = 'sz_course' AND post_status != 'trash' LIMIT 1", 
										$course['title'] 
									) );
									$course['local_post_id'] = $existing_id ? intval( $existing_id ) : 0;
									$parsed_data[] = $course;
								}
							}
						} else {
							$error_msg = 'Érvénytelen fájl! Kérjük, kizárólag a rendszerből letöltött formázott sablont töltse fel!';
						}
					} catch ( Exception $e ) {
						$error_msg = 'Nem sikerült beolvasni az Excel fájlt: ' . $e->getMessage();
					}
				}
			} else {
				$error_msg = 'Feltöltési hiba történt.';
			}
		}
		
		$fixed_formats = ["BSc", "MSc", "Osztatlan", "Felsőoktatási szakképzés", "Szakirányú továbbképzés", "Mikroképzés", "Előkészítő"];
		?>
		<div class="wrap">
			<h1>Tömeges Excel Import / Export</h1>

			<div class="card" style="max-width: 1000px; padding: 20px; margin-top: 20px; display: flex; gap: 30px;">
				
				<!-- SABLON LETÖLTÉS ŰRLAP -->
				<div style="flex: 1;">
					<form method="POST" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
						<input type="hidden" name="action" value="szeducate_download_csv_template">
						<h3>📥 Üres Sablon Generálása</h3>
						<p style="color: #646970; font-size: 13px;">Válaszd ki, melyik képzési formákhoz szeretnél sablont letölteni. Az Excel csak a releváns oszlopokat fogja tartalmazni. <strong>Ha nem jelölsz ki egyet sem, az összes oszlopot letölti.</strong></p>
						
						<div style="margin: 15px 0; display: flex; flex-direction: column; gap: 8px;">
							<label style="border-bottom: 1px solid #ddd; padding-bottom: 8px; margin-bottom: 5px;">
								<input type="checkbox" class="sz-toggle-all" data-target=".dl-formats-sablon"> <strong>Mind Kijelölése / Törlése</strong>
							</label>
							<?php foreach($fixed_formats as $format): ?>
								<label><input type="checkbox" name="formats[]" class="dl-formats-sablon" value="<?php echo esc_attr($format); ?>"> <?php echo esc_html($format); ?></label>
							<?php endforeach; ?>
						</div>
						
						<button type="submit" class="button button-secondary">Sablon Letöltése (.xlsx)</button>
					</form>
				</div>

				<!-- EXPORTÁLÁS ŰRLAP -->
				<div style="border-left: 1px solid #dcdde1; padding-left: 30px; flex: 1;">
					<form method="POST" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
						<input type="hidden" name="action" value="szeducate_export_all_csv">
						<h3>📦 Adatok Exportálása</h3>
						<p style="color: #646970; font-size: 13px;">Válaszd ki, mely képzési formákat szeretnéd kiexportálni. A rendszer leszűri a sorokat ÉS a felesleges oszlopokat is. <strong>Ha nem jelölsz ki egyet sem, a teljes adatbázist kiexportálja.</strong></p>
						
						<div style="margin: 15px 0; display: flex; flex-direction: column; gap: 8px;">
							<label style="border-bottom: 1px solid #ddd; padding-bottom: 8px; margin-bottom: 5px;">
								<input type="checkbox" class="sz-toggle-all" data-target=".dl-formats-export"> <strong>Mind Kijelölése / Törlése</strong>
							</label>
							<?php foreach($fixed_formats as $format): ?>
								<label><input type="checkbox" name="formats[]" class="dl-formats-export" value="<?php echo esc_attr($format); ?>"> <?php echo esc_html($format); ?></label>
							<?php endforeach; ?>
						</div>
						
						<button type="submit" class="button button-primary" style="background:#007cba;">Adatok Exportálása (.xlsx)</button>
					</form>
				</div>
			</div>

			<?php if ( $error_msg ) : ?>
				<div class="notice notice-error"><p><?php echo esc_html( $error_msg ); ?></p></div>
			<?php endif; ?>

			<?php if ( $parsed_data === null ) : ?>
				<div class="card" style="max-width: 1000px; padding: 20px; margin-top: 20px;">
					<h3>Fájl Importálása</h3>
					<p>Töltsd fel a kitöltött (vagy korábban exportált és módosított) Excel fájlt. A rendszer automatikusan szinkronizálni fogja az adatokat a Hub felé is!</p>
					<form method="post" enctype="multipart/form-data">
						<input type="file" name="excel_file" accept=".xlsx" required>
						<p class="submit">
							<input type="submit" name="submit_excel" class="button button-primary" value="Fájl elemzése és Importálás">
						</p>
					</form>
				</div>
			<?php else : ?>
				<div class="card" style="max-width: 1000px; padding: 20px; margin-top: 20px;">
					<h3>Feldolgozás Folyamatban...</h3>
					<p>Talált bejegyzések: <strong><?php echo count( $parsed_data ); ?></strong> db.</p>
					<div style="width: 100%; background: #eee; height: 20px; border-radius: 3px; margin-bottom: 20px;">
						<div id="import-progress-bar" style="width: 0%; background: #007cba; height: 100%; border-radius: 3px; transition: width 0.3s;"></div>
					</div>
					<p id="import-status-text">Készülj fel az importálásra...</p>
					<textarea id="import-log" readonly style="width: 100%; height: 150px; font-family: monospace; font-size: 11px; background: #f9f9f9; padding: 10px;"></textarea>
				</div>
			<?php endif; ?>
		</div>

		<script>
		document.addEventListener('DOMContentLoaded', function() {
			const toggleAlls = document.querySelectorAll('.sz-toggle-all');
			toggleAlls.forEach(toggle => {
				toggle.addEventListener('change', function() {
					const checkboxes = document.querySelectorAll(this.dataset.target);
					checkboxes.forEach(cb => cb.checked = this.checked);
				});
			});

			<?php if ( $parsed_data !== null ) : ?>
			const courses = <?php echo wp_json_encode( $parsed_data ); ?>;
			const restUrl = '<?php echo esc_url_raw( rest_url( 'szeducate/v1/client/course' ) ); ?>';
			const nonce = '<?php echo wp_create_nonce( 'wp_rest' ); ?>';
			
			const progressBar = document.getElementById('import-progress-bar');
			const statusText = document.getElementById('import-status-text');
			const logArea = document.getElementById('import-log');

			let currentIndex = 0;
			const total = courses.length;

			function logMsg(msg) {
				logArea.value += msg + '\n';
				logArea.scrollTop = logArea.scrollHeight;
			}

			async function processNext() {
				if (currentIndex >= total) {
					statusText.innerHTML = '<strong>Importálás befejezve!</strong>';
					progressBar.style.background = '#46b450';
					return;
				}

				const course = courses[currentIndex];
				const progressPct = Math.round(((currentIndex + 1) / total) * 100);
				progressBar.style.width = progressPct + '%';
				
				const actionText = course.local_post_id > 0 ? 'Frissítés' : 'Létrehozás';
				statusText.innerText = `Feldolgozás: ${currentIndex + 1} / ${total} - [${actionText}] ${course.title}`;

				try {
					const response = await fetch(restUrl, {
						method: 'POST',
						headers: { 'Content-Type': 'application/json', 'X-WP-Nonce': nonce },
						body: JSON.stringify({
							local_post_id: course.local_post_id,
							title: course.title,
							course_data: course.course_data
						})
					});
					
					const data = await response.json();
					if (data.success) {
						logMsg(`[OK - ${actionText}] ${course.title}`);
					} else {
						logMsg(`[HIBA] ${course.title} - ${data.message || data.code}`);
					}
				} catch (e) {
					logMsg(`[HÁLÓZATI HIBA] ${course.title}`);
				}

				currentIndex++;
				setTimeout(processNext, 200); 
			}

			logMsg('Importálás indítása...');
			processNext();
			<?php endif; ?>
		});
		</script>
		<?php
	}
}