/**
 * Menu Section – editor.
 */
import { registerBlockType } from '@wordpress/blocks';
import { InspectorControls, useBlockProps } from '@wordpress/block-editor';
import {
	PanelBody,
	SelectControl,
	ToggleControl,
	Disabled,
} from '@wordpress/components';
import { useSelect } from '@wordpress/data';
import { store as coreStore } from '@wordpress/core-data';
import ServerSideRender from '@wordpress/server-side-render';
import { __ } from '@wordpress/i18n';

import metadata from './block.json';
import './style.css';

function Edit( { attributes, setAttributes } ) {
	const { section, chefsPicks, showFilters, showTitle, showImages, layout } =
		attributes;

	const sections = useSelect(
		( select ) =>
			select( coreStore ).getEntityRecords( 'taxonomy', 'menu_section', {
				per_page: -1,
				hide_empty: false,
			} ),
		[]
	);

	const options = [
		{ label: __( 'Choose a section…', 'oe-reservations' ), value: '' },
		...( sections || [] ).map( ( term ) => ( {
			label: term.name,
			value: term.slug,
		} ) ),
	];

	return (
		<>
			<InspectorControls>
				<PanelBody title={ __( 'Content', 'oe-reservations' ) }>
					<ToggleControl
						__nextHasNoMarginBottom
						label={ __(
							"Chef's picks only (all sections)",
							'oe-reservations'
						) }
						checked={ chefsPicks }
						onChange={ ( value ) =>
							setAttributes( { chefsPicks: value } )
						}
					/>
					{ ! chefsPicks && (
						<SelectControl
							__nextHasNoMarginBottom
							label={ __( 'Section', 'oe-reservations' ) }
							value={ section }
							options={ options }
							onChange={ ( value ) =>
								setAttributes( { section: value } )
							}
						/>
					) }
				</PanelBody>
				<PanelBody title={ __( 'Display', 'oe-reservations' ) }>
					<SelectControl
						__nextHasNoMarginBottom
						label={ __( 'Layout', 'oe-reservations' ) }
						value={ layout }
						options={ [
							{
								label: __(
									'Menu card (list)',
									'oe-reservations'
								),
								value: 'list',
							},
							{
								label: __(
									'Cards with photos (grid)',
									'oe-reservations'
								),
								value: 'grid',
							},
						] }
						onChange={ ( value ) =>
							setAttributes( { layout: value } )
						}
					/>
					<ToggleControl
						__nextHasNoMarginBottom
						label={ __( 'Show section title', 'oe-reservations' ) }
						checked={ showTitle }
						onChange={ ( value ) =>
							setAttributes( { showTitle: value } )
						}
					/>
					<ToggleControl
						__nextHasNoMarginBottom
						label={ __( 'Show photos', 'oe-reservations' ) }
						checked={ showImages }
						onChange={ ( value ) =>
							setAttributes( { showImages: value } )
						}
					/>
					<ToggleControl
						__nextHasNoMarginBottom
						label={ __(
							'Show allergen and diet filters',
							'oe-reservations'
						) }
						help={ __(
							'Filters apply to every Menu Section block on the page: enable them on the first one only.',
							'oe-reservations'
						) }
						checked={ showFilters }
						onChange={ ( value ) =>
							setAttributes( { showFilters: value } )
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
