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
            'edit.php?post_type=sz_course',
            'Excel Import / Export',
            'Excel Import / Export',
            'edit_sz_courses',
            'szeducate-import',
            array( $this, 'render_page' )
        );
    }

    public function register_bulk_export_action( $bulk_actions ) {
        $bulk_actions['szeducate_export_selected'] = 'Exportálás formázott Excel-be';
        return $bulk_actions;
    }

    private function get_schema_field_types() {
        $schema_json = get_option( 'szeducate_local_schema', '[]' );
        $schema = json_decode( $schema_json, true );
        $types = array( 'public' => 'boolean' );
        
        if ( is_array( $schema ) ) {
            foreach ( $schema as $group ) {
                if ( empty( $group['fields'] ) ) continue;
                foreach ( $group['fields'] as $field ) {
                    $types[ $field['key'] ] = $field['type'];
                }
            }
        }
        return $types;
    }

    private function evaluate_conditions( $conditions, $course_data ) {
        if ( empty( $conditions ) || empty( $conditions['rules'] ) ) return true;

        $logical_operator = isset( $conditions['logical_operator'] ) ? $conditions['logical_operator'] : 'AND';
        $results = array();

        foreach ( $conditions['rules'] as $rule ) {
            if ( isset( $rule['logical_operator'] ) ) {
                $results[] = $this->evaluate_conditions( $rule, $course_data );
            } else {
                $field = isset( $rule['field'] ) ? $rule['field'] : '';
                $operator = isset( $rule['operator'] ) ? $rule['operator'] : '==';
                $target_value = isset( $rule['value'] ) ? $rule['value'] : '';

                $actual_value = isset( $course_data[ $field ] ) ? $course_data[ $field ] : '';
                $actual_string = is_array( $actual_value ) ? implode( ';', $actual_value ) : (string) $actual_value;

                $rule_result = true;
                switch ( $operator ) {
                    case '==': $rule_result = ( $actual_string === $target_value ); break;
                    case '!=': $rule_result = ( $actual_string !== $target_value ); break;
                    case 'contains': $rule_result = ( strpos( $actual_string, $target_value ) !== false ); break;
                }
                $results[] = $rule_result;
            }
        }

        return $logical_operator === 'AND' ? ! in_array( false, $results, true ) : in_array( true, $results, true );
    }

    private function evaluate_format_only($conditions, $test_format) {
        if ( empty( $conditions ) || empty( $conditions['rules'] ) ) return true;
        $logical_operator = isset( $conditions['logical_operator'] ) ? $conditions['logical_operator'] : 'AND';
        $results = array();

        foreach ( $conditions['rules'] as $rule ) {
            if ( isset( $rule['logical_operator'] ) ) {
                $results[] = $this->evaluate_format_only( $rule, $test_format );
            } else {
                $field = isset( $rule['field'] ) ? $rule['field'] : '';
                if ($field !== 'kepzesi_forma') {
                    $results[] = true;
                    continue;
                }

                $operator = isset( $rule['operator'] ) ? $rule['operator'] : '==';
                $target_value = isset( $rule['value'] ) ? $rule['value'] : '';
                
                $rule_result = true;
                switch ( $operator ) {
                    case '==': $rule_result = ( $test_format === $target_value ); break;
                    case '!=': $rule_result = ( $test_format !== $target_value ); break;
                    case 'contains': $rule_result = ( strpos( $test_format, $target_value ) !== false ); break;
                }
                $results[] = $rule_result;
            }
        }
        return $logical_operator === 'AND' ? ! in_array( false, $results, true ) : in_array( true, $results, true );
    }

    private function get_allowed_formats($perms) {
        $fixed_formats = ["BSc", "MSc", "Osztatlan", "Felsőoktatási szakképzés", "Szakirányú továbbképzés", "Mikroképzés", "Előkészítő"];
        if (empty($perms['conditions']) || empty($perms['conditions']['rules'])) return $fixed_formats;
        
        $json = wp_json_encode($perms['conditions']);
        if (strpos($json, 'kepzesi_forma') === false) return $fixed_formats;

        $allowed = array();
        foreach ($fixed_formats as $f) {
            if ($this->evaluate_format_only($perms['conditions'], $f)) {
                $allowed[] = $f;
            }
        }
        return empty($allowed) ? $fixed_formats : $allowed;
    }

    public function process_bulk_export_action() {
        $wp_list_table = _get_list_table( 'WP_Posts_List_Table' );
        $action = $wp_list_table->current_action();

        if ( $action === 'szeducate_export_selected' && isset( $_REQUEST['post'] ) && is_array( $_REQUEST['post'] ) ) {
            if ( ! current_user_can( 'edit_sz_courses' ) && ! current_user_can( 'manage_options' ) ) wp_die( 'Nincs jogosultságod.' );
            
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
        if ( ! current_user_can( 'edit_sz_courses' ) && ! current_user_can( 'manage_options' ) ) wp_die( 'Nincs jogosultságod.' );
        $formats = isset($_POST['formats']) && is_array($_POST['formats']) ? array_map('sanitize_text_field', $_POST['formats']) : array();
        $this->generate_excel( array(), true, $formats );
    }

    public function handle_export_all() {
        if ( ! current_user_can( 'edit_sz_courses' ) && ! current_user_can( 'manage_options' ) ) wp_die( 'Nincs jogosultságod.' );
        $formats = isset($_POST['formats']) && is_array($_POST['formats']) ? array_map('sanitize_text_field', $_POST['formats']) : array();
        $this->generate_excel( array(), false, $formats );
    }

    private function generate_excel( $post_ids = array(), $is_template_only = false, $requested_formats = array() ) {
        if ( ! class_exists( '\PhpOffice\PhpSpreadsheet\Spreadsheet' ) ) {
            wp_die('A PhpSpreadsheet konyvtar hianyzik!');
        }

        global $wpdb;
        $schema_json = get_option( 'szeducate_local_schema', '[]' );
        $schema = json_decode( $schema_json, true );
        $field_types = $this->get_schema_field_types();
        $perms = json_decode( get_option('szeducate_client_permissions', '{}'), true );
        $allowed_formats = $this->get_allowed_formats($perms);

        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('Kepzesek');

        $hidden_sheet = $spreadsheet->createSheet();
        $hidden_sheet->setTitle('_opciok');
        $hidden_sheet->setSheetState(\PhpOffice\PhpSpreadsheet\Worksheet\Worksheet::SHEETSTATE_HIDDEN);
        $hidden_col_index = 1;

        $reference_sheet = $spreadsheet->createSheet();
        $reference_sheet->setTitle('Kulcsszavak (Puska)');
        $ref_col_index = 1;
        $has_refs = false;

        $multi_select_keys = array();
        if ( is_array( $schema ) ) {
            foreach ( $schema as $group ) {
                if ( empty( $group['fields'] ) ) continue;
                foreach ( $group['fields'] as $field ) {
                    if ( in_array($field['type'], ['checkbox', 'multiselect']) || $field['key'] === 'kulcsszavak' ) {
                        $multi_select_keys[] = $field['key'];
                    }
                }
            }
        }

        $all_courses = $wpdb->get_results( "SELECT course_data FROM {$wpdb->prefix}szeducate_courses_data", ARRAY_A );
        $unique_values = array();
        
        if ( $all_courses ) {
            foreach ( $all_courses as $c ) {
                $data = json_decode( $c['course_data'], true );
                if ( ! is_array( $data ) ) continue;
                
                if ( !empty($perms['conditions']) && !empty($perms['conditions']['rules']) ) {
                    if ( !$this->evaluate_conditions( $perms['conditions'], $data ) ) continue;
                }

                foreach ( $data as $k => $v ) {
                    if ( empty( $v ) ) continue;
                    if ( ! in_array( $k, $multi_select_keys ) ) continue; 
                    
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
        $required_cols = array(1);

        $is_all_formats = empty($requested_formats);

        $sheet->setCellValue([$col_index, 1], 'Rendszer adatok');
        $sheet->setCellValue([$col_index, 2], 'Cím (Szak megnevezése)');
        $sheet->setCellValue([$col_index, 3], 'title');
        $sheet->setCellValue([$col_index, 4], '[KOTELEZO MEZO!] Szoveges mezo');
        
        $keys_map[$col_index] = [
            'key' => 'title',
            'group_id' => 'alap_adatok',
            'group_label' => 'Alap adatok'
        ];
        $group_borders[] = $col_index; 
        $col_index++;

        if ( is_array( $schema ) ) {
            foreach ( $schema as $group ) {
                if ( empty( $group['fields'] ) || !is_array( $group['fields'] ) ) continue;

                $include_group = false;
                if ( $group['group_id'] === 'alap_adatok' ) {
                    $include_group = true; 
                } elseif ( in_array($group['group_label'], $allowed_formats) ) {
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
                        $help_text = '[KOTELEZO MEZO!] ';
                        $required_cols[] = $col_index;
                    }

                    if ( $is_single_select ) {
                        $help_text .= '[Csak egy valaszthato]';
                    } elseif ( in_array( $field['type'], ['checkbox', 'multiselect'] ) ) {
                        $help_text .= '[Tobb ertek eseten PONTOSVESSZOVEL (;) valaszd el]';
                    } else {
                        switch ( $field['type'] ) {
                            case 'boolean': 
                            case 'true_false': 
                                $help_text .= '[Kapcsolo: "Igaz" vagy "Hamis"]'; break;
                            case 'number': $help_text .= '[Csak szam]'; break;
                            case 'date': $help_text .= '[Datum: EEEE-HH-NN]'; break;
                            case 'email': $help_text .= '[Email cim @sze.hu]'; break;
                            case 'wysiwyg': $help_text .= '[Formazott szoveg. Sima szoveget is fogad.]'; break;
                            case 'repeater': 
                            case 'links':
                            case 'group':
                            case 'flexible_content':
                                $help_text .= '[Komplex mezo (JSON formatum). Kerlek ne piszkald a strukturat!]'; break;
                            default: 
                                if ( $is_multi_select ) {
                                    $help_text .= '[Tobb ertek eseten PONTOSVESSZOVEL (;) valaszd el]';
                                } else {
                                    $help_text .= '[Szoveges mezo]'; 
                                }
                                break;
                        }
                    }

                    if ( !empty($field['help_text']) ) {
                        $help_text .= "\n\n Sugo: " . $field['help_text'];
                    }
                    $sheet->setCellValue([$col_index, 4], $help_text);

                    if ( $is_single_select && !empty($field['options']) ) {
                        $opts = array_map('trim', explode(';', $field['options']));
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
                            $validation->setErrorStyle( DataValidation::STYLE_STOP ); 
                            $validation->setAllowBlank(true);
                            $validation->setShowInputMessage(true);
                            $validation->setShowErrorMessage(true);
                            $validation->setShowDropDown(true);
                            $validation->setErrorTitle('Ervenytelen ertek');
                            $validation->setError('Kerlek, kizarolag a legordulo listabol valassz!');
                            $validation->setFormula1("'_opciok'!\${$hidden_col_letter}\$1:\${$hidden_col_letter}\$" . ($hidden_row - 1));
                            
                            $sheet->setDataValidation("{$col_letter}5:{$col_letter}1000", $validation);
                            $hidden_col_index++;
                        }
                    }

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
            $reference_sheet->setCellValue([1, 1], 'Meg nincs egyetlen kitoltott kulcsszo sem az adatbazisban, amibol referenciat lehetne epiteni.');
        } else {
            $reference_sheet->getStyle('A1:' . Coordinate::stringFromColumnIndex($ref_col_index - 1) . '1')->applyFromArray([
                'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['argb' => 'FFE0E0E0']],
            ]);
        }

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

        foreach ($required_cols as $req_col) {
            $letter = Coordinate::stringFromColumnIndex($req_col);
            $sheet->getStyle("{$letter}2")->applyFromArray([
                'font' => ['bold' => true, 'color' => ['argb' => 'FFFFFFFF']],
                'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['argb' => 'FFD63638']],
            ]);
            $sheet->getStyle("{$letter}5:{$letter}1000")->applyFromArray([
                'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['argb' => 'FFFFF2F2']],
            ]);
        }

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
                'borders' => ['right' => ['borderStyle' => Border::BORDER_MEDIUM, 'color' => ['argb' => 'FF000000']]],
            ]);
        }

        for ($i = 1; $i < $col_index; $i++) {
            $sheet->getColumnDimensionByColumn($i)->setWidth(25); 
        }
        $sheet->freezePane('B5');

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

                    if ( !empty($perms['conditions']) && !empty($perms['conditions']['rules']) ) {
                        if ( !$this->evaluate_conditions( $perms['conditions'], $course_data ) ) continue;
                    }

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
                        } elseif ( in_array( $group_label, $allowed_formats ) ) {
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
                            $ftype = $field_types[$key] ?? 'text';
                            
                            if ( $ftype === 'boolean' || $ftype === 'true_false' ) {
                                $is_true = ($val === true || $val === '1' || $val === 1 || strtolower((string)$val) === 'true');
                                $sheet->setCellValue([$col_idx, $current_row], $is_true ? 'Igaz' : 'Hamis');
                            } elseif ( in_array($ftype, ['repeater', 'links', 'group', 'flexible_content']) ) {
                                if ( is_array($val) || is_object($val) ) {
                                    $sheet->setCellValueExplicit([$col_idx, $current_row], wp_json_encode($val, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES), \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
                                } else {
                                    $sheet->setCellValueExplicit([$col_idx, $current_row], (string)$val, \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
                                }
                            } elseif ( is_array( $val ) ) {
                                $sheet->setCellValueExplicit([$col_idx, $current_row], implode( '; ', $val ), \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
                            } else {
                                $sheet->setCellValue([$col_idx, $current_row], (string)$val);
                            }
                        }
                    }
                    $current_row++;
                }
            }
        }

        $spreadsheet->setActiveSheetIndex(0);

        if ( $is_template_only ) {
            $formats_str = empty($requested_formats) ? 'MINDEN' : implode('_', $requested_formats);
            $formats_str = preg_replace( '/[^A-Za-z0-9_aeioouuAEIOOUU]/', '_', $formats_str );
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

    public function render_page() {
        $parsed_data = null;
        $error_msg = '';
        $skipped_courses = array();

        $perms = json_decode( get_option('szeducate_client_permissions', '{}'), true );
        $allowed_formats = $this->get_allowed_formats($perms);

        if ( isset( $_POST['submit_excel'] ) && isset( $_FILES['excel_file'] ) ) {
            if ( $_FILES['excel_file']['error'] === UPLOAD_ERR_OK ) {
                $file_tmp = $_FILES['excel_file']['tmp_name'];

                if ( ! class_exists( '\PhpOffice\PhpSpreadsheet\IOFactory' ) ) {
                    $error_msg = 'Kritikus hiba: A PhpSpreadsheet konyvtar hianyzik a szerverrol!';
                } else {
                    try {
                        $spreadsheet = \PhpOffice\PhpSpreadsheet\IOFactory::load($file_tmp);
                        $sheet = $spreadsheet->getSheetByName('Kepzesek');
                        if ( ! $sheet ) {
                            $sheet = $spreadsheet->getActiveSheet(); 
                        }

                        $rows = $sheet->toArray(); 
                        
                        $is_valid_format = (isset($rows[0][0]) && $rows[0][0] === 'Rendszer adatok');
                        $keys = isset($rows[2]) ? $rows[2] : array();
                        
                        if ( $is_valid_format && !empty($keys) && in_array( 'title', $keys ) ) {
                            $parsed_data = array();
                            $field_types = $this->get_schema_field_types();
                            
                            for ( $i = 4; $i < count($rows); $i++ ) {
                                $row = $rows[$i];
                                if ( empty( array_filter( $row ) ) ) continue; 
                                
                                $course = array( 'course_data' => array() );
                                foreach ( $row as $index => $value ) {
                                    $key = $keys[$index] ?? null;
                                    if ( ! $key ) continue;
                                    
                                    $val_str = trim((string)$value);

                                    if ( $key === 'title' ) {
                                        $course['title'] = $val_str;
                                    } else {
                                        $ftype = $field_types[$key] ?? 'text';
                                        $val_to_set = $val_str;

                                        if ( $ftype === 'boolean' || $ftype === 'true_false' ) {
                                            $val_lower = strtolower($val_str);
                                            if ( in_array( $val_lower, ['1', 'true', 'igen', 'yes', 'y', 'igaz'], true ) ) {
                                                $val_to_set = "1";
                                            } elseif ( in_array( $val_lower, ['0', 'false', 'nem', 'no', 'n', 'hamis'], true ) ) {
                                                $val_to_set = "0";
                                            } else {
                                                $val_to_set = null;
                                            }
                                        } 
                                        elseif ( in_array($ftype, ['repeater', 'links', 'group', 'flexible_content']) ) {
                                            if ( $val_str === '' ) {
                                                $val_to_set = null;
                                            } else {
                                                $decoded = json_decode($val_str, true);
                                                if ( json_last_error() === JSON_ERROR_NONE && (is_array($decoded) || is_object($decoded)) ) {
                                                    $val_to_set = $decoded;
                                                } else {
                                                    $val_to_set = $val_str; 
                                                }
                                            }
                                        } 
                                        elseif ( in_array($ftype, ['checkbox', 'multiselect', 'select']) ) {
                                            if ( $val_str === '' ) {
                                                $val_to_set = null;
                                            } elseif ( strpos($val_str, ';') !== false ) {
                                                $parts = array_map('trim', explode(';', $val_str));
                                                $val_to_set = array_values(array_filter($parts, function($p){ return $p !== ''; }));
                                            } else {
                                                if ( in_array($ftype, ['checkbox', 'multiselect']) ) {
                                                    $val_to_set = [ $val_str ];
                                                } else {
                                                    $val_to_set = $val_str;
                                                }
                                            }
                                        } 
                                        else {
                                            $val_to_set = ( $val_str === '' ) ? null : $val_str;
                                        }

                                        $course['course_data'][$key] = $val_to_set;
                                    }
                                }

                                if ( ! empty( $course['title'] ) ) {
                                    if ( !empty($perms['conditions']) && !empty($perms['conditions']['rules']) ) {
                                        if ( !$this->evaluate_conditions( $perms['conditions'], $course['course_data'] ) ) {
                                            $skipped_courses[] = $course['title'];
                                            continue;
                                        }
                                    }

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
                            $error_msg = 'Ervenytelen fajl! Kerjuk, kizarolag a rendszerbol letoltott formazott sablont toltse fel!';
                        }
                    } catch ( Exception $e ) {
                        $error_msg = 'Nem sikerult beolvasni az Excel fajlt: ' . $e->getMessage();
                    }
                }
            } else {
                $error_msg = 'Feltoltesi hiba tortent.';
            }
        }
        
        ?>
        <div class="wrap">
            <h1>Tömeges Excel Import / Export</h1>

            <div class="card" style="max-width: 1000px; padding: 20px; margin-top: 20px; display: flex; gap: 30px;">
                
                <div style="flex: 1;">
                    <form method="POST" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
                        <input type="hidden" name="action" value="szeducate_download_csv_template">
                        <h3>Üres Sablon Generálása</h3>
                        <p style="color: #646970; font-size: 13px;">Válaszd ki, melyik képzési formákhoz szeretnél sablont letölteni. Az Excel csak a releváns oszlopokat fogja tartalmazni. <strong>Ha nem jelölsz ki egyet sem, az összes engedélyezett oszlopot letölti.</strong></p>
                        
                        <div style="margin: 15px 0; display: flex; flex-direction: column; gap: 8px;">
                            <label style="border-bottom: 1px solid #ddd; padding-bottom: 8px; margin-bottom: 5px;">
                                <input type="checkbox" class="sz-toggle-all" data-target=".dl-formats-sablon"> <strong>Mind Kijelölése / Törlése</strong>
                            </label>
                            <?php foreach($allowed_formats as $format): ?>
                                <label><input type="checkbox" name="formats[]" class="dl-formats-sablon" value="<?php echo esc_attr($format); ?>"> <?php echo esc_html($format); ?></label>
                            <?php endforeach; ?>
                        </div>
                        
                        <button type="submit" class="button button-secondary">Sablon Letöltése (.xlsx)</button>
                    </form>
                </div>

                <div style="border-left: 1px solid #dcdde1; padding-left: 30px; flex: 1;">
                    <form method="POST" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
                        <input type="hidden" name="action" value="szeducate_export_all_csv">
                        <h3>Adatok Exportálása</h3>
                        <p style="color: #646970; font-size: 13px;">Válaszd ki, mely képzési formákat szeretnéd kiexportálni. A rendszer leszűri a sorokat ÉS a felesleges oszlopokat is. <strong>Ha nem jelölsz ki egyet sem, a teljes adatbázist kiexportálja.</strong></p>
                        
                        <div style="margin: 15px 0; display: flex; flex-direction: column; gap: 8px;">
                            <label style="border-bottom: 1px solid #ddd; padding-bottom: 8px; margin-bottom: 5px;">
                                <input type="checkbox" class="sz-toggle-all" data-target=".dl-formats-export"> <strong>Mind Kijelölése / Törlése</strong>
                            </label>
                            <?php foreach($allowed_formats as $format): ?>
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

            <?php if ( !empty($skipped_courses) ) : ?>
                <div class="notice notice-warning">
                    <p><strong>Figyelem!</strong> Az alábbi képzéseket az importálás során a rendszer kihagyta, mert a karod Jogosultsági Mátrixa nem engedélyezi a módosításukat vagy létrehozásukat:</p>
                    <ul style="list-style: disc; margin-left: 20px;">
                        <?php foreach($skipped_courses as $sc) echo '<li>' . esc_html($sc) . '</li>'; ?>
                    </ul>
                </div>
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
                    <p>Talált, engedélyezett bejegyzések: <strong><?php echo count( $parsed_data ); ?></strong> db.</p>
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
                        body: JSON.stringify(course)
                    });

                    const result = await response.json();
                    if (response.ok) {
                        logMsg(`Siker: ${course.title}`);
                    } else {
                        logMsg(`Hiba [${course.title}]: ${result.message || 'Ismeretlen API hiba'}`);
                    }
                } catch (error) {
                    logMsg(`Kritikus hiba [${course.title}]: ${error.message}`);
                }

                currentIndex++;
                processNext();
            }

            if (total > 0) {
                processNext();
            } else {
                statusText.innerText = 'Nem található érvényes adat az importáláshoz.';
            }
            <?php endif; ?>
        });
        </script>
        <?php
    }
}