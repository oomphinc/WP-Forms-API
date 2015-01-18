# WP_Forms_API

A Drupal-esque API for creating and processing forms in WordPress

Provides a 'WP_Forms_API' class composed of static methods which can be used
to render forms defined by arbitrary data structures and also process the values
submitted from those forms into a coherent set of values.

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

## Why?

Because writing and managing admin forms in WordPress is a real pain in the butt,
and a data-driven approach is much more flexible. I have not seen any other similar
development projects that brings some of the best ideas from Drupal into WordPress where
they can benefit developers and clients alike by providing rapid development tools.

Having forms driven by data sets instead of templates and markup creates a very generic,
predictable, and stylizable structure in the rendered form markup, and easy managements
and updates to the form structure.
