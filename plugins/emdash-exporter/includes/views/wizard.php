<?php
/**
 * Migration wizard view.
 *
 * Variables from EmDash_Admin_Page::render():
 * @var array $checks     Health check results
 * @var bool  $checks_ok  No check failed
 * @var bool  $checks_warn At least one check returned a warning
 * @var bool  $key_exists Migration app password exists
 * @var array $overview   Content overview data
 */

defined('ABSPATH') || exit;
?>
<div class="wrap emdash-wizard">
	<h1 class="emdash-title">
		<?php esc_html_e('Migrate to EmDash', 'emdash-exporter'); ?>
		<span class="emdash-title-sub"><?php esc_html_e('in 3 simple steps', 'emdash-exporter'); ?></span>
	</h1>

	<!-- Step 1: Site check -->
	<div class="emdash-step <?php echo $checks_ok ? 'is-done' : 'is-active'; ?>" id="emdash-step-1">
		<div class="emdash-step-header">
			<span class="emdash-step-badge"><?php echo $checks_ok ? '&#10003;' : '1'; ?></span>
			<div>
				<h2><?php esc_html_e('Site check', 'emdash-exporter'); ?></h2>
				<p><?php esc_html_e('We check whether EmDash will be able to connect to this site.', 'emdash-exporter'); ?></p>
			</div>
			<?php if ($checks_ok) : ?>
				<span class="emdash-step-status"><?php echo $checks_warn
					? esc_html__('Passed with warnings', 'emdash-exporter')
					: esc_html__('All checks passed', 'emdash-exporter'); ?></span>
			<?php endif; ?>
		</div>
		<div class="emdash-step-body">
			<ul class="emdash-checks">
				<?php foreach ($checks as $check) : ?>
					<li class="emdash-check emdash-check--<?php echo esc_attr($check['status']); ?>">
						<span class="emdash-check-icon" aria-hidden="true"><?php
							echo $check['status'] === 'pass' ? '&#10003;' : ($check['status'] === 'warn' ? '&#9888;' : '&#10005;');
						?></span>
						<div>
							<strong><?php echo esc_html($check['label']); ?></strong>
							<p><?php echo wp_kses_post($check['message']); ?></p>
							<?php if (!empty($check['fix'])) : ?>
								<p class="emdash-check-fix"><?php echo wp_kses_post($check['fix']); ?></p>
							<?php endif; ?>
						</div>
					</li>
				<?php endforeach; ?>
			</ul>
			<a href="<?php echo esc_url(admin_url('tools.php?page=' . EmDash_Admin_Page::SLUG)); ?>" class="button">
				<?php esc_html_e('Re-run checks', 'emdash-exporter'); ?>
			</a>
		</div>
	</div>

	<!-- Step 2: Migration key -->
	<div class="emdash-step <?php echo $checks_ok ? 'is-active' : ''; ?>" id="emdash-step-2">
		<div class="emdash-step-header">
			<span class="emdash-step-badge">2</span>
			<div>
				<h2><?php esc_html_e('Create your migration key', 'emdash-exporter'); ?></h2>
				<p><?php esc_html_e('One key contains everything EmDash needs to connect: your site address and a secure, revocable access password. No manual setup.', 'emdash-exporter'); ?></p>
			</div>
		</div>
		<div class="emdash-step-body">
			<div id="emdash-key-area">
				<button type="button" class="button button-primary button-hero" id="emdash-generate-key">
					<?php echo $key_exists
						? esc_html__('Regenerate migration key', 'emdash-exporter')
						: esc_html__('Generate migration key', 'emdash-exporter'); ?>
				</button>
				<button type="button" class="button" id="emdash-revoke-key"<?php echo $key_exists ? '' : ' hidden'; ?>><?php esc_html_e('Revoke key', 'emdash-exporter'); ?></button>
				<?php if ($key_exists) : ?>
					<p class="description" id="emdash-key-exists"><?php esc_html_e('A migration key already exists. For security, the key is only displayed once when generated — regenerate it if you no longer have it.', 'emdash-exporter'); ?></p>
				<?php endif; ?>
				<div id="emdash-key-result" hidden>
					<div class="emdash-key-row">
						<input type="text" id="emdash-key-value" class="emdash-key-input" readonly value="" aria-label="<?php esc_attr_e('Migration key', 'emdash-exporter'); ?>">
						<button type="button" class="button button-primary" id="emdash-copy-key"><?php esc_html_e('Copy key', 'emdash-exporter'); ?></button>
					</div>
					<p class="description">
						<?php esc_html_e('Keep this key private — anyone who has it can read all content on this site. Revoking it here disables access instantly.', 'emdash-exporter'); ?>
					</p>
				</div>
				<div id="emdash-key-error" class="notice notice-error inline" hidden><p></p></div>
			</div>
		</div>
	</div>

	<!-- Step 3: Connect EmDash -->
	<div class="emdash-step" id="emdash-step-3">
		<div class="emdash-step-header">
			<span class="emdash-step-badge">3</span>
			<div>
				<h2><?php esc_html_e('Connect from EmDash', 'emdash-exporter'); ?></h2>
				<p><?php esc_html_e('The import runs from your EmDash site — it pulls the content from here.', 'emdash-exporter'); ?></p>
			</div>
		</div>
		<div class="emdash-step-body">
			<div class="emdash-columns">
				<div class="emdash-column">
					<h3><?php esc_html_e('I already have an EmDash site', 'emdash-exporter'); ?></h3>
					<ol>
						<li><?php esc_html_e('Open your EmDash admin and go to Import → WordPress.', 'emdash-exporter'); ?></li>
						<li><?php esc_html_e('Paste the migration key from step 2.', 'emdash-exporter'); ?></li>
						<li><?php esc_html_e('Review what will be imported and start the migration.', 'emdash-exporter'); ?></li>
					</ol>
				</div>
				<div class="emdash-column">
					<h3><?php esc_html_e('I don\'t have an EmDash site yet', 'emdash-exporter'); ?></h3>
					<p><?php esc_html_e('Deploy a free EmDash starter site to Cloudflare — it takes about two minutes:', 'emdash-exporter'); ?></p>
					<a href="<?php echo esc_url(EmDash_Admin_Page::DEPLOY_URL); ?>" target="_blank" rel="noopener" class="button button-primary emdash-deploy-btn">
						<span class="emdash-cf-icon" aria-hidden="true"></span>
						<?php esc_html_e('Deploy to Cloudflare', 'emdash-exporter'); ?>
					</a>
					<p class="description">
						<?php esc_html_e('Requires the Cloudflare Workers Paid plan ($5/month) for EmDash\'s plugin sandbox. On the Free plan: fork the template, comment out the worker_loaders block in wrangler.jsonc, and deploy from your fork.', 'emdash-exporter'); ?>
					</p>
					<p class="description">
						<?php esc_html_e('If the deploy fails with HTTP 400, a resource from an earlier attempt may already exist on your account — check for a Worker, D1 database, R2 bucket, or KV namespace (including one titled "SESSION") with a conflicting name, then rename or delete it and retry.', 'emdash-exporter'); ?>
					</p>
					<p class="description">
						<a href="<?php echo esc_url(EmDash_Admin_Page::TEMPLATES_URL); ?>" target="_blank" rel="noopener"><?php esc_html_e('Other templates (marketing, portfolio, starter)', 'emdash-exporter'); ?></a>
					</p>
					<p><?php esc_html_e('After deployment:', 'emdash-exporter'); ?></p>
					<ol>
						<li><?php esc_html_e('Open your new site and finish setup at /_emdash/setup (creates your passkey login).', 'emdash-exporter'); ?></li>
						<li><?php esc_html_e('In the EmDash admin, go to Import → WordPress.', 'emdash-exporter'); ?></li>
						<li><?php esc_html_e('Paste the migration key from step 2.', 'emdash-exporter'); ?></li>
					</ol>
				</div>
			</div>

			<h3><?php esc_html_e('What will be migrated', 'emdash-exporter'); ?></h3>
			<div class="emdash-columns">
				<div class="emdash-column">
					<h4 class="emdash-yes"><?php esc_html_e('Included', 'emdash-exporter'); ?></h4>
					<ul class="emdash-overview">
						<?php foreach ($overview['types'] as $type) : ?>
							<li><?php echo esc_html($type['label']); ?> <span class="emdash-count"><?php echo esc_html(number_format_i18n($type['count'])); ?></span></li>
						<?php endforeach; ?>
						<?php foreach ($overview['taxonomies'] as $taxonomy) : ?>
							<li><?php echo esc_html($taxonomy['label']); ?> <span class="emdash-count"><?php echo esc_html(number_format_i18n($taxonomy['count'])); ?></span></li>
						<?php endforeach; ?>
						<li><?php esc_html_e('Media library', 'emdash-exporter'); ?> <span class="emdash-count"><?php echo esc_html(number_format_i18n($overview['media_count'])); ?></span></li>
						<?php if ($overview['menu_count'] > 0) : ?>
							<li><?php esc_html_e('Navigation menus', 'emdash-exporter'); ?> <span class="emdash-count"><?php echo esc_html(number_format_i18n($overview['menu_count'])); ?></span></li>
						<?php endif; ?>
						<?php if ($overview['comment_count'] > 0) : ?>
							<li><?php esc_html_e('Comments', 'emdash-exporter'); ?> <span class="emdash-count"><?php echo esc_html(number_format_i18n($overview['comment_count'])); ?></span></li>
						<?php endif; ?>
						<li><?php esc_html_e('Site title, tagline, logo, and favicon', 'emdash-exporter'); ?></li>
						<?php if ($overview['acf']) : ?>
							<li><?php esc_html_e('ACF custom fields', 'emdash-exporter'); ?></li>
						<?php endif; ?>
						<?php if ($overview['yoast']) : ?>
							<li><?php esc_html_e('Yoast SEO titles and meta descriptions', 'emdash-exporter'); ?></li>
						<?php endif; ?>
						<?php if ($overview['rankmath']) : ?>
							<li><?php esc_html_e('Rank Math SEO titles and meta descriptions', 'emdash-exporter'); ?></li>
						<?php endif; ?>
						<?php if ($overview['i18n']) : ?>
							<li><?php
								printf(
									/* translators: 1: plugin name, 2: number of languages */
									esc_html__('Translations (%1$s, %2$d languages)', 'emdash-exporter'),
									esc_html($overview['i18n']['plugin'] === 'wpml' ? 'WPML' : 'Polylang'),
									count($overview['i18n']['locales'])
								);
							?></li>
						<?php endif; ?>
					</ul>
				</div>
				<div class="emdash-column">
					<h4 class="emdash-no"><?php esc_html_e('Not included', 'emdash-exporter'); ?></h4>
					<ul class="emdash-overview emdash-overview--excluded">
						<?php if (!empty($overview['page_builder'])) : ?>
							<li><?php
								printf(
									/* translators: %s: page builder names */
									esc_html__('%s layouts (only standard editor content converts; builder pages arrive as plain content)', 'emdash-exporter'),
									esc_html(implode(', ', $overview['page_builder']))
								);
							?></li>
						<?php endif; ?>
						<?php if ($overview['yoast'] || $overview['rankmath']) : ?>
							<li><?php esc_html_e('SEO robots settings (noindex) and canonical URLs', 'emdash-exporter'); ?></li>
						<?php endif; ?>
						<?php if (!empty($overview['form_plugins'])) : ?>
							<li><?php
								printf(
									/* translators: %s: form plugin names */
									esc_html__('Forms and form entries from %s', 'emdash-exporter'),
									esc_html(implode(', ', $overview['form_plugins']))
								);
							?></li>
						<?php endif; ?>
						<?php if (!empty($overview['redirect_plugins'])) : ?>
							<li><?php
								printf(
									/* translators: %s: plugin names */
									esc_html__('Redirects managed by %s', 'emdash-exporter'),
									esc_html(implode(', ', $overview['redirect_plugins']))
								);
							?></li>
						<?php endif; ?>
						<li><?php esc_html_e('Installed plugins, themes, and their settings', 'emdash-exporter'); ?></li>
						<li><?php esc_html_e('WooCommerce orders and customers', 'emdash-exporter'); ?></li>
						<li><?php esc_html_e('Users and passwords (authors are recreated as bylines)', 'emdash-exporter'); ?></li>
					</ul>
				</div>
			</div>
		</div>
	</div>
</div>
