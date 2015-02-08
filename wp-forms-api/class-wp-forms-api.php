<?php
/***********************************************
 * WordPress Forms API
 *
 * Featuring: Ideas mostly stolen from Drupal's Forms UI.
 *
 * This class is simply a container of static methods which provide the ability
 * to render and process arbitrary forms without having to write any markup.
 *
 * This lets you specify forms as data structures (which can be stored and manipulated)
 * and generates markup with plenty of classes for styling on.
 **********************************************/
class WP_Forms_API {
	/**
	 * The defaults for all elements
	 */
	static $element_defaults = array(
		'#id' => '',
		'#type' => null,
		'#key' => '',
		'#slug' => '',
		'#name' => '',
		'#form' => null,
		'#placeholder' => null,
		'#default' => null,
		'#size' => null,
		'#options' => array(),
		'#container' => 'div',
		'#container_classes' => array(),
		'#attrs' => array(),
		'#class' => array(),
		'#label' => null,
		'#description' => null,
		'#required' => false,
		'#index' => null,
		'#multiple' => null,
		'#content' => null,
		'#add_link' => 'Add item',
		'#remove_link' => 'Remove item',
		'#tag' => '',
		'#value' => null,
	);

	/**
	 * Initialize this module
	 *
	 * @action init
	 */
	static function init() {
		wp_register_script( 'wp-forms', plugins_url( 'wp-forms-api.js', 'wp-forms-api' ), array( 'jquery-ui-autocomplete' ), 1, true );

		add_action( 'admin_enqueue_scripts', array( __CLASS__, 'admin_enqueue' ) );
		add_action( 'wp_ajax_wp_form_search_posts', array( __CLASS__, 'search_posts' ) );
	}

	/**
	 * Search for a post by name.
	 *
	 * @action wp_ajax_wp_form_search_posts
	 */
	static function search_posts() {
		global $wpdb;

		if( !isset( $_POST['term'] ) ) {
			return;
		}

		$query_args = array(
			's' => $_POST['term'],
			'post_status' => 'any'
		);

		if( isset( $_POST['post_type'] ) ) {
			$query_args['post_type'] = (array) $_POST['post_type'];
		}

		$query = new WP_Query( $query_args );

		wp_send_json_success( $query->posts );
	}

	/**
	 * Enqueue admin assets
	 *
	 * @action admin_enqueue_scripts
	 */
	static function admin_enqueue() {
		wp_enqueue_style( 'wp-forms', plugins_url( 'wp-forms-api.css', 'wp-forms-api' ) );
	}

	/**
	 * Return HTML with tag $tagname and keyed attrs $attrs.
	 *
	 * If $content is not null, contain with $tagname and
	 * render close tag.
	 *
	 * If $content === false, just emit an open tag.
	 */
	static function make_tag( $tagname, $attrs, $content = null ) {
		if( empty( $tagname ) ) {
			return;
		}

		$html = '<' . $tagname;

		foreach( $attrs as $attr => $val ) {
			$html .= ' ' . $attr . '="' . esc_attr( $val ) . '"';
		}

		// Self-closing tag:
		if( !isset( $content ) ) {
			$html .= ' />';
		}
		else {
			$html .= '>';

			if( $content !== false ) {
				$html .= $content . '</' . $tagname . '>';
			}
		}

		return $html;
	}

	/**
	 * Get elements from a form.
	 *
	 * @param array $form
	 *
	 * Filters out elements with keys starting with '#', and sets default
	 * properties for each element so they can be safely assumed to be present.
	 */
	static function get_elements( $form ) {
		$elements = array();

		foreach( $form as $key => &$element ) {
			if( $key[0] == '#' ) {
				continue;
			}

			$element += self::$element_defaults;

			if( !is_array( $element['#class'] ) ) {
				$element['#class'] = array( $element['#class'] );
			}

			// Default some properties to $key
			foreach( array( '#key', '#slug', '#name' ) as $field ) {
				if( empty( $element[$field] ) ) {
					$element[$field] = $key;
				}
			}

			$elements[$key] = &$element;
		}

		return $elements;
	}

