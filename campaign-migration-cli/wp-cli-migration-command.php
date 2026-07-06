<?php
/**
 * WP-CLI command for migrating legacy campaign action records into
 * `campaign_action` custom posts.
 *
 * Written as a code sample for a job application. It isn't pulled from a
 * client codebase; my production WordPress migration work lives in private
 * employer repos I no longer have access to. This models a scenario close
 * to real work I've done: importing a large volume of petition and action
 * signups from a legacy platform export into WordPress, in a way that
 * survives being interrupted partway through, is safe to re-run without
 * creating duplicates, and leaves an audit trail of what happened.
 *
 * Usage:
 *   wp campaign-migrate import-actions ./legacy-export.csv
 *   wp campaign-migrate import-actions ./legacy-export.csv --dry-run
 *   wp campaign-migrate import-actions ./legacy-export.csv --batch-size=500
 */

if ( ! defined( 'WP_CLI' ) || ! WP_CLI ) {
	return;
}

class Campaign_Action_Migration_Command extends WP_CLI_Command {

	const POST_TYPE          = 'campaign_action';
	const LEGACY_ID_META_KEY = '_legacy_action_id';

	const REQUIRED_COLUMNS = array(
		'legacy_id',
		'campaign_slug',
		'title',
		'status',
	);

	/**
	 * Imports campaign action records from a legacy CSV export.
	 *
	 * ## OPTIONS
	 *
	 * <file>
	 * : Path to the CSV export from the legacy action platform.
	 *
	 * [--batch-size=<number>]
	 * : Number of rows to process between cache flushes and progress updates. Default 200.
	 *
	 * [--dry-run]
	 * : Parse and validate every row without writing anything to the database.
	 *
	 * [--stop-on-error]
	 * : Abort the entire import on the first row-level failure. Off by
	 * default, since one malformed row in a large export shouldn't block
	 * every row after it.
	 *
	 * ## EXAMPLES
	 *
	 *     wp campaign-migrate import-actions ./legacy-export.csv
	 *     wp campaign-migrate import-actions ./legacy-export.csv --dry-run
	 *     wp campaign-migrate import-actions ./legacy-export.csv --batch-size=500
	 *
	 * @when after_wp_load
	 */
	public function import_actions( $args, $assoc_args ) {
		list( $file ) = $args;

		if ( ! file_exists( $file ) || ! is_readable( $file ) ) {
			WP_CLI::error( "Cannot read file: {$file}" );
		}

		$batch_size    = (int) WP_CLI\Utils\get_flag_value( $assoc_args, 'batch-size', 200 );
		$dry_run       = (bool) WP_CLI\Utils\get_flag_value( $assoc_args, 'dry-run', false );
		$stop_on_error = (bool) WP_CLI\Utils\get_flag_value( $assoc_args, 'stop-on-error', false );

		$handle = fopen( $file, 'r' );
		if ( false === $handle ) {
			WP_CLI::error( "Unable to open file: {$file}" );
		}

		$header = fgetcsv( $handle );
		if ( false === $header ) {
			WP_CLI::error( 'File appears to be empty.' );
		}

		$missing_columns = array_diff( self::REQUIRED_COLUMNS, $header );
		if ( ! empty( $missing_columns ) ) {
			WP_CLI::error( 'Missing required column(s): ' . implode( ', ', $missing_columns ) );
		}

		WP_CLI::log( sprintf(
			'Importing %s%s',
			$file,
			$dry_run ? ' (dry run, no changes will be written)' : ''
		) );

		// A file this size runs noticeably faster with WordPress's usual
		// term counting and cache invalidation deferred until the end,
		// rather than firing after every single post write.
		if ( ! $dry_run ) {
			wp_defer_term_counting( true );
			wp_suspend_cache_invalidation( true );
		}

		$results = array(
			'created' => 0,
			'updated' => 0,
			'failed'  => 0,
		);

		$row_number  = 1; // Row 0 was the header.
		$since_flush = 0;

		// A single streaming pass: rows are counted and processed together
		// rather than reading the file once to size a progress bar and
		// again to import, which would double the I/O on a large export.
		while ( false !== ( $row = fgetcsv( $handle ) ) ) {
			$row_number++;
			$since_flush++;

			if ( count( $row ) !== count( $header ) ) {
				$this->record_failure( $results, $row_number, 'Column count does not match header', $stop_on_error );
			} else {
				$record = array_combine( $header, $row );

				try {
					$outcome            = $this->import_row( $record, $dry_run );
					$results[ $outcome ]++;
				} catch ( Exception $e ) {
					$this->record_failure( $results, $row_number, $e->getMessage(), $stop_on_error );
				}
			}

			if ( $since_flush >= $batch_size ) {
				WP_CLI::log( sprintf( '...processed %d row(s)', $row_number - 1 ) );

				if ( ! $dry_run ) {
					wp_cache_flush();
				}

				$since_flush = 0;
			}
		}

		fclose( $handle );

		if ( ! $dry_run ) {
			wp_defer_term_counting( false );
			wp_suspend_cache_invalidation( false );
		}

		WP_CLI::log( '' );
		WP_CLI::log( sprintf(
			'Done. Created: %d, Updated: %d, Failed: %d',
			$results['created'],
			$results['updated'],
			$results['failed']
		) );

		if ( $results['failed'] > 0 ) {
			WP_CLI::warning(
				"{$results['failed']} row(s) failed. Fix the source data and re-run the same file. " .
				'Rows are matched by legacy ID, so already-imported rows update in place instead of duplicating.'
			);
		}
	}

