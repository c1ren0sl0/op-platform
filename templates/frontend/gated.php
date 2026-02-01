<?php
/**
 * Gated content template.
 *
 * Displayed when user doesn't have access to a page or artifact.
 *
 * Variables available:
 * - $page: OP_Page or OP_Content_Item
 * - $breadcrumbs: array of breadcrumb items
 * - $navigation: OP_Navigation instance
 * - $access_level: required access tier
 * - $content_type: 'page' or artifact type
 * - $access_reason: reason for denial (optional)
 *
 * @package OperationalPlatform
 */

defined( 'ABSPATH' ) || exit;

$title = is_object( $page ) && method_exists( $page, 'get_title' )
	? $page->get_title()
	: ( $page->post_title ?? __( 'Content', 'op-platform' ) );
?>

<div class="op-page op-gated">
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
		<h1><?php echo esc_html( $title ); ?></h1>
	</header>

	<!-- Gate Message -->
	<div class="op-gate-message">
		<div class="op-gate-icon">
			<svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" width="64" height="64">
				<path stroke-linecap="round" stroke-linejoin="round" d="M16.5 10.5V6.75a4.5 4.5 0 10-9 0v3.75m-.75 11.25h10.5a2.25 2.25 0 002.25-2.25v-6.75a2.25 2.25 0 00-2.25-2.25H6.75a2.25 2.25 0 00-2.25 2.25v6.75a2.25 2.25 0 002.25 2.25z" />
			</svg>
		</div>

		<h2><?php esc_html_e( 'This content requires access', 'op-platform' ); ?></h2>

		<?php if ( ! is_user_logged_in() ) : ?>
			<p>
				<?php esc_html_e( 'Please log in to view this content.', 'op-platform' ); ?>
			</p>
			<p class="op-gate-actions">
				<a href="<?php echo esc_url( wp_login_url( home_url( $_SERVER['REQUEST_URI'] ) ) ); ?>" class="op-button op-button-primary">
					<?php esc_html_e( 'Log In', 'op-platform' ); ?>
				</a>
			</p>
		<?php else : ?>
			<?php if ( $access_level === 'premium' || $access_level === 'subscriber' ) : ?>
				<p>
					<?php esc_html_e( 'This content is available to subscribers.', 'op-platform' ); ?>
				</p>
				<?php
				// Allow filtering the upgrade URL.
				$upgrade_url = apply_filters( 'op_platform_upgrade_url', '', $access_level );
				if ( $upgrade_url ) :
					?>
					<p class="op-gate-actions">
						<a href="<?php echo esc_url( $upgrade_url ); ?>" class="op-button op-button-primary">
							<?php esc_html_e( 'Upgrade Now', 'op-platform' ); ?>
						</a>
					</p>
				<?php endif; ?>
			<?php else : ?>
				<p>
					<?php esc_html_e( 'You do not have permission to view this content.', 'op-platform' ); ?>
				</p>
			<?php endif; ?>
		<?php endif; ?>

		<?php if ( ! empty( $access_reason ) ) : ?>
			<p class="op-gate-reason">
				<?php echo esc_html( $access_reason ); ?>
			</p>
		<?php endif; ?>
	</div>
</div>

<style>
	.op-gated {
		text-align: center;
		padding: 40px 20px;
	}
	.op-gate-message {
		max-width: 400px;
		margin: 40px auto;
		padding: 40px;
		background: #f8f9fa;
		border-radius: 8px;
	}
	.op-gate-icon {
		color: #6c757d;
		margin-bottom: 20px;
	}
	.op-gate-message h2 {
		margin-top: 0;
	}
	.op-gate-actions {
		margin-top: 20px;
	}
	.op-button {
		display: inline-block;
		padding: 12px 24px;
		text-decoration: none;
		border-radius: 4px;
		font-weight: 500;
	}
	.op-button-primary {
		background: #0073aa;
		color: #fff;
	}
	.op-button-primary:hover {
		background: #005177;
		color: #fff;
	}
	.op-gate-reason {
		font-size: 0.9em;
		color: #6c757d;
		margin-top: 20px;
	}
</style>
