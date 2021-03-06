<?php
/**
 * Plugin Name:       Cortex Preview
 * Plugin URI:        http://logaritm.ca/cortex
 * Description:       Generates image preview for blocks.
 * Version:           1.0.2
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
define('CORTEX_PREVIEW_LG_SIZE', 1600);

load_plugin_textdomain('cortex-preview', false, dirname(dirname(plugin_basename(__FILE__))) . '/languages/');

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

	wp_enqueue_script('cortex-preview-main', plugins_url('scripts/main.js', __FILE__));
	wp_enqueue_style('cortex-preview-styles', plugins_url('styles/styles.css', __FILE__));

	wp_localize_script('cortex-preview-main', 'CortexPreviewSettings', array(
		'serverUrl' => get_option('cortex_preview_server_url'),
		'serverKey' => get_option('cortex_preview_server_key'),
		'url' => plugins_url('', __FILE__)
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

		if ($result == '' ||
			$result == null) {
			continue;
		}

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

			default:
				continue;
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

		if (cortex_preview_is_local_dev()) {
			cortex_preview_render_simple_preview($post);
			return;
		}

		$preview_url_sm = get_post_meta($id, '_cortex_preview_url_sm', true);
		$preview_src_sm = get_post_meta($id, '_cortex_preview_src_sm', true);

		$preview_url_md = get_post_meta($id, '_cortex_preview_url_md', true);
		$preview_src_md = get_post_meta($id, '_cortex_preview_src_md', true);

		$preview_url_lg = get_post_meta($id, '_cortex_preview_url_lg', true);
		$preview_src_lg = get_post_meta($id, '_cortex_preview_src_lg', true);

		$sizes = array();

		if (CORTEX_PREVIEW_SM_ENABLED) {
			if (empty($preview_src_sm) || @file_exists($preview_src_sm) === false || @is_readable($preview_src_sm) == false || _cortex_file_empty($preview_src_sm)) {
				$preview_src_sm = null;
				$preview_url_sm = null;
				array_push($sizes, 'sm');
			}
		}

		if (CORTEX_PREVIEW_MD_ENABLED) {
			if (empty($preview_src_md) || @file_exists($preview_src_md) === false || @is_readable($preview_src_md) == false || _cortex_file_empty($preview_src_md)) {
				$preview_src_md = null;
				$preview_url_md = null;
				array_push($sizes, 'md');
			}
		}

		if (CORTEX_PREVIEW_LG_ENABLED) {
			if (empty($preview_src_lg) || @file_exists($preview_src_lg) === false || @is_readable($preview_src_lg) == false || _cortex_file_empty($preview_src_lg)) {
				$preview_src_lg = null;
				$preview_url_lg = null;
				array_push($sizes, 'lg');
			}
		}

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

			.cortex-preview-set[data-id="<?php echo $id ?>"] img {
				display: block;
				height: auto;
				width: 100%;
			}

			.cortex-preview-set[data-id="<?php echo $id ?>"] img.error {
				display: block;
				margin-left: auto;
				margin-right: auto;
				width: 56px;
			}

			.cortex-preview-set[data-id="<?php echo $id ?>"] img.sm,
			.cortex-preview-set[data-id="<?php echo $id ?>"] img.md,
			.cortex-preview-set[data-id="<?php echo $id ?>"] img.lg {
				display: none;
			}

			<?php if (CORTEX_PREVIEW_SM_ENABLED): ?>
				@media (min-width: <?php echo $minSM ?>px) and (max-width:<?php echo $maxSM ?>px) {
					.cortex-preview-set[data-id="<?php echo $id ?>"] img.sm {
						display: block;
					}
				}
			<?php endif ?>

			<?php if (CORTEX_PREVIEW_MD_ENABLED): ?>
				@media (min-width: <?php echo $minMD ?>px) and (max-width:<?php echo $maxMD ?>px) {
					.cortex-preview-set[data-id="<?php echo $id ?>"] img.md {
						display: block;
					}
				}
			<?php endif ?>

			<?php if (CORTEX_PREVIEW_LG_ENABLED): ?>
				@media (min-width: <?php echo $minLG ?>px) and (max-width:<?php echo $maxLG ?>px) {
					.cortex-preview-set[data-id="<?php echo $id ?>"] img.lg {
						display: block;
					}
				}
			<?php endif ?>

		</style>

	<?php

		$url = get_option('cortex_preview_server_url');
		$key = get_option('cortex_preview_server_key');

		if ($url == '' ||
			$url == null) {
			echo __('Cortex preview server url missing.', 'cortex-preview');
			return;
		}

		if ($key == '' ||
			$url == null) {
			echo __('Cortex preview server key missing.', 'cortex-preview');
			return;
		}

		$ver = cortex_get_block_ver($post);
		$url = cortex_get_block_url($post);

		if (count($sizes) > 0) {

			_cortex_render_script($id, $url, $ver, $sizes);

		} else {

			_cortex_render_images($id, $url, $ver);

		}

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

	add_options_page(
		'Cortex Preview',
		'Cortex Preview',
		'manage_options',
		'cortex-preview-options',
		'cortex_preview_options_page',
		'dashicons-welcome-view-site',
		'80.025'
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

		<h2>Reset previews</h2>

		<?php

			if (isset($_POST['cortex_preview_reset'])) {

				global $wpdb;

				$wpdb->query("
					DELETE FROM $wpdb->postmeta
					WHERE
						meta_key = '_cortex_preview_url_sm' OR
						meta_key = '_cortex_preview_url_md' OR
						meta_key = '_cortex_preview_url_lg' OR
						meta_key = '_cortex_preview_src_sm' OR
						meta_key = '_cortex_preview_src_md' OR
						meta_key = '_cortex_preview_src_lg'
				");

				?>

					<div id="setting-error-settings_updated" class="updated settings-error notice is-dismissible">
						<p><strong>Previews have been reset.</strong></p>
						<button type="button" class="notice-dismiss"><span class="screen-reader-text">Dismiss this notice.</span></button>
					</div>

				<?php
			}

		?>

		<p>Clicking reset will erase all previews and force a regeneration.</p>

		<form method="post" action="<?php echo $_SERVER['REQUEST_URI'] ?>">

			<input id="cortex-reset" type="submit" value="Reset" class="button button-primary" onClick="return confirm('This will erase all previews. Do you want to continue ?')">
			<input type="hidden" name="cortex_preview_reset">

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
	echo __('Cortex Previews uses an external server to create block rendering. You need to provide a server URL and a key.', 'cortex-preview');
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
		class="cortex-preview-set"
		data-id="<?php echo $id ?>"
		data-url="<?php echo $url ?>"
		data-ver="<?php echo $ver ?>"
		<?php if (CORTEX_PREVIEW_SM_ENABLED): ?>data-size-sm="<?php echo CORTEX_PREVIEW_SM_SIZE ?>"<?php endif ?>
		<?php if (CORTEX_PREVIEW_MD_ENABLED): ?>data-size-md="<?php echo CORTEX_PREVIEW_MD_SIZE ?>"<?php endif ?>
		<?php if (CORTEX_PREVIEW_LG_ENABLED): ?>data-size-lg="<?php echo CORTEX_PREVIEW_LG_SIZE ?>"<?php endif ?>>

		<script type="text/javascript">

			(function($) {

				var generate = function() {

					var element = $('.cortex-preview-set[data-id="<?php echo $id ?>"]')

					var id = element.attr('data-id')
					var url = element.attr('data-url')
					var ver = element.attr('data-ver')

					id = parseInt(id)

					var sm = parseInt(element.attr('data-size-sm'))
					var md = parseInt(element.attr('data-size-md'))
					var lg = parseInt(element.attr('data-size-lg'))

					var formats = []

					if (element.attr('data-size-sm') != null) formats.push(sm)
					if (element.attr('data-size-md') != null) formats.push(md)
					if (element.attr('data-size-lg') != null) formats.push(lg)

					CortexPreview.generate(id, url, ver, formats)
				}

				var manage = function() {

					var element = $('.cortex-preview-set[data-id="<?php echo $id ?>"]')

					var id = element.attr('data-id')
					var url = element.attr('data-url')
					var ver = element.attr('data-ver')

					id = parseInt(id)

					var sm = parseInt(element.attr('data-size-sm'))
					var md = parseInt(element.attr('data-size-md'))
					var lg = parseInt(element.attr('data-size-lg'))

					var formats = []

					if (element.attr('data-size-sm') != null) formats.push(sm)
					if (element.attr('data-size-md') != null) formats.push(md)
					if (element.attr('data-size-lg') != null) formats.push(lg)

					CortexPreview.manage(id, url, ver, formats, element)
				}

				if (document.readyState === 'complete') {
					generate()
					manage()
					return
				}

				$(generate)
				$(manage)

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
function _cortex_render_images($id, $url, $ver) {

	$preview_url_sm = get_post_meta($id, '_cortex_preview_url_sm', true);
	$preview_url_md = get_post_meta($id, '_cortex_preview_url_md', true);
	$preview_url_lg = get_post_meta($id, '_cortex_preview_url_lg', true);

	?>

	<div
		class="cortex-preview-set"
		data-id="<?php echo $id ?>"
		data-url="<?php echo $url ?>"
		data-ver="<?php echo $ver ?>"
		<?php if (CORTEX_PREVIEW_SM_ENABLED): ?>data-size-sm="<?php echo CORTEX_PREVIEW_SM_SIZE ?>"<?php endif ?>
		<?php if (CORTEX_PREVIEW_MD_ENABLED): ?>data-size-md="<?php echo CORTEX_PREVIEW_MD_SIZE ?>"<?php endif ?>
		<?php if (CORTEX_PREVIEW_LG_ENABLED): ?>data-size-lg="<?php echo CORTEX_PREVIEW_LG_SIZE ?>"<?php endif ?>>

		<script type="text/javascript">

			(function($) {

				var manage = function() {

					var element = $('.cortex-preview-set[data-id="<?php echo $id ?>"]')

					var id = element.attr('data-id')
					var url = element.attr('data-url')
					var ver = element.attr('data-ver')

					id = parseInt(id)

					var sm = parseInt(element.attr('data-size-sm'))
					var md = parseInt(element.attr('data-size-md'))
					var lg = parseInt(element.attr('data-size-lg'))

					var formats = []

					if (element.attr('data-size-sm') != null) formats.push(sm)
					if (element.attr('data-size-md') != null) formats.push(md)
					if (element.attr('data-size-lg') != null) formats.push(lg)

					CortexPreview.manage(id, url, ver, formats, element)
				}

				if (document.readyState === 'complete') {
					manage()
					return
				}

				$(manage)

			})(jQuery)

		</script>

		<?php if (CORTEX_PREVIEW_SM_ENABLED): ?><img class="sm" src="<?php echo $preview_url_sm ?>"><?php endif ?>
		<?php if (CORTEX_PREVIEW_MD_ENABLED): ?><img class="md" src="<?php echo $preview_url_md ?>"><?php endif ?>
		<?php if (CORTEX_PREVIEW_LG_ENABLED): ?><img class="lg" src="<?php echo $preview_url_lg ?>"><?php endif ?>

	</div>

	<?php
}


/**
 * Tries to detect whether this
 * @function cortex_preview_options_page
 * @since 0.1.0
 */
