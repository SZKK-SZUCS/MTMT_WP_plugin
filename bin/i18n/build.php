<?php
/**
 * i18n build eszköz — kinyeri a fordítható stringeket a plugin PHP-fájljaiból,
 * és legenerálja belőlük a `languages/mtmt-sync.pot` sablont, valamint a
 * `languages/mtmt-sync-en_US.po`/`.mo` angol fordítást.
 *
 * Használat (a plugin gyökeréből vagy bárhonnan, WP-CLI/build-pipeline nélkül,
 * sima PHP CLI-vel):
 *   php bin/i18n/build.php
 *
 * Új angol fordítás felvételéhez: bővítsd a `bin/i18n/translations-en.php`
 * tömböt (Hungarian msgid => English msgstr), majd futtasd újra ezt a
 * szkriptet. Ha egy stringhez nincs fordítás, a szkript listázza és hibával
 * kilép — a `languages/` fájlok nem generálódnak újra hiányos fordítással.
 *
 * Ez NEM kötelező build-lépés a plugin futásához (CLAUDE.md §2) — a
 * `languages/*.pot/.po/.mo` fájlok már a repóban vannak, verziózva; ezt a
 * szkriptet csak akkor kell futtatni, ha új fordítható stringet adsz a
 * kódhoz, és frissíteni akarod a fordítás-fájlokat.
 *
 * @package Mtmt_Sync
 */

// Windows-on __DIR__ visszaadhat backslash-es utat; a forward-slashre
// normalizálás itt, EGYSZER történik meg, hogy a lenti relatív-út
// str_replace($root . '/', '', ...) tényleg illeszkedjen (különben a
// vegyes elválasztójelek miatt csendben nem cserélne, és a #: referenciák
// teljes abszolút útként kerülnének a .pot/.po fájlba).
$root          = str_replace( '\\', '/', dirname( __DIR__, 2 ) );
$languages_dir = $root . '/languages';

// --- 1) Stringek kinyerése PHP tokenizerrel ---

$funcs_single = array( '__', '_e', 'esc_html__', 'esc_html_e', 'esc_attr__', 'esc_attr_e' );
$funcs_plural = array( '_n' );

$dirs = array( '', 'includes', 'admin', 'elementor' );
$files = array();
foreach ( $dirs as $d ) {
	$path = '' === $d ? $root : $root . '/' . $d;
	if ( ! is_dir( $path ) ) {
		continue;
	}
	foreach ( glob( $path . '/*.php' ) as $f ) {
		$files[] = $f;
	}
}
sort( $files );

/**
 * @param array<string,array{msgid:string,plural:?string,refs:string[]}> $entries
 */
function mtmt_i18n_add_entry( array &$entries, string $msgid, ?string $plural, string $ref ): void {
	$key = $msgid . "\0" . ( $plural ?? '' );
	if ( ! isset( $entries[ $key ] ) ) {
		$entries[ $key ] = array(
			'msgid'  => $msgid,
			'plural' => $plural,
			'refs'   => array(),
		);
	}
	$entries[ $key ]['refs'][] = $ref;
}

$entries = array();

foreach ( $files as $file ) {
	$src      = file_get_contents( $file );
	$tokens   = token_get_all( $src );
	$relative = str_replace( $root . '/', '', str_replace( '\\', '/', $file ) );

	$n = count( $tokens );
	for ( $i = 0; $i < $n; $i++ ) {
		$tok = $tokens[ $i ];
		if ( ! is_array( $tok ) || T_STRING !== $tok[0] ) {
			continue;
		}
		$func_name = $tok[1];
		$line      = $tok[2];

		$is_single = in_array( $func_name, $funcs_single, true );
		$is_plural = in_array( $func_name, $funcs_plural, true );
		if ( ! $is_single && ! $is_plural ) {
			continue;
		}

		$j = $i + 1;
		while ( $j < $n && is_array( $tokens[ $j ] ) && T_WHITESPACE === $tokens[ $j ][0] ) {
			++$j;
		}
		if ( ! isset( $tokens[ $j ] ) || '(' !== $tokens[ $j ] ) {
			continue;
		}
		++$j;

		$args                  = array();
		$current               = '';
		$depth                 = 1;
		$saw_non_string_in_arg = false;
		while ( $j < $n && $depth > 0 ) {
			$t = $tokens[ $j ];
			if ( '(' === $t ) {
				++$depth;
			} elseif ( ')' === $t ) {
				--$depth;
				if ( 0 === $depth ) {
					break;
				}
			} elseif ( ',' === $t && 1 === $depth ) {
				$args[]                 = $saw_non_string_in_arg ? null : $current;
				$current                = '';
				$saw_non_string_in_arg  = false;
				++$j;
				continue;
			} elseif ( is_array( $t ) && T_CONSTANT_ENCAPSED_STRING === $t[0] ) {
				$raw    = $t[1];
				$quote  = $raw[0];
				$inner  = substr( $raw, 1, -1 );
				$inner  = "'" === $quote
					? str_replace( array( '\\\\', "\\'" ), array( '\\', "'" ), $inner )
					: str_replace( array( '\\\\', '\\"' ), array( '\\', '"' ), $inner );
				$current .= $inner;
			} elseif ( is_array( $t ) && T_WHITESPACE === $t[0] ) {
				// skip
			} else {
				$saw_non_string_in_arg = true;
			}
			++$j;
		}
		$args[] = $saw_non_string_in_arg ? null : $current;

		if ( $is_plural ) {
			if ( isset( $args[0], $args[1] ) && null !== $args[0] && null !== $args[1] ) {
				mtmt_i18n_add_entry( $entries, $args[0], $args[1], $relative . ':' . $line );
			}
		} elseif ( isset( $args[0] ) && null !== $args[0] ) {
			mtmt_i18n_add_entry( $entries, $args[0], null, $relative . ':' . $line );
		}
	}
}

