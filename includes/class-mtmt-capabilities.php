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
 * projektazonosító-ellenőrzés). Aktiváláskor mindkettő az Editor és az
 * Administrator szerepkörhöz kerül — role→capability mapping később
 * finomítható, ha kell (nincs még hozzá admin UI).
 */
final class Mtmt_Capabilities {

	const MODERATE = 'mtmt_moderate';
	const CLASSIFY = 'mtmt_classify';

	/**
	 * @var string[]
	 */
	private const DEFAULT_ROLES = array( 'editor', 'administrator' );

	/**
	 * Aktiváláskor lefutó capability-hozzárendelés.
	 */
	public static function activate(): void {
		foreach ( self::DEFAULT_ROLES as $role_name ) {
			$role = get_role( $role_name );
			if ( ! $role ) {
				continue;
			}
			$role->add_cap( self::MODERATE );
			$role->add_cap( self::CLASSIFY );
		}
	}
}
