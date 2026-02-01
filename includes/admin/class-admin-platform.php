<?php
/**
 * Admin Platform Status page.
 *
 * @package OperationalPlatform
 */

declare(strict_types=1);

/**
 * Platform status admin page.
 */
class OP_Admin_Platform {

	/**
	 * Configuration instance.
	 *
	 * @var OP_Config
	 */
	private OP_Config $config;

	/**
	 * Platform instance.
	 *
	 * @var OP_Platform
	 */
	private OP_Platform $platform;

	/**
	 * Page tree instance.
	 *
	 * @var OP_Page_Tree
	 */
	private OP_Page_Tree $page_tree;

	/**
	 * Constructor.
	 *
	 * @param OP_Config    $config    Configuration instance.
	 * @param OP_Platform  $platform  Platform instance.
	 * @param OP_Page_Tree $page_tree Page tree instance.
	 */
	public function __construct( OP_Config $config, OP_Platform $platform, OP_Page_Tree $page_tree ) {
		$this->config    = $config;
		$this->platform  = $platform;
		$this->page_tree = $page_tree;
	}

	/**
	 * Render the platform status page.
	 */
	public function render(): void {
		$stats = $this->page_tree->get_stats();
		$validation = $this->platform->validate();
		?>
		<div class="wrap">
			<h1><?php esc_html_e( 'Platform Status', 'op-platform' ); ?></h1>

			<?php if ( isset( $_GET['rebuilt'] ) ) : ?>
				<div class="notice notice-success is-dismissible">
					<p><?php esc_html_e( 'Platform rebuilt successfully.', 'op-platform' ); ?></p>
				</div>
			<?php endif; ?>

			<div class="op-section">
				<h2><?php esc_html_e( 'Page Tree', 'op-platform' ); ?></h2>
				<table class="widefat">
					<tbody>
						<tr>
							<th><?php esc_html_e( 'Built', 'op-platform' ); ?></th>
							<td><?php echo $stats['built'] ? esc_html__( 'Yes', 'op-platform' ) : esc_html__( 'No', 'op-platform' ); ?></td>
						</tr>
						<tr>
							<th><?php esc_html_e( 'Built At', 'op-platform' ); ?></th>
							<td><?php echo esc_html( $stats['built_at'] ?? 'Never' ); ?></td>
						</tr>
						<tr>
							<th><?php esc_html_e( 'Total Pages', 'op-platform' ); ?></th>
							<td><?php echo esc_html( $stats['total_pages'] ); ?></td>
						</tr>
						<tr>
							<th><?php esc_html_e( 'Root Pages', 'op-platform' ); ?></th>
							<td><?php echo esc_html( $stats['root_pages'] ); ?></td>
						</tr>
					</tbody>
				</table>

				<form method="post" action="" style="margin-top: 15px;">
					<?php wp_nonce_field( 'op_rebuild' ); ?>
					<input type="submit" name="op_rebuild" class="button-secondary"
						   value="<?php esc_attr_e( 'Rebuild Platform', 'op-platform' ); ?>">
				</form>
			</div>

			<?php if ( ! empty( $validation['errors'] ) ) : ?>
				<div class="op-section">
					<h2><?php esc_html_e( 'Validation Errors', 'op-platform' ); ?></h2>
					<ul class="op-error-list">
						<?php foreach ( $validation['errors'] as $error ) : ?>
							<li><?php echo esc_html( $error ); ?></li>
						<?php endforeach; ?>
					</ul>
				</div>
			<?php endif; ?>

			<?php if ( ! empty( $validation['warnings'] ) ) : ?>
				<div class="op-section">
					<h2><?php esc_html_e( 'Warnings', 'op-platform' ); ?></h2>
					<ul class="op-warning-list">
						<?php foreach ( $validation['warnings'] as $warning ) : ?>
							<li><?php echo esc_html( $warning ); ?></li>
						<?php endforeach; ?>
					</ul>
				</div>
			<?php endif; ?>

			<div class="op-section">
				<h2><?php esc_html_e( 'Page Tree Structure', 'op-platform' ); ?></h2>
				<?php $this->render_tree( $this->page_tree->to_array() ); ?>
			</div>
		</div>

		<style>
			.op-section {
				background: #fff;
				border: 1px solid #ccd0d4;
				border-radius: 4px;
				padding: 20px;
				margin: 20px 0;
			}
			.op-section h2 {
				margin-top: 0;
			}
			.op-error-list li {
				color: #dc3232;
			}
			.op-warning-list li {
				color: #ffb900;
			}
			.op-tree {
				list-style: none;
				padding-left: 20px;
			}
			.op-tree-root {
				padding-left: 0;
			}
			.op-tree-item {
				padding: 5px 0;
			}
			.op-tree-title {
				font-weight: bold;
			}
			.op-tree-route {
				color: #666;
				font-family: monospace;
				font-size: 0.9em;
			}
			.op-tree-type {
				background: #e0e0e0;
				padding: 2px 6px;
				border-radius: 3px;
				font-size: 0.8em;
				margin-left: 5px;
			}
		</style>
		<?php
	}

	/**
	 * Render tree structure.
	 *
	 * @param array $items Tree items.
	 * @param bool  $is_root Whether this is the root level.
	 */
	private function render_tree( array $items, bool $is_root = true ): void {
		if ( empty( $items ) ) {
			echo '<p>' . esc_html__( 'No pages found.', 'op-platform' ) . '</p>';
			return;
		}

		$class = $is_root ? 'op-tree op-tree-root' : 'op-tree';
		echo '<ul class="' . esc_attr( $class ) . '">';
		foreach ( $items as $item ) {
			echo '<li class="op-tree-item">';
			echo '<span class="op-tree-title">' . esc_html( $item['title'] ) . '</span>';
			echo ' <span class="op-tree-route">' . esc_html( $item['route'] ) . '</span>';
			if ( ! empty( $item['artifact_type'] ) ) {
				echo ' <span class="op-tree-type">' . esc_html( $item['artifact_type'] ) . '</span>';
			}
			if ( ! empty( $item['children'] ) ) {
				$this->render_tree( $item['children'], false );
			}
			echo '</li>';
		}
		echo '</ul>';
	}
}
