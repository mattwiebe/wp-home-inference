#!/usr/bin/env node

/**
 * Home Inference Proxy Server
 *
 * A lightweight authenticated proxy that sits in front of a local inference
 * server (Ollama, LM Studio, etc.) and exposes it securely via Tailscale
 * Funnel so your WordPress site can reach it.
 *
 *   1. Auto-detects installed backends (Ollama, LM Studio) and prompts to choose.
 *   2. Validates a shared API key on every request.
 *   3. Forwards authenticated requests to the local inference server.
 *   4. Uses Tailscale Funnel to expose the proxy with a public HTTPS URL.
 *
 * Usage:
 *   node server.mjs [options]
 *   node server.mjs init [options]
 *
 * Options:
 *   --port <n>              Port for this proxy (default: 13531).
 *   --funnel-port <n>       Public HTTPS port for Tailscale Funnel (default: 8443).
 *   --backend <url>         Skip auto-detection and use this backend URL directly.
 *   --api-key <key>         Shared secret for authentication (auto-generated if omitted).
 *   --no-tunnel             Skip Tailscale Funnel (if you handle networking yourself).
 *
 * @package WordPress\HomeInference
 */

import { createServer } from 'node:http';
import { randomBytes } from 'node:crypto';
import { execFileSync, execFile } from 'node:child_process';
import { existsSync, mkdirSync, readFileSync, writeFileSync } from 'node:fs';
import { dirname, join } from 'node:path';
import { createInterface } from 'node:readline';
import { fileURLToPath } from 'node:url';

// ---------------------------------------------------------------------------
// CLI args
// ---------------------------------------------------------------------------

function arg( name, fallback ) {
	const idx = process.argv.indexOf( `--${ name }` );
	if ( idx === -1 || idx + 1 >= process.argv.length ) {
		return fallback;
	}
	return process.argv[ idx + 1 ];
}

function hasFlag( name ) {
	return process.argv.includes( `--${ name }` );
}

const IS_INIT  = 'init' === process.argv[ 2 ];
const SCRIPT_DIR = dirname( fileURLToPath( import.meta.url ) );
const ENV_PATH   = process.env.WP_HOME_INFERENCE_ENV_PATH || join( SCRIPT_DIR, '.env' );
const PORT_ARG            = arg( 'port', '' );
const FUNNEL_PORT_ARG     = arg( 'funnel-port', '' );
const BACKEND_ARG         = arg( 'backend', '' );
const API_KEY_ARG         = arg( 'api-key', '' );
const NO_TUNNEL_FLAG      = hasFlag( 'no-tunnel' );
const FUNNEL_PORT_CHOICES = [
	{ port: 8443, label: '8443 (default)' },
	{ port: 443, label: '443' },
	{ port: 10000, label: '10000' },
];
const ALLOWED_FUNNEL_PORTS = FUNNEL_PORT_CHOICES.map( ( choice ) => choice.port );
let PORT = 13531;
let BACKEND = '';
let API_KEY = '';
let NO_TUNNEL = false;

// ---------------------------------------------------------------------------
// Backend detection
// ---------------------------------------------------------------------------

/**
 * Known local inference backends.
 */
const BACKENDS = [
	{
		name:     'Ollama',
		cli:      'ollama',
		url:      'http://localhost:11434',
		probeUrl: 'http://localhost:11434/v1/models',
	},
	{
		name:     'LM Studio',
		cli:      'lms',
		url:      'http://localhost:1234',
		probeUrl: 'http://localhost:1234/v1/models',
	},
];

function parseEnvFile( contents ) {
	const values = {};

	for ( const rawLine of contents.split( /\r?\n/ ) ) {
		const line = rawLine.trim();
		if ( '' === line || line.startsWith( '#' ) ) {
			continue;
		}

		const equalsIndex = line.indexOf( '=' );
		if ( -1 === equalsIndex ) {
			continue;
		}

		const key = line.slice( 0, equalsIndex ).trim();
		let value = line.slice( equalsIndex + 1 ).trim();

		if (
			( value.startsWith( '"' ) && value.endsWith( '"' ) ) ||
			( value.startsWith( '\'' ) && value.endsWith( '\'' ) )
		) {
			value = value.slice( 1, -1 );
		}

		values[ key ] = value;
	}

	return values;
}

function loadStoredConfig() {
	if ( ! existsSync( ENV_PATH ) ) {
		return {};
	}

	return parseEnvFile( readFileSync( ENV_PATH, 'utf8' ) );
}

