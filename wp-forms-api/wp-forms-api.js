/**
 * Deal with various features of the fancy "Forms UI" type implementaion
 */
(function($) {
	// Multiple-list field
	$(function() {
		$('.wp-form .wp-form-multiple').each(function() {
			var $container = $(this),
			  $list = $('#' + $container.data('list')),
			  $tmpl = $('#' + $container.data('template'));

			function reindex() {
				$list.find('> li').each(function(i) {
					var $item = $(this);

					$item.html($item.html()
						// Replace patterns like -2- or [4] with -$index- and [$index] in attributes
						// Not exactly super safe, but easy
						.replace(/(="[ \w_\[\]-]+[\[-])\d+([\]-][ \w_\[\]-]+")/g, '$1' + i + '$2'));
				});
			}

			$container
				// Add a new multiple item on click
				.on('click', '.add-multiple-item', function() {
					var $t = $(this),
						count = $list.children('li').length,
						html = $tmpl.text().replace(/%INDEX%/g, count);

					$list.append(html);
				})

				// Remove an item item on click
				.on('click', '.remove-multiple-item', function() {
					var $t = $(this),
						$item = $t.parents('li');

					$item.remove();

					reindex();
				});
		});
	});

	// Image field
	$(function() {
		$('body').on('change', '.select-image-field input', function() {
			var $field = $(this).closest('.select-image-field');
			var attachment = media.model.Attachment.get(this.value);

			$field.find('input').removeClass('ui-dirty');

			attachment.fetch()
				.done(function() {
					$field.find('img').attr('src', this.get('sizes').thumbnail.url);
				})
				.fail(function() {
					$field.find('input').addClass('ui-dirty');
				});
		});

		$('body').on('click', '.select-image-field .image-container', function(ev) {
			var $field = $(this).closest('.select-image-field');

			ev.preventDefault();

			wp.media.frame = wp.media({
				frame: 'select',
				title: "Select Image"
			});

			wp.media.frame.on('select', function(el) {
				var image = this.get('library').get('selection').single();

				$field.find('input').removeClass('ui-dirty');

				$field.find('input').val(image.id);
				$field.find('img').attr('src', image.get('sizes').thumbnail.url);
			});
		});
	});

	// "Select Post" field
	$(function() {
		$('.wp-form-post-select').each(function() {
			var items = new Backbone.Collection();
			var $input = $(this);
			var $field = $('<input type="text" />');

			$(this).before($field);

			if($input.data('title')) {
				$field.val($input.data('title'));
			}

			$field.autocomplete({
				source: function(request, response) {
					var attrs = { term: request.term };

					if($input.data('post-type')) {
						attrs['post_type'] = $input.data('post-type').split(' ');
					}

					wp.ajax.post('wp_form_search_posts', attrs)
						.done(function(data) {
							response(_.map(data, function(v) {
								v.id = v.ID;

								var itemModel = new Backbone.Model(v);

								items.remove(v.id);
								items.add(itemModel);

								return {
									label: v.post_title,
									value: v.post_title,
									model: itemModel
								}
							}));
						})
						.fail(function(data) {
							response([]);
						});
				},
				change: function(ev, ui) {
					$input.val(ui.item.model.get('id'));
				},
				select: function(ev, ui) {
					$input.val(ui.item.model.get('id'));
				},
				minLength: 0,
			});
		});
	});
})(jQuery);
