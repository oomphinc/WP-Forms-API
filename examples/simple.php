<!-- Render the form as a POST on /my/form/handler -->
<form method="post" action="/my/form/handler">
<?php
	$form = array(
		'name' => array(
			'#type' => 'text',
			'#label' => "Enter your name:",
			'#placeholder' => "Charlie Brown"
		),
		'zipcode' => array(
			'#type' => 'text',
			'#label' => "Enter your ZIP code:",
			'#placeholder' => "90210",
			'#size' => 5
		),
		'save' => array(
			'#type' => 'submit',
			'#value' => "Save Information"
		)
	);

	echo WP_Forms_API::render_form( $form, $input );
?>
</form>

<!-- Then process the form -->
<?php
	WP_Forms_API::process_form( $form, $values );
?>

<p>
	Your name is: <?php echo esc_html( $form['name'] ); ?>
</p>

<p>
	Your ZIP is: <?php echo esc_html( $form['zipcode'] ); ?>
</p>
