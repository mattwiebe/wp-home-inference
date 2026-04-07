#!/usr/bin/env node

import { execFileSync } from 'node:child_process';
import { existsSync, readFileSync } from 'node:fs';
import { dirname, join } from 'node:path';
import { fileURLToPath } from 'node:url';

const SCRIPT_DIR = dirname( fileURLToPath( import.meta.url ) );
const ROOT_DIR = dirname( SCRIPT_DIR );
const PACKAGE_JSON_PATH = join( ROOT_DIR, 'package.json' );
const PLUGIN_PATH = join( ROOT_DIR, 'plugin.php' );
const RELEASE_WORKFLOW = 'release.yml';
const REPO = 'mattwiebe/wp-home-inference';

function run( command, args, options = {} ) {
	return execFileSync( command, args, {
		cwd: ROOT_DIR,
		encoding: 'utf8',
		stdio: options.stdio ?? 'pipe',
		env: options.env ?? process.env,
	} );
}

function parseArgs( argv ) {
	const options = {
		publishNpm: false,
		otp: '',
		noWait: false,
		help: false,
	};

	for ( let i = 0; i < argv.length; i++ ) {
		const arg = argv[ i ];

		if ( arg === '--help' || arg === '-h' ) {
			options.help = true;
			continue;
		}

		if ( arg === '--publish-npm' ) {
			options.publishNpm = true;
			continue;
		}

		if ( arg === '--no-wait' ) {
			options.noWait = true;
			continue;
		}

		if ( arg === '--otp' && argv[ i + 1 ] ) {
			options.otp = argv[ i + 1 ];
			i++;
			continue;
		}

		throw new Error( `Unknown release option: ${ arg }` );
	}

	return options;
}

function helpText() {
	return `Usage:
  npm run release
  npm run release -- --publish-npm --otp <code>
  npm run release -- --no-wait

Options:
  --publish-npm   Publish to npm as part of the release flow.
  --otp <code>    npm OTP code to use with --publish-npm.
  --no-wait       Do not wait for the GitHub Release workflow to finish.
  --help, -h      Show this help message.
`;
}

function getPackageVersion() {
	const packageJson = JSON.parse( readFileSync( PACKAGE_JSON_PATH, 'utf8' ) );
	return packageJson.version;
}

function getPluginVersion() {
	const pluginPhp = readFileSync( PLUGIN_PATH, 'utf8' );
	const match = pluginPhp.match( /^\s*\*\s+Version:\s+(.+)$/m );

	if ( ! match ) {
		throw new Error( 'Could not find plugin version header in plugin.php.' );
	}

	return match[1].trim();
}

function ensureVersionAlignment() {
	const packageVersion = getPackageVersion();
	const pluginVersion = getPluginVersion();

	if ( packageVersion !== pluginVersion ) {
		throw new Error(
			`Version mismatch: package.json is ${ packageVersion }, plugin.php is ${ pluginVersion }.`
		);
	}

	return packageVersion;
}

function ensureCleanWorktree() {
	const status = run( 'git', [ 'status', '--short' ] ).trim();

	if ( status !== '' ) {
		throw new Error( `Git worktree is not clean:\n${ status }` );
	}
}

function ensureOnMainBranch() {
	const branch = run( 'git', [ 'rev-parse', '--abbrev-ref', 'HEAD' ] ).trim();

	if ( branch !== 'main' ) {
		throw new Error( `Release command must run from main. Current branch: ${ branch }` );
	}
}

function ensureTagDoesNotExist( tag ) {
	try {
		run( 'git', [ 'rev-parse', '--verify', '--quiet', tag ] );
		throw new Error( `Tag ${ tag } already exists locally.` );
	} catch ( error ) {
		if ( ! String( error.message ).includes( 'already exists locally' ) ) {
			// Expected when tag does not exist.
		}
	}

	try {
		const remoteTags = run( 'git', [ 'ls-remote', '--tags', 'origin', tag ] ).trim();
		if ( remoteTags !== '' ) {
			throw new Error( `Tag ${ tag } already exists on origin.` );
		}
	} catch ( error ) {
		if ( String( error.message ).includes( 'already exists on origin' ) ) {
			throw error;
		}
	}
}

function runVerification() {
	run( 'composer', [ 'lint' ], { stdio: 'inherit' } );
	run( 'composer', [ 'test' ], { stdio: 'inherit' } );
	run( 'npm', [ 'run', 'lint' ], { stdio: 'inherit' } );
	run( 'npm', [ 'test' ], { stdio: 'inherit' } );
	run( 'npm', [ 'run', 'build' ], { stdio: 'inherit' } );
}

