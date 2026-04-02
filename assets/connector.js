/**
 * Home Inference — Connector UI override.
 *
 * Registers a custom render component for the Home Inference connector
 * in the Connectors admin page, replacing the default API key form
 * with a link to the plugin's setup page.
 *
 * @package WordPress\HomeInference
 */

import {
	__experimentalRegisterConnector as registerConnector,
	__experimentalConnectorItem as ConnectorItem,
} from '@wordpress/connectors';

const { createElement: h } = window.React ?? window.wp.element;

// Read data injected via the script_module_data filter.
function getData() {
	try {
		const el = document.getElementById(
			'wp-script-module-data-home-inference-connector'
		);
		return JSON.parse( el?.textContent ?? '{}' );
	} catch {
		return {};
	}
}

const { setupUrl, isConnected } = getData();

/**
 * Custom connector component that shows a "Set up" / "Settings" button
 * linking to the plugin's own setup page.
 */
function HomeInferenceConnector( { name, description, logo } ) {
	const label = isConnected ? 'Settings' : 'Set up';

	const badge = isConnected
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
			href: setupUrl,
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

// Override the connector registration.  registerConnector is additive —
// calling it again with the same slug replaces the previous entry.
registerConnector( 'home-inference', {
	name: 'Home Inference',
	description: 'Run AI inference on your own hardware using local models.',
	render: HomeInferenceConnector,
} );