function envValue( value ) {
	return JSON.stringify( String( value ) );
}

function writeConfig( config ) {
	mkdirSync( dirname( ENV_PATH ), { recursive: true } );

	const contents = [
		'# Home Inference local configuration',
		`PORT=${ envValue( config.port ) }`,
		`FUNNEL_PORT=${ envValue( config.funnelPort ) }`,
		`BACKEND_URL=${ envValue( config.backendUrl ) }`,
		`API_KEY=${ envValue( config.apiKey ) }`,
		`NO_TUNNEL=${ envValue( config.noTunnel ? '1' : '0' ) }`,
		'',
	].join( '\n' );

	writeFileSync( ENV_PATH, contents, 'utf8' );
}

function parseNumberOrFallback( value, fallback ) {
	const parsed = Number( value );
	return Number.isFinite( parsed ) && parsed > 0 ? parsed : fallback;
}

function parseBooleanEnv( value ) {
	return '1' === value || 'true' === value;
}

function getEffectiveConfig() {
	const stored = loadStoredConfig();
	const config = {
		port: parseNumberOrFallback( stored.PORT, 13531 ),
		funnelPort: parseNumberOrFallback( stored.FUNNEL_PORT, 8443 ),
		backendUrl: stored.BACKEND_URL ?? '',
		apiKey: stored.API_KEY ?? '',
		noTunnel: parseBooleanEnv( stored.NO_TUNNEL ?? '0' ),
	};

	if ( '' !== PORT_ARG ) {
		config.port = parseNumberOrFallback( PORT_ARG, config.port );
	}
	if ( '' !== BACKEND_ARG ) {
		config.backendUrl = BACKEND_ARG;
	}
	if ( '' !== API_KEY_ARG ) {
		config.apiKey = API_KEY_ARG;
	}
	if ( NO_TUNNEL_FLAG ) {
		config.noTunnel = true;
	}
	if ( '' !== FUNNEL_PORT_ARG ) {
		const parsed = Number( FUNNEL_PORT_ARG );
		if ( ! ALLOWED_FUNNEL_PORTS.includes( parsed ) ) {
			console.error( `  Invalid --funnel-port value: ${ FUNNEL_PORT_ARG }` );
			console.error( `  Allowed ports: ${ ALLOWED_FUNNEL_PORTS.join( ', ' ) }` );
			console.error( '' );
			process.exit( 1 );
		}
		config.funnelPort = parsed;
	}

	if ( '' === config.apiKey ) {
		config.apiKey = randomBytes( 32 ).toString( 'hex' );
	}

	return config;
}

function hasUsableConfig( config ) {
	return '' !== String( config.backendUrl ?? '' ).trim() && '' !== String( config.apiKey ?? '' ).trim();
}

function buildPublicUrl( dnsName, publicPort ) {
	return 443 === publicPort
		? `https://${ dnsName }`
		: `https://${ dnsName }:${ publicPort }`;
}

/**
 * Check whether a CLI binary is available on PATH.
 */
function hasCli( bin ) {
	try {
		const cmd = process.platform === 'win32' ? 'where' : 'which';
		execFileSync( cmd, [ bin ], { stdio: 'ignore' } );
		return true;
	} catch {
		return false;
	}
}

/**
 * Try to reach a backend's probe URL and pull model names from the response.
 */
async function probeModels( probeUrl ) {
	try {
		const res = await fetch( probeUrl, { signal: AbortSignal.timeout( 3000 ) } );
		if ( ! res.ok ) return null;
		const json = await res.json();
		if ( Array.isArray( json?.data ) ) {
			return json.data.map( ( m ) => m.id ).filter( Boolean );
		}
		return [];
	} catch {
		return null;
	}
}

/**
 * Detect which backends are installed and running.
 */
async function detectBackends() {
	const results = [];

	for ( const backend of BACKENDS ) {
		const installed = hasCli( backend.cli );
		let running = false;
		let models  = null;

		if ( installed ) {
			models = await probeModels( backend.probeUrl );
			running = models !== null;
		}

		results.push( { ...backend, installed, running, models } );
	}

	return results;
}

/**
 * Prompt the user to pick from a list of choices. Returns the 0-based index.
 */
