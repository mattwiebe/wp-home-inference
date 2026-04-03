import test from 'node:test';
import assert from 'node:assert/strict';

import { helpText, normalizeArgs, VERSION } from '../../bin/wp-home-inference.mjs';

test( 'normalizeArgs handles help and version flags locally', () => {
	assert.deepEqual( normalizeArgs( [ '--help' ] ), { action: 'help', forwardedArgs: [] } );
	assert.deepEqual( normalizeArgs( [ '-h' ] ), { action: 'help', forwardedArgs: [] } );
	assert.deepEqual( normalizeArgs( [ '--version' ] ), { action: 'version', forwardedArgs: [] } );
	assert.deepEqual( normalizeArgs( [ '-v' ] ), { action: 'version', forwardedArgs: [] } );
} );

test( 'normalizeArgs maps up to bare server run and preserves init', () => {
	assert.deepEqual( normalizeArgs( [ 'up' ] ), { action: 'run', forwardedArgs: [] } );
	assert.deepEqual( normalizeArgs( [ 'up', '--port', '13531' ] ), { action: 'run', forwardedArgs: [ '--port', '13531' ] } );
	assert.deepEqual( normalizeArgs( [ 'init' ] ), { action: 'run', forwardedArgs: [ 'init' ] } );
} );

test( 'helpText and version include the package version', () => {
	assert.match( helpText(), new RegExp( `wphi v${ VERSION.replace( /\./g, '\\.' ) }` ) );
	assert.match( helpText(), /wphi --version/ );
} );
