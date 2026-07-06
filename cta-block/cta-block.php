<?php
/**
 * Plugin Name:  LM Call to Action (A/B)
 * Description:  A cache-safe, A/B-testable call-to-action block built on ACF.
 * Version:      1.0.0
 * Requires PHP: 7.4
 * Author:       Luke Michaels
 *
 * @package LM\CtaBlock
 */

namespace LM\CtaBlock;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

const BLOCK_NAME = 'acf/cta';

/**
 * Register the block once ACF is ready. Rendering is handled by render.php.
 */
function register_block(): void {
	if ( ! function_exists( 'acf_register_block_type' ) ) {
		return;
	}

	acf_register_block_type(
		array(
			'name'            => 'cta',
			'title'           => __( 'Call to Action (A/B)', 'lm-cta' ),
			'description'     => __( 'A call-to-action that can run a weighted A/B test across variants.', 'lm-cta' ),
			'category'        => 'design',
			'icon'            => 'megaphone',
			'keywords'        => array( 'cta', 'button', 'ab test' ),
			'render_template' => __DIR__ . '/render.php',
			'enqueue_assets'  => __NAMESPACE__ . '\\enqueue_assets',
			'supports'        => array(
				'anchor' => true,
				'align'  => array( 'left', 'center', 'right' ),
			),
		)
	);
}
add_action( 'acf/init', __NAMESPACE__ . '\\register_block' );

/**
 * Load the front-end script that assigns and tracks variants.
 *
 * Runs only on the front end; the editor preview renders every variant.
 */
function enqueue_assets(): void {
	if ( is_admin() ) {
		return;
	}

	wp_enqueue_style(
		'lm-cta',
		plugins_url( 'style.css', __FILE__ ),
		array(),
		'1.0.0'
	);

	wp_enqueue_script(
		'lm-cta',
		plugins_url( 'view.js', __FILE__ ),
		array(),
		'1.0.0',
		true
	);
}

/**
 * Register the block's fields in code so its configuration is versioned with
 * the block, rather than living only in the database.
 */
function register_fields(): void {
	if ( ! function_exists( 'acf_add_local_field_group' ) ) {
		return;
	}

	acf_add_local_field_group(
		array(
			'key'      => 'group_lm_cta',
			'title'    => 'Call to Action (A/B)',
			'fields'   => array(
				array(
					'key'          => 'field_lm_cta_experiment',
					'label'        => 'Experiment ID',
					'name'         => 'experiment_id',
					'type'         => 'text',
					'instructions' => 'Optional. Groups this CTA with an analytics experiment. Auto-generated when left blank.',
				),
				array(
					'key'          => 'field_lm_cta_variants',
					'label'        => 'Variants',
					'name'         => 'variants',
					'type'         => 'repeater',
					'min'          => 1,
					'layout'       => 'block',
					'button_label' => 'Add variant',
					'sub_fields'   => array(
						array(
							'key'      => 'field_lm_cta_label',
							'label'    => 'Button label',
							'name'     => 'label',
							'type'     => 'text',
							'required' => 1,
						),
						array(
							'key'      => 'field_lm_cta_url',
							'label'    => 'Destination URL',
							'name'     => 'url',
							'type'     => 'url',
							'required' => 1,
						),
						array(
							'key'           => 'field_lm_cta_style',
							'label'         => 'Style',
							'name'          => 'style',
							'type'          => 'select',
							'choices'       => array(
								'primary'   => 'Primary',
								'secondary' => 'Secondary',
							),
							'default_value' => 'primary',
						),
						array(
							'key'           => 'field_lm_cta_weight',
							'label'         => 'Weight',
							'name'          => 'weight',
							'type'          => 'number',
							'default_value' => 1,
							'min'           => 0,
							'instructions'  => 'Relative weight. Two variants at 1 and 1 split traffic evenly; 3 and 1 is a 75/25 split.',
						),
					),
				),
			),
			'location' => array(
				array(
					array(
						'param'    => 'block',
						'operator' => '==',
						'value'    => BLOCK_NAME,
					),
				),
			),
		)
	);
}
add_action( 'acf/init', __NAMESPACE__ . '\\register_fields' );