function promptChoice( question, choices, defaultIndex = null ) {
	const rl = createInterface( { input: process.stdin, output: process.stdout } );

	for ( let i = 0; i < choices.length; i++ ) {
		console.log( `    ${ i + 1 }. ${ choices[ i ] }` );
	}
	console.log( '' );

	return new Promise( ( resolve ) => {
		function ask() {
			rl.question( question, ( answer ) => {
				if ( '' === answer.trim() && defaultIndex !== null ) {
					rl.close();
					resolve( defaultIndex );
					return;
				}

				const n = parseInt( answer, 10 );
				if ( n >= 1 && n <= choices.length ) {
					rl.close();
					resolve( n - 1 );
				} else {
					ask();
				}
			} );
		}
		ask();
	} );
}

/**
 * Resolve which public HTTPS port to use for Tailscale Funnel.
 */
async function resolveFunnelPort() {
	console.log( '  Choose a public Tailscale Funnel port:' );
	console.log( '' );

	const choices = FUNNEL_PORT_CHOICES.map( ( choice ) => choice.label );

	const idx = await promptChoice( '  Which public HTTPS port? [1]: ', choices, 0 );
	console.log( '' );

	return FUNNEL_PORT_CHOICES[ idx ].port;
}

/**
 * Resolve which backend URL to use.
 */
async function resolveBackend() {
	if ( BACKEND_ARG ) {
		return BACKEND_ARG;
	}

	console.log( '' );
	console.log( '  Detecting local inference backends...' );
	console.log( '' );

	const detected = await detectBackends();

	const running   = detected.filter( ( b ) => b.running );
	const installed = detected.filter( ( b ) => b.installed && ! b.running );

	if ( running.length === 1 ) {
		const b = running[0];
		const modelCount = b.models?.length ?? 0;
		console.log( `  Found ${ b.name } running at ${ b.url }` );
		if ( modelCount > 0 ) {
			console.log( `  ${ modelCount } model${ modelCount === 1 ? '' : 's' } available: ${ b.models.join( ', ' ) }` );
		}
		console.log( '' );
		return b.url;
	}

	if ( running.length > 1 ) {
		console.log( '  Multiple backends detected:' );
		console.log( '' );

		const choices = running.map( ( b ) => {
			const modelCount = b.models?.length ?? 0;
			const modelInfo = modelCount > 0
				? ` — ${ modelCount } model${ modelCount === 1 ? '' : 's' }: ${ b.models.join( ', ' ) }`
				: '';
			return `${ b.name } (${ b.url })${ modelInfo }`;
		} );

		const idx = await promptChoice( '  Which backend? [number]: ', choices );
		console.log( '' );
		return running[ idx ].url;
	}

	if ( installed.length > 0 ) {
		console.error( '  Found installed but not running:' );
		for ( const b of installed ) {
			const startHint = b.cli === 'ollama' ? 'ollama serve' : 'lms server start';
			console.error( `    • ${ b.name } (${ b.cli }) — start it with: ${ startHint }` );
		}
		console.error( '' );
		console.error( '  Start one of the above, then re-run this script.' );
		console.error( '' );
		process.exit( 1 );
	}

	console.error( '  No supported backends found.' );
	console.error( '' );
	console.error( '  Install one of:' );
	console.error( '    • Ollama  — https://ollama.com' );
	console.error( '    • LM Studio — https://lmstudio.ai' );
	console.error( '' );
	console.error( '  Or pass --backend <url> to specify a custom endpoint.' );
	console.error( '' );
	process.exit( 1 );
}

async function promptYesNo( question, defaultValue = true ) {
	const rl = createInterface( { input: process.stdin, output: process.stdout } );
	const suffix = defaultValue ? ' [Y/n]: ' : ' [y/N]: ';

	return new Promise( ( resolve ) => {
		rl.question( `${ question }${ suffix }`, ( answer ) => {
			rl.close();

			const normalized = answer.trim().toLowerCase();
			if ( '' === normalized ) {
				resolve( defaultValue );
				return;
			}

			resolve( 'y' === normalized || 'yes' === normalized );
		} );
	} );
}

async function runInit() {
	console.log( '' );
	console.log( '  Initializing Home Inference configuration...' );
	console.log( '' );

	const noTunnel = NO_TUNNEL_FLAG ? true : ! await promptYesNo( '  Enable Tailscale Funnel?', true );

	const config = {
		port: '' !== PORT_ARG ? parseNumberOrFallback( PORT_ARG, 13531 ) : 13531,
		funnelPort: noTunnel
			? 8443
			: '' !== FUNNEL_PORT_ARG
				? Number( FUNNEL_PORT_ARG )
				: await resolveFunnelPort(),
		backendUrl: await resolveBackend(),
		apiKey: '' !== API_KEY_ARG ? API_KEY_ARG : randomBytes( 32 ).toString( 'hex' ),
		noTunnel,
	};

	if ( ! config.noTunnel && ! ALLOWED_FUNNEL_PORTS.includes( config.funnelPort ) ) {
		console.error( `  Invalid funnel port: ${ config.funnelPort }` );
		console.error( `  Allowed ports: ${ ALLOWED_FUNNEL_PORTS.join( ', ' ) }` );
		console.error( '' );
		process.exit( 1 );
	}

		writeConfig( config );

		console.log( `  Saved configuration to ${ ENV_PATH }` );
	console.log( '' );

	return config;
}