	/**
	 * Imports or updates a single record, matched against any existing post
	 * by its legacy ID. Matching this way is what makes it safe to run the
	 * same file twice, whether that's a deliberate retry or someone
	 * accidentally kicking off the same import job in two terminal tabs.
	 *
	 * @return string Either 'created' or 'updated'.
	 * @throws Exception If the row is missing required data.
	 */
	private function import_row( array $record, $dry_run ) {
		foreach ( self::REQUIRED_COLUMNS as $column ) {
			if ( empty( $record[ $column ] ) ) {
				throw new Exception( "Missing required value for '{$column}'" );
			}
		}

		$legacy_id = sanitize_text_field( $record['legacy_id'] );
		$existing  = $this->find_by_legacy_id( $legacy_id );

		$post_args = array(
			'post_type'   => self::POST_TYPE,
			'post_title'  => sanitize_text_field( $record['title'] ),
			'post_status' => $this->map_status( $record['status'] ),
			'post_name'   => sanitize_title( $record['title'] . '-' . $legacy_id ),
		);

		if ( $existing ) {
			$post_args['ID'] = $existing->ID;
		}

		if ( $dry_run ) {
			return $existing ? 'updated' : 'created';
		}

		$post_id = $existing
			? wp_update_post( $post_args, true )
			: wp_insert_post( $post_args, true );

		if ( is_wp_error( $post_id ) ) {
			throw new Exception( $post_id->get_error_message() );
		}

		update_post_meta( $post_id, self::LEGACY_ID_META_KEY, $legacy_id );
		update_post_meta( $post_id, '_campaign_slug', sanitize_title( $record['campaign_slug'] ) );

		if ( isset( $record['signature_count'] ) && is_numeric( $record['signature_count'] ) ) {
			update_post_meta( $post_id, '_signature_count', (int) $record['signature_count'] );
		}

		wp_set_object_terms( $post_id, sanitize_title( $record['campaign_slug'] ), 'campaign', false );

		return $existing ? 'updated' : 'created';
	}

	/**
	 * Looks up an existing post by the legacy ID stored in its meta, rather
	 * than by title or slug, since titles get edited and legacy IDs don't.
	 */
	private function find_by_legacy_id( $legacy_id ) {
		$existing = get_posts( array(
			'post_type'      => self::POST_TYPE,
			'post_status'    => 'any',
			'meta_key'       => self::LEGACY_ID_META_KEY,
			'meta_value'     => $legacy_id,
			'posts_per_page' => 1,
			'fields'         => 'ids',
		) );

		return $existing ? get_post( $existing[0] ) : null;
	}

	private function map_status( $legacy_status ) {
		$status_map = array(
			'active'   => 'publish',
			'closed'   => 'publish',
			'archived' => 'draft',
			'draft'    => 'draft',
		);

		$key = strtolower( trim( $legacy_status ) );

		return isset( $status_map[ $key ] ) ? $status_map[ $key ] : 'draft';
	}

	private function record_failure( array &$results, $row_number, $message, $stop_on_error ) {
		$results['failed']++;
		WP_CLI::warning( "Row {$row_number}: {$message}" );

		if ( $stop_on_error ) {
			WP_CLI::error( 'Stopping import due to --stop-on-error.' );
		}
	}
}

WP_CLI::add_command( 'campaign-migrate', 'Campaign_Action_Migration_Command' );
