import test from 'node:test';
import assert from 'node:assert/strict';

import {
	buildPublicUrl,
	FUNNEL_PORT_CHOICES,
	hasUsableConfig,
	parseBooleanEnv,
	parseEnvFile,
	parseNumberOrFallback,
} from '../../local/server.mjs';

test( 'parseEnvFile parses quoted values and ignores comments', () => {
	const env = parseEnvFile( `
# Comment
PORT="13531"
BACKEND_URL='http://localhost:11434'
NO_TUNNEL=1
` );

	assert.deepEqual( env, {
		PORT: '13531',
		BACKEND_URL: 'http://localhost:11434',
		NO_TUNNEL: '1',
	} );
} );

test( 'buildPublicUrl omits port 443 and includes non-default public ports', () => {
	assert.equal( buildPublicUrl( 'wiebook.tail7a347e.ts.net', 443 ), 'https://wiebook.tail7a347e.ts.net' );
	assert.equal( buildPublicUrl( 'wiebook.tail7a347e.ts.net', 8443 ), 'https://wiebook.tail7a347e.ts.net:8443' );
} );

test( 'default funnel choice is 8443', () => {
	assert.equal( FUNNEL_PORT_CHOICES[0].port, 8443 );
	assert.equal( FUNNEL_PORT_CHOICES[0].label, '8443 (default)' );
} );

test( 'hasUsableConfig requires backend URL and API key', () => {
	assert.equal( hasUsableConfig( { backendUrl: 'http://localhost:11434', apiKey: 'secret' } ), true );
	assert.equal( hasUsableConfig( { backendUrl: '', apiKey: 'secret' } ), false );
	assert.equal( hasUsableConfig( { backendUrl: 'http://localhost:11434', apiKey: '' } ), false );
} );

test( 'parseBooleanEnv and parseNumberOrFallback normalize persisted values', () => {
	assert.equal( parseBooleanEnv( '1' ), true );
	assert.equal( parseBooleanEnv( 'true' ), true );
	assert.equal( parseBooleanEnv( '0' ), false );
	assert.equal( parseNumberOrFallback( '13531', 1 ), 13531 );
	assert.equal( parseNumberOrFallback( 'not-a-number', 8443 ), 8443 );
} );