function createAndPushTag( tag ) {
	run( 'git', [ 'push', 'origin', 'main' ], { stdio: 'inherit' } );
	run( 'git', [ 'tag', tag ], { stdio: 'inherit' } );
	run( 'git', [ 'push', 'origin', tag ], { stdio: 'inherit' } );
}

function sleep( ms ) {
	return new Promise( ( resolve ) => setTimeout( resolve, ms ) );
}

async function waitForReleaseRun( tag ) {
	for ( let attempt = 0; attempt < 20; attempt++ ) {
		const raw = run( 'gh', [
			'run',
			'list',
			'--repo',
			REPO,
			'--workflow',
			RELEASE_WORKFLOW,
			'--json',
			'databaseId,headBranch,event,status,conclusion,url',
			'--limit',
			'10',
		] );
		const runs = JSON.parse( raw );
		const runMatch = runs.find( ( item ) => item.headBranch === tag && item.event === 'push' );

		if ( runMatch ) {
			return runMatch;
		}

		await sleep( 3000 );
	}

	throw new Error( `Timed out waiting for GitHub Release workflow for ${ tag }.` );
}

function watchRun( runId ) {
	run( 'gh', [ 'run', 'watch', String( runId ), '--repo', REPO ], { stdio: 'inherit' } );
}

function getReleaseAssets( tag ) {
	const output = run( 'gh', [ 'release', 'view', tag, '--repo', REPO ] );
	return output
		.split( '\n' )
		.filter( ( line ) => line.startsWith( 'asset:' ) )
		.map( ( line ) => line.replace( 'asset:\t', '' ).trim() );
}

function getGitHubSecrets() {
	const output = run( 'gh', [ 'secret', 'list', '--repo', REPO ] );
	return output
		.split( '\n' )
		.map( ( line ) => line.trim().split( /\s+/ )[0] )
		.filter( Boolean );
}

function publishNpmRelease( otp ) {
	const args = [ 'publish' ];

	if ( otp ) {
		args.push( `--otp=${ otp }` );
	}

	run( 'npm', args, { stdio: 'inherit' } );
}

function printManualNpmStep( version ) {
	console.log( '' );
	console.log( 'npm publish remains manual by default for this repo.' );
	console.log( `Run next: npm publish --otp=<code>  # publishes @mattwiebe/wp-home-inference@${ version }` );
}

async function main() {
	const options = parseArgs( process.argv.slice( 2 ) );

	if ( options.help ) {
		console.log( helpText() );
		return;
	}

	const version = ensureVersionAlignment();
	const tag = `v${ version }`;

	console.log( `Preparing release ${ tag }` );
	ensureCleanWorktree();
	ensureOnMainBranch();
	ensureTagDoesNotExist( tag );

	console.log( 'Running verification and build steps...' );
	runVerification();

	console.log( `Pushing main and tag ${ tag }...` );
	createAndPushTag( tag );

	let releaseAssets = [];
	let packagistConfigured = false;

	if ( ! options.noWait ) {
		console.log( 'Waiting for GitHub Release workflow...' );
		const releaseRun = await waitForReleaseRun( tag );
		watchRun( releaseRun.databaseId );
		releaseAssets = getReleaseAssets( tag );
		const secrets = getGitHubSecrets();
		packagistConfigured = secrets.includes( 'PACKAGIST_USERNAME' ) && secrets.includes( 'PACKAGIST_API_TOKEN' );
	}

	if ( options.publishNpm ) {
		console.log( 'Publishing npm package...' );
		publishNpmRelease( options.otp );
	} else {
		printManualNpmStep( version );
	}

	console.log( '' );
	console.log( 'Release summary' );
	console.log( `  Git tag: ${ tag }` );
	console.log( `  GitHub release: https://github.com/${ REPO }/releases/tag/${ tag }` );

	if ( releaseAssets.length > 0 ) {
		console.log( `  Release assets: ${ releaseAssets.join( ', ' ) }` );
	}

	if ( ! options.noWait ) {
		console.log(
			packagistConfigured
				? '  Packagist: GitHub workflow should have notified Packagist.'
				: '  Packagist: GitHub secrets are not configured; notification was skipped.'
		);
	}

	if ( options.publishNpm ) {
		console.log( `  npm: published @mattwiebe/wp-home-inference@${ version }` );
	} else {
		console.log( `  npm: pending manual publish for @mattwiebe/wp-home-inference@${ version }` );
	}
}

main().catch( ( error ) => {
	console.error( error.message );
	process.exit( 1 );
} );
