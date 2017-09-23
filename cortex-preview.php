<?php
/**
 * Plugin Name:       Cortex Preview
 * Plugin URI:        http://logaritm.ca/cortex
 * Description:       Generates image preview for blocks.
 * Version:           1.0.0
 * Author:            Jean-Philippe Dery
 * Author URI:        http://logaritm.ca
 * Text Domain:       cortex
 * Domain Path:       /languages
 */

define('CORTEX_PREVIEW_SM_ENABLED', false);
define('CORTEX_PREVIEW_MD_ENABLED', false);
define('CORTEX_PREVIEW_LG_ENABLED', true);

define('CORTEX_PREVIEW_SM_SIZE', 375);
define('CORTEX_PREVIEW_MD_SIZE', 1024);
define('CORTEX_PREVIEW_LG_SIZE', 1280);

/**
 * Invalidates the preview upon saving.
 * @action cortex/save_block
 * @since 1.0.0
 */
add_action('cortex/save_block', function($document, $id) {

	if (CORTEX_PREVIEW_SM_ENABLED) {
		update_post_meta($id, '_cortex_preview_url_sm', '');
		update_post_meta($id, '_cortex_preview_src_sm', '');
	}

	if (CORTEX_PREVIEW_MD_ENABLED) {
		update_post_meta($id, '_cortex_preview_url_md', '');
		update_post_meta($id, '_cortex_preview_src_md', '');
	}

	if (CORTEX_PREVIEW_LG_ENABLED) {
		update_post_meta($id, '_cortex_preview_url_lg', '');
		update_post_meta($id, '_cortex_preview_src_lg', '');
	}

}, 10, 2);

add_action('admin_enqueue_scripts', function() {

	wp_enqueue_script('cortex-preview-main', plugins_url('scripts/main.js', __FILE__ ));

	wp_localize_script('cortex-preview-main', 'CortexPreviewSettings', array(
		'serverUrl' => get_option('cortex_preview_server_url'),
		'serverKey' => get_option('cortex_preview_server_key'),
	));
});

/**
 * Saves the block preview.
 * @action wp_ajax_cortex_preview_save
 * @since 1.0.0
 */
add_action('wp_ajax_cortex_preview_update', function() {

	$data = $_POST['data'];
	$data = json_decode(stripslashes($data));

	$formats = $data->formats;
	$results = $data->results;

	for ($i = 0; $i < count($formats); $i++) {

		$format = $formats[$i];
		$result = $results[$i];

		$size = '';

		switch ($format) {

			case CORTEX_PREVIEW_SM_SIZE:
				$size = 'sm';
				break;

			case CORTEX_PREVIEW_MD_SIZE:
				$size = 'md';
				break;

			case CORTEX_PREVIEW_LG_SIZE:
				$size = 'lg';
				break;
		}

	 	$preview_src = WP_CONTENT_DIR . '/cache/cortex-previews/' . basename($result);
	 	$preview_url = WP_CONTENT_URL . '/cache/cortex-previews/' . basename($result);

		update_post_meta($data->options->block, '_cortex_preview_url_' . $size, $preview_url);
		update_post_meta($data->options->block, '_cortex_preview_src_' . $size, $preview_src);

		$preview_url_sm = get_post_meta($data->options->block, '_cortex_preview_url_' . $size, true);
		$preview_src_sm = get_post_meta($data->options->block, '_cortex_preview_src_' . $size, true);

		@mkdir(dirname($preview_src), 0777, true);

		file_put_contents($preview_src, file_get_contents($result));
	}

	clearstatcache();

	echo '{"result":"success"}';
	exit;
});

/**
 * Creates the generate preview function.
 * @filter get_twig
 * @since 1.0.0
 */
