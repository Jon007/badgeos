<?php

if ( ! defined( 'ABSPATH' ) )
	exit; // Exit if accessed directly

class P2P_Field_Title_User extends P2P_Field_Title {

	function get_data( $user ) {
		return array(
			'title-attr' => '',
		);
	}
}

