#!/usr/bin/env node

import { spawn } from 'node:child_process';
import { readFileSync } from 'node:fs';
import { homedir } from 'node:os';
import { dirname, join } from 'node:path';
import { fileURLToPath } from 'node:url';

const BIN_DIR = dirname( fileURLToPath( import.meta.url ) );
const ROOT_DIR = dirname( BIN_DIR );
const SERVER_PATH = join( ROOT_DIR, 'local', 'server.mjs' );
const CONFIG_PATH = join( homedir(), '.config', 'wp-home-inference', '.env' );
const PACKAGE_JSON_PATH = join( ROOT_DIR, 'package.json' );
const PACKAGE_JSON = JSON.parse( readFileSync( PACKAGE_JSON_PATH, 'utf8' ) );
const VERSION = PACKAGE_JSON.version;

function normalizeArgs( rawArgs ) {
	if ( rawArgs.length === 0 ) {
		return { action: 'run', forwardedArgs: [] };
	}

	const first = rawArgs[0];

	if ( [ '--help', '-h', 'help' ].includes( first ) ) {
		return { action: 'help', forwardedArgs: [] };
	}

	if ( [ '--version', '-v', 'version' ].includes( first ) ) {
		return { action: 'version', forwardedArgs: [] };
	}

	if ( first === 'up' ) {
		return { action: 'run', forwardedArgs: rawArgs.slice( 1 ) };
	}

	if ( first === 'init' ) {
		return { action: 'run', forwardedArgs: rawArgs };
	}

	if ( first.startsWith( '-' ) ) {
		return { action: 'run', forwardedArgs: rawArgs };
	}

	return { action: 'help', forwardedArgs: [] };
}

function helpText() {
	return `wphi v${ VERSION }

Usage:
  wphi up [options]
  wphi init [options]
  wphi --help
  wphi --version

Commands:
  up        Start the Home Inference proxy
  init      Configure or reconfigure the proxy

Options:
  --port <n>         Local proxy port (default: 13531)
  --funnel-port <n>  Tailscale Funnel public port (default: 8443)
  --backend <url>    Backend URL to proxy to
  --api-key <key>    Shared API key for the proxy
  --no-tunnel        Disable Tailscale Funnel
`;
}

function main() {
	const args = process.argv.slice( 2 );
	const normalized = normalizeArgs( args );

	if ( normalized.action === 'help' ) {
		console.log( helpText() );
		process.exit( 0 );
	}

	if ( normalized.action === 'version' ) {
		console.log( `wphi v${ VERSION }` );
		process.exit( 0 );
	}

	const child = spawn(
		process.execPath,
		[ SERVER_PATH, ...normalized.forwardedArgs ],
		{
			stdio: 'inherit',
			env: {
				...process.env,
				WP_HOME_INFERENCE_ENV_PATH: process.env.WP_HOME_INFERENCE_ENV_PATH || CONFIG_PATH,
			},
		}
	);

	child.on( 'exit', ( code, signal ) => {
		if ( signal ) {
			process.kill( process.pid, signal );
			return;
		}

		process.exit( code ?? 0 );
	} );
}

const IS_DIRECT_RUN = process.argv[ 1 ] && fileURLToPath( import.meta.url ) === process.argv[ 1 ];

if ( IS_DIRECT_RUN ) {
	main();
}

export {
	helpText,
	main,
	normalizeArgs,
	VERSION,
};
