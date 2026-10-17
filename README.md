# Duplicate Post (One-Click)

A tiny, dependency-free WordPress plugin that adds a **Duplicate** link to
every post, page and custom-post-type row in the admin list tables, plus a
**Duplicate** bulk action.

Clicking it creates a new **draft** titled "<original> — Copy" assigned to you,
with:

- content, excerpt, password, parent, menu order, comment/ping status
- all taxonomy terms (categories, tags, custom taxonomies)
- all post meta, including serialized arrays (ACF/Elementor data survives)

## Install

Upload the folder to `wp-content/plugins/`, activate. No settings page —
the link appears in each row's action list and in the bulk-actions dropdown.

## Developer hooks

Skip extra meta keys from being copied:

```php
add_filter( 'wdp_excluded_meta_keys', function ( $keys ) {
    $keys[] = '_my_internal_flag';
    return $keys;
} );
```

Run code after a duplication:

```php
add_action( 'wdp_post_duplicated', function ( $new_id, $source_id ) {
    // e.g. clear a cache, notify someone
}, 10, 2 );
```

## Security

- Per-post nonces on the duplicate URL (`check_admin_referer`)
- `current_user_can( 'edit_posts' )` for the link, `edit_post` for the copy
- All output escaped; redirects use `wp_safe_redirect`

Deleting the plugin (not just deactivating) runs the uninstall handler —
nothing is stored, so there is nothing to drop.

MIT licensed.
