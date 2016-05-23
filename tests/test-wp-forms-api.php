<?php

class WP_Forms_API_Test extends WP_UnitTestCase {
	function test_render() {
		$values = array();
		$html = WP_Forms_API::render_form( array(
			'text-input' => array( '#type' => 'text' )
		), $values );

		$this->assertContains( 'type="text"', $html );
		$this->assertContains( 'name="text-input"', $html );
	}

	function test_multiple_mces() {

	}

}