// ---------------------------------------------------------------------------
// Tailscale Funnel
// ---------------------------------------------------------------------------

/**
 * Check if Tailscale is installed and get its status.
 */
function getTailscaleStatus() {
	try {
		const output = execFileSync( 'tailscale', [ 'status', '--json' ], {
			encoding: 'utf-8',
			timeout: 5000,
		} );
		return JSON.parse( output );
	} catch {
		return null;
	}
}

/**
 * Get the Tailscale DNS name for this machine.
 */
function getTailscaleDnsName() {
	const status = getTailscaleStatus();
	if ( ! status?.Self?.DNSName ) {
		return null;
	}
	// DNSName has a trailing dot — remove it.
	return status.Self.DNSName.replace( /\.$/, '' );
}

/**
 * Start Tailscale Funnel for the local proxy. Returns the public HTTPS URL.
 */
function startFunnel( localPort, publicPort ) {
	const dnsName = getTailscaleDnsName();
	if ( ! dnsName ) {
		console.error( '  Error: Could not determine your Tailscale DNS name.' );
		console.error( '  Make sure Tailscale is running: tailscale up' );
		console.error( '' );
		process.exit( 1 );
	}

	console.log( `  Starting Tailscale Funnel on public port ${ publicPort }...` );

	// Run `tailscale funnel` in the background — it stays running.
	const child = execFile(
		'tailscale',
		[ 'funnel', '--bg', `--https=${ publicPort }`, String( localPort ) ],
		{ timeout: 15000 },
		( err, stdout, stderr ) => {
			if ( err ) {
				// The --bg flag may not be available in older versions.
				// If it fails, we'll try without it below.
				if ( stderr?.includes( 'unknown flag' ) ) {
					return;
				}
				// Non-fatal: funnel may already be running.
			}
		}
	);

	// Detach so it doesn't block shutdown.
	child.unref();

	const publicUrl = buildPublicUrl( dnsName, publicPort );
	return publicUrl;
}

/**
 * Ensure Tailscale is available, or guide the user to install it.
 */
function ensureTailscale() {
	if ( hasCli( 'tailscale' ) ) {
		const status = getTailscaleStatus();
		if ( ! status ) {
			console.error( '' );
			console.error( '  Tailscale is installed but not running.' );
			console.error( '  Start it with:' );
			console.error( '' );
			if ( process.platform === 'darwin' ) {
				console.error( '    open /Applications/Tailscale.app' );
			} else if ( process.platform === 'linux' ) {
				console.error( '    sudo tailscale up' );
			} else {
				console.error( '    tailscale up' );
			}
			console.error( '' );
			console.error( '  Then re-run this script.' );
			console.error( '' );
			process.exit( 1 );
		}
		return;
	}

	console.error( '' );
	console.error( '  Tailscale is required but not installed.' );
	console.error( '' );
	console.error( '  Install Tailscale:' );
	if ( process.platform === 'darwin' ) {
		console.error( '    brew install tailscale' );
		console.error( '    — or —' );
		console.error( '    https://tailscale.com/download/mac' );
	} else if ( process.platform === 'linux' ) {
		console.error( '    curl -fsSL https://tailscale.com/install.sh | sh' );
	} else if ( process.platform === 'win32' ) {
		console.error( '    https://tailscale.com/download/windows' );
	} else {
		console.error( '    https://tailscale.com/download' );
	}
	console.error( '' );
	console.error( '  After installing, run `tailscale up` and then re-run this script.' );
	console.error( '' );
	process.exit( 1 );
}

// ---------------------------------------------------------------------------
// Proxy handler
// ---------------------------------------------------------------------------

