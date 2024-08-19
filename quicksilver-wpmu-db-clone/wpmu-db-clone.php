<?php
/**
 * Quicksilver action to update the sites
 */

use Symfony\Component\Process\Process;

require_once __DIR__ . '/vendor/autoload.php';

$all_sites = [
    'ucf-coscom' => 'coscomcms.cm.ucf.edu',
//    'ucf-aawp' => 'aawpcms.smca.ucf.edu',
//    'ucf-chps' => 'chpscms.smca.ucf.edu',
//    'ucf-coba' => 'cobacms.smca.ucf.edu',
//    'ucf-fa' => 'facms.smca.ucf.edu',

//    'ucf-cedhp' => 'cedhpcms.smca.ucf.edu',


//  'ucf-cah' => 'cahcms.smca.ucf.edu',
//	'ucf-creol' => 'creolcms.smca.ucf.edu',
//	'ucf-cugs' => 'cugscms.smca.ucf.edu',
//	'ucf-ucfit' => 'ucfitcms.smca.ucf.edu',
//	'ucf-sdes' => 'sdescms.smca.ucf.edu',
//	'ucf-bs' => 'bscms.smca.ucf.edu',
//	'ucf-dtl' => 'dtlcms.smca.ucf.edu',
//	'ucf-grit' => 'gritcms.smca.ucf.edu',
//	'ucf-osi' => 'osicms.smca.ucf.edu',
//	'ucf-rchm' => 'rchmcms.smca.ucf.edu',
//	'ucf-ucn' => 'ucncms.smca.ucf.edu',
];

function logMsg( $message ) {
	echo $message . PHP_EOL;
	$log_msg = date('Y-m-d H:i:s') . ': ' . $message;
	file_put_contents( 'clone.log', $log_msg, FILE_APPEND );
}

/**
 * Run a command through Symfony's process
 *
 * @param string $site  The Pantheon site to run the command in.
 * @param string $env   The Pantheon environment to run the command in.
 * @param string $cmd   The command to run.
 * @param bool   $async Whether to run the command sync or async.
 * @return Symfony\Component\Process\Process;
 */
function run_wp_cli( $site, $env, $cmd, $async = false ) {
	$cmd = array_merge( [ 'terminus', 'wp', "$site.$env", '--' ], (array) $cmd, [ '--skip-plugins', '--skip-themes', '--quiet' ] );
	logMsg( 'Running: ' . implode( ' ', $cmd ) );

	$process = new Process( $cmd );
	$process->setTimeout( null );

	if ( $async ) {
		$process->start();
	} else {
		try {
			$process->mustRun();
		} catch ( Exception $e ) {
			logMsg( $e->getMessage() );
		}
	}

	return $process;
}

/**
 * Run a command through Symfony's process
 *
 * @param string $cmd   The command to run.
 * @param bool   $async Whether to run the command sync or async.
 * @return Symfony\Component\Process\Process;
 */
function run_terminus( $site, $env, string $cmd, array $flags = [], $async = false ) {
	$cmd = array_merge( [ 'terminus', $cmd, "$site.$env" ], (array) $flags );
	logMsg( 'Running: ' . implode( ' ', $cmd ) );

	$process = new Process( $cmd );
	$process->setTimeout( 60 * 10 );

	if ( $async ) {
		$process->start();
	} else {
		try {
			$process->mustRun();
		} catch ( Exception $e ) {
			logMsg( $e->getMessage() );
		}
	}

	return $process;
}

/**
 * Clean out done processes
 *
 * @param array $processes Array of Symfony processes.
 * @return array           Original processes, with complete ones filtered out.
 */
function clean_processes( $processes ) {
	foreach ( $processes as $key => $process ) {
		if ( $process->isTerminated() ) {
			if ( ! $process->isSuccessful() ) {
				logMsg( $process->getErrorOutput() );
			}

			unset( $processes[ $key ] );
		}
	}

	return $processes;
}