function cortex_preview_is_local_dev() {

	$server_name = isset($_SERVER['SERVER_NAME']) ? $_SERVER['SERVER_NAME'] : null;
	$server_addr = isset($_SERVER['SERVER_ADDR']) ? $_SERVER['SERVER_ADDR'] : null;
	$remote_addr = isset($_SERVER['REMOTE_ADDR']) ? $_SERVER['REMOTE_ADDR'] : null;

	if ($server_addr && $remote_addr &&
		$server_addr == $remote_addr) {
		return true;
	}

	if ($server_name == 'localhost' ||
		substr($server_name, strrpos($server_name, '.') + 1) == 'test' ||
		substr($server_name, 0, 3) == '10.' ||
        substr($server_name, 0, 7) == '192.168') {
		return true;
	}

	$http_host = isset($_SERVER['HTTP_HOST']) ? $_SERVER['HTTP_HOST'] : null;

	if ($http_host == 'localhost' ||
		substr($http_host, strrpos($http_host, '.') + 1) == 'test' ||
		substr($http_host, 0, 3) == '10.' ||
        substr($http_host, 0, 7) == '192.168') {
		return true;
	}

	return false;
}

/**
 * Renders a text prewview.
 * @function cortex_preview_render_simple_preview
 * @since 0.1.0
 */