async function handler( req, res ) {
	// Authenticate.
	const auth = req.headers.authorization || '';
	if ( auth !== `Bearer ${ API_KEY }` ) {
		res.writeHead( 401, { 'Content-Type': 'application/json' } );
		res.end( JSON.stringify( { error: 'Unauthorized' } ) );
		return;
	}

	// Build upstream URL.
	const upstreamUrl = new URL( req.url, BACKEND );

	// Forward headers, minus hop-by-hop ones.
	const forwardHeaders = { ...req.headers };
	delete forwardHeaders.host;
	delete forwardHeaders.authorization;
	delete forwardHeaders.connection;
	forwardHeaders.host = upstreamUrl.host;

	try {
		const upstream = await fetch( upstreamUrl.href, {
			method: req.method,
			headers: forwardHeaders,
			body: ( req.method !== 'GET' && req.method !== 'HEAD' )
				? await readBody( req )
				: undefined,
			redirect: 'manual',
		} );

		// Relay status + headers.
		const relayHeaders = {};
		upstream.headers.forEach( ( value, key ) => {
			if ( key === 'transfer-encoding' ) return;
			relayHeaders[ key ] = value;
		} );

		res.writeHead( upstream.status, relayHeaders );

		if ( upstream.body ) {
			const reader = upstream.body.getReader();
			async function pump() {
				const { done, value } = await reader.read();
				if ( done ) {
					res.end();
					return;
				}
				res.write( value );
				await pump();
			}
			await pump();
		} else {
			res.end();
		}
	} catch ( err ) {
		res.writeHead( 502, { 'Content-Type': 'application/json' } );
		res.end( JSON.stringify( { error: 'Backend unreachable', detail: err.message } ) );
	}
}

function readBody( req ) {
	return new Promise( ( resolve, reject ) => {
		const chunks = [];
		req.on( 'data', ( c ) => chunks.push( c ) );
		req.on( 'end', () => resolve( Buffer.concat( chunks ) ) );
		req.on( 'error', reject );
	} );
}

// ---------------------------------------------------------------------------
// Start
// ---------------------------------------------------------------------------

async function main() {
	const hasStoredConfig = existsSync( ENV_PATH );
	const storedConfig = hasStoredConfig ? getEffectiveConfig() : null;
	const config = IS_INIT || ! storedConfig || ! hasUsableConfig( storedConfig )
		? await runInit()
		: storedConfig;

	PORT = config.port;
	BACKEND = config.backendUrl;
	API_KEY = config.apiKey;
	NO_TUNNEL = config.noTunnel;

	let publicUrl = null;
	let funnelPort = NO_TUNNEL ? null : config.funnelPort;

	if ( ! NO_TUNNEL ) {
		ensureTailscale();
		publicUrl = startFunnel( PORT, funnelPort );
	}

	const server = createServer( handler );
	server.listen( PORT, '0.0.0.0', () => {
		console.log( '' );
		console.log( '  Home Inference Proxy' );
		console.log( '  ────────────────────────────────────────' );
		console.log( `  Listening on   http://0.0.0.0:${ PORT }` );
		console.log( `  Backend        ${ BACKEND }` );
		if ( publicUrl ) {
			console.log( `  Public URL     ${ publicUrl }` );
			console.log( `  Public Route   ${ publicUrl } -> http://127.0.0.1:${ PORT }` );
		}
		console.log( `  API Key        ${ API_KEY }` );
		console.log( '  ────────────────────────────────────────' );
		console.log( '' );
		console.log( '  In your WordPress admin, set:' );
		if ( publicUrl ) {
			console.log( `    Endpoint URL:  ${ publicUrl }` );
			console.log( `    Note:          Tailscale Funnel listens on public port ${ funnelPort } and forwards to local port ${ PORT }` );
		} else {
			console.log( `    Endpoint URL:  http://<your-ip>:${ PORT }` );
		}
		console.log( `    API Key:       ${ API_KEY }` );
		console.log( '' );
		console.log( '  Local smoke test:' );
		console.log( `    curl -H "Authorization: Bearer ${ API_KEY }" http://127.0.0.1:${ PORT }/v1/models` );
		console.log( '' );
	} );

	process.on( 'SIGINT', () => {
		console.log( '\n  Shutting down...' );
		server.close();
		process.exit( 0 );
	} );
}

const IS_DIRECT_RUN = process.argv[ 1 ] && fileURLToPath( import.meta.url ) === process.argv[ 1 ];

if ( IS_DIRECT_RUN ) {
	main();
}

export {
	buildPublicUrl,
	FUNNEL_PORT_CHOICES,
	getEffectiveConfig,
	hasUsableConfig,
	parseBooleanEnv,
	parseEnvFile,
	parseNumberOrFallback,
};