add_filter('get_twig', function($twig) {

	$generate_preview = function($post) {

		$id = $post->ID;
		$document = $post->post_parent;

		$preview_url_sm = get_post_meta($id, '_cortex_preview_url_sm', true);
		$preview_src_sm = get_post_meta($id, '_cortex_preview_src_sm', true);

		$preview_url_md = get_post_meta($id, '_cortex_preview_url_md', true);
		$preview_src_md = get_post_meta($id, '_cortex_preview_src_md', true);

		$preview_url_lg = get_post_meta($id, '_cortex_preview_url_lg', true);
		$preview_src_lg = get_post_meta($id, '_cortex_preview_src_lg', true);

		$sizes = [];

		if (CORTEX_PREVIEW_SM_ENABLED) {
			if ($preview_src_sm == '' || file_exists($preview_src_sm) === false || is_readable($preview_src_sm) == false || _cortex_file_empty($preview_src_sm)) {
				$preview_src_sm = null;
				$preview_url_sm = null;
				array_push($sizes, 'sm');
			}
		}

		if (CORTEX_PREVIEW_MD_ENABLED) {
			if ($preview_src_md == '' || file_exists($preview_src_md) === false || is_readable($preview_src_md) == false || _cortex_file_empty($preview_src_md)) {
				$preview_src_md = null;
				$preview_url_md = null;
				array_push($sizes, 'md');
			}
		}

		if (CORTEX_PREVIEW_LG_ENABLED) {
			if ($preview_src_lg == '' || file_exists($preview_src_lg) === false || is_readable($preview_src_lg) == false || _cortex_file_empty($preview_src_lg)) {
				$preview_src_lg = null;
				$preview_url_lg = null;
				array_push($sizes, 'lg');
			}
		}

		?>

		<div id="cortex-preview-set-<?php echo $id ?>" class="cortex-preview-set">

			<?php

				$start = 0;

				if (CORTEX_PREVIEW_SM_ENABLED) {
					$minSM = $start;
					$maxSM = CORTEX_PREVIEW_MD_SIZE - 1;
					$start = $maxSM + 1;
				}

				if (CORTEX_PREVIEW_MD_ENABLED) {
					$minMD = $start;
					$maxMD = CORTEX_PREVIEW_LG_SIZE - 1;
					$start = $maxMD + 1;
				}

				if (CORTEX_PREVIEW_LG_ENABLED) {
					$minLG = $start;
					$maxLG = 10000;
				}

			?>

			<style type="text/css">

				#cortex-preview-set-<?php echo $id ?> img {
					height: auto;
					width: 100%;
				}

				#cortex-preview-set-<?php echo $id ?> img.sm,
				#cortex-preview-set-<?php echo $id ?> img.md,
				#cortex-preview-set-<?php echo $id ?> img.lg {
					display: none;
				}

				<?php if (CORTEX_PREVIEW_SM_ENABLED): ?>
					@media (min-width: <?php echo $minSM ?>px) and (max-width:<?php echo $maxSM ?>px) {
						#cortex-preview-set-<?php echo $id ?> img.sm {
							display: block;
						}
					}
				<?php endif ?>

				<?php if (CORTEX_PREVIEW_MD_ENABLED): ?>
					@media (min-width: <?php echo $minMD ?>px) and (max-width:<?php echo $maxMD ?>px) {
						#cortex-preview-set-<?php echo $id ?> img.md {
							display: block;
						}
					}
				<?php endif ?>

				<?php if (CORTEX_PREVIEW_LG_ENABLED): ?>
					@media (min-width: <?php echo $minLG ?>px) and (max-width:<?php echo $maxLG ?>px) {
						#cortex-preview-set-<?php echo $id ?> img.lg {
							display: block;
						}
					}
				<?php endif ?>

			</style>

			<?php

				if (count($sizes) > 0) {

					$url = get_option('cortex_preview_server_url');
					$key = get_option('cortex_preview_server_key');

					if ($url == '' ||
						$url == null) {
						echo 'Cortex preview server url missing.';
						return;
					}

					if ($key == '' ||
						$url == null) {
						echo 'Cortex preview server key missing.';
						return;
					}

					$ver = cortex_get_block_ver($post);
					$url = cortex_get_block_url($post);

					_cortex_render_script($id, $url, $ver, $sizes);

				} else {

					_cortex_render_images($id);

				}

			?>

		</div>

		<?php
	};

	$twig->addFunction(new \Twig_SimpleFunction('generate_preview', $generate_preview));

	return $twig;
});

/**
 * Adds an admin menu to display the settings.
 * @action admin_menu
 * @since 0.1.0
 */
add_action('admin_menu', function() {

	add_menu_page(
		'Cortex Preview',
		'Cortex Preview',
		'manage_options',
		'cortex-preview-options',
		'cortex_preview_options_page',
		'',
		100
	);

});

/**
 * Adds teh setting menus
 * @action admin_init
 * @since 0.1.0
 */
add_action('admin_init', function() {

	add_settings_section('cortex_preview_server_section', 'Server Options', 'cortex_preview_render_server_section', 'cortex-preview-options');

	add_settings_field('cortex_preview_server_url', 'Server URL', 'cortex_render_server_url_field', 'cortex-preview-options', 'cortex_preview_server_section');
	add_settings_field('cortex_preview_server_api', 'Server Key', 'cortex_render_server_key_field', 'cortex-preview-options', 'cortex_preview_server_section');

	register_setting('cortex_preview_server_section', 'cortex_preview_server_url');
	register_setting('cortex_preview_server_section', 'cortex_preview_server_key');

});

/**
 * Options page.
 * @function cortex_preview_options_page
 * @since 0.1.0
 */
function cortex_preview_options_page() {
	?>
	<div class='wrap'>

		<h1>Cortex Preview Options</h1>

		<form method="post" action="options.php">

			<?php

				settings_fields('cortex_preview_server_section');
				do_settings_sections('cortex-preview-options');
				submit_button();

			?>

		</form>

	</div>

	<?php
}

/**
 * Renders the server setting section.
 * @function cortex_preview_render_server_section
 * @since 0.1.0
 */
