<?php

namespace NikolayS93\Exchange;

function register_post_types() {
	$warehouseLabels = array(
		'name'              => 'Склады',
		'singular_name'     => 'Склад',
		'search_items'      => 'Search склад',
		'all_items'         => 'All склад',
		'view_item '        => 'View склад',
		'parent_item'       => 'Parent склад',
		'parent_item_colon' => 'Parent склад:',
		'edit_item'         => 'Edit склад',
		'update_item'       => 'Update склад',
		'add_new_item'      => 'Add New склад',
		'new_item_name'     => 'New склад Name',
		'menu_name'         => 'Склады',
	);

	register_taxonomy(
		apply_filters( 'warehouseTaxonomySlug', DEFAULT_WAREHOUSE_TAX_SLUG ),
		array( 'product' ),
		array(
			'label'       => $warehouseLabels['name'],
			'labels'      => $warehouseLabels,
			'description' => '', // описание таксономии
			'public'      => true,
			// 'publicly_queryable'    => null, // равен аргументу public
			// 'show_in_nav_menus'     => true, // равен аргументу public
			// 'show_ui'               => true, // равен аргументу public
			// 'show_tagcloud'         => true, // равен аргументу show_ui
		)
	);
}