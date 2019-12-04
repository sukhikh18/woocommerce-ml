<?php
/**
 * F. Завершающее событие загрузки данных
 * http://<сайт>/<путь> /1c_exchange.php?type=catalog&mode=complete
 *
 * @since  3.0
 */

namespace NikolayS93\Exchange;

/**
 * Insert count the number of records in a category
 * /
 * Update::update_term_counts();
 */
// flush_rewrite_rules();

/**
 * Reset start date
 *
 * @todo @fixit (check between)
 */
update_option( 'exchange_start-date', '' );

/**
 * Refresh version
 */
update_option( 'exchange_version', '' );

delete_transient( 'wc_attribute_taxonomies' );

Plugin::set_mode( '' );
update_option( 'exchange_last-update', current_time( 'mysql' ) );

// if ( is_debug() ) {
// $Plugin = Plugin();
// $file   = Request::get_file();
// get_exchange_dir contain Plugin::try_make_dir(), Plugin::check_writable()
// $path = $Plugin->get_exchange_dir( Request::get_type() ) . '/' . date( 'YmdH' ) . '_debug';
// @mkdir( $path );
// @rename( $zip_path, $path . '/' . $file['name'] . '.' . $file['ext'] );
// }

exit( "success\nВыгрузка данных завершена" );