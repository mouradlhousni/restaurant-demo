/**
 * Opening Hours – editor.
 */
import { registerBlockType } from '@wordpress/blocks';
import { InspectorControls, useBlockProps } from '@wordpress/block-editor';
import {
	PanelBody,
	SelectControl,
	ToggleControl,
	Disabled,
} from '@wordpress/components';
import ServerSideRender from '@wordpress/server-side-render';
import { __ } from '@wordpress/i18n';

import metadata from './block.json';
import './style.css';

function Edit( { attributes, setAttributes } ) {
	return (
		<>
			<InspectorControls>
				<PanelBody title={ __( 'Display', 'oe-reservations' ) }>
					<SelectControl
						__nextHasNoMarginBottom
						label={ __( 'Style', 'oe-reservations' ) }
						value={ attributes.variant }
						options={ [
							{
								label: __(
									'Full week (table)',
									'oe-reservations'
								),
								value: 'table',
							},
							{
								label: __(
									'Compact (grouped days)',
									'oe-reservations'
								),
								value: 'compact',
							},
							{
								label: __(
									"Today's service (timeline)",
									'oe-reservations'
								),
								value: 'strip',
							},
							{
								label: __(
									'Badge only (open now / closed)',
									'oe-reservations'
								),
								value: 'badge',
							},
						] }
						onChange={ ( variant ) => setAttributes( { variant } ) }
					/>
					<ToggleControl
						__nextHasNoMarginBottom
						label={ __(
							'Show “Open now / Closed” badge',
							'oe-reservations'
						) }
						checked={ attributes.showBadge }
						onChange={ ( showBadge ) =>
							setAttributes( { showBadge } )
						}
					/>
				</PanelBody>
			</InspectorControls>
			<div { ...useBlockProps() }>
				<Disabled>
					<ServerSideRender
						block={ metadata.name }
						attributes={ attributes }
					/>
				</Disabled>
			</div>
		</>
	);
}

registerBlockType( metadata.name, { edit: Edit, save: () => null } );
