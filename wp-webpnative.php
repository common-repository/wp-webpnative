<?php
/**
* Plugin Name: WP WebPNative
* Plugin URI: alex.alouit.fr/#wpwebpnative
* Description: WebP support for Wordpress.
* Version: 1.0
* Author: Alex Alouit
* Author URI: alex.alouit.fr
* Author Email: alex@alouit.fr
* Text Domain: wp-webpnative
* Domain Path: /languages
* License: GPLv2
*/

register_activation_hook(__FILE__, function () {
	add_option(
		'wpwebpnative_transparent',
		true
	);
});

register_deactivation_hook(__FILE__, function () {
	wp_unschedule_event(
		wp_next_scheduled(
		'wpwebpnative_cron'
	),
	'wpwebpnative_cron'
	);
});

add_action('plugins_loaded', function () {
	load_plugin_textdomain('wp-webpnative', false, 'wp-webpnative/languages');
});

add_action('admin_enqueue_scripts', function () {
	if (function_exists('current_user_can') && ! current_user_can('manage_options')) return;

	wp_enqueue_style('wpwebpnative-css', plugins_url('/style.css', __FILE__), array(), '1.0.0', 'all');
});

add_filter('plugin_action_links', function ($links, $file) {
	if ($file == plugin_basename(dirname(__FILE__) . '/wp-webpnative.php')) {
		$links[] = '<a href="' . add_query_arg(array('page' => 'wpwebpnative'), admin_url('options-general.php')) . '">' . __('Settings', 'wpwebpnative') . '</a>';
	}

	return $links;
}, 10, 2);

add_action('manage_media_custom_column', function ($column_name, $id) {
	if ($column_name != 'wpwebpnative')
		return;

	if (get_post_meta((int) $id, '_wp_webpnative', true)) {
?>
<span class="dashicons dashicons-yes" title="<?php _e('Compressed', 'wpwebpnative'); ?>"></span>
<?php
		return;
	} else {
		$media = get_post((int) $id, ARRAY_A);
		if (
			in_array(
				$media['post_mime_type'],
				array(
					'image/jpeg',
					'image/jpg',
					'image/png'
				)
			)
		) {
?>
<span class="dashicons dashicons-clock" title="<?php _e('Waiting', 'wpwebpnative'); ?>"></span>
<?php
		}
	}
}, 10, 2);

add_filter('manage_media_columns', function ($columns) {
	$columns['wpwebpnative'] = 'WebP';
	return $columns;
});

add_action('admin_menu', function () {
	if (function_exists('current_user_can') && ! current_user_can('manage_options')) return;

	$hook = add_options_page('WP WebpNative', 'WP WebPNative', 'manage_options', 'wpwebpnative', 'wpwebpnative');
});

add_action('admin_notices', function () {
	if (! get_option('wpwebpnative_spent'))
		return;
?>
<div class="notice notice-warning">
	<p>
		<strong>
			<?php print sprintf(
				__(
					'WP-WebPNative: No more credits, wait a week or <a href="%s" target="_blank">buy 1 year subscription</a>.',
					'wpwebpnative'
				),
				sprintf(
					'https://www.paypal.com/cgi-bin/webscr?cmd=_s-xclick&hosted_button_id=C2RFRF68M2LGE&custom=%s',
					str_replace(
						array(
							'https://',
							'http://'
						),
						'',
						home_url()
					)
				)
			); ?>
		</strong>
	</p>
</div>
<?php
});

add_action('admin_notices', function () {
	if (
		isset($_POST['wpwebpnative_transparent']) &&
		in_array($_POST['wpwebpnative_transparent'], array("1", "0")) &&
		$_POST['wpwebpnative_transparent'] != (bool) get_option('wpwebpnative_transparent')
	) {
		update_option('wpwebpnative_transparent', (bool) $_POST['wpwebpnative_transparent']);
?>
<div class="notice notice-success is-dismissible"><p><strong><?php esc_html_e('WP-WebPNative: Options saved.', 'wpwebpnative'); ?></strong></p></div>
<?php
	}
});

