<?php
/**
 * Operational page template.
 *
 * Renders operational pages with optional artifact grids.
 * Uses provider configuration for filters, badges, and card rendering.
 *
 * Variables available:
 * - $page: OP_Page object
 * - $breadcrumbs: array of breadcrumb items
 * - $navigation: OP_Navigation instance
 * - $children: array of child OP_Page objects
 * - $content_html: parsed markdown body
 * - $items: array of OP_Content_Item (if artifact_type set)
 * - $total: total items count
 * - $total_pages: total pages
 * - $current_page: current page number
 * - $provider: OP_Content_Provider (if artifact_type set)
 * - $type_config: OP_Type_Config (if artifact_type set)
 * - $artifact_type: string (if set)
 *
 * @package OperationalPlatform
 */

defined( 'ABSPATH' ) || exit;

// Get site branding functions if available.
$logo_url  = function_exists( 'ds_get_logo_url' ) ? ds_get_logo_url() : '';
$site_name = function_exists( 'ds_get_site_name' ) ? ds_get_site_name() : get_bloginfo( 'name' );
?>

<div class="min-h-screen bg-ds-bg">
	<!-- Platform Navigation -->
	<?php if ( $navigation && $navigation->is_built() ) : ?>
		<nav class="ds-main-nav bg-ds-nav border-b border-ds-nav-border" aria-label="<?php esc_attr_e( 'Platform navigation', 'op-platform' ); ?>">
			<div class="max-w-7xl mx-auto px-4 flex items-center justify-between">
				<!-- Site Branding -->
				<a href="<?php echo esc_url( home_url( '/' ) ); ?>" class="flex items-center gap-3 py-3 text-ds-nav-text-hover hover:text-ds-primary transition-colors">
					<?php if ( $logo_url ) : ?>
						<img src="<?php echo esc_url( $logo_url ); ?>" alt="" class="h-8 w-auto" style="height: 2rem; width: auto;">
					<?php endif; ?>
					<span class="text-lg font-semibold"><?php echo esc_html( $site_name ); ?></span>
				</a>

				<!-- Navigation Items -->
				<ul class="flex items-center gap-1">
					<?php foreach ( $navigation->get_items() as $item ) : ?>
						<?php $is_current = isset( $page ) && $item['route'] === $page->get_route(); ?>
						<li>
							<a href="<?php echo esc_url( home_url( $item['route'] ) ); ?>" class="block px-4 py-3 text-sm font-medium transition-colors <?php echo $is_current ? 'bg-ds-nav-active text-ds-primary-text' : 'text-ds-nav-text hover:text-ds-nav-text-hover hover:bg-ds-nav-border'; ?>">
								<?php echo esc_html( $item['title'] ); ?>
							</a>
						</li>
					<?php endforeach; ?>
				</ul>
			</div>
		</nav>
	<?php endif; ?>

	<div class="max-w-4xl mx-auto px-4 py-8">
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

	<!-- Page Header -->
	<header class="op-page-header">
		<h1><?php echo esc_html( $page->get_title() ); ?></h1>
		<?php if ( $page->get_description() ) : ?>
			<p class="op-page-description"><?php echo esc_html( $page->get_description() ); ?></p>
		<?php endif; ?>
	</header>

	<!-- Page Content -->
	<?php if ( ! empty( $content_html ) ) : ?>
		<div class="op-page-content prose">
			<?php echo wp_kses_post( $content_html ); ?>
		</div>
	<?php endif; ?>

	<!-- Artifact Grid -->
	<?php if ( ! empty( $artifact_type ) && isset( $provider ) ) : ?>
		<div class="op-artifact-grid" data-type="<?php echo esc_attr( $artifact_type ); ?>">
			<?php if ( ! empty( $type_config ) ) : ?>
				<!-- Filters -->
				<?php
				$filters = $type_config->get_filters();
				if ( ! empty( $filters ) ) :
					?>
					<div class="op-filters">
						<?php foreach ( $filters as $filter ) : ?>
							<?php if ( $filter['type'] === 'select' && ! empty( $filter['options'] ) ) : ?>
								<select class="op-filter" data-field="<?php echo esc_attr( $filter['field'] ); ?>">
									<option value=""><?php echo esc_html( $filter['label'] ); ?>: All</option>
									<?php foreach ( $filter['options'] as $option ) : ?>
										<option value="<?php echo esc_attr( $option['value'] ); ?>">
											<?php echo esc_html( $option['label'] ); ?>
										</option>
									<?php endforeach; ?>
								</select>
							<?php endif; ?>
						<?php endforeach; ?>
					</div>
				<?php endif; ?>
			<?php endif; ?>

			<!-- Items -->
			<?php if ( ! empty( $items ) ) : ?>
				<div class="op-items">
					<?php foreach ( $items as $item ) : ?>
						<?php
						// Try provider's card template first.
						$card_template = $provider->get_card_template( $artifact_type );
						if ( $card_template && file_exists( $card_template ) ) :
							include $card_template;
						else :
							// Default card rendering.
							?>
							<a href="<?php echo esc_url( $item->get_url() ); ?>" class="op-item-card">
								<h3 class="op-item-title"><?php echo esc_html( $item->get_title() ); ?></h3>
								<?php
								// Render badges if type_config available.
								if ( $type_config ) :
									$card_config = $type_config->get_card_config();
									if ( ! empty( $card_config['badges'] ) ) :
										?>
										<div class="op-item-badges">
											<?php foreach ( $card_config['badges'] as $badge ) : ?>
												<?php
												$badge_value = $item->get_meta( $badge['field'] );
												if ( $badge_value ) :
													?>
													<span class="op-badge <?php echo esc_attr( $badge['class'] ?? '' ); ?>">
														<?php echo esc_html( $badge_value ); ?>
													</span>
												<?php endif; ?>
											<?php endforeach; ?>
										</div>
									<?php endif; ?>
								<?php endif; ?>
							</a>
						<?php endif; ?>
					<?php endforeach; ?>
				</div>

				<!-- Pagination -->
				<?php if ( $total_pages > 1 ) : ?>
					<nav class="op-pagination">
						<?php if ( $current_page > 1 ) : ?>
							<a href="<?php echo esc_url( add_query_arg( 'paged', $current_page - 1 ) ); ?>" class="op-page-prev">
								<?php esc_html_e( 'Previous', 'op-platform' ); ?>
							</a>
						<?php endif; ?>

						<span class="op-page-info">
							<?php
							printf(
								/* translators: 1: current page, 2: total pages */
								esc_html__( 'Page %1$d of %2$d', 'op-platform' ),
								$current_page,
								$total_pages
							);
							?>
						</span>

						<?php if ( $current_page < $total_pages ) : ?>
							<a href="<?php echo esc_url( add_query_arg( 'paged', $current_page + 1 ) ); ?>" class="op-page-next">
								<?php esc_html_e( 'Next', 'op-platform' ); ?>
							</a>
						<?php endif; ?>
					</nav>
				<?php endif; ?>
			<?php else : ?>
				<p class="op-no-items"><?php esc_html_e( 'No items found.', 'op-platform' ); ?></p>
			<?php endif; ?>
		</div>
	<?php endif; ?>

	<!-- Child Pages -->
	<?php if ( ! empty( $children ) ) : ?>
		<div class="op-children">
			<h2><?php esc_html_e( 'In This Section', 'op-platform' ); ?></h2>
			<ul class="op-children-list">
				<?php foreach ( $children as $child ) : ?>
					<li>
						<a href="<?php echo esc_url( home_url( $child->get_route() ) ); ?>">
							<?php echo esc_html( $child->get_title() ); ?>
						</a>
						<?php if ( $child->get_description() ) : ?>
							<p><?php echo esc_html( $child->get_description() ); ?></p>
						<?php endif; ?>
					</li>
				<?php endforeach; ?>
			</ul>
		</div>
	<?php endif; ?>
	</div><!-- .max-w-4xl -->
</div><!-- .min-h-screen -->
