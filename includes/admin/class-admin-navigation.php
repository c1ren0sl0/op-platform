<?php
/**
 * Admin Navigation page.
 *
 * @package OperationalPlatform
 */

declare(strict_types=1);

/**
 * Navigation admin page.
 */
class OP_Admin_Navigation {

	/**
	 * Navigation instance.
	 *
	 * @var OP_Navigation
	 */
	private OP_Navigation $navigation;

	/**
	 * Constructor.
	 *
	 * @param OP_Navigation $navigation Navigation instance.
	 */
	public function __construct( OP_Navigation $navigation ) {
		$this->navigation = $navigation;
	}

	/**
	 * Render the navigation page.
	 */
	public function render(): void {
		$stats = $this->navigation->get_stats();
		$items = $this->navigation->get_items();
		?>
		<div class="wrap">
			<h1><?php esc_html_e( 'Navigation', 'op-platform' ); ?></h1>

			<div class="op-section">
				<h2><?php esc_html_e( 'Statistics', 'op-platform' ); ?></h2>
				<table class="widefat">
					<tbody>
						<tr>
							<th><?php esc_html_e( 'Built', 'op-platform' ); ?></th>
							<td><?php echo $stats['built'] ? esc_html__( 'Yes', 'op-platform' ) : esc_html__( 'No', 'op-platform' ); ?></td>
						</tr>
						<tr>
							<th><?php esc_html_e( 'Total Items', 'op-platform' ); ?></th>
							<td><?php echo esc_html( $stats['total_items'] ); ?></td>
						</tr>
						<tr>
							<th><?php esc_html_e( 'Primary Items', 'op-platform' ); ?></th>
							<td><?php echo esc_html( $stats['primary_items'] ); ?></td>
						</tr>
					</tbody>
				</table>
			</div>

			<div class="op-section">
				<h2><?php esc_html_e( 'Navigation Structure', 'op-platform' ); ?></h2>
				<?php $this->render_nav_tree( $items ); ?>
			</div>

			<div class="op-section">
				<h2><?php esc_html_e( 'WordPress Menu', 'op-platform' ); ?></h2>
				<p>
					<?php
					printf(
						/* translators: %s: menu location name */
						esc_html__( 'Navigation is synced to WordPress menu location: %s', 'op-platform' ),
						'<code>' . esc_html( OP_Navigation::MENU_LOCATION ) . '</code>'
					);
					?>
				</p>
				<p>
					<a href="<?php echo esc_url( admin_url( 'nav-menus.php' ) ); ?>" class="button">
						<?php esc_html_e( 'Manage Menus', 'op-platform' ); ?>
					</a>
				</p>
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
			.op-nav-tree {
				list-style: none;
				padding-left: 20px;
			}
			.op-nav-tree-root {
				padding-left: 0;
			}
			.op-nav-item {
				padding: 8px 0;
				border-bottom: 1px solid #eee;
			}
			.op-nav-title {
				font-weight: bold;
			}
			.op-nav-url {
				color: #0073aa;
				font-size: 0.9em;
			}
		</style>
		<?php
	}

	/**
	 * Render navigation tree.
	 *
	 * @param array $items Navigation items.
	 * @param bool  $is_root Whether this is the root level.
	 */
	private function render_nav_tree( array $items, bool $is_root = true ): void {
		if ( empty( $items ) ) {
			echo '<p>' . esc_html__( 'No navigation items.', 'op-platform' ) . '</p>';
			return;
		}

		$class = $is_root ? 'op-nav-tree op-nav-tree-root' : 'op-nav-tree';
		echo '<ul class="' . esc_attr( $class ) . '">';
		foreach ( $items as $item ) {
			echo '<li class="op-nav-item">';
			echo '<span class="op-nav-title">' . esc_html( $item['title'] ) . '</span>';
			echo ' <a href="' . esc_url( $item['url'] ) . '" class="op-nav-url" target="_blank">' . esc_html( $item['route'] ) . '</a>';
			if ( ! empty( $item['children'] ) ) {
				$this->render_nav_tree( $item['children'], false );
			}
			echo '</li>';
		}
		echo '</ul>';
	}
}
