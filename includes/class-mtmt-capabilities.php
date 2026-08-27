<?php
/**
 * Jogosultságok (CLAUDE.md §8.3).
 *
 * @package Mtmt_Sync
 */

defined( 'ABSPATH' ) || exit;

/**
 * Kétszintű capability: `mtmt_moderate` (jóváhagyás/elutasítás, alap
 * szerkesztés, indexkép) és `mtmt_classify` (kutatócsoport-besorolás +
 * projektazonosító-ellenőrzés).
 *
 * FRISSÍTVE (0.9 előkészítés, 2026-08, docs/decisions.md #82): a role→capability
 * leképzés mostantól admin UI-n konfigurálható (`Mtmt_Capabilities_Page`,
 * "Jogosultságok" almenü), TETSZŐLEGES WP-szerepkörre, nem csak Editor+Administrator-ra.
 * A tényleges leképzést két `wp_options` bejegyzés tárolja
 * (`mtmt_moderate_roles`, `mtmt_classify_roles`) — ha még sosem lett explicit
 * beállítva, a `get_option()` alapértelmezése (Editor+Administrator) él tovább,
 * tehát egy már futó, korábbi verziójú telepítés is zökkenőmentesen frissül.
 */
final class Mtmt_Capabilities {

	const MODERATE = 'mtmt_moderate';
	const CLASSIFY = 'mtmt_classify';

	const OPTION_MODERATE_ROLES = 'mtmt_moderate_roles';
	const OPTION_CLASSIFY_ROLES = 'mtmt_classify_roles';

	/**
	 * @var string[]
	 */
	private const DEFAULT_ROLES = array( 'editor', 'administrator' );

	/**
	 * Aktiváláskor lefutó capability-hozzárendelés. `add_option()` — NEM
	 * `update_option()` — hogy egy már admin által testreszabott role→capability
	 * mappinget ne írjon felül egy plugin-újraaktiválás/frissítés.
	 */
	public static function activate(): void {
		add_option( self::OPTION_MODERATE_ROLES, self::DEFAULT_ROLES );
		add_option( self::OPTION_CLASSIFY_ROLES, self::DEFAULT_ROLES );

		self::apply();
	}

	/**
	 * @return string[] Szerepkör-slugok, amik jelenleg `mtmt_moderate`-et kapnak.
	 */
	public static function get_moderate_roles(): array {
		return (array) get_option( self::OPTION_MODERATE_ROLES, self::DEFAULT_ROLES );
	}

	/**
	 * @return string[] Szerepkör-slugok, amik jelenleg `mtmt_classify`-t kapnak.
	 */
	public static function get_classify_roles(): array {
		return (array) get_option( self::OPTION_CLASSIFY_ROLES, self::DEFAULT_ROLES );
	}

	/**
	 * Elmenti az új role→capability leképzést, és AZONNAL rá is vezeti a
	 * WP_Role objektumokra (nem kell külön oldalbetöltés/aktiválás).
	 *
	 * @param string[] $moderate_roles
	 * @param string[] $classify_roles
	 */
	public static function save_role_mapping( array $moderate_roles, array $classify_roles ): void {
		update_option( self::OPTION_MODERATE_ROLES, array_values( array_unique( $moderate_roles ) ) );
		update_option( self::OPTION_CLASSIFY_ROLES, array_values( array_unique( $classify_roles ) ) );

		self::apply();
	}

	/**
	 * A tárolt role→capability leképzést ráveti MINDEN létező WP-szerepkörre —
	 * amelyik benne van a listában, `add_cap()`; amelyik nincs, `remove_cap()`,
	 * hogy egy korábban bejelölt, majd kivett szerepkör ténylegesen el is
	 * veszítse a jogot, ne csak "ne kapja meg újra" legközelebb.
	 */
	public static function apply(): void {
		if ( ! function_exists( 'wp_roles' ) ) {
			return;
		}

		$moderate_roles = self::get_moderate_roles();
		$classify_roles = self::get_classify_roles();

		foreach ( array_keys( wp_roles()->roles ) as $role_name ) {
			$role = get_role( $role_name );
			if ( ! $role ) {
				continue;
			}

			if ( in_array( $role_name, $moderate_roles, true ) ) {
				$role->add_cap( self::MODERATE );
			} else {
				$role->remove_cap( self::MODERATE );
			}

			if ( in_array( $role_name, $classify_roles, true ) ) {
				$role->add_cap( self::CLASSIFY );
			} else {
				$role->remove_cap( self::CLASSIFY );
			}
		}
	}
}