ksort( $entries );
$strings = array_values( $entries );

// --- 2) Fordítás-lefedettség ellenőrzése ---

$trans   = require __DIR__ . '/translations-en.php';
$missing = array();
foreach ( $strings as $entry ) {
	if ( ! array_key_exists( $entry['msgid'], $trans ) ) {
		$missing[] = $entry['msgid'];
	}
}
if ( $missing ) {
	fwrite( STDERR, 'HIÁNYZÓ FORDÍTÁS (' . count( $missing ) . "):\n" );
	foreach ( $missing as $m ) {
		fwrite( STDERR, '  - ' . $m . "\n" );
	}
	fwrite( STDERR, "\nVedd fel ezeket a bin/i18n/translations-en.php tömbbe, majd futtasd újra.\n" );
	exit( 1 );
}
echo 'Lefedettség OK: ' . count( $strings ) . " string, mind lefordítva.\n";

// --- 3) .pot / .po írása ---

function mtmt_i18n_po_escape( string $s ): string {
	$s = str_replace( '\\', '\\\\', $s );
	$s = str_replace( '"', '\\"', $s );
	$s = str_replace( "\n", '\\n"' . "\n" . '"', $s );
	$s = str_replace( "\t", '\\t', $s );
	return $s;
}

$creation_date = gmdate( 'Y-m-d\TH:i:s+00:00' );

$pot_header = <<<HEADER
# Copyright (C) 2026 MTMT Sync
# This file is distributed under the GPLv2 or later.
msgid ""
msgstr ""
"Project-Id-Version: MTMT Sync 0.9.0\\n"
"Report-Msgid-Bugs-To: https://github.com/SZKK-SZUCS/MTMT_WP_plugin/issues\\n"
"MIME-Version: 1.0\\n"
"Content-Type: text/plain; charset=UTF-8\\n"
"Content-Transfer-Encoding: 8bit\\n"
"POT-Creation-Date: {$creation_date}\\n"
"X-Generator: bin/i18n/build.php\\n"
"X-Domain: mtmt-sync\\n"

HEADER;

$po_header = <<<HEADER
# Copyright (C) 2026 MTMT Sync
# This file is distributed under the GPLv2 or later.
msgid ""
msgstr ""
"Project-Id-Version: MTMT Sync 0.9.0\\n"
"Report-Msgid-Bugs-To: https://github.com/SZKK-SZUCS/MTMT_WP_plugin/issues\\n"
"MIME-Version: 1.0\\n"
"Content-Type: text/plain; charset=UTF-8\\n"
"Content-Transfer-Encoding: 8bit\\n"
"PO-Revision-Date: {$creation_date}\\n"
"Language: en_US\\n"
"Plural-Forms: nplurals=2; plural=(n != 1);\\n"
"X-Generator: bin/i18n/build.php\\n"
"X-Domain: mtmt-sync\\n"

HEADER;

$pot = $pot_header;
$po  = $po_header;

foreach ( $strings as $entry ) {
	$refs = '#: ' . implode( ' ', $entry['refs'] ) . "\n";
	$pot .= $refs;
	$po  .= $refs;

	if ( null !== $entry['plural'] ) {
		$pot .= 'msgid "' . mtmt_i18n_po_escape( $entry['msgid'] ) . "\"\n";
		$pot .= 'msgid_plural "' . mtmt_i18n_po_escape( $entry['plural'] ) . "\"\n";
		$pot .= "msgstr[0] \"\"\nmsgstr[1] \"\"\n\n";

		$en = $trans[ $entry['msgid'] ];
		$po .= 'msgid "' . mtmt_i18n_po_escape( $entry['msgid'] ) . "\"\n";
		$po .= 'msgid_plural "' . mtmt_i18n_po_escape( $entry['plural'] ) . "\"\n";
		$po .= 'msgstr[0] "' . mtmt_i18n_po_escape( $en ) . "\"\n";
		$po .= 'msgstr[1] "' . mtmt_i18n_po_escape( $en ) . "\"\n\n";
	} else {
		$pot .= 'msgid "' . mtmt_i18n_po_escape( $entry['msgid'] ) . "\"\n";
		$pot .= "msgstr \"\"\n\n";

		$po .= 'msgid "' . mtmt_i18n_po_escape( $entry['msgid'] ) . "\"\n";
		$po .= 'msgstr "' . mtmt_i18n_po_escape( $trans[ $entry['msgid'] ] ) . "\"\n\n";
	}
}

