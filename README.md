# WP_Forms_API

<p align="center">
  <img width="490" height="275" src="https://github.com/bendoh/WP-Forms-API/raw/master/finally.jpg">
</p>

A Drupal-esque API for creating and processing forms in WordPress

Provides a `WP_Forms_API` class composed of static methods which can be used to render forms defined by arbitrary data structures. You can also process the results submitted in those forms into a coherent set of values, smoothing over data types, validation (TODO: not yet!!) and allowing for function integration into WordPress.

## Why?

Writing and managing admin forms in WordPress is a real pain in the butt, and a data-driven approach is much more flexible and Drupal-y. WordPress tends to implement forms and other complex markup structures with literal markup templates, but those can be very difficult to manage and update. I have not seen any other similar development projects that brings some of the best ideas from Drupal into WordPress where they can benefit developers and clients alike by providing rapid development tools.

Having forms driven by data sets instead of templates and markup creates a very generic, predictable, and stylizable structure in the rendered form markup, and easy managements and updates to the form structure.

## Overview

There are two basic elements:

'form', which is any associative array.

'element', which is any associative array having at least `#type` and `#key` keys.

## Quick Start

```php
/**
 * Define a form called 'my-form' which contains an address1 and address2
 * input, and another form called 'citystatezip' which contains three input
 * elements: city, state, zipcode
 */
$form = array(
  '#id' => 'my-form',

  'address1' => array(
    '#label' => "Street",
    '#type' => 'text',
    '#placeholder' => "Line 1",
  ),
  'address2' => array(
    '#type' => 'text',
    '#placeholder' => "Line 2"
  ),

  'citystatezip' => array(
    'city' => array(
      '#type' => 'text',
      '#label' => "City",
      '#placeholder' => "Boston",
      '#size' => 20
    ),
    'state' => array(
      '#type' => 'text',
      '#label' => "State",
      '#placeholder' => "MA",
      '#size' => 4,
    ),
    'zipcode' => array(
      '#type' => 'text',
      '#label' => "ZIP",
      '#placeholder' => "01011",
      '#size' => 7
    )
  ),
);

/**
 * Define the values for this form
 */
$values = array(
  'city' => "Omaha",
  'state' => "Nebraska"
);

/**
 * You can render the form in whatever context you'd like: Front-end, meta boxes,
 * wherever. Does not containing <form> elements: the form is expected to be
 * defined at this point.
 */
echo WP_Forms_API::render_form( $form, $values );

/**
 * Now I want to save the elements from this form. Each element gets an input
 * with the same name as its '#key' which defaults to its array key.
 */
add_action( 'save_post', function( $post ) use ( $form ) {
  $post = get_post( $post );

  // Fill in posted values for this form in $values. Every key
  // in $values is guaranteed to be defined for every input in defined in $form.
  WP_Forms_API::process_form( $form, $values );

  update_post_meta( $post->ID, 'city', $values['city'] );
} )
```

## Reference

Forms and elements are represented with simple associative arrays.

A form is a top-level object, but an element is also a form.

Special keys start with '#', all other keys are interpreted as elements in themselves.

Any form that contains a `#type` key is considered to be an input element and will
create a corresponding named form input.

### Functions

This plugin implements a class `WP_Forms_API` which provides the following static methods:


* `WP_Forms_API::render_form( $form, &$values )`

Recursively render a form using values in `$values` and return its markup. This is the primary rendering function you'll need. Applies `wp_form` filter to the form before rendering.

`$form` - (array) - The form to render.

`$values` - (array ref) - The values for the elements in this form. While the form structure is nested and heirarchical, the values structure is flat (mostly: see 'composite' values).

`$top` - (optional array) - The top-level form.


* `WP_Forms_API::render_element( $element, &$values )` - Render a single element.

Renders an element, and any sub-forms, returning the rendered markup. You must specify a scalar `#key` in an element or else an exception will be thrown.

`$form` - (array) - The form to render.

`$values` - (array ref) - The values for the elements in this form. While the form structure is nested and heirarchical, the values structure is flat (mostly: see 'composite' values).

Applies `wp_form_element` filter to element before rendering.


* `WP_Forms_API::make_tag( $tagname, $attrs, $content = null )`

Make and return a single HTML tag.

`$tagname` - (string) - The name of the HTML tag to emit. If empty, do nothing.

`$attrs` - (array) - An associative array of HTML attributes for this tag.

`$content` - (string|false) - The content for this tag, if any. If null, then emit a self-closing tag. If `false`, then the tag will not be closed.


* `WP_Forms_API::get_elements( $form )` - Initialize and return all of the elements of a form.

`$form` - (array) The form to extract elements from.

Use this function to get initialized sub-elements from a form, skipping all the special keys.


* `WP_Forms_API::process_form( $form, &$values, $input = null )` - Process a submitted form and extract submitted values.

`$form` - (array) - The form to process.

`$values` - (array reference) - Save result values into this structure.

`$input` - (array, optional) - The input array. Defaults to `$_POST`.


### Filters

Forms and form elements are run through the `wp_form` and `wp_form_element` WP filters, respectively, before they are rendered. This gives developers the ability to define special input tags, (by setting in the `#tag` property), as well as updating or hiding elements as they are rendered.

The `wp_form` filter receives `$form, $top` as arguments, where `$form` is the current form node, and `$top` is the top-level form. You can identify specific forms using the `#id` key, but any arbitrary key starting with `#` can be also be used.

The `wp_form_element` filter recieves `$element, $form` as


### Forms

All keys in forms are optional.

* `#id` (string)

The reference ID for this form, for filtering output. When defined, elements are run through the filter `wp_form_element_{$form_id}-{$element_key}` before they are rendered, so they can be modified, removed, or otherwise.

