<?php
/**
 * Plugin Name: Duplicate Post (One-Click)
 * Description: One-click "Duplicate" action for posts, pages and custom post types. Copies content, taxonomies and meta into a new draft.
 * Version: 1.0.0
 * Author: Azan Umer
 * License: MIT
 * Text Domain: wdp
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'WDP_VERSION', '1.0.0' );

/**
 * Add a "Duplicate" row action to post/page list tables.
 */
add_filter( 'post_row_actions', 'wdp_row_action', 10, 2 );
add_filter( 'page_row_actions', 'wdp_row_action', 10, 2 );

function wdp_row_action( $actions, $post ) {
	if ( current_user_can( 'edit_posts' ) && 'trash' !== $post->post_status ) {
		$url = wp_nonce_url(
			admin_url( 'admin.php?action=wdp_duplicate&post=' . $post->ID ),
			'wdp_duplicate_' . $post->ID
		);
		$actions['wdp_duplicate'] = sprintf(
			'<a href="%s" aria-label="%s">%s</a>',
			esc_url( $url ),
			esc_attr__( 'Duplicate this item', 'wdp' ),
			esc_html__( 'Duplicate', 'wdp' )
		);
	}
	return $actions;
}

/**
 * Register a "Duplicate" bulk action.
 */
add_filter( 'bulk_actions-edit-post', 'wdp_bulk_action' );
add_filter( 'bulk_actions-edit-page', 'wdp_bulk_action' );

function wdp_bulk_action( $actions ) {
	if ( current_user_can( 'edit_posts' ) ) {
		$actions['wdp_duplicate_bulk'] = __( 'Duplicate', 'wdp' );
	}
	return $actions;
}

add_filter( 'handle_bulk_actions-edit-post', 'wdp_handle_bulk', 10, 3 );
add_filter( 'handle_bulk_actions-edit-page', 'wdp_handle_bulk', 10, 3 );

function wdp_handle_bulk( $redirect, $action, $ids ) {
	if ( 'wdp_duplicate_bulk' !== $action ) {
		return $redirect;
	}
	$count = 0;
	foreach ( $ids as $id ) {
		if ( wdp_duplicate_post( (int) $id ) ) {
			$count++;
		}
	}
	return add_query_arg( 'wdp_duplicated', $count, $redirect );
}

add_action( 'admin_notices', function () {
	if ( ! isset( $_GET['wdp_duplicated'] ) ) {
		return;
	}
	$n = (int) $_GET['wdp_duplicated'];
	printf(
		'<div class="notice notice-success is-dismissible"><p>%s</p></div>',
		esc_html( sprintf( _n( '%d draft created.', '%d drafts created.', $n, 'wdp' ), $n ) )
	);
} );

/**
 * Handle the single-duplicate admin action (nonce + capability checked).
 */
add_action( 'admin_action_wdp_duplicate', function () {
	$post_id = isset( $_GET['post'] ) ? (int) $_GET['post'] : 0;
	check_admin_referer( 'wdp_duplicate_' . $post_id );

	if ( ! current_user_can( 'edit_posts' ) ) {
		wp_die( esc_html__( 'You are not allowed to duplicate posts.', 'wdp' ) );
	}

	$new_id = wdp_duplicate_post( $post_id );
	if ( ! $new_id ) {
		wp_die( esc_html__( 'Duplication failed.', 'wdp' ) );
	}

	wp_safe_redirect(
		add_query_arg(
			array( 'wdp_duplicated' => 1 ),
			admin_url( 'edit.php?post_type=' . get_post_type( $new_id ) )
		)
	);
	exit;
} );

/**
 * Duplicate a post/page as a new draft.
 *
 * Copies the content fields, taxonomy terms and post meta (except the keys
 * listed by the wdp_excluded_meta_keys filter). The copy is assigned to the
 * current user and titled "<original> — Copy".
 *
 * @param int $post_id Source post ID.
 * @return int|false New post ID, or false on failure.
 */
function wdp_duplicate_post( $post_id ) {
	$post = get_post( $post_id );
	if ( ! $post || ! current_user_can( 'edit_post', $post_id ) ) {
		return false;
	}

	$new_id = wp_insert_post(
		array(
			'post_title'     => $post->post_title . ' ' . __( '— Copy', 'wdp' ),
			'post_content'   => $post->post_content,
			'post_excerpt'   => $post->post_excerpt,
			'post_status'    => 'draft',
			'post_type'      => $post->post_type,
			'post_author'    => get_current_user_id(),
			'post_password'  => $post->post_password,
			'post_parent'    => $post->post_parent,
			'menu_order'     => $post->menu_order,
			'comment_status' => $post->comment_status,
			'ping_status'    => $post->ping_status,
		),
		true
	);

	if ( is_wp_error( $new_id ) ) {
		return false;
	}

	// Taxonomy terms.
	$taxonomies = get_object_taxonomies( $post->post_type );
	foreach ( $taxonomies as $taxonomy ) {
		$terms = wp_get_object_terms( $post_id, $taxonomy, array( 'fields' => 'ids' ) );
		if ( ! is_wp_error( $terms ) ) {
			wp_set_object_terms( $new_id, $terms, $taxonomy );
		}
	}

	// Post meta (values from get_post_meta() are already unserialized;
	// add_post_meta() re-serializes arrays/objects on insert).
	$excluded = apply_filters(
		'wdp_excluded_meta_keys',
		array( '_wp_old_slug', '_edit_lock', '_edit_last' )
	);
	$meta = get_post_meta( $post_id );
	foreach ( $meta as $key => $values ) {
		if ( in_array( $key, $excluded, true ) ) {
			continue;
		}
		foreach ( $values as $value ) {
			add_post_meta( $new_id, $key, $value );
		}
	}

	/**
	 * Fires after a post has been duplicated.
	 *
	 * @param int $new_id  New (draft) post ID.
	 * @param int $post_id Source post ID.
	 */
	do_action( 'wdp_post_duplicated', $new_id, $post_id );

	return $new_id;
}