	/**
	 * Render forms.
	 *
	 * @param array $form. any value with a key not
	 *   starting with '#' is considered an element.
	 *
	 * Special keys, all optional:
	 *
	 * #key
	 * The key for this form. Optional.
	 *
	 * #id
	 * The ID for this form. Optional.
	 *
	 * #attrs
	 * Form container tag attributes.
	 *
	 * Elements are also forms, but a form is not necessarily an element.
	 * If a member value has a '#type' key, then it is considered an element.
	 *
	 * There is no strict typing of these object, merely duck-typing. If it doesn't have
	 * a '#key', it can be considered a form, if it does, it is a renderable element that
	 * is associated with a value in $values.
	 *
	 * Elements are rendered separately, in render_element(). The form structure is walked
	 * through using the render_form() method.
	 *
	 * @param array $values. The values of the form, where each key is the '#key' of the element.
	 *
	 * Special rules may apply, see below.
	 */
	static function render_form( $form, &$values ) {
		if( !isset( $form['#form'] ) ) {
			$form['#form'] = $form;
		}

		$form += self::$element_defaults;

		$form['#class'][] = 'wp-form';

		if( $form['#id'] ) {
			$form['#attrs']['id'] = $form['#id'];
			$form['#class'][] = 'wp-form-' . $form['#id'];
		}

		$form = apply_filters( 'wp_form', $form );

		$elements = self::get_elements( $form );

		// No elements = no form
		if( empty( $elements ) ) {
			return;
		}

		$form['#attrs']['class'] = join( ' ', $form['#class'] );

		$markup = '';

		$value_root = &$values;

		if( $form['#type'] == 'composite' && $form['#key'] ) {
			$value_root = &$values[$form['#key']];
		}

		foreach( $elements as $key => $element ) {
			// Add index when applicable
			if( isset( $form['#index'] ) && $form['#name'] ) {
				$element['#name'] = $form['#name'] . '[' . $form['#index'] . '][' . $key . ']';
				$element['#slug'] = $form['#slug'] . '-' . $form['#index'] . '-' . $key;
			}
			else {
				if( $form['#slug'] ) {
					$element['#slug'] = $form['#slug'] . '-' . $element['#slug'];
				}
			}

			if( $form['#type'] == 'composite' && $form['#name'] ) {
				$element['#name'] = $form['#name'] . '[' . $element['#key'] . ']';
			}

			$markup .= self::render_element( $element, $value_root );
		}

		wp_enqueue_script( 'wp-forms' );
		wp_enqueue_style( 'wp-forms' );

		return self::make_tag( $form['#container'], $form['#attrs'], $markup );
	}

