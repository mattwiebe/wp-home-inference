/**
 * Managed connectors UI override.
 *
 * Replaces the default API key form with a link to the plugin's setup page for
 * each provider managed by this plugin.
 *
 * @package Mattwiebe\LocalAiConnector
 */

import {
	__experimentalRegisterConnector as registerConnector,
	__experimentalConnectorItem as ConnectorItem,
} from '@wordpress/connectors';

const { createElement: h } = window.React ?? window.wp.element;

function getData() {
	try {
		const el = document.getElementById( 'wp-script-module-data-managed-connectors' );
		return JSON.parse( el?.textContent ?? '{}' );
	} catch {
		return {};
	}
}

const { connectors = {} } = getData();

/**
 * Custom connector component that shows a "Set up" / "Settings" button
 * linking to the plugin's own setup page.
 */
function ManagedConnector( { connector, name, description, logo } ) {
	const label = connector.isConnected ? 'Settings' : 'Set up';

	const badge = connector.isConnected
		? h(
				'span',
				{
					style: {
						color: '#007017',
						background: '#edfaef',
						padding: '2px 10px',
						borderRadius: '2px',
						fontSize: '13px',
						fontWeight: 500,
						whiteSpace: 'nowrap',
					},
				},
				'Connected'
		  )
		: null;

	const button = h(
		'a',
		{
			href: connector.setupUrl,
			className: 'components-button is-secondary is-compact',
			style: { textDecoration: 'none' },
		},
		label
	);

	return h( ConnectorItem, {
		logo,
		name,
		description,
		actionArea: h(
			'div',
			{ style: { display: 'flex', alignItems: 'center', gap: '12px' } },
			badge,
			button
		),
	} );
}

Object.entries( connectors ).forEach( ( [ slug, connector ] ) => {
	registerConnector( slug, {
		name: connector.name,
		description: connector.description,
		render: ( props ) => h( ManagedConnector, { ...props, connector } ),
	} );
} );
