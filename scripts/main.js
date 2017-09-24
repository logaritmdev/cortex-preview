(function($) {

	var url = CortexPreviewSettings.serverUrl || ''
	var key = CortexPreviewSettings.serverKey || ''

	if (url === '') {
		console.error('CortexPreview: Missing server url parameter.')
		return
	}

	if (key === '') {
		console.error('CortexPreview: Missing server key parameter.')
		return
	}

	var encode = function(object) {
		return JSON.stringify(object)
	}

	var decode = function(string) {
		return JSON.parse(string)
	}

	var finish = function(data) {

		update(data)

		var element = $('.cortex-preview-generator[data-id=' + data.options.block + ']')

		var sm = parseInt(element.attr('data-size-sm'))
		var md = parseInt(element.attr('data-size-md'))
		var lg = parseInt(element.attr('data-size-lg'))

		var results = data.results
		var formats = data.formats

		var images = []
		var loaded = 0

		var onImageLoad = function() {

			loaded++

			if (images.length === loaded) {
				$('.cortex-block-list-item[data-id=' + data.options.block + ']').removeClass('cortex-block-list-item-loading')
			}
		}

		for (var i = 0; i < results.length; i++) {

			var result = results[i]
			var format = formats[i]

			var image = null

			switch (format) {

				case sm:
					image = new Image()
					image.src = result
					element.append('<img class="sm" src="' + result + '">')
					break

				case md:
					image = new Image()
					image.src = result
					element.append('<img class="md" src="' + result + '">')
					break

				case lg:
					image = new Image()
					image.src = result
					element.append('<img class="lg" src="' + result + '">')
					break
			}

			if (image) {

				if (image.complete) {
					onImageLoad()
					return
				}

				image.addEventListener('load', onImageLoad)

				images.push(image)
			}
		}
	}

	var update = function(data) {
		return $.ajax({
			url: ajaxurl,
			method: 'post',
			data: {
				action: 'cortex_preview_update',
				data: encode(data)
			}
		})
	}

	var socket = new WebSocket(url);

	socket.addEventListener('message', function(e) {

		var message = decode(e.data)
		if (message == null) {
			return
		}

		switch (message.type) {

			case 'RENDER_COMPLETE':
				finish(message.data)
				break;

			case 'ERROR':
				console.error(message.data.message)
				break
		}

	})

	var CortexPreview = {

		/**
		 * Send a render request to the server.
		 * @since 0.1.0
		 */
		generate: function(id, url, ver, formats) {

			var options = {
				block: id
			}

			var data = encode({
				type: 'CREATE_RENDER',
				data: {
					key: key,
					url: url,
					ver: ver,
					options: options,
					formats: formats
				}
			})

			socket.send(data)

			$('.cortex-block-list-item[data-id=' + id + ']').addClass('cortex-block-list-item-loading')
		}
	}

	window.CortexPreview = CortexPreview

})(jQuery)