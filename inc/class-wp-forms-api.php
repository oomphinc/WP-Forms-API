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
		// The id attribute of the input element
		'#id' => '',

		// The type of input element
		'#type' => null,

		// The key of this input element, matching the form key.
		'#key' => '',

		// The slug of this input element, built heirarchically.
		'#slug' => '',

		// The name of this input element. Typically derived from the key in the form.
		'#name' => '',

		// Reference to the top-level form
		'#form' => null,

		// Input placeholder
		'#placeholder' => null,

		// Default value
		'#default' => null,

		// Text field size
		'#size' => null,

		// Select / Multi-select options, value => label
		'#options' => array(),

		// Value used for checkboxes
		'#checked' => '1',

		// Container used for this element
		'#container' => 'div',

		// Classes applied to this container
		'#container_classes' => array(),
		'#markup' => '',

		// Attributes on the input element
		'#attrs' => array(),

		// Classes applied to the input element
		'#class' => array(),

		// The label to attach to this element
		'#label' => null,

		// The label's position -- either "before" or "after"
		'#label_position' => 'before',

		// The textual description of this element
		'#description' => null,

		// Whether or not a value is required
		'#required' => false,

		// When #type=multiple, the index of this particular element in the set
		'#index' => null,

		// For #type=select fields, allow multiple values to be selected
		// For #type=multiple, the form that can capture multiple values
		'#multiple' => null,

		// The content of the input tag, when applicable
		'#content' => null,

		// Add / Remove link text for multi-value elements
		'#add_link' => 'Add item',
		'#remove_link' => 'Remove item',

		// Tag name of element. Typically derived from `#type`
		'#tag' => '',

		// Value of element. Typically filled in with process_form()
		'#value' => null,

		// Whether or not to allow HTML in an input value. Sanitizes using
		// wp_kses_post
		'#allow_html' => false,

		// Conditional logic. Used to conditional show/hide elements depending
		// on the field's value. NOTE: Can only be used on elements that trigger a
		// change event.
		'#conditional' => array( 'element' => null, 'value' => null, 'action' => null ),

		// Whether to use only the array values passed to an options argument
		'#labels_as_values' => false,
	);

	/**
	 * Initialize this module
	 *
	 * @action init
	 */
	static function init() {
		wp_register_script( 'wp-forms', plugins_url( 'wp-forms-api.js', __FILE__ ), array( 'jquery-ui-autocomplete', 'jquery-ui-sortable', 'backbone', 'wp-util' ), 1, true );
		add_action( 'admin_enqueue_scripts', array( __CLASS__, 'admin_enqueue' ) );
		add_action( 'wp_ajax_wp_form_search_posts', array( __CLASS__, 'search_posts' ) );
		add_action( 'wp_ajax_wp_form_search_terms', array( __CLASS__, 'search_terms' ) );
		add_action( 'print_media_templates', array( __CLASS__, 'media_templates' ) );
	}

	/**
	 * Search for a post by name.
	 *
	 * @action wp_ajax_wp_form_search_posts
	 */
	static function search_posts() {
		global $wpdb;

		if( !isset( $_POST['term'] ) ) {
			wp_send_json_error();
		}

		$posts = apply_filters( 'wp_form_pre_search_posts', null );

		if( !isset( $posts ) ) {
			$query_args = array(
				's' => $_POST['term'],
				'post_status' => 'any'
			);

			if( isset( $_POST['post_type'] ) ) {
				$query_args['post_type'] = (array) $_POST['post_type'];
			}

			$query_args = apply_filters( 'wp_form_search_posts', $query_args );

			$query = new WP_Query( $query_args );
			$posts = $query->posts;
		}

		$posts = apply_filters( 'wp_form_search_results', $posts );

		wp_send_json_success( $posts );
	}

	/**
	 * Search for a term by name.
	 *
	 * @action wp_ajax_wp_form_search_posts
	 */
	static function search_terms() {
		global $wpdb;

		$input = filter_input_array( INPUT_POST, array(
			'term' => FILTER_SANITIZE_STRING,
			'taxonomy' => FILTER_SANITIZE_STRING
		) );

		if( empty( $input['taxonomy'] ) || !taxonomy_exists( $input['taxonomy'] ) ) {
			wp_send_json_error();
		}

		$terms = apply_filters( 'wp_form_pre_search_terms', null );

		if( !isset( $terms ) ) {
			$query_args = array(
				'search' => $input['term'],
				'hide_empty' => false
			);

			$terms = get_terms( $input['taxonomy'], $query_args );
		}

		$terms = apply_filters( 'wp_form_search_terms', $terms );

		wp_send_json_success( $terms );
	}

	/**
	 * Enqueue admin assets
	 *
	 * @action admin_enqueue_scripts
	 */
	static function admin_enqueue() {
		wp_enqueue_style( 'wp-forms', plugins_url( 'wp-forms-api.css', __FILE__ ) );
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
	 * Is the element a button?
	 *
	 * @return bool
	 */
	static function is_button( $element ) {
		return preg_match( '/^button|submit$/', $element['#type'] );
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
			$element['#form'] = $form;

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
	 * 	'image' - An image selection field
	 * 	'attachment' - An attachment selection field
	 * 	'radio' - A radio button
	 * 	'multiple' - A zero-to-infinity multiple value defined in #multiple key
	 * 	'markup' - Literal markup. Specify markup value in #markup key.
	 * 	'post_select' - A post selection field. Can specify types in #post_type key.
	 * 	'term_select' - A taxonomy term selection field. Can specify types in #taxonomy key.
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
	 * #markup
	 * Literal markup to use. Only applies to '#type' = 'markup'
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
			$element['#form'] = &$form;
		}

		// All elements require a key, always.
		if( !is_scalar( $element['#key'] ) ) {
			throw new Exception( "Form UI error: Every element must have a #key" );
		}

		// Allow for pre-processing of this element
		$element = apply_filters( 'wp_form_prepare_element', $element );
		$element = apply_filters( 'wp_form_prepare_element_key_' . $element['#key'], $element );

		// Ignore inputted values for buttons. Just use their #value for display.
		if( !self::is_button( $element ) && !isset( $element['#value'] ) ) {
			if( isset( $values[$element['#key']] ) ) {
				$element['#value'] = $values[$element['#key']];
			}
			else if( isset( $element['#default'] ) ) {
				$element['#value'] = $element['#default'];
			}
		}

		$input_id = $element['#id'] ? $element['#id'] : 'wp-form-' . $element['#slug'];

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

			// Conditional logic
			if( $element['#conditional']['element'] && $element['#conditional']['action'] && $element['#conditional']['value'] ) {
				$attrs['data-conditional-element'] = $element['#conditional']['element'];
				$attrs['data-conditional-action'] = $element['#conditional']['action'];
				$attrs['data-conditional-value'] = $element['#conditional']['value'];
			}

			// Adjust form element attributes based on input type
			switch( $element['#type'] ) {
			case 'button':
				$element['#tag'] = 'button';
				break;

			case 'checkbox':
				$attrs['value'] = $element['#checked'];
				$element['#content'] = null;
				$element['#label_position'] = 'after';

				if ( $element['#value'] === $element['#checked'] ) {
					$attrs['checked'] = 'checked';
				}

				break;

			case 'radio':
				if( !$element['#options'] ) {
					$element['#options'] = array( 'No', 'Yes' );
				}

				$element['#tag'] = 'div';
				$element['#class'][] = 'wp-form-radio-group';
				$element['#content'] = '';
				$element['#label_position'] = 'after';

				foreach( $element['#options'] as $value => $label ) {
					$radio_attrs = array(
						'type' => 'radio',
						'name' => $element['#name'],
						'value' => $value
					);

					if( $value === $element['#value'] || $value === $element['#default'] ) {
						$radio_attrs['checked'] = 'checked';
					}

					$element['#content'] .= self::make_tag( 'label', array(
						'for' => $element['#slug'] . '-' . $value,
					), self::make_tag( 'input', $radio_attrs, $label ) );
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

				if( $element['#multiple'] ) {
					$attrs['multiple'] = 'multiple';
					$attrs['name'] .= '[]';
					$element['#value'] = array_map( 'strval', (array) $element['#value'] );
				}

				if( !$element['#required'] ) {
					$options[''] = isset( $element['#placeholder'] ) ? $element['#placeholder'] : "- select -";
				}

				$options = $options + $element['#options'];

				$element['#content'] = self::render_options( $options, $element );

				break;

			case 'attachment':
			case 'image':
				// Fancy JavaScript UI will take care of this field. Degrades to a simple
				// ID field
				wp_enqueue_media();

				$element['#class'][] = 'select-attachment-field';

				if( $element['#type'] == 'image' ) {
					$element['#class'][] = 'select-image-field';
				}

				$attrs['type'] = 'text';
				$attrs['data-attachment-type'] = $element['#type'];

				break;

			case 'mce':
				if( !user_can_richedit() ) {
					// User doesn't have capabilities to richedit - just display
					// a regular textarea with html tags allowed
					$element['#type'] = 'textarea';
					$element['#allow_html'] = true;

					if( !isset( $attrs['rows'] ) ) {
						$attrs['rows'] = 10;
					}

					return self::render_element( $element, $values, $form );
				}

				$element['#tag'] = 'div';
				$element['#class'][] = 'wp-forms-mce-area';
				$element['#id'] = 'wp-form-mce-' . $element['#slug'];
				unset( $attrs['value'] );

				ob_start();
				wp_editor( $element['#value'], $element['#id'], array( 'textarea_name' => $element['#name'] ) );
				$element['#content'] = ob_get_clean();

				break;

			case 'post_select':
				$element['#class'][] = 'wp-form-post-select';
				$attrs['type'] = 'hidden';

				if( isset( $element['#post_type'] ) ) {
					$element['#post_type'] = (array) $element['#post_type'];
				}

				$attrs['data-post-type'] = implode( ' ', $element['#post_type'] );

				if( $element['#value'] ) {
					$post = get_post( $element['#value'] );

					if( $post ) {
						$attrs['data-title'] = $post->post_title;
					}
				}

				break;

			case 'term_select':
				$element['#class'][] = 'wp-form-term-select';
				$attrs['type'] = 'hidden';

				$attrs['data-taxonomy'] = $element['#taxonomy'];

				if( $element['#value'] ) {
					$term = get_term( (int) $element['#value'], $element['#taxonomy'] );

					if( $term && !is_wp_error( $term ) ) {
						$attrs['data-name'] = $term->name;
					}
				}

				break;

			default:
				$element['#content'] = null;
				break;
			}
		}

		$element = apply_filters( 'wp_form_element', $element );
		$element = apply_filters( 'wp_form_element_key_' . $element['#key'], $element );

		$markup = '';

		$label = '';
		if( isset( $element['#label'] ) ) {
			$label = self::make_tag( 'label', array(
				'class' => 'wp-form-label',
				'for' => $input_id,
			), esc_html( $element['#label'] ) );
		}

		$attrs['class'] = join( ' ', $element['#class'] );

		// Markup types just get a literal markup block
		if( $element['#type'] == 'markup' ) {
			$markup .= $element['#markup'];
		}
		// Tagname may have been unset (such as in a composite value)
		else if( $element['#tag'] ) {
			$markup .= $element['#label_position'] == 'before' ? $label : '';
			$markup .= self::make_tag( $element['#tag'], $element['#attrs'], $element['#content'] );
			$markup .= $element['#label_position'] == 'after' ? $label : '';
		}

		if( $element['#description'] ) {
			$markup .= self::make_tag( 'p', array( 'class' => 'description' ), $element['#description'] );
		}

		$markup .= self::render_form( $element, $values );

		return self::make_tag( $element['#container'], array( 'class' => join( ' ', $element['#container_classes'] ) ), $markup );
	}

	/**
	 * Recursively render the options and any contained <optgroups> for a select menu
	 */
	static function render_options( $options, &$element ) {
		$markup = '';

		foreach( $options as $value => $label ) {
			// ignore the value and use the label instead?
			if ( $element['#labels_as_values'] && !is_array( $label ) ) {
				$value = $label;
			}
			$option_atts = array( 'value' => $value );

			if( isset( $element['#value'] ) &&
				( ( $element['#multiple'] && in_array( (string) $value, $element['#value'] ) ) ||
					(string) $value === (string) $element['#value'] ) ) {
				$option_atts['selected'] = "selected";
			}

			// Allow for nesting one-level deeper by using a key which becomes
			// the label for an <optgroup> and an array value representing
			// the options within that optgroup. A downside to this data format
			// is that values and optgroup labels share the same namespace and thus
			// a value share the same label of an optgroup.
			//
			// This isn't exactly correct, but it works for most cases. Can we make it better?
			if( is_array( $label ) ) {
				$markup .= self::make_tag( 'optgroup', array( 'label' => $value ), self::render_options( $label, $element ) );
			}
			else {
				$markup .= self::make_tag( 'option', $option_atts, esc_html( $label ) );
			}
		}

		return $markup;
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

		$container_atts = array(
			'class' => 'wp-form-multiple wp-form-multiple-' . $element['#key'],
		);

		// Placeholders filled in by JavaScript
		$multiple['#index'] = '%INDEX%';
		$multiple['#slug'] = $element['#slug'] . '-%INDEX%';

		$item_classes = array( 'wp-form-multiple-item' );
		$blank_values = array_fill_keys( array_keys( $element ), '' );

		if( !is_array( $values ) || empty( $values ) ) {
			$values = array();
		}

		$multiple_ui =
			self::make_tag( 'span', array( 'class' => 'dashicons dashicons-dismiss remove-multiple-item' ), '' ) .
			self::make_tag( 'span', array( 'class' => 'dashicons dashicons-sort sort-multiple-item' ), '' );

		// First, render a JavaScript template which can be filled out.
		// JavaScript replaces %INDEX% with the actual index. Indexes are used
		// to ensure the correct order and grouping when the values come back out in PHP
		$template = self::make_tag( 'li', array( 'class' => implode( ' ', $item_classes ) ),
			$multiple_ui .
			self::render_form( $multiple, $blank_values ) );

		$markup = self::make_tag( 'script', array( 'type' => 'text/html', 'class' => 'wp-form-multiple-template' ), $template );

		$list_items = '';

		// Now render each item with a remove link and a particular index
		foreach( $values as $index => $value ) {
			// Throw out non-integer indices
			if( !is_int( $index ) ) {
				continue;
			}

			$multiple['#index'] = $index;
			$multiple['#slug'] = $element['#slug'];

			$list_items .= self::make_tag( 'li', array( 'class' => implode( ' ', $item_classes ) ),
				$multiple_ui .
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

		// avoid double slashing
		$input = stripslashes_deep( $input );

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

		// Process checkbox or button value by simple presence of #key
		if( $element['#type'] === 'checkbox' || self::is_button( $element ) ) {
			$element['#value'] = isset( $input[$element['#key']] ) && $input[$element['#key']];
		}
		// Munge composite elements
		else if( $element['#type'] == 'composite' ) {
			$values_root = &$values[$element['#key']];
			$input_root = &$input[$element['#key']];
		}
		// Munge multi-select elements
		else if( $element['#type'] == 'select' && $element['#multiple'] ) {
			$element['#value'] = isset( $input[$element['#key']] ) ? (array) $input[$element['#key']] : array();
		}
		// Munge multiple elements
		else if( $element['#type'] == 'multiple' ) {
			$values[$element['#key']] = array();

			if( isset( $input[$element['#key']] ) && is_array( $input[$element['#key']] ) ) {
				foreach( $input[$element['#key']] as $item ) {
					self::process_form( $element['#multiple'], $value, $item );
					$values[$element['#key']][] = $value;
				}
			}
		}
		// Or just pull the value from the input
		else if( isset( $input[$element['#key']] ) ) {
			$element['#value'] = $input[$element['#key']];

			// Sanitization of fields that allow html
			if( ( isset( $element['#allow_html'] ) && $element['#allow_html'] ) || $element['#type'] == 'mce' ) {
				$element['#value'] = wp_kses_post( $element['#value'] );
			}
			// Simple sanitization of most values
			else if( isset( $element['#type'] ) && $element['#type'] != 'composite' ) {
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

	/**
	 * Templates used in this module
	 */
	static function media_templates() { ?>
<script id="tmpl-wp-form-attachment-field" type="text/html">
<div class="attachment-container">
	<img src="{{ data.type == 'image' ? data.url : data.icon }}" />
</div>
<label>
	<span><?php esc_html_e( "ID:", 'wp-forms-api' ); ?></span>
	<input type="text" name="{{ data.input_name }}" class="wp-form-attachment-id" value="{{ data.id }}" />
</label>
<label>
	<span><?php esc_html_e( "Title:", 'wp-forms-api' ); ?></span>
	<input type="text" value="{{ data.title }}" readonly="readonly" />
</label>
<label>
	<a href="{{ data.link }}" target="_blank"><?php __( "View", 'wp-forms-api' ); ?></a>
</label>
<# if(data.url) { #><span class="attachment-delete"></span><# } #>
<p>
<# if(data.editLink) { #><a href="{{ data.editLink }}">Edit</a>&nbsp;&nbsp;<# } #>
<# if(data.url) { #><a href="{{ data.url }}">View</a><# } #>
</p>
</script>
	<?php
	}
}
add_action( 'init', array( 'WP_Forms_API', 'init' ) );
