/**
 * Deal with various features of the fancy "Forms UI" type implementaion
 */
(function($) {
	$(function() {
		// Set up multiple-list handling
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
})(jQuery);
