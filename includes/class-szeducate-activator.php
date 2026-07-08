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
				`owner_client_id` bigint(20) unsigned DEFAULT NULL,
				`local_post_id` bigint(20) unsigned DEFAULT NULL,
				`title` varchar(255) NOT NULL,
				`course_data` longtext DEFAULT NULL,
				`status` varchar(20) DEFAULT 'publish',
				`updated_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
				`updated_by` varchar(191) DEFAULT NULL,
				PRIMARY KEY (`id`),
				KEY `hub_id` (`hub_id`),
				KEY `owner_client_id` (`owner_client_id`),
				KEY `local_post_id` (`local_post_id`),
				KEY `title` (`title`)
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

			// Rendszer-szintű oszlopok, amiknek MINDIG léteznie kell, függetlenül a sémától -
			// régebbi, e módosítás előtt létrehozott táblákon is pótoljuk őket.
			$system_columns = array(
				'owner_client_id' => "bigint(20) unsigned DEFAULT NULL",
				'title'           => null, // már kötelezően létezik a CREATE TABLE óta, csak az indexét pótoljuk itt
			);

			foreach ( $system_columns as $col_name => $col_type ) {
				if ( $col_type && ! in_array( $col_name, $existing_cols ) ) {
					$wpdb->query( "ALTER TABLE `{$table_name}` ADD COLUMN `$col_name` $col_type" );
				}
				if ( ! in_array( $col_name, $existing_indexes ) ) {
					$wpdb->query( "ALTER TABLE `{$table_name}` ADD INDEX `$col_name` (`$col_name`)" );
				}
			}

			// Ki (melyik kliens, vagy Hub admin) módosította utoljára a rekordot - csak
			// megjelenítésre használt, nem indexelt oszlop.
			if ( ! in_array( 'updated_by', $existing_cols ) ) {
				$wpdb->query( "ALTER TABLE `{$table_name}` ADD COLUMN `updated_by` varchar(191) DEFAULT NULL" );
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

			self::invalidate_table_columns_cache( $table_name );
		}

		self::ensure_versions_table();
	}

	// --- Szerkesztési előzmények (ki, mikor, mit módosított egy Képzésen) - minden
	// sikeres mentés egy új sort hoz létre, nem íródik felül semmi. Ugyanaz a tábla-
	// szerkezet fut a Hub-on (hub_id-vel kulcsolva - ez az ÁTFOGÓ, kliens-független
	// előzmény) ÉS a Kliensen (local_post_id-vel kulcsolva - csak tartalék, ha a Hub
	// épp nem lenne elérhető).
	private static function ensure_versions_table() {
		global $wpdb;
		$table_name = $wpdb->prefix . 'szeducate_course_versions';

		if ( $wpdb->get_var( "SHOW TABLES LIKE '{$table_name}'" ) != $table_name ) {
			$charset_collate = $wpdb->get_charset_collate();
			$sql = "CREATE TABLE `{$table_name}` (
				`id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
				`local_post_id` bigint(20) unsigned DEFAULT NULL,
				`hub_id` bigint(20) unsigned DEFAULT NULL,
				`title` varchar(255) NOT NULL,
				`course_data` longtext DEFAULT NULL,
				`changed_fields` longtext DEFAULT NULL,
				`edited_by` varchar(191) DEFAULT NULL,
				`edited_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
				PRIMARY KEY (`id`),
				KEY `local_post_id` (`local_post_id`),
				KEY `hub_id` (`hub_id`)
			) $charset_collate;";

			$wpdb->query( $sql );
			return;
		}

		// Retrofit: a hub_id oszlop egy korábbi (Kliens-only) körben még nem létezett.
		$existing_cols = $wpdb->get_col( "DESCRIBE `{$table_name}`", 0 );
		if ( ! in_array( 'hub_id', $existing_cols ) ) {
			$wpdb->query( "ALTER TABLE `{$table_name}` ADD COLUMN `hub_id` bigint(20) unsigned DEFAULT NULL, ADD INDEX `hub_id` (`hub_id`)" );
		}
	}

	// --- DESCRIBE-eredmény rövid gyorsítótárazása, hogy ne kelljen minden egyes Képzés-írásnál
	// (Hub és Kliens oldalon egyaránt) újra lekérdezni az oszlopneveket - csak séma-mentéskor
	// (update_database_schema() lefutásakor) érvénytelenítjük.
	public static function get_cached_table_columns( $table_name ) {
		$cache_key = 'szeducate_columns_' . md5( $table_name );
		$cached = get_transient( $cache_key );
		if ( is_array( $cached ) ) return $cached;

		global $wpdb;
		$columns = $wpdb->get_col( "DESCRIBE `{$table_name}`", 0 );
		set_transient( $cache_key, $columns, HOUR_IN_SECONDS );
		return $columns;
	}

	public static function invalidate_table_columns_cache( $table_name ) {
		delete_transient( 'szeducate_columns_' . md5( $table_name ) );
	}
}