function cortex_preview_render_server_section() {
	echo 'Rendering server information.';
}

/**
 * Renders the server url field.
 * @function cortex_render_server_url_field
 * @since 0.1.0
 */
function cortex_render_server_url_field() {
	?>
		<input type="text" name="cortex_preview_server_url" id="cortex_preview_server_url" value='<?php echo get_option('cortex_preview_server_url') ?>' />
	<?php
}

/**
 * Renders the server key field.
 * @function cortex_render_server_key_field
 * @since 0.1.0
 */
function cortex_render_server_key_field() {
	?>
		<input type="text" name="cortex_preview_server_key" id="cortex_preview_server_key" value='<?php echo get_option('cortex_preview_server_key') ?>' />
	<?php
}

/**
 * Returns the block page url.
 * @function cortex_get_block_url
 * @since 0.1.0
 */
function cortex_get_block_url($post) {
	return admin_url('admin-ajax.php') . '?action=render_block&document=' . $post->post_parent . '&id=' . $post->id;
}

/**
 * Returns the block date.
 * @function cortex_get_block_ver
 * @since 0.1.0
 */
function cortex_get_block_ver($post) {
	return get_the_modified_date('U', $post);
}

//------------------------------------------------------------------------------
// Private API
//------------------------------------------------------------------------------

/**
 * Renders the javascript code that generates the previews.
 * @function _cortex_render_script
 * @since 0.1.0
 */
function _cortex_render_script($id, $url, $ver, $sizes) {
	?>

	<div
		class="cortex-preview-generator"
		data-id="<?php echo $id ?>"
		data-url="<?php echo $url ?>"
		data-ver="<?php echo $ver ?>"
		<?php if (CORTEX_PREVIEW_SM_ENABLED): ?>data-size-sm="<?php echo CORTEX_PREVIEW_SM_SIZE ?>"<?php endif ?>
		<?php if (CORTEX_PREVIEW_MD_ENABLED): ?>data-size-md="<?php echo CORTEX_PREVIEW_MD_SIZE ?>"<?php endif ?>
		<?php if (CORTEX_PREVIEW_LG_ENABLED): ?>data-size-lg="<?php echo CORTEX_PREVIEW_LG_SIZE ?>"<?php endif ?>
		<?php if (CORTEX_PREVIEW_SM_ENABLED): ?>data-size-sm-invalid="<?php echo in_array('sm', $sizes) ? 'true' : 'false' ?>"<?php endif ?>
		<?php if (CORTEX_PREVIEW_MD_ENABLED): ?>data-size-md-invalid="<?php echo in_array('md', $sizes) ? 'true' : 'false' ?>"<?php endif ?>
		<?php if (CORTEX_PREVIEW_LG_ENABLED): ?>data-size-lg-invalid="<?php echo in_array('lg', $sizes) ? 'true' : 'false' ?>"<?php endif ?>>

		<script type="text/javascript">

			(function($) {

				var generate = function() {

					var element = $('.cortex-preview-generator[data-id=<?php echo $id ?>]')

					var id = element.attr('data-id')
					var url = element.attr('data-url')
					var ver = element.attr('data-ver')

					id = parseInt(id)

					var sm = parseInt(element.attr('data-size-sm'))
					var md = parseInt(element.attr('data-size-md'))
					var lg = parseInt(element.attr('data-size-lg'))

					var formats = []

					if (element.attr('data-size-sm-invalid') === 'true') formats.push(sm)
					if (element.attr('data-size-md-invalid') === 'true') formats.push(md)
					if (element.attr('data-size-lg-invalid') === 'true') formats.push(lg)

					CortexPreview.generate(id, url, ver, formats)
				}

				if (document.readyState === 'complete') {
					generate()
					return
				}

				$(generate)

			})(jQuery)

		</script>

	</div>

	<?php
}

/**
 * Renders the javascript code that generates the previews.
 * @function _cortex_render_script
 * @since 0.1.0
 */
function _cortex_render_images($id) {

	$preview_url_sm = get_post_meta($id, '_cortex_preview_url_sm', true);
	$preview_url_md = get_post_meta($id, '_cortex_preview_url_md', true);
	$preview_url_lg = get_post_meta($id, '_cortex_preview_url_lg', true);

	?>

	<?php if (CORTEX_PREVIEW_SM_ENABLED): ?><img class="sm" src="<?php echo $preview_url_sm ?>"><?php endif ?>
	<?php if (CORTEX_PREVIEW_MD_ENABLED): ?><img class="md" src="<?php echo $preview_url_md ?>"><?php endif ?>
	<?php if (CORTEX_PREVIEW_LG_ENABLED): ?><img class="lg" src="<?php echo $preview_url_lg ?>"><?php endif ?>

	<?php
}

function _cortex_file_empty($file) {
	clearstatcache();
	return filesize($file) == 0;
}