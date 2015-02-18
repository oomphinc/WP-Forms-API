/**
 * Deal with various features of the fancy "Forms UI" type implementaion
 */
(function($) {
	var media = wp.media;

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
						$html = $($tmpl.text().replace(/%INDEX%/g, count));

					initializeAttachments($html);

					$list.append($html);
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
	var WPFormImageField = media.View.extend({
		template: media.template('wp-form-attachment-field'),
		events: {
			'change .wp-form-attachment-id': 'update',
			'click .attachment-delete': 'removeAttachment',
			'click .attachment-container': 'selectAttachment'
		},

		selectAttachment: function() {
			var view = this,
				frameOpts = {
					frame: 'select',
					title: this.input_type == 'image' ? "Select Image" : "Select Attachment"
				};

			if(this.input_type == 'image') {
				frameOpts.library = { type: 'image' };
			}

			media.frame = media(frameOpts).open();

			media.frame.on('select', function(el) {
				var image = this.get('library').get('selection').single();

				view.model.set(image.attributes);
			});
		},

		removeAttachment: function() {
			this.model.clear();
		},

		initialize: function() {
			if(!this.model) {
				this.model = new Backbone.Model();
			}

			this.model.on('change', this.render, this);
		},

		prepare: function() {
			var data = this.model.toJSON();

			data.input_name = this.input_name;
			data.input_type = this.input_type;

			return data;
		},

		update: function() {
			var view = this,
				$field = this.$el.find('.wp-form-attachment-id'),
				attachmentId = $field.val(),
				attachment = media.model.Attachment.get(attachmentId).clone();

			view.model.clear({ silent: true });
			view.model.set({ id: attachmentId });

			$field.addClass('ui-dirty');

			attachment.fetch()
				.done(function() {
					$field.removeClass('ui-dirty');
					view.model.set(this.attributes);
				})
				.fail(function() {
					$field.addClass('ui-dirty');
				});
		}
	});

	var initializeAttachments = function(context) {
		$(context).find('.select-attachment-field').each(function() {
			var view = new WPFormImageField({
				model: media.model.Attachment.get(this.value).clone()
			});

			view.model.fetch();

			// Don't save input name as part of the model as it should be invariant
			view.input_name = this.name;
			view.input_type = $(this).data('attachment-type');

			view.render();

			view.$el.attr('class', $(this).attr('class'));
			view.$el.data('view', view);

			$(this).replaceWith(view.$el);
		});
	}

	$(function() {
		initializeAttachments('body');
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
					if(ui.item) {
						$input.val(ui.item.model.get('id'));
					}
					else {
						$input.val('');
					}
				},
				select: function(ev, ui) {
					$input.val(ui.item.model.get('id'));
				},
				minLength: 0,
			});
		});
	});
})(jQuery);