function cortex_preview_render_html_preview($post) {

}

/**
 * Renders a text prewview.
 * @function cortex_preview_render_simple_preview
 * @since 0.1.0
 */
function cortex_preview_render_simple_preview($post) {

	$objects = get_field_objects($post->ID);

	if ($objects == false) {
		echo '<div class="cortex-preview">';
		echo '<i>' . __('This block has not been saved yet.', 'cortex-preview') . '</i>';
		echo '</div>';
		return;
	}

	echo '<table class="cortex-preview-simple-table">';

	foreach ($objects as $field) {

		echo '<tr>';

		$label = $field['label'];
		$value = $field['value'];

		echo '<td class="cortex-preview-simple-label"><strong>' . $label . '</strong></td>';
		echo '<td class="cortex-preview-simple-value">';

		cortex_preview_render_simple_field_preview($field);

		echo '</td>';
		echo '</tr>';
	}

	echo '</table>';
}

/**
 * Renders a field prewview.
 * @function cortex_preview_render_text_preview
 * @since 0.1.0
 */
function cortex_preview_render_simple_field_preview($field) {

	$preview = apply_filters('cortex_preview_render_' . $field['type'], $field);

	if ($preview === null || is_string($preview) == false) {
		$preview = $field['value'];
	}

	_cortex_print($preview, $field);
}

/**
 * Previews flexible_content field.
 * @since 0.1.0
 */
