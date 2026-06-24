<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class SZEducate_Activator {

	public static function activate() {
		if ( false === get_option( 'szeducate_settings' ) ) {
			add_option( 'szeducate_settings', array(
				'mode'      => 'client',
				'hub_url'   => 'https://wordpress.sze.hu',
				'api_token' => ''
			) );
		}
		
		self::update_database_schema();
	}

	public static function update_database_schema() {
		global $wpdb;
		$table_name = $wpdb->prefix . 'szeducate_courses_data';
		$charset_collate = $wpdb->get_charset_collate();

		$schema_json = get_option( 'szeducate_schema', '[]' );
		$schema = json_decode( $schema_json, true );
		
		// Ha a fő séma üres (mert pl. a Kliens gépen vagyunk), használjuk a letöltött lokális sémát
		if ( empty( $schema ) ) {
			$schema_json = get_option( 'szeducate_local_schema', '[]' );
			$schema = json_decode( $schema_json, true );
		}

		$dynamic_columns = array();

		// Összegyűjtjük az indexelendő/szűrhető mezőket
		if ( is_array( $schema ) ) {
			foreach ( $schema as $group ) {
				if ( ! empty( $group['fields'] ) && is_array( $group['fields'] ) ) {
					foreach ( $group['fields'] as $field ) {
						if ( ! empty( $field['is_filterable'] ) && $field['is_filterable'] ) {
							$key = preg_replace( '/[^a-z0-9_]/', '', strtolower( $field['key'] ) );
							if ( empty( $key ) ) continue;

							$type = "varchar(255) DEFAULT NULL";
							if ( $field['type'] === 'number' ) {
								$type = "bigint(20) DEFAULT NULL";
							} elseif ( $field['type'] === 'date' ) {
								$type = "datetime DEFAULT NULL";
							} elseif ( $field['type'] === 'boolean' || $field['type'] === 'true_false' ) {
								$type = "tinyint(1) DEFAULT 0";
							}

							$dynamic_columns[$key] = $type;
						}
					}
				}
			}
		}

		// 1. Tábla létrehozása (Teljesen megkerülve a megbízhatatlan dbDelta motort)
		if ( $wpdb->get_var( "SHOW TABLES LIKE '{$table_name}'" ) != $table_name ) {
			$sql = "CREATE TABLE `{$table_name}` (
				`id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
				`hub_id` bigint(20) unsigned DEFAULT NULL,
				`local_post_id` bigint(20) unsigned DEFAULT NULL,
				`title` varchar(255) NOT NULL,
				`course_data` longtext DEFAULT NULL,
				`status` varchar(20) DEFAULT 'publish',
				`updated_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
				PRIMARY KEY (`id`),
				KEY `hub_id` (`hub_id`),
				KEY `local_post_id` (`local_post_id`)
			) $charset_collate;";
			
			$wpdb->query( $sql );
		}

		// 2. Oszlopok és Indexek "sebészi" hozzáadása egyesével (Védett kulcsszavak kezelésével)
		if ( $wpdb->get_var( "SHOW TABLES LIKE '{$table_name}'" ) == $table_name ) {
			$existing_cols = $wpdb->get_col( "DESCRIBE `{$table_name}`", 0 );
			
			$existing_indexes_raw = $wpdb->get_results( "SHOW INDEX FROM `{$table_name}`", ARRAY_A );
			$existing_indexes = array();
			if ( $existing_indexes_raw ) {
				foreach ( $existing_indexes_raw as $ei ) {
					$existing_indexes[] = $ei['Key_name'];
				}
			}

			foreach ( $dynamic_columns as $col_name => $col_type ) {
				// Oszlop hozzáadása, ha hiányzik
				if ( ! in_array( $col_name, $existing_cols ) ) {
					$wpdb->query( "ALTER TABLE `{$table_name}` ADD COLUMN `$col_name` $col_type" );
				}
				
				// Index hozzáadása, ha hiányzik
				if ( ! in_array( $col_name, $existing_indexes ) ) {
					$wpdb->query( "ALTER TABLE `{$table_name}` ADD INDEX `$col_name` (`$col_name`)" );
				}
			}
		}
	}
}