if ( ! is_dir( $languages_dir ) ) {
	mkdir( $languages_dir, 0777, true );
}

file_put_contents( $languages_dir . '/mtmt-sync.pot', $pot );
file_put_contents( $languages_dir . '/mtmt-sync-en_US.po', $po );
echo "Kiírva: languages/mtmt-sync.pot, languages/mtmt-sync-en_US.po\n";

// --- 4) .mo (bináris GNU MO formátum) írása ---

/**
 * @param array<int,array{msgid:string,plural:?string,refs:string[]}> $strings
 * @param array<string,string>                                        $trans
 */
function mtmt_i18n_build_mo( array $strings, array $trans, string $header_msgstr ): string {
	$entries   = array();
	$entries[] = array(
		'orig'  => '',
		'trans' => $header_msgstr,
	);

	foreach ( $strings as $entry ) {
		if ( null !== $entry['plural'] ) {
			$orig   = $entry['msgid'] . "\x00" . $entry['plural'];
			$en     = $trans[ $entry['msgid'] ];
			$trstr  = $en . "\x00" . $en;
		} else {
			$orig  = $entry['msgid'];
			$trstr = $trans[ $entry['msgid'] ];
		}
		$entries[] = array(
			'orig'  => $orig,
			'trans' => $trstr,
		);
	}

	// A GNU MO formátum megköveteli, hogy az eredeti-string tábla növekvő
	// bájt-sorrendben legyen rendezve (binális keresést használ) — a fejléc
	// (üres kulcs) marad az első bejegyzés.
	$header = array_shift( $entries );
	usort(
		$entries,
		static function ( $a, $b ) {
			return strcmp( $a['orig'], $b['orig'] );
		}
	);
	array_unshift( $entries, $header );

	$n = count( $entries );

	$orig_table  = '';
	$trans_table = '';
	$orig_strs   = '';
	$trans_strs  = '';

	$offset = 28 + $n * 8 + $n * 8;

	$orig_offsets = array();
	foreach ( $entries as $e ) {
		$len            = strlen( $e['orig'] );
		$orig_offsets[] = array( $len, $offset );
		$orig_strs     .= $e['orig'] . "\x00";
		$offset        += $len + 1;
	}
	$trans_offsets = array();
	foreach ( $entries as $e ) {
		$len             = strlen( $e['trans'] );
		$trans_offsets[] = array( $len, $offset );
		$trans_strs     .= $e['trans'] . "\x00";
		$offset         += $len + 1;
	}

	foreach ( $orig_offsets as $o ) {
		$orig_table .= pack( 'VV', $o[0], $o[1] );
	}
	foreach ( $trans_offsets as $o ) {
		$trans_table .= pack( 'VV', $o[0], $o[1] );
	}

	$orig_table_offset  = 28;
	$trans_table_offset = 28 + $n * 8;

	$mo  = pack( 'V', 0x950412de );
	$mo .= pack( 'V', 0 );
	$mo .= pack( 'V', $n );
	$mo .= pack( 'V', $orig_table_offset );
	$mo .= pack( 'V', $trans_table_offset );
	$mo .= pack( 'V', 0 );
	$mo .= pack( 'V', 0 );

	$mo .= $orig_table;
	$mo .= $trans_table;
	$mo .= $orig_strs;
	$mo .= $trans_strs;

	return $mo;
}

$header_msgstr = "Project-Id-Version: MTMT Sync 0.9.0\n"
	. "Report-Msgid-Bugs-To: https://github.com/SZKK-SZUCS/MTMT_WP_plugin/issues\n"
	. "PO-Revision-Date: {$creation_date}\n"
	. "MIME-Version: 1.0\n"
	. "Content-Type: text/plain; charset=UTF-8\n"
	. "Content-Transfer-Encoding: 8bit\n"
	. "Language: en_US\n"
	. "Plural-Forms: nplurals=2; plural=(n != 1);\n"
	. "X-Generator: bin/i18n/build.php\n"
	. "X-Domain: mtmt-sync\n";

$mo_data = mtmt_i18n_build_mo( $strings, $trans, $header_msgstr );
file_put_contents( $languages_dir . '/mtmt-sync-en_US.mo', $mo_data );
echo 'Kiírva: languages/mtmt-sync-en_US.mo (' . strlen( $mo_data ) . " byte)\n";
