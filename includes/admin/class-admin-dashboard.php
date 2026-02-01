<?php
/**
 * Admin Dashboard page.
 *
 * @package OperationalPlatform
 */

declare(strict_types=1);

/**
 * Dashboard admin page.
 */
class OP_Admin_Dashboard {

	/**
	 * Configuration instance.
	 *
	 * @var OP_Config
	 */
	private OP_Config $config;

	/**
	 * Diagnostics instance.
	 *
	 * @var OP_Diagnostics
	 */
	private OP_Diagnostics $diagnostics;

	/**
	 * Constructor.
	 *
	 * @param OP_Config      $config      Configuration instance.
	 * @param OP_Diagnostics $diagnostics Diagnostics instance.
	 */
	public function __construct( OP_Config $config, OP_Diagnostics $diagnostics ) {
		$this->config      = $config;
		$this->diagnostics = $diagnostics;
	}

	/**
	 * Render the dashboard page.
	 */
	public function render(): void {
		$status = $this->diagnostics->get_status();
		$report = $this->diagnostics->get_full_report();
		?>
		<div class="wrap">
			<h1><?php esc_html_e( 'Operational Platform', 'op-platform' ); ?></h1>

			<?php if ( isset( $_GET['updated'] ) ) : ?>
				<div class="notice notice-success is-dismissible">
					<p><?php esc_html_e( 'Configuration saved.', 'op-platform' ); ?></p>
				</div>
			<?php endif; ?>

			<div class="op-dashboard-grid">
				<!-- Status Card -->
				<div class="op-card">
					<h2><?php esc_html_e( 'Platform Status', 'op-platform' ); ?></h2>
					<p class="op-status <?php echo esc_attr( $this->diagnostics->get_status_class( $status ) ); ?>">
						<?php echo esc_html( $this->diagnostics->get_status_label( $status ) ); ?>
					</p>
				</div>

				<!-- Configuration Card -->
				<div class="op-card">
					<h2><?php esc_html_e( 'Configuration', 'op-platform' ); ?></h2>
					<form method="post" action="">
						<?php wp_nonce_field( 'op_save_config' ); ?>
						<table class="form-table">
							<tr>
								<th scope="row">
									<label for="library_path"><?php esc_html_e( 'Library Path', 'op-platform' ); ?></label>
								</th>
								<td>
									<input type="text" name="library_path" id="library_path"
										   value="<?php echo esc_attr( $this->config->get_library_path() ); ?>"
										   class="regular-text">
									<p class="description">
										<?php esc_html_e( 'Absolute path to the library directory containing /platform/.', 'op-platform' ); ?>
									</p>
								</td>
							</tr>
						</table>
						<p class="submit">
							<input type="submit" name="op_save_config" class="button-primary"
								   value="<?php esc_attr_e( 'Save Configuration', 'op-platform' ); ?>">
						</p>
					</form>
				</div>

				<!-- Providers Card -->
				<div class="op-card">
					<h2><?php esc_html_e( 'Content Providers', 'op-platform' ); ?></h2>
					<?php
					$providers = $report['providers']['provider_list'] ?? [];
					if ( empty( $providers ) ) :
						?>
						<p class="op-notice"><?php esc_html_e( 'No content providers registered.', 'op-platform' ); ?></p>
					<?php else : ?>
						<table class="widefat">
							<thead>
								<tr>
									<th><?php esc_html_e( 'Provider', 'op-platform' ); ?></th>
									<th><?php esc_html_e( 'Types', 'op-platform' ); ?></th>
								</tr>
							</thead>
							<tbody>
								<?php foreach ( $providers as $provider ) : ?>
									<tr>
										<td><?php echo esc_html( $provider['label'] ); ?></td>
										<td><?php echo esc_html( implode( ', ', $provider['types'] ) ); ?></td>
									</tr>
								<?php endforeach; ?>
							</tbody>
						</table>
					<?php endif; ?>
				</div>

				<!-- Quick Stats Card -->
				<div class="op-card">
					<h2><?php esc_html_e( 'Quick Stats', 'op-platform' ); ?></h2>
					<ul class="op-stats-list">
						<li>
							<strong><?php esc_html_e( 'Pages:', 'op-platform' ); ?></strong>
							<?php echo esc_html( $report['page_tree']['stats']['total_pages'] ?? 0 ); ?>
						</li>
						<li>
							<strong><?php esc_html_e( 'Root Pages:', 'op-platform' ); ?></strong>
							<?php echo esc_html( $report['page_tree']['stats']['root_pages'] ?? 0 ); ?>
						</li>
						<li>
							<strong><?php esc_html_e( 'Providers:', 'op-platform' ); ?></strong>
							<?php echo esc_html( $report['providers']['providers'] ?? 0 ); ?>
						</li>
						<li>
							<strong><?php esc_html_e( 'Artifact Types:', 'op-platform' ); ?></strong>
							<?php echo esc_html( $report['providers']['types'] ?? 0 ); ?>
						</li>
					</ul>
				</div>
			</div>
		</div>

		<style>
			.op-dashboard-grid {
				display: grid;
				grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
				gap: 20px;
				margin-top: 20px;
			}
			.op-card {
				background: #fff;
				border: 1px solid #ccd0d4;
				border-radius: 4px;
				padding: 20px;
			}
			.op-card h2 {
				margin-top: 0;
				border-bottom: 1px solid #eee;
				padding-bottom: 10px;
			}
			.op-status {
				font-size: 1.5em;
				font-weight: bold;
			}
			.op-status-success { color: #46b450; }
			.op-status-warning { color: #ffb900; }
			.op-status-error { color: #dc3232; }
			.op-stats-list {
				list-style: none;
				padding: 0;
				margin: 0;
			}
			.op-stats-list li {
				padding: 8px 0;
				border-bottom: 1px solid #eee;
			}
			.op-stats-list li:last-child {
				border-bottom: none;
			}
			.op-notice {
				color: #666;
				font-style: italic;
			}
		</style>
		<?php
	}
}