add_filter('cortex_preview_render_flexible_content', function($field) {
	return _cortex_buff(function() use($field) {
		foreach (_cortex_process_flexible_content($field) as $sub_field) {
			echo '<div class="cortex-preview-row-label">' . $sub_field['label'] . '</div>';
			echo '<div class="cortex-preview-row-value">';
			cortex_preview_render_simple_field_preview($sub_field);
			echo '</div>';
		}
	});
});

/**
 * Previews flexible_content field.
 * @since 0.1.0
 */
add_filter('cortex_preview_render_repeater', function($field) {
	return _cortex_buff(function() use($field) {
		foreach (_cortex_process_repeater($field) as $sub_field) {
			echo '<div class="cortex-preview-row-label">' . $sub_field['label'] . '</div>';
			echo '<div class="cortex-preview-row-value">';
			cortex_preview_render_simple_field_preview($sub_field);
			echo '</div>';
		}
	});
});

/**
 * Previews gallery field.
 * @since 0.1.0
 */
add_filter('cortex_preview_render_gallery', function($field) {
	return _cortex_buff(function() use($field) {
		echo '<div class="cortex-preview-row-value">';
		foreach ($field['value'] as $image) {
			echo sprintf('<img src="%s">', $image['url']);
		}
		echo '</div>';
	});
});

/**
 * Previews text field.
 * @since 0.1.0
 */
add_filter('cortex_preview_render_text', function($field) {
	return $field['value'] ? $field['value'] : $field['default_value'];
});

/**
 * Previews wysiwyg field.
 * @since 0.1.0
 */
add_filter('cortex_preview_render_wysiwyg', function($field) {
	return $field['value'] ? $field['value'] : $field['default_value'];
});

/**
 * Previews image field.
 * @since 0.1.0
 */
add_filter('cortex_preview_render_image', function($field) {
	return $field['value'] ? sprintf('<img src="%s">', $field['value']['url']) : null;
});

/**
 * Previews radio field.
 * @since 0.1.0
 */
add_filter('cortex_preview_render_radio', function($field) {
	return isset($field['choices'][$field['value']]) ? $field['choices'][$field['value']] : null;
});

/**
 * Previews checkbox field.
 * @since 0.1.0
 */
add_filter('cortex_preview_render_checkbox', function($field) {
	return isset($field['choices'][$field['value']]) ? $field['choices'][$field['value']] : null;
});

/**
 * Previews select field.
 * @since 0.1.0
 */
add_filter('cortex_preview_render_select', function($field) {
	return isset($field['choices'][$field['value']]) ? $field['choices'][$field['value']] : null;
});

/**
 * Previews relationship field.
 * @since 0.1.0
 */
add_filter('cortex_preview_render_relationship', function($field) {
	return _cortex_buff(function() use($field) {
		foreach ($field['value'] as $value) {
			echo '<div class="cortex-preview-row-value">';
			echo $value->post_title;
			echo '</div>';
		}
	});
});

/**
 * @function _cortex_process_flexible_content
 * @since 0.1.0
 * @hidden
 */
function _cortex_process_flexible_content($field) {

	$fields = array();
	$layouts = array();

	foreach ($field['layouts'] as $layout) {
		$layouts[$layout['name']] = $layout;
	}

	foreach ($field['value'] as $value) {

		$layout = $layouts[$value['acf_fc_layout']];

		if ($layout == null) {
			continue;
		}

		foreach ($value as $key => $val) {

			if ($key == 'acf_fc_layout') {
				continue;
			}

			foreach ($layout['sub_fields'] as $sub_field) {
				if ($sub_field['name'] == $key) {
					$data = $sub_field;
					$data['value'] = $val;
					$fields[] = $data;
				}
			}
		}
	}

	return $fields;
}

/**
 * @function _cortex_process_repeater
 * @since 0.1.0
 * @hidden
 */
function _cortex_process_repeater($field) {

	$fields = array();

	foreach ($field['sub_fields'] as $i => $sub_field) {
		$field = $sub_field;
		$field['value'] = $field['value'][$i];
		$fields[] = $field;
	}

	return $fields;
}

/**
 * @function _cortex_print
 * @since 0.1.0
 * @hidden
 */
function _cortex_print($value, $field) {

	if ($value == null) {
		echo __('Empty', 'cortex-preview');
		return;
	}

	if (is_array($value)) {
		echo $field['type'];
		echo '<pre>';
		print_r($value);
		echo '</pre>';
 		return;
	}

	echo $value;
}

/**
 * @function _cortex_buff
 * @since 0.1.0
 * @hidden
 */
function _cortex_buff($function) {
	ob_start();
	$function();
	$contents = ob_get_contents();
	ob_end_clean();
	return $contents;
}

/**
 * @function _cortex_file_empty
 * @since 0.1.0
 * @hidden
 */
function _cortex_file_empty($file) {
	clearstatcache();
	return filesize($file) == 0;
}