* `#label` (string)

The label for this form or element.

* `#class` (array)

CSS classes in `class` attributes.

* `#attrs` (array)

The attributes to use for the rendered container tag. If `class` key is specified, is it prepended by the classes in `#class`.

* `#form` (array)

The top-level form. This defaults to the form itself.

* `#container` (string)

The tag name for the container of this form or element. Defaults to `div`.

* `#container_classes` (array)

An array of CSS classes to add to the container element.


### Elements

An element is an associative array with at least a `#type` key. An element
can have any of the properties of a form, as well as the following:

* `#type` (string)

When present, indicates that the element is an input element. Values:

* `'text'` – Plain text input.
* `'select'` - A select box. Requires `#options` key.
* `'checkbox'` - A boolean.
* `'textarea'` - A textarea.
* `'multiple'` - A collection of values.
* `'composite'` - A composite value which is posted as an array in #key.
* Any other value will be rendered as a text input. You can use custom types along with the `wp_form_element` filter to define input tag types 

*  `#key` (string)

The key for this element. Is used to create default form element `name`
attributes and slugs for classes.

*  `#placeholder` (string)

The placeholder to use in the form element. Applies to 'text' and 'textarea' types.

*  `#options` (array)

The options, as given by 'value' => "Label", for this input element. Only applies to select.

*  `#required` (bool)

Whether or not the key is required. (TODO: Currently only affects `'select'`-type elements.)

*  `#name` (string, optional)

The input element name - defaults to `#key`.

*  `#slug` (string, optional)

The CSS element name - defaults to `#key`.

*  `#size` (int)

The size of the element.

*  `#multiple` (array)

Required for `$form['#type'] == 'multiple'`. Define another form whose values are collected into an array for this element.

*  `#add_link` (string)

When using #multiple, the text to show for the "Add Item" button.

*  `#remove_link` (string)

When using #multiple, the text to show for the "Remove Item" button.


### Filterable element properties

These properties should only be modified in the `wp_form_element` filter.

*  `#content` (string)

Any content to put in the input tag. Additional content from rendering the tag will be appended to this value if it is provided. Only applies to `'select'` and `'checkbox'` types, but will render <input> tags with this content if it is provided.

*  `#tag` (string)

The actual tag name to use for the input.

### Rendered Input Names

Form input elements rendered using this API are receive the name specified in `#name`, which defaults to as their `#key`.

For `'#type' => 'composite'`, any elements in the form tree below the current element receive names that result in an associative array being submitted for that `#key`. For example, the form defined below:

```php
$form = array(
  'address' => array(
    '#type' => 'composite',
    'city' => array( '#type' => 'text', '#label' => "City" ),
    'state' => array( '#type' => 'text', '#label' => "State" ),
    'zip' => array( '#type' => 'text', '#label' => "ZIP" ),
  )
);
```

Will result in three input elements named `address[city]`, `address[state]`, and `address[zip]`.

For `'#type' => 'multiple'`, you must specify a `#multiple` key, which is a form whose values are collected into an indexed array. For example, the following form:

```php
$form = array(
  'favorites' => array(
    '#type' => 'multiple',
    '#multiple' => array(
      'name' => array( '#type' => 'text', '#label' => "Favorite:" )
    )
  )
);
```

Will result in a multi-valued form with inputs named `favorites[0][name]`, `favorites[1][name]`, and so on, for each value submitted. The form will always render at least one empty input. When multiple-valued form elements are used, the script handle `wp-forms` is enqueued, which manages control of adding / removing multiple elements.

In general, the naming should be unimportant to you when using process_form(), but it is important to know how `$values` will be structured `$values` after calling `process_form()`.

### Processing

Use the method `WP_Forms_API::process_form( $form, $values )` to populate $values with named elements defined by $form. By default will use values from `$_POST`, but you can pass an optional third argument to pull element values from.

The filter `wp_form_process` is called with arguments `$form, &$values, &$input` and allows modification of forms or sub-forms before they are rendered. You can access the top-level form in `$form['#form']`

The filter `wp_form_process_element` is called with `$element, &$values, &$input`, and allows modification of individual elements before they are processed. You can access the sub-form that this element is a part of in `$element['#form']`, and the top-level form in `$element['#form']['#form']`.

In each of these filters, `&$values` and `&$input` may refer to sub-arrays of the original `$values` and `$input` arrays. To access the top-level of these structures, access `$form['#form']['#values']` and `$form['#form']['#input']` for forms, and `$element['#form']['#form']['#values']` and `$element['#form']['#form']['#input']` for elements.


## CSS

Element slugs are built from the chain of parent elements, separated by `'-'`. These slugs are used to form CSS class names for elements for targeted styling.

Forms get the following classes: `.wp-form`, and if #id is defined, `.wp-form-{$form['#id']}`. They also receive the attribute `id="{$element['#id']}"`

Each element is rendered in a container element of tag `#container` with classes defined in `#container_classes`. The following classes are also added to the container element: `.wp-form-element .wp-form-element-{$slug}`.

Each input element is rendered using the tag `$element['#tag']` and attributes in `$element['#attrs']`. These values can be modified per-element using the `wp_form_element` filter. The default classes are `.wp-form-input` and `.wp-form-input-{$element['#slug']}`.

Labels receive the classes `.wp-form-label` and `.wp-form-label-{$element['#slug']}`.

## Please help!

This project is merely a generalization of work I did for another project. I've spent many frustrating hours building forms in WordPress, and I knew there had to be an easier way. This doesn't claim to be nearly as powerful as the Drupal Forms API, but maybe one day, with your help, it could be!

