<?php
/**
 * Se corre solo cuando el usuario borra el plugin desde wp-admin (no en
 * desactivación). Limpia lo que Profit Lens haya guardado en la base de
 * datos.
 *
 * Por ahora el plugin no persiste opciones ni tablas propias — esta fase es
 * solo andamiaje. Se deja preparado el guard estándar y el bloque de
 * limpieza para cuando existan (settings, transients de caché de cálculo).
 *
 * @package ProfitLens
 */

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

global $wpdb;

// Opciones con prefijo profitlens_ (todavía ninguna en esta fase).
delete_option( 'profitlens_settings' );

// Transients de caché del engine, si llegan a existir.
$wpdb->query(
	"DELETE FROM {$wpdb->options} WHERE option_name LIKE '\\_transient\\_profitlens\\_%' OR option_name LIKE '\\_transient\\_timeout\\_profitlens\\_%'"
);
