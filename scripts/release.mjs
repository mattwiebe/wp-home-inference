#!/usr/bin/env node

import { execFileSync } from 'node:child_process';
import { readFileSync, writeFileSync } from 'node:fs';
import { createInterface } from 'node:readline/promises';
import { stdin as input, stdout as output } from 'node:process';
import { dirname, join } from 'node:path';
import { fileURLToPath } from 'node:url';

const SCRIPT_DIR = dirname( fileURLToPath( import.meta.url ) );
const ROOT_DIR = dirname( SCRIPT_DIR );
const PACKAGE_JSON_PATH = join( ROOT_DIR, 'package.json' );
const PACKAGE_LOCK_PATH = join( ROOT_DIR, 'package-lock.json' );
const PLUGIN_PATH = join( ROOT_DIR, 'plugin.php' );
const README_TXT_PATH = join( ROOT_DIR, 'readme.txt' );
const RELEASE_WORKFLOW = 'release.yml';
const REPO = 'mattwiebe/ai-connector-for-local-ai';

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

function writePackageVersion( version ) {
	const packageJson = JSON.parse( readFileSync( PACKAGE_JSON_PATH, 'utf8' ) );
	packageJson.version = version;
	writeFileSync( PACKAGE_JSON_PATH, `${ JSON.stringify( packageJson, null, 2 ) }\n`, 'utf8' );
}

function writePackageLockVersion( version ) {
	const packageLock = JSON.parse( readFileSync( PACKAGE_LOCK_PATH, 'utf8' ) );
	packageLock.version = version;

	if ( packageLock.packages?.[''] ) {
		packageLock.packages[''].version = version;
	}

	writeFileSync( PACKAGE_LOCK_PATH, `${ JSON.stringify( packageLock, null, 2 ) }\n`, 'utf8' );
}

function getPluginVersion() {
	const pluginPhp = readFileSync( PLUGIN_PATH, 'utf8' );
	const match = pluginPhp.match( /^\s*\*\s+Version:\s+(.+)$/m );

	if ( ! match ) {
		throw new Error( 'Could not find plugin version header in plugin.php.' );
	}

	return match[1].trim();
}

function writePluginVersion( version ) {
	const pluginPhp = readFileSync( PLUGIN_PATH, 'utf8' );
	const updated = pluginPhp.replace(
		/^(\s*\*\s+Version:\s+).+$/m,
		`$1${ version }`
	);

	writeFileSync( PLUGIN_PATH, updated, 'utf8' );
}

function getReadmeStableTag() {
	const readme = readFileSync( README_TXT_PATH, 'utf8' );
	const match = readme.match( /^Stable tag:\s+(.+)$/m );

	if ( ! match ) {
		throw new Error( 'Could not find Stable tag in readme.txt.' );
	}

	return match[1].trim();
}

function writeReadmeStableTag( version ) {
	const readme = readFileSync( README_TXT_PATH, 'utf8' );
	const updated = readme.replace(
		/^(Stable tag:\s+).+$/m,
		`$1${ version }`
	);

	writeFileSync( README_TXT_PATH, updated, 'utf8' );
}

