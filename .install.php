<?php

namespace NikolayS93\Exchange;

global $wpdb;

/**
 * Add XML post_mime_type and meta index.
 */
const MIME_TYPE_INDEX = 'id_post_mime_type';
const META_XML_INDEX = 'ex_meta_key_meta_value';

// Check, if table already exists.
if ( ! $wpdb->get_var( "SHOW INDEX FROM {$wpdb->posts} WHERE Key_name = '{MIME_TYPE_INDEX}';" ) ) {
	$wpdb->query( "ALTER TABLE {$wpdb->posts} ADD INDEX {MIME_TYPE_INDEX} (ID, post_mime_type(78))" );
}

// Check, if table already exists.
if ( ! $wpdb->get_var( "SHOW INDEX FROM {$wpdb->termmeta} WHERE Key_name = '{META_XML_INDEX}';" ) ) {
	$wpdb->query( "ALTER TABLE {$wpdb->termmeta} ADD INDEX {META_XML_INDEX} (ID, post_mime_type(68))" );
}

/**
 * Create taxonomy meta table
 */
$taxonomy_meta_table = $wpdb->get_blog_prefix() . 'woocommerce_attribute_taxonomymeta';
$charset_collate = $wpdb->get_charset_collate();

// Check, if table already exists.
if ( $wpdb->get_var( "SHOW TABLES LIKE '{$taxonomy_meta_table}'" ) !== $taxonomy_meta_table ) {
	require_once( ABSPATH . 'wp-admin/includes/upgrade.php' ); // dbDelta utils required!
	// Create table.
	dbDelta( "CREATE TABLE {$taxonomy_meta_table} (
        `meta_id` bigint(20) unsigned NOT NULL AUTO_INCREMENT PRIMARY KEY,
        `tax_id` bigint(20) unsigned NOT NULL DEFAULT '0',
        `meta_key` varchar(255) NULL,
        `meta_value` longtext NULL
    ) {$charset_collate};" );
	// ..with indexes.
	$wpdb->query( "ALTER TABLE {$taxonomy_meta_table}
        ADD INDEX `tax_id` (`tax_id`),
        ADD INDEX `meta_key` (`meta_key`(191));" );
}

/**
 * Create exhcange buffer table.
 */
$buffer_table = $wpdb->get_blog_prefix() . 'exchange';

// Check, if table already exists.
if ( $wpdb->get_var( "SHOW TABLES LIKE '{$buffer_table}'" ) !== $buffer_table ) {
	require_once( ABSPATH . 'wp-admin/includes/upgrade.php' ); // dbDelta utils required!
	// Create table.
	dbDelta( "CREATE TABLE {$buffer_table} (
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
        `attributes` longtext NULL,
        `delete` tinyint(1) NULL
    ) {$charset_collate};" );
	// ..with indexes.
    $wpdb->query( "ALTER TABLE {$buffer_table}
		ADD UNIQUE INDEX `code` (`code`);" );
}

/**
 * Create empty folder for upload exhange data
 */
$exchange_dir = Plugin::get_exchange_data_dir();

if ( ! is_dir( $exchange_dir ) ) {
	mkdir( $exchange_dir );
    mkdir( $exchange_dir . "/logs/" );

	file_put_contents( $exchange_dir . "/.htaccess", "Deny from all" );
	file_put_contents( $exchange_dir . "/index.html", '' );
}

/**
 * Create plugin settings option.
 */
add_option( Plugin::get_option_name(), array() );
