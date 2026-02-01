<?php
/**
 * Default artifact detail template.
 *
 * This is a minimal fallback template. Providers should supply their own
 * detail templates with richer formatting.
 *
 * Variables available:
 * - $item: OP_Content_Item
 * - $type: artifact type string
 * - $type_config: OP_Type_Config (may be null)
 * - $provider: OP_Content_Provider
 * - $breadcrumbs: array of breadcrumb items
 * - $navigation: OP_Navigation instance
 * - $content_html: rendered content
 * - $meta: array of metadata
 *
 * @package OperationalPlatform
 */

defined( 'ABSPATH' ) || exit;
?>

<div class="op-artifact-detail">
	<!-- Breadcrumbs -->
	<?php if ( ! empty( $breadcrumbs ) ) : ?>
		<nav class="op-breadcrumbs" aria-label="<?php esc_attr_e( 'Breadcrumb', 'op-platform' ); ?>">
			<ol>
				<?php foreach ( $breadcrumbs as $i => $crumb ) : ?>
					<li>
						<?php if ( $i < count( $breadcrumbs ) - 1 ) : ?>
							<a href="<?php echo esc_url( home_url( $crumb['route'] ) ); ?>">
								<?php echo esc_html( $crumb['title'] ); ?>
							</a>
						<?php else : ?>
							<span aria-current="page"><?php echo esc_html( $crumb['title'] ); ?></span>
						<?php endif; ?>
					</li>
				<?php endforeach; ?>
			</ol>
		</nav>
	<?php endif; ?>

	<article>
		<!-- Header -->
		<header class="op-artifact-header">
			<h1><?php echo esc_html( $item->get_title() ); ?></h1>

			<?php if ( $type_config ) : ?>
				<p class="op-artifact-type">
					<?php echo esc_html( $type_config->get_label() ); ?>
				</p>
			<?php endif; ?>
		</header>

		<!-- Metadata -->
		<?php if ( $type_config ) : ?>
			<?php
			$detail_fields = $type_config->get_detail_fields();
			if ( ! empty( $detail_fields ) ) :
				?>
				<aside class="op-artifact-meta">
					<dl>
						<?php foreach ( $detail_fields as $field ) : ?>
							<?php
							$value = $item->get_meta( $field['field'] );
							if ( ! empty( $value ) ) :
								// Format value based on type.
								$formatted = $value;
								if ( isset( $field['format'] ) ) {
									switch ( $field['format'] ) {
										case 'date':
											$formatted = date_i18n( get_option( 'date_format' ), strtotime( $value ) );
											break;
										case 'url':
											$formatted = '<a href="' . esc_url( $value ) . '" target="_blank" rel="noopener">' . esc_html( $value ) . '</a>';
											break;
									}
								}
								?>
								<div class="op-meta-row">
									<dt><?php echo esc_html( $field['label'] ); ?></dt>
									<dd><?php echo wp_kses_post( $formatted ); ?></dd>
								</div>
							<?php endif; ?>
						<?php endforeach; ?>
					</dl>
				</aside>
			<?php endif; ?>
		<?php endif; ?>

		<!-- Content -->
		<?php if ( ! empty( $content_html ) ) : ?>
			<div class="op-artifact-content prose">
				<?php echo wp_kses_post( $content_html ); ?>
			</div>
		<?php endif; ?>
	</article>
</div>

<style>
	.op-artifact-detail {
		max-width: 900px;
		margin: 0 auto;
		padding: 20px;
	}
	.op-artifact-header {
		margin-bottom: 30px;
	}
	.op-artifact-header h1 {
		margin-bottom: 10px;
	}
	.op-artifact-type {
		color: #666;
		font-size: 0.9em;
		text-transform: uppercase;
		letter-spacing: 0.5px;
	}
	.op-artifact-meta {
		background: #f8f9fa;
		padding: 20px;
		border-radius: 8px;
		margin-bottom: 30px;
	}
	.op-artifact-meta dl {
		margin: 0;
	}
	.op-meta-row {
		display: flex;
		padding: 8px 0;
		border-bottom: 1px solid #e9ecef;
	}
	.op-meta-row:last-child {
		border-bottom: none;
	}
	.op-meta-row dt {
		flex: 0 0 150px;
		font-weight: 600;
		color: #495057;
	}
	.op-meta-row dd {
		flex: 1;
		margin: 0;
	}
	.op-artifact-content {
		line-height: 1.7;
	}
</style>