add_action('wpwebpnative_cron', function () {
	global $wpdb;

	$limit = 32;
	$basedir = wp_upload_dir()['basedir'];

	$sql = "
SELECT `ID`
FROM `{$wpdb->posts}`
WHERE 
`post_type` = 'attachment' AND
`post_mime_type` IN ('image/jpeg','image/jpg','image/png') AND
`ID` NOT IN
(
 SELECT `post_id`
 FROM `{$wpdb->postmeta}`
 WHERE
 `meta_key` = '_wp_webpnative'
)
ORDER BY RAND()
LIMIT {$limit};
	";

	foreach ($wpdb->get_col($sql) as $id) {
		$files = array();

		$meta = get_post_meta((int) $id, '_wp_attachment_metadata', true);
		foreach ($meta['sizes'] as $size) {
			$files[] = preg_replace_callback(
				'/^((?:[0-9]{4})\/(?:[0-9]{2})\/)(?:.*)$/',
				function ($matches) use ($size) {
					return $matches[1] . $size['file'];
				},
				$meta['file']
			);
		}
		$files[] = $meta['file'];

		foreach ($files as $file) {
			if (! file_exists("{$basedir}/{$file}")) {
				continue;
			}

			if (filesize("{$basedir}/{$file}") > 8388608) {
				continue;
			}

			if (file_exists("{$basedir}/{$file}.webp")) {
				continue;
			}

			$data = base64_encode(
				file_get_contents(
					"{$basedir}/{$file}"
				)
			);

			$response = wp_remote_post(
				'https://webp.api.alouit.fr/',
					array(
						'headers' => array(
							"Content-Type" => @mime_content_type("{$basedir}/{$file}"),
							"Content-Transfer-Encoding" => "base64",
							"Content-Length" => strlen($data),
							"Content-X-Filename" => $file,
							"Content-X-Uri" => "{$basedir}/{$file}",
							"Content-X-Base" => str_replace(array('https://', 'http://'), '', home_url()),
							"Content-X-Client" => "Wordpress " . get_bloginfo('version')
						),
						'body' => $data
				)
			);

			if (is_wp_error($response)) {
				break 2;
			}

			if ((int) $response['response']['code'] === 402) {
				update_option('wpwebpnative_spent', time());
				break 2;
			}

			if (get_option('wpwebpnative_spent', true)) {
				delete_option('wpwebpnative_spent');
			}

			if ((int) $response['response']['code'] !== 200) {
				break 2;
			}

			if (strlen($response['body']) < 32) {
				continue;
			}

			file_put_contents(
				"{$basedir}/{$file}.webp",
				$response['body']
			);
		}

		update_post_meta((int) $id, '_wp_webpnative', true);
	}
});

add_filter('cron_schedules', function ($schedules) {
	$schedules['twice_hour'] = array(
		'interval' => ((60 * 60) / 2),
		'display' => esc_html__('Half Hour')
	);

	return $schedules;
});

if (! wp_next_scheduled('wpwebpnative_cron')) {
	wp_schedule_event(time(), 'twice_hour', 'wpwebpnative_cron');
}

add_action(
	'shutdown',
	function () {
		if (false === @strpos(strtolower($_SERVER['HTTP_ACCEPT']), 'webp'))
			return;

		if (! get_option('wpwebpnative_transparent'))
			return;

		if (is_admin())
			return;

		if (function_exists("wp_doing_ajax") && wp_doing_ajax() || (defined( 'DOING_AJAX' ) && DOING_AJAX))
			return;

		$upload = wp_get_upload_dir();
		$base = str_replace(home_url(), '', $upload['baseurl']);

		$output = preg_replace_callback(
			'/'.str_replace('/', '\/', $base).'\/(?:[a-z0-9\_\-\.\:\/]+).(?:png|jpg|jpeg)(?!\.webp)/i',
			function ($matches) {
				if (file_exists(ABSPATH.$matches[0].'.webp'))
					return $matches[0].'.webp';

				return $matches[0];
			},
			ob_get_contents()
		);

		ob_get_clean();
		print $output;
	},
	0
);

function wpwebpnative() {
	if (
		! function_exists('current_user_can') ||
		! current_user_can('manage_options')
	)
		return;

	global $wpdb;

	$wpwebpnative_transparent = get_option('wpwebpnative_transparent');

	$total = $wpdb->get_col("SELECT COUNT(*) FROM {$wpdb->posts} WHERE `post_type` = 'attachment' AND `post_mime_type` IN ('image/jpeg','image/jpg','image/png')");
	$done = $wpdb->get_col("SELECT COUNT(*) FROM {$wpdb->postmeta} WHERE `meta_key` = '_wp_webpnative'");
?>

<div class="wrap">
		<h1 class='wp-heading-inline'>WP WebPNative</h1>
		<h3 class="nav-tab-wrapper">
			<span style="font-weight: 200;">
				<?php echo sprintf(__('%s/%s media compressed (~%s files)', 'wpwebpnative'), $done[0], $total[0], ($total[0] * 4)); ?>
			</span>
			<a class="nav-tab" style="float: right;" href="https://www.paypal.com/cgi-bin/webscr?cmd=_donations&business=4GJGDY4J4PRXS" target="_blank"><?php esc_html_e('Donate', 'wpwebpnative'); ?></a>
			<a class="nav-tab" style="float: right;" href="https://wordpress.org/support/plugin/wp-webpnative" target="_blank"><?php esc_html_e('Support', 'wpwebpnative'); ?></a>
			<a class="nav-tab" style="float: right;" href="https://wordpress.org/plugins/wp-webpnative/#faq" target="_blank"><?php esc_html_e('FAQ', 'wpwebpnative'); ?></a>
		</h3>


		<div id="poststuff">
			<div id="post-body-content">

				<form name="wpwebpnative" method="post" action="<?php echo $_SERVER['REQUEST_URI']; ?>">
					<table class="form-table">

						<tr>
							<th scope="row"><?php esc_html_e('Configuration', 'wpwebpnative'); ?></th>
							<td>
								<fieldset>
									<label>
										<input name="wpwebpnative_transparent" type="hidden" id="debug" value="0">
										<input name="wpwebpnative_transparent" type="checkbox" id="debug" value="1"<?php if ($wpwebpnative_transparent): ?> checked<?php endif; ?>> <?php esc_html_e('HTML modification', 'wpwebpnative'); ?>
									</label>
								</fieldset>
								<small>
									<?php esc_html_e('(disable if rules are dones by Apache/Nginx)', 'wpwebpnative'); ?>
								</small>
							</td>
						</tr>

					</table>
					<?php submit_button(); ?>
				</form>

			</div>
		</div>
	</div>
<?php
}
