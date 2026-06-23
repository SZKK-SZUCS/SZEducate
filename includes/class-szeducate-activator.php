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
		require_once( ABSPATH . 'wp-admin/includes/upgrade.php' );

		$table_name = $wpdb->prefix . 'szeducate_courses_data';
		$charset_collate = $wpdb->get_charset_collate();

		$sql = "CREATE TABLE $table_name (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			hub_id bigint(20) unsigned DEFAULT NULL,
			local_post_id bigint(20) unsigned DEFAULT NULL,
			title varchar(255) NOT NULL,
			course_data longtext DEFAULT NULL,
			status varchar(20) DEFAULT 'publish',
			updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
";

		$schema_json = get_option( 'szeducate_schema', '[]' );
		$schema = json_decode( $schema_json, true );
		$dynamic_columns = array();
		$indexes = array();

		if ( is_array( $schema ) ) {
			foreach ( $schema as $group ) {
				if ( ! empty( $group['fields'] ) && is_array( $group['fields'] ) ) {
					foreach ( $group['fields'] as $field ) {
						if ( ! empty( $field['is_filterable'] ) && $field['is_filterable'] ) {
							$key = preg_replace( '/[^a-z0-9_]/', '', strtolower( $field['key'] ) );
							if ( empty( $key ) ) continue;

							$type = "varchar(255) DEFAULT ''";
							if ( $field['type'] === 'number' ) {
								$type = "bigint(20) DEFAULT NULL";
							} elseif ( $field['type'] === 'date' ) {
								$type = "datetime DEFAULT NULL";
							} elseif ( $field['type'] === 'boolean' ) {
								$type = "tinyint(1) DEFAULT 0";
							}

							$dynamic_columns[] = "$key $type";
							$indexes[] = "KEY $key ($key)";
						}
					}
				}
			}
		}

		foreach ( $dynamic_columns as $col ) {
			$sql .= "\t\t\t$col,\n";
		}

		$sql .= "\t\t\tPRIMARY KEY  (id),\n";
		$sql .= "\t\t\tKEY hub_id (hub_id),\n";
		$sql .= "\t\t\tKEY local_post_id (local_post_id)";

		if ( ! empty( $indexes ) ) {
			$sql .= ",\n\t\t\t" . implode( ",\n\t\t\t", $indexes );
		}

		$sql .= ",\n\t\t\tCONSTRAINT course_data_json CHECK (json_valid(course_data))";
		
		$sql .= "\n\t\t) ENGINE=InnoDB $charset_collate;";

		dbDelta( $sql );
	}
}