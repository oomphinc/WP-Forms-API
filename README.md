# WP_Forms_API

A Drupal-esque API for creating and processing forms in WordPress

Provides a 'WP_Forms_API' class composed of static methods which can be used
to render forms defined by arbitrary data structures. You can also process the results
submitted in those forms into a coherent set of values, smoothing over data types,
validation (TODO: not yet!!) and allowing for function integration into WordPress.

## Why?

Writing and managing admin forms in WordPress is a real pain in the butt, and a
data-driven approach is much more flexible and Drupal-y. WordPress tends to
implement forms and other complex markup structures with literal markup
templates, but those can be very difficult to manage and update. I have not
seen any other similar development projects that brings some of the best ideas
from Drupal into WordPress where they can benefit developers and clients alike
by providing rapid development tools.

Having forms driven by data sets instead of templates and markup creates a very generic,
predictable, and stylizable structure in the rendered form markup, and easy managements
and updates to the form structure.

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

WP_Forms_API::render_form( $form, $values );

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


`WP_Forms_API::render_form( $form, &$values, $top = null )`

Render a form with values. This is the primary rendering function you'll need.

`$form` - (array) - The form to render.

`$values` - (array ref) - The values for the elements in this form. While the form structure is nested and heirarchical, the values structure is flat (mostly: see 'composite' values).

`$top` - (optional array) - The top-level form.


`WP_Forms_API::render_element( $element, $values, $form = null )` - Render a single element

Renders an element, emitting the appropriate markup.

Applies 'wp_form_element_{$form_id}-{$element_key}' filter to element before rendering.


`WP_Forms_API::make_tag( $tagname, $attrs, $content = null )

Render and emit single HTML tag.

`$tagname` - (string) - The name of the HTML tag to emit. If empty, do nothing.

`$attrs` - (array) - An associative array of HTML attributes for this tag.

`$content` - (string|false) - The content for this tag, if any. If null, then emit a self-closing tag. If `false`, then the tag will not be closed.


`WP_Forms_API::get_elements( $form )` - Initialize and return all of the elements of a form.

`$form` - (array) The form to extract elements from.

Use this function to get initialized sub-elements from a form, and skip all the special keys.

### Forms

All keys in forms are optional.

`#id` The reference ID for this form, for filtering output. When defined, elements
are run through the filter `wp_form_element_{$form_id}-{$element_key}` before they
are rendered, so they can be modified, removed, or otherwise.

### Elements

An element is an associative array with the following special keys:

`#type` The only valid types for a form is `'composite'`, which aggregates the values
of all sub-elements into a single value.