foreach ( [ 'dev', 'test' ] as $env ) {
	foreach ( $all_sites as $site => $domain ) {

		$resp = get_headers( "https://$site:$site@$env-$site.pantheonsite.io", true );

		if ( stristr( $resp[0], '200' ) ) {
			//logMsg( "Site $site.$env loads. Skipping" );
			//continue;
            logMsg( "Site $site.$env loads. Continue anyway..." );
		} else {
			logMsg( "ERROR: Site $site.$env does not load. Proceeding" );
		}

		//continue;

		logMsg( "Processing site: $site.$env\n" );

		$workflows = json_decode( run_terminus( $site, $env, 'workflow:list', [ '--format=json' ], false )->getOutput(), true );

		$isCloned = false;

		foreach ( $workflows as $workflow ) {
			if ( $workflow['env'] == $env && $workflow['status'] == 'succeeded' && $workflow['finished_at'] > strtotime('-1 day') && stristr( $workflow['workflow'], 'Clone database' ) ) {
				$isCloned = true;
				break;
			}
		}

		if ( ! $isCloned ) {
			$cloning = run_terminus( $site, 'live', 'env:clone-content', [ $env, '--db-only', '--cc', '--yes' ], false );
			$cloning->wait();
		}

		/**
		 * Edit these domains to match your site's configuration.
		 *
		 * If your test or dev sites have a custom domain and support
		 * wildcard subdomains, include them as well.
		 */
		$domains = [
			'live'    => $domain,
			'lando'   => $site . '.lndo.site',
			'default' => $env . '-' . $site . '.pantheonsite.io',
		];

		logMsg( 'Replacing domain names in wp_blogs table' );

		// If the database isn't coming from the live site, skip processing.
		// We can't trust that we know what to set the blog path to if it's not the live database.
		if ( 'live' === $env ) {
			logMsg( "Database is being cloned to live environment." );
			logMsg( "Manually update the wp_blogs and wp_site tables." );
			continue;
		}

		// Figure out what the domain is for the current site.
		$domain_new = $domains[ $env ] ?? $domains['default'];

		// Should we move to a subdirectory setup?
		// pantheonsite.io doesn't support subdomains, so we need to move to subdirectory
		$is_subdirectory = stristr( $domain_new, 'pantheonsite.io' );

		// Get the primary blog's domain.
		$process     = run_wp_cli( $site, $env, [ 'db', 'query', 'SELECT domain FROM wp_blogs WHERE site_id=1 AND blog_id=1;', '--skip-column-names', "--url={$domains['live']}" ] );
		$domain_orig = trim( $process->getOutput() );

		// If the database isn't coming from the live site, skip processing.
		// We can't trust that we know what to set the blog path to if it's not the live database.
		if ( $domains['live'] !== $domain_orig ) {
			logMsg( "Origin database isn't from live, skipping table processing." );
			continue;
		}

		// Check if we've already replaced the domain.
		if ( $domain_orig === $domain_new ) {
			logMsg( "Domain is already set to $domain_new. Skipping." );
			return;
		}

		// Get the list of sites.
		$process = run_wp_cli( $site, $env, [ 'db', 'query', 'SELECT blog_id, domain, path FROM wp_blogs WHERE site_id=1', '--skip-column-names', "--url={$domains['live']}" ] );
		$blogs   = explode( PHP_EOL, $process->getOutput() );

		// Update wp_site domain to the new domain.
		run_wp_cli( $site, $env, [ 'db', 'query', "UPDATE wp_site SET domain='{$domain_new}', path='/' WHERE id=1", "--url={$domains['live']}" ], true );

		$processes = [];

		// Update individual site urls.
		foreach ( $blogs as $blog_raw ) {
			$blog    = explode( "\t", $blog_raw );
			$blog_id = intval( $blog[0] );

			// If the blog ID isn't a positive integer, something's not right. Skip it.
			if ( 0 >= $blog_id ) {
				continue;
			}

			$blog_domain_orig = $blog[1];
			$blog_path_orig   = $blog[2];

			logMsg( "Processing blog #$blog_id {$blog_domain_orig}{$blog_path_orig}\n" );

			if ( $is_subdirectory ) {
				// Convert URLs to a subdirectory pattern.
				// site.com           => test-site.pantheonsite.io
				// blog.site.com      => test-site.pantheonsite.io/blog/
				// blog.site.com/dir/ => test-site.pantheonsite.io/blog-dir/
				// blog.com           => test-site.pantheonsite.io/blog-com/
				// blog.com/dir/      => test-site.pantheonsite.io/blog-com-dir/

				// Process URLs to a subdirectory format.
				$blog_domain_new = $domain_new;

				if ( 1 == $blog_id ) {
					// First blog gets a path of just /
					$blog_path_new = '/';
				} else {
					// All other blogs get a path made of the subdomain and original path.
					$blog_path_new = str_replace( '.' . $domain_orig, '', $blog_domain_orig ) . $blog_path_orig;

					// Convert to a single subdirectory.
					$blog_path_new = '/' . str_replace( ['.', '/' ], '-', $blog_path_new );

					$blog_path_new = rtrim( $blog_path_new, '-' ) . '/';
				}
			} else {
				// Process URLs to a subdomain format.
				$blog_path_new = $blog_path_orig;

				if ( 1 === $blog_id ) {
					$blog_domain_new = $domain_new;
				} else {
					// First, remove the live domain from the site's original domain
					$subdir = str_replace( ".{$domains['live']}", '', $blog_domain_orig );
					// For edge cases of sub-sub domains or fully unique domains, swap dots to dashes.
					$subdir = str_replace( '.', '-', $subdir );

					$blog_domain_new = "{$subdir}.{$domain_new}";
				}
			}

			// Update wp_blogs record.
			run_wp_cli( $site, $env, [ 'db', 'query', "UPDATE wp_blogs SET domain='{$blog_domain_new}', path='{$blog_path_new}' WHERE site_id=1 AND blog_id={$blog_id}", "--url={$domains['live']}" ], false );

			// Run search-replace on all of the blog's tables.
			// Search-replace limited to just the blog's tables for speed.
			$blog_url_orig = trim( "{$blog_domain_orig}{$blog_path_orig}", '/' );
			$blog_url_new  = trim( "{$blog_domain_new}{$blog_path_new}", '/' );
			$processes[] = run_wp_cli( $site, $env, [ 'search-replace', "//$blog_url_orig", "//$blog_url_new", "--url=$blog_url_new", '--skip-tables=wp_blogs,wp_site' ], true );

			while ( count ( $processes ) > 100 ) {
				$processes = clean_processes( $processes );
				sleep( 1 );
			}
		}

		// Wait for all processes to finish.
		while ( ! empty( $processes ) ) {
			$processes = clean_processes( $processes );

			sleep( 1 );

			printf( '%d processes executing', count( $processes ) );
		}

		run_wp_cli( $site, $env, [ 'cache', 'flush', "--url={$domain_new}" ], false );
		run_terminus( $site, $env, 'env:clear-cache', [], false );
	}
}