function ensureVersionAlignment() {
	const packageVersion = getPackageVersion();
	const pluginVersion = getPluginVersion();
	const readmeStableTag = getReadmeStableTag();

	if ( packageVersion !== pluginVersion ) {
		throw new Error(
			`Version mismatch: package.json is ${ packageVersion }, plugin.php is ${ pluginVersion }.`
		);
	}

	if ( packageVersion !== readmeStableTag ) {
		throw new Error(
			`Version mismatch: package.json is ${ packageVersion }, readme.txt Stable tag is ${ readmeStableTag }.`
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

function localTagExists( tag ) {
	try {
		run( 'git', [ 'rev-parse', '--verify', '--quiet', tag ] );
	} catch ( error ) {
		return false;
	}

	return true;
}

function remoteTagExists( tag ) {
	try {
		const remoteTags = run( 'git', [ 'ls-remote', '--tags', 'origin', tag ] ).trim();
		return remoteTags !== '';
	} catch {
		return false;
	}
}

function ensureTagDoesNotExist( tag ) {
	if ( localTagExists( tag ) ) {
		throw new Error( `Tag ${ tag } already exists locally.` );
	}

	if ( remoteTagExists( tag ) ) {
		throw new Error( `Tag ${ tag } already exists on origin.` );
	}
}

function bumpPatchVersion( version ) {
	const parts = version.split( '.' ).map( ( value ) => Number( value ) );

	if ( parts.length !== 3 || parts.some( ( value ) => ! Number.isInteger( value ) || value < 0 ) ) {
		throw new Error( `Cannot auto-bump non-semver version: ${ version }` );
	}

	parts[2] += 1;
	return parts.join( '.' );
}

async function promptYesNo( question, defaultValue = true ) {
	const rl = createInterface( { input, output } );
	const suffix = defaultValue ? ' [Y/n] ' : ' [y/N] ';

	try {
		const answer = ( await rl.question( `${ question }${ suffix }` ) ).trim().toLowerCase();

		if ( answer === '' ) {
			return defaultValue;
		}

		return answer === 'y' || answer === 'yes';
	} finally {
		rl.close();
	}
}

function commitVersionBump( version ) {
	run( 'git', [ 'add', 'package.json', 'package-lock.json', 'plugin.php', 'readme.txt' ], { stdio: 'inherit' } );
	run( 'git', [ 'commit', '-m', `Bump version to ${ version }` ], { stdio: 'inherit' } );
}

async function resolveReleaseVersion() {
	let version = ensureVersionAlignment();

	for (;;) {
		const tag = `v${ version }`;

		if ( ! localTagExists( tag ) && ! remoteTagExists( tag ) ) {
			return version;
		}

		const nextVersion = bumpPatchVersion( version );
		const shouldBump = await promptYesNo(
			`Tag ${ tag } already exists. Bump version to ${ nextVersion } and continue?`,
			true
		);

		if ( ! shouldBump ) {
			throw new Error( `Release aborted because ${ tag } already exists.` );
		}

		writePackageVersion( nextVersion );
		writePackageLockVersion( nextVersion );
		writePluginVersion( nextVersion );
		writeReadmeStableTag( nextVersion );
		commitVersionBump( nextVersion );
		version = nextVersion;
	}
}

function runVerification() {
	run( 'composer', [ 'lint' ], { stdio: 'inherit' } );
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

function publishNpmRelease( otp ) {
	const args = [ 'publish' ];

	if ( otp ) {
		args.push( `--otp=${ otp }` );
	}

	run( 'npm', args, { stdio: 'inherit' } );
}

function printManualNpmStep( version ) {
	console.log( '' );
	console.log( 'npm publish can be handled by the GitHub release workflow via trusted publishing.' );
	console.log( `If trusted publishing is not configured yet, run manually: npm publish --otp=<code>  # publishes @mattwiebe/ai-connector-for-local-ai@${ version }` );
}

async function main() {
	const options = parseArgs( process.argv.slice( 2 ) );

	if ( options.help ) {
		console.log( helpText() );
		return;
	}

	ensureCleanWorktree();
	ensureOnMainBranch();
	const version = await resolveReleaseVersion();
	const tag = `v${ version }`;

	console.log( `Preparing release ${ tag }` );
	ensureTagDoesNotExist( tag );

	console.log( 'Running verification and build steps...' );
	runVerification();

	console.log( `Pushing main and tag ${ tag }...` );
	createAndPushTag( tag );

	let releaseAssets = [];
	if ( ! options.noWait ) {
		console.log( 'Waiting for GitHub Release workflow...' );
		const releaseRun = await waitForReleaseRun( tag );
		watchRun( releaseRun.databaseId );
		releaseAssets = getReleaseAssets( tag );
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

	console.log( '  Packagist: updates should flow through the connected Packagist GitHub integration.' );

	if ( options.publishNpm ) {
		console.log( `  npm: published @mattwiebe/ai-connector-for-local-ai@${ version }` );
	} else {
		console.log( `  npm: published by GitHub Actions if trusted publishing is configured for @mattwiebe/ai-connector-for-local-ai` );
	}
}

main().catch( ( error ) => {
	console.error( error.message );
	process.exit( 1 );
} );