	/**
	 * Render an element
	 *
	 * @param array $element
	 *
	 * The element to render. Any keys starting with '#' are considered special,
	 * any other keys are considered sub-elements
	 *
	 * Meaningful keys:
	 *
	 * #type - When present, this element contains an input.
	 * 	'text' – Plan text
	 * 	'select' - A select box. Requires #options
	 * 	'checkbox' - A boolean
	 * 	'textarea' - A textarea
	 * 	'composite' - A composite value which is posted as an array in #key
	 *
	 * #key
	 * The key (form name) of this element. This is the only absolutely required
	 * key in the element, but is set as part of get_elements().
	 *
	 * #placeholder
	 * Placeholder for elements that support it
	 *
	 * #options
	 * Array of options for select boxes, given by value => label
	 *
	 * #slug
	 * The machine-readable slug for this element. This is used to compose
	 * machine-readable ids and class names.
	 *
	 * #label
	 * Displayed label for this element
	 *
	 * #required
	 * TODO: Does nothing right now. Will hide non-default options in select
	 * boxes
	 *
	 * #multiple
	 * If defined, a form structure that becomes part of a collection with CRUD.
	 * instances of the child can be created and updated, and is stored as an
	 * array rather than a dictionary in $values.
	 *
	 * #add_link
	 * Link text to show to add an item to this multiple list
	 *
	 * #remove_link
	 * Link text to show to remove an item to this multiple list
	 *
	 * @param array $values
	 *
	 * The array of values to use to populate input elements.
	 *
	 * @param array $form
	 *
	 * The top-level form for this element.
	 */
	static function render_element( $element, &$values, $form = null ) {
		if( !isset( $form ) ) {
			$form = $element;
		}

		if( !isset( $element['#form'] ) ) {
			$element['#form'] = $form;
		}

		// All elements require a key, always.
		if( !is_scalar( $element['#key'] ) ) {
			throw new Exception( "Form UI error: Every element must have a #key" );
		}

		// Allow for pre-processing of this element
		$element = apply_filters( 'wp_form_prepare_element', $element );

		if( isset( $values[$element['#key']] ) ) {
			$element['#value'] = $values[$element['#key']];
		}
		else if( isset( $element['#default'] ) ) {
			$element['#value'] = $element['#default'];
		}

		$input_id = 'wp-form-' . $element['#slug'];

		$element['#container_classes'][] = 'wp-form-key-' . $element['#key'];
		$element['#container_classes'][] = 'wp-form-slug-' . $element['#slug'];

		if( $element['#type'] ) {
			$attrs = &$element['#attrs'];
			$attrs['id'] = $input_id;
			$attrs['name'] = $element['#name'];
			$attrs['type'] = $element['#type'];

			$element['#tag'] = 'input';
			$element['#container_classes'][] = 'wp-form-element';
			$element['#container_classes'][] = 'wp-form-type-' . $element['#type'];

			$element['#class'][] = 'wp-form-input';

			if( is_scalar( $element['#value'] ) && strlen( $element['#value'] ) > 0 ) {
				$attrs['value'] = $element['#value'];
			}

			if( $element['#placeholder'] ) {
				$attrs['placeholder'] = $element['#placeholder'];
			}

			if( $element['#size'] ) {
				$attrs['size'] = $element['#size'];
			}

			// Adjust form element attributes based on input type
			switch( $element['#type'] ) {
			case 'button':
				$element['#tag'] = 'button';
				break;

			case 'checkbox':
				$attrs['value'] =	'1';
				$element['#content'] = null;

				if( $element['#value'] ) {
					$attrs['checked'] = 'checked';
				}

				break;

			case 'textarea':
				$element['#tag'] = 'textarea';
				$element['#content'] = esc_textarea( $element['#value'] );
				unset( $attrs['value'] );
				unset( $attrs['type'] );

				break;

			case 'multiple':
				$element['#tag'] = 'div';
				$element['#content'] = self::render_multiple_element( $element, $values[$element['#key']] );
				unset( $attrs['value'] );
				unset( $attrs['type'] );
				unset( $attrs['name'] );
				break;

			case 'composite':
				unset( $attrs['value'] );
				unset( $attrs['name'] );
				unset( $attrs['type'] );
				$element['#content'] = null;
				$element['#tag'] = '';
				break;

			case 'select':
				$element['#tag'] = 'select';
				unset( $attrs['value'] );
				unset( $attrs['type'] );

				$options = array();

				if( !$element['#required'] ) {
					$options[''] = "- select -";
				}

				$options = $options + $element['#options'];

				$element['#content'] = '';

				foreach( $options as $value => $label ) {
					$option_atts = array( 'value' => $value );

					if( $value == $element['#value'] ) {
						$option_atts['selected'] = "selected";
					}

					$element['#content'] .= self::make_tag('option', $option_atts, esc_html( $label ) );
				}
				break;

			case 'image':
				$image_url = '';

				wp_enqueue_media();

				if( $element['#value'] ) {
					$image_src = wp_get_attachment_image_src( $element['#value'] );

					if( isset( $image_src[0] ) ) {
						$image_url = $image_src[0];
					}
				}

				$element['#tag'] = 'div';
				$element['#class'][] = 'select-image-field';
				$element['#content'] =
					self::make_tag( 'div', array( 'class' => 'image-container' ),
						self::make_tag( 'img', array( 'src' => $image_url ) ) ) .
					self::make_tag( 'input', array( 'type' => 'text', 'name' => $element['#name'], 'value' => $element['#value'] ) ) .
					self::make_tag( 'span', array( 'class' => 'image-delete' ), '' );
				break;

			case 'post_select':
				$element['#class'][] = 'wp-form-post-select';
				$element['#attrs']['type'] = 'hidden';

				if( isset( $element['#post_type'] ) ) {
					if( !is_array( $element['#post_type'] ) ) {
						$element['#post_type'] = array( $element['#post_type'] );
					}

					$element['#attrs']['data-post-type'] = implode( ' ', $element['#post_type'] );
				}

				if( $element['#value'] ) {
					$post = get_post( $element['#value'] );

					if( $post ) {
						$element['#attrs']['data-title'] = $post->post_title;
					}
				}

				break;

			default:
				$element['#content'] = null;
				break;
			}
		}

		$element = apply_filters( 'wp_form_element', $element );

		$markup = '';

		if( isset( $element['#label'] ) ) {
			$markup .= self::make_tag( 'label', array(
				'class' => 'wp-form-label',
				'for' => $input_id,
			), esc_html( $element['#label'] ) );
		}

		$attrs['class'] = join( ' ', $element['#class'] );

		// Tagname may have been unset (such as in a composite value)
		if( $element['#tag'] ) {
			$markup .= self::make_tag( $element['#tag'], $element['#attrs'], $element['#content'] );
		}

		if( $element['#description'] ) {
			$markup .= self::make_tag( 'p', array( 'class' => 'description' ), $element['#description'] );
		}

		$markup .= self::render_form( $element, $values );

		return self::make_tag( $element['#container'], array( 'class' => join( ' ', $element['#container_classes'] ) ), $markup );
	}

