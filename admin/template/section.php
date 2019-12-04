<?php
/**
 * Page main section area output file
 *
 * @package Newproject.WordPress.plugin
 */

use NikolayS93\WPListTable as Table;

/**
 * WordPress table example
 */

$table = new Table();
$table->set_columns();

foreach ( get_posts() as $_post ) {
	$table->set_value( array( 'title' => esc_html( $_post->post_title ) ) );
}

$table->prepare_items();
$table->display();
