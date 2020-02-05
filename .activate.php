<?php

namespace NikolayS93\Exchanger;

/**
 * Maybe insert posts mime_type INDEX if is not exists
 * @return [type] [description]
 */
function set_mime_type_indexes() {
	global $wpdb;

	$postMimeIndexName = 'id_post_mime_type';
	$result            = $wpdb->get_var( "SHOW INDEX FROM $wpdb->posts WHERE Key_name = '$postMimeIndexName';" );
	if ( ! $result ) {
		return $wpdb->query( "ALTER TABLE $wpdb->posts
			ADD INDEX $postMimeIndexName (ID, post_mime_type(78))" );
	}

	return false;
}

/**
 * Maybe create taxonomy meta table
 * @return [type] [description]
 */
function create_taxonomy_meta_table() {
	global $wpdb;

	$charset_collate = $wpdb->get_charset_collate();
	$taxonomymeta = $wpdb->get_blog_prefix() . 'woocommerce_attribute_taxonomymeta';

	if ( $wpdb->get_var( "SHOW TABLES LIKE '$taxonomymeta'" ) != $taxonomymeta ) {
		/** Required for dbDelta */
		require_once( ABSPATH . 'wp-admin/includes/upgrade.php' );

		dbDelta( "CREATE TABLE {$taxonomymeta} (
            `meta_id` bigint(20) unsigned NOT NULL AUTO_INCREMENT PRIMARY KEY,
            `tax_id` bigint(20) unsigned NOT NULL DEFAULT '0',
            `meta_key` varchar(255) NULL,
            `meta_value` longtext NULL
        ) {$charset_collate};" );

		$wpdb->query( "
            ALTER TABLE {$taxonomymeta}
                ADD INDEX `tax_id` (`tax_id`),
                ADD INDEX `meta_key` (`meta_key`(191));" );
	}
}

function create_temporary_exchange_table() {
	global $wpdb;

	$charset_collate = $wpdb->get_charset_collate();
	$tmp_exchange_table_name = $wpdb->get_blog_prefix() . EXCHANGE_TMP_TABLENAME;

	// If table not exists
	if ( $wpdb->get_var( "SHOW TABLES LIKE '$tmp_exchange_table_name'" ) != $tmp_exchange_table_name ) {
		/** Required for dbDelta */
		require_once( ABSPATH . 'wp-admin/includes/upgrade.php' );

		dbDelta( "CREATE TABLE {$tmp_exchange_table_name} (
            `product_id` bigint(20) unsigned NULL DEFAULT '0',
            `code` varchar(100) NOT NULL PRIMARY KEY,
            `name` varchar(200) NULL,
            `slug` varchar(200) NULL,
            `qty` float NULL,
            `price` float unsigned NULL,
            `tax` float unsigned NULL,
            `description` longtext NULL,
            `meta` longtext NULL,
            `cats` longtext NULL,
            `warehouses` longtext NULL,
            `attributes` longtext NULL
        ) {$charset_collate};" );

		$wpdb->query( "
            ALTER TABLE {$tmp_exchange_table_name}
                ADD UNIQUE INDEX `code` (`code`);" );
	}
}

/**
 * Call this script before activate plugin
 */
set_mime_type_indexes();
create_taxonomy_meta_table();
create_temporary_exchange_table();

file_put_contents( plugin()->get_exchange_dir() . "/.htaccess", "Deny from all" );
file_put_contents( plugin()->get_exchange_dir() . "/index.html", '' );