	/**
	 * Render a multi-element, one that can receive CRUD operations
	 */
	static function render_multiple_element( $element, &$values ) {
		if( !isset( $element['#multiple'] ) ) {
			return;
		}

		$multiple = $element['#multiple'];

		$multiple += array(
			'#key' => $element['#key'],
			'#slug' => $element['#slug'],
			'#name' => $element['#name'],
			'#type' => ''
		);

		$template_id = 'wp-form-tmpl-' . $element['#slug'];
		$list_id = 'wp-multiple-list-' . $element['#slug'];

		$container_atts = array(
			'class' => 'wp-form-multiple wp-form-multiple-' . $element['#key'],
			'data-template' => $template_id,
			'data-list' => $list_id
		);

		// Placeholders filled in by JavaScript
		$multiple['#index'] = '%INDEX%';
		$multiple['#slug'] = $element['#slug'] . '-%INDEX%';

		$item_classes = array( 'wp-form-multiple-item' );
		$blank_values = array_fill_keys( array_keys( $element ), '' );

		if( !is_array( $values ) || empty( $values ) ) {
			$values = array();
		}

		// First, render a JavaScript template which can be filled out.
		// JavaScript replaces %INDEX% with the actual index. Indexes are used
		// to ensure the correct order and grouping when the values come back out in PHP
		$template = self::make_tag( 'li', array( 'class' => implode( ' ', $item_classes ) ),
			self::make_tag('a', array( 'class' => 'remove-multiple-item' ), $element['#remove_link'] ) .
			self::render_form( $multiple, $blank_values ) );

		$markup = self::make_tag( 'script', array( 'type' => "text/html", 'id' => $template_id ), $template );

		$list_items = '';

		// Now render each item with a remove link and a particular index
		foreach( $values as $index => $value ) {
			// Throw out non-integer indices
			if( !is_int( $index ) ) {
				continue;
			}

			$multiple['#index'] = $index;
			$multiple['#slug'] = $element['#slug'] . '-' . $index;

			$list_items .= self::make_tag( 'li', array( 'class' => implode( ' ', $item_classes ) ),
				self::make_tag( 'a', array( 'class' => 'remove-multiple-item' ), $element['#remove_link'] ) .
				self::render_form( $multiple, $value ) );
		}

		$markup .= self::make_tag( 'ol', array( 'id' => $list_id, 'class' => 'wp-form-multiple-list' ), $list_items );

		// Render the "add" link
		$markup .= self::make_tag( 'a', array( 'class' => 'add-multiple-item' ), $element['#add_link'] );

		return self::make_tag( 'div', $container_atts, $markup );
	}

	/**
	 * Process a form, filling in $values with what's been posted
	 */
	static function process_form( $form, &$values, &$input = null ) {
		$form += self::$element_defaults;

		if( !isset( $form['#form'] ) ) {
			$form['#form'] = $form;
			$form['#values'] = &$values;
			$form['#input'] = &$input;
		}

		if( !isset( $input ) ) {
			$input = &$_POST;
		}

		$form = apply_filters_ref_array( 'wp_form_process', array( &$form, &$values, &$input ) );

		foreach( self::get_elements( $form ) as $key => $element ) {
			$element['#form'] = $form;

			self::process_element( $element, $values, $input );
		}
	}

	/**
	 * Recursively process a meta form element,	filling in $values accordingly
	 *
	 * @param array $element - The element to process.
	 *
	 * @param array &$values - Processed values are written to this array with
	 * for any element in the form with a '#key' and a '#type'.
	 */
	static function process_element( $element, &$values, &$input ) {
		$values_root = &$values;
		$input_root = &$input;

		// Process checkbox value by simple presence of #key
		if( $element['#type'] === 'checkbox' ) {
			$element['#value'] = isset( $input[$element['#key']] );
		}
		// Munge composite elements
		else if( $element['#type'] == 'composite' ) {
			$values_root = &$values[$element['#key']];
			$input_root = &$input[$element['#key']];
		}
		// Munge multiple elements
		else if( $element['#type'] == 'multiple' ) {
			$values[$element['#key']] = array();

			if( isset( $input[$element['#key']] ) && is_array( $input[$element['#key']] ) ) {
				$element['#value'] = $input[$element['#key']];
			}
		}
		// Ignore buttons
		else if( $element['#type'] == 'button' ) {
		}
		// Or just pull the value from the input
		else if( isset( $input[$element['#key']] ) ) {
			$element['#value'] = $input[$element['#key']];

			// Simple sanitization of most values
			if( isset( $element['#type'] ) && $element['#type'] != 'composite' ) {
				$element['#value'] = sanitize_text_field( $element['#value'] );
			}
		}

		// If there's a value, use it. May have been fed in as part of the form
		// structure
		if( isset( $element['#value'] ) ) {
			$values[$element['#key']] = $element['#value'];
		}

		$element = apply_filters_ref_array( 'wp_form_process_element', array( &$element, &$values, &$input ) );

		self::process_form( $element, $values_root, $input_root );
	}
}
add_action( 'init', array( 'WP_Forms_API', 'init' ) );
