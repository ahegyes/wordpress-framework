<?php declare( strict_types=1 );

namespace DeepWebSolutions\Framework\Storage\ObjectMeta;

/**
 * The WordPress core metadata object types a metadata repository can target.
 *
 * Each case's backing value is the meta type WordPress's metadata API expects — the `$meta_type`
 * argument to get_metadata(), update_metadata(), and delete_metadata().
 *
 * @since   2.0.0
 * @version 2.0.0
 */
enum MetaType: string {
	case Post    = 'post';
	case User    = 'user';
	case Term    = 'term';
	case Comment = 'comment';
}
