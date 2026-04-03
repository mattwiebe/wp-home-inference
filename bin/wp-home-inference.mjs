#!/usr/bin/env node

import { spawn } from 'node:child_process';
import { homedir } from 'node:os';
import { dirname, join } from 'node:path';
import { fileURLToPath } from 'node:url';

const BIN_DIR = dirname( fileURLToPath( import.meta.url ) );
const ROOT_DIR = dirname( BIN_DIR );
const SERVER_PATH = join( ROOT_DIR, 'local', 'server.mjs' );
const CONFIG_PATH = join( homedir(), '.config', 'wp-home-inference', '.env' );

const args = process.argv.slice( 2 );
const forwardedArgs = args[0] === 'up' ? args.slice( 1 ) : args;

const child = spawn(
	process.execPath,
	[ SERVER_PATH, ...forwardedArgs ],
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
