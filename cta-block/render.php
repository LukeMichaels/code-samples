<?php
/**
 * Server-side render for the "Call to Action (A/B)" block.
 *
 * Every configured variant is printed into the markup so the page response
 * stays fully cacheable. A single variant is chosen per visitor on the client
 * (see view.js). With JavaScript disabled the first variant is shown, so the
 * call to action still works for crawlers and no-JS visitors. In the block
 * editor, every variant stays visible so an author can confirm all of them
 * before publishing.
 *
 * @package LM\CtaBlock
 *
 * @var array $block The ACF block settings and attributes.
 */
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
$experiment_id = sanitize_key( (string) get_field( 'experiment_id' ) );
$raw_variants  = get_field( 'variants' );
$is_preview    = ! empty( $block['is_preview'] );
// Normalize once so the markup and the client config can never drift apart.
$variants = array();
if ( is_array( $raw_variants ) ) {
	foreach ( $raw_variants as $variant ) {
		$url = esc_url_raw( (string) ( $variant['url'] ?? '' ) );
		// A call to action with no destination is not worth rendering.
		if ( '' === $url ) {
			continue;
		}
		$variants[] = array(
			'label'  => sanitize_text_field( (string) ( $variant['label'] ?? '' ) ),
			'url'    => $url,
			'style'  => 'secondary' === ( $variant['style'] ?? '' ) ? 'secondary' : 'primary',
			'weight' => max( 0, (int) ( $variant['weight'] ?? 1 ) ),
		);
	}
}
if ( empty( $variants ) ) {
	if ( $is_preview ) {
		echo '<p>' . esc_html__( 'Add at least one call-to-action variant.', 'lm-cta' ) . '</p>';
	}
	return;
}
// Generate a stable id when the editor left it blank, so analytics still groups.
if ( '' === $experiment_id ) {
	$experiment_id = 'cta-' . substr( md5( (string) ( $block['id'] ?? '' ) ), 0, 8 );
}
// Config consumed by view.js. wp_json_encode handles escaping for the attribute.
$config = wp_json_encode(
	array(
		'experiment' => $experiment_id,
		'weights'    => array_column( $variants, 'weight' ),
	)
);
?>
<div class="lm-cta" data-lm-cta="<?php echo esc_attr( $config ); ?>"<?php echo $is_preview ? ' data-preview="1"' : ''; ?>>
	<?php foreach ( $variants as $position => $variant ) : ?>
		<a
			class="lm-cta__button lm-cta__button--<?php echo esc_attr( $variant['style'] ); ?>"
			href="<?php echo esc_url( $variant['url'] ); ?>"
			data-variant="<?php echo esc_attr( (string) $position ); ?>"
			<?php
			// The first variant is the control: it stays visible so no-JS
			// visitors and crawlers always have a working CTA. view.js reveals
			// whichever variant a visitor is assigned and hides the rest. In
			// the block editor, every variant stays visible so an author can
			// confirm all of them before publishing.
			echo ( 0 === $position || $is_preview ) ? '' : 'hidden';
			?>
		>
			<?php echo esc_html( $variant['label'] ); ?>
		</a>
	<?php endforeach; ?>
</div>
