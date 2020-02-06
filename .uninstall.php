<?php

namespace NikolayS93\Exchanger;

global $wpdb;

$table = Register::get_exchange_table_name();

// When table is exists
if ( $wpdb->get_var( "SHOW TABLES LIKE '$table'" ) == $table ) {
	// Required for dbDelta.
	require_once( ABSPATH . 'wp-admin/includes/upgrade.php' );

	$wpdb->query( "ALTER TABLE  `{$table}` DROP INDEX  `code`" );
	dbDelta( "DROP TABLE `{$table}`;" );
}
