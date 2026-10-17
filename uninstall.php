<?php
/**
 * Uninstall handler for Duplicate Post (One-Click).
 *
 * Nothing to clean up: the plugin stores no options, transients, usermeta or
 * custom tables. Draft copies created by the plugin belong to their authors
 * and are left untouched.
 */

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}
