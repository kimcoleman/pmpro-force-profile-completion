<?php
/**
 * Plugin Name: Paid Memberships Pro - Force Profile Completion
 * Plugin URI: https://www.paidmembershipspro.com/add-ons/pmpro-force
 * Description: Require all members to complete required profile fields before accessing restricted content.
 * Version: 1.0
 * Author: Paid Memberships Pro
 * Author URI: https://www.paidmembershipspro.com
 * Text Domain: pmpro-force-profile-completion
 * Domain Path: /languages
 */


if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

define( 'PMPROFPC_VERSION', '1.0' );

/**
 * Check to see if the member is trying to view a restricted page. Ignore open/public pages.
 *
 * @since 1.0
 * 
 * @param int $post_id The post ID to check if the user has access for.
 * @return bool True if the page is restricted, false if it is public.
 */
function pmprofpc_is_page_restricted( $post_id ) {
	
	// Not logged in, so we can't check. Let core handle it.
	if ( ! is_user_logged_in() ) {
		return false; 
	}

	$post_access_information = pmpro_has_membership_access( (int) $post_id, '', true );
	$post_required_levels = $post_access_information[1]; // Level IDs

	// Return true or false based on whether there are any level IDs. 
	// If there are no level ID's we can assume that the content is public.
	if ( ! empty( $post_required_levels ) ) {
		return true;
	}

	return false;
}

/**
 * Get all missing fields that are required for the member.
 *
 * @since 1.0
 * 
 * @param int $user_id The WordPress user ID. Defaults to current user.
 * @return array An array of incomplete required fields.
 */
function pmprofpc_get_incomplete_fields( $user_id = null ) {
	global $current_user;
	if ( empty( $user_id ) ) {
		$user_id = $current_user->ID;
	}

	// Get cache for current user.
	$cached_fields = get_transient( 'pmprofpc_incomplete_fields_' . $user_id );
	if ( $cached_fields !== false ) {
		return $cached_fields;
	}

	$fields_for_member = array();
	// Get from User Fields class.
	foreach( PMPro_Field_Group::get_all() as $group ) {
		$fields_for_member[] = $group->get_fields_to_display();
	}

	// Loop through all fields_for_member and flatten the array.
	$required_fields = array();
	foreach( $fields_for_member as $field ) {
		array_filter( $field, function( $required_field ) use ( &$required_fields ) {
			if ( $required_field->required ) {
				$required_fields[$required_field->name] = $required_field->label;
			}
		} );
	}

	// No required fields, return an empty array.
	if ( empty( $required_fields ) ) {
		// Cache the results for 10 minutes
		set_transient( 'pmprofpc_incomplete_fields_' . $user_id, array(), 10 * MINUTE_IN_SECONDS );
		return array();
	}

	// Check each required field for a value.
	foreach ( $required_fields as $key => $field_name ) {
		$field_value = get_user_meta( $user_id, $key, true );
		if ( $field_value !== '' ) {
			unset( $required_fields[ $key ] );
		}
	}

	// Cache the results for 10 minutes
	set_transient( 'pmprofpc_incomplete_fields_' . $user_id, $required_fields, 10 * MINUTE_IN_SECONDS );

	return $required_fields;
}


/**
 * Redirect members with incompleted required fields to the edit profile page to complete their profile.
 *
 * @since 1.0
 */
function pmprofpc_redirect_on_incomplete() {
	global $pmpro_pages, $post;

	// User logged out.
	if ( ! is_user_logged_in() ) {
		return; 
	}

	// We're not on a post/page, just bail.
	if ( empty( $post) ) {
		return;
	}

	// Unset directory pages so members may still need fields completed to view them.
	unset( $pmpro_pages['directory'] );
	unset( $pmpro_pages['profile'] );

	// Don't redirect away from any PMPro assigned page.
	if ( is_page( $pmpro_pages ) ) {
		return; 
	}

	// Page is a public page, no check needed.
	$is_restricted = pmprofpc_is_page_restricted( $post->ID );
	if ( ! $is_restricted ) {
		return; 
	}

	// Let's see if there are outstanding required fields.
	$incomplete_fields = pmprofpc_get_incomplete_fields();
	if ( empty( $incomplete_fields ) ) {
		return;
	}

	// Redirect to the profile edit page.
	wp_safe_redirect( get_permalink( $pmpro_pages['member_profile_edit'] ) );
	exit;
}
add_action( 'template_redirect', 'pmprofpc_redirect_on_incomplete' );


/**
 * Shows a warning on the PMPro account page for incompleted fields.
 *
 * @since 1.0
 */
function pmprofpc_show_warning_on_account() {
	global $pmpro_pages, $current_user;

	// Only show warning on the profile edit page.
	if ( empty( $pmpro_pages['member_profile_edit'] ) || ! is_page( $pmpro_pages['member_profile_edit'] ) ) {
		return;
	}

	// We are submitting the profile form, so we don't need to show the warning.
	if ( isset( $_REQUEST['action'] ) && 'update-profile' === $_REQUEST['action'] ) {
		return;
	}

	// Get the incomplete fields for the current user.
	$incomplete_fields = pmprofpc_get_incomplete_fields( $current_user->ID );

	// No incomplete fields, bail.
	if ( empty( $incomplete_fields ) ) {
		return;
	}

	// Get the field labels.
	$missing_fields_labels = array_values( $incomplete_fields );

	// Tweak according to number of error fields.
	if ( count( $missing_fields_labels ) === 1 ) {
		$error_message = sprintf( esc_html__( 'The %s field is required.', 'pmpro-force-profile-completion' ), implode( ', ', $missing_fields_labels ) );
	} else {
		$error_message = sprintf( esc_html__( 'The %s fields are required.', 'pmpro-force-profile-completion' ), implode( ', ', $missing_fields_labels ) );
	}

	echo '<div role="alert" id="pmpro_incomplete_field_warning" class="' . esc_attr( pmpro_get_element_class( 'pmpro_message pmpro_error' ) ) . '">' . wp_kses_post( $error_message ) . '</div>';

	?>
	<script>
		jQuery( document ).ready( function() {
			// Move the warning to above the #member-profile-edit form.
			jQuery( '#pmpro_incomplete_field_warning' ).insertBefore( '.pmpro' );

			// Loop through incomplete fields and highlight each field.
			var missing_field_keys = <?php echo wp_json_encode( array_keys( $incomplete_fields ) ); ?>;
			missing_field_keys.forEach( function( field_name ) {
				jQuery( 'input[name="' + field_name + '"], select[name="' + field_name + '"], textarea[name="' + field_name + '"]' ).addClass( 'pmpro_form_input-error' );
			} );
		} );
	</script>
	<?php
}
add_action( 'wp_head', 'pmprofpc_show_warning_on_account' );

/**
 * Show an error on submit when the form saves.
 * Note: This does not prevent the form from saving, it just shows an error - it allows clearance of required fields.
 * The redirect when fields are empty is the true prevention mechanism.
 * 
 * @since 1.0
 */
function pmprofpc_update_profile_error( &$errors, $update, &$user ) {
	if ( ! empty( $errors ) ) {
		return $errors;
	}

	// Get all empty required fields from user meta to make sure they are filled out during submission.
	$incomplete_fields = pmprofpc_get_incomplete_fields( $user->ID );
	$still_missing_fields = array();
	
	// Get a list of empty required fields, and cross reference with $_REQUEST to see if they were filled out during submission.
	if ( ! empty( $incomplete_fields ) ) {
		foreach( $incomplete_fields as $key => $field_name ) {
			if ( $_REQUEST[$field_name] == '' ) {
				$still_missing_fields[$key] = sanitize_text_field( $field_name );
			}
		}
	}

	// If there are still missing fields, add an error.
	if ( ! empty( $still_missing_fields ) ) {
		global $pmpro_error_fields;
		$required = array_unique($still_missing_fields);
		
		$pmpro_error_fields = array_merge( (array) $pmpro_error_fields, array_keys( $still_missing_fields ) );

		if( count( $required ) == 1 ) {
			$errors[] = sprintf( esc_html__( 'The %s field is required.', 'pmpro-force-profile-completion' ),  implode(", ", $still_missing_fields) );
		} else {
			$errors[] = sprintf( esc_html__( 'The %s fields are required.', 'pmpro-force-profile-completion' ),  implode(", ", $still_missing_fields) );
		}
	}

	return $errors;
}
add_filter( 'pmpro_user_profile_update_errors', 'pmprofpc_update_profile_error', 10, 3 );

/**
 * Enqueue the Javascript for handling errors and the UI.
 *
 * @since 1.0
 */
function pmprofpc_enqueue_scripts() {
	global $pmpro_pages;

	// Only enqueue this on the edit profile page.
	if ( is_page( $pmpro_pages['member_profile_edit'] ) ) {
		wp_enqueue_script( 'pmprofpc-frontend', plugins_url( 'js/pmprofpc-frontend.js', __FILE__ ), array( 'jquery' ), PMPROFPC_VERSION, true );
	}
}
add_action( 'wp_enqueue_scripts', 'pmprofpc_enqueue_scripts' );

/** 
 * Show the required indicators in the frontend profile page and unhook the default function.
 * 
 * @since 1.0
 */
function pmprofpc_show_user_fields_in_frontend_profile_with_locations( $user ) {
	$groups = PMPro_Field_Group::get_all();
	foreach( $groups as $group ) {
		$group->display(
			array(
				'markup' => 'div',
				'scope' => 'profile',
				'user_id' => $user->ID,
				'show_required' => true
			)
		);
	}	
}
add_action( 'pmpro_show_user_profile', 'pmprofpc_show_user_fields_in_frontend_profile_with_locations' );
remove_action( 'pmpro_show_user_profile', 'pmpro_show_user_fields_in_frontend_profile_with_locations' );

/**
 * Redirect on login if there are incomplete required fields.
 *
 * @since 1.0
 * 
 * @param string   $user_login The user login name.
 * @param WP_User  $user       The WP_User object of the logged-in user.
 */
function pmprofpc_redirect_on_login( $user_login, $user ) {
	
	// Let's make sure we're not logging in at PMPro checkout.
	if ( pmpro_is_checkout() ) {
		return;
	}

	$incomplete_fields = pmprofpc_get_incomplete_fields( $user->ID );
	if ( empty( $incomplete_fields ) ) {
		return;
	}

	// Redirect to the profile edit page.
	global $pmpro_pages;
	if ( ! empty( $pmpro_pages['member_profile_edit'] ) ) {
		wp_safe_redirect( get_permalink( $pmpro_pages['member_profile_edit'] ) );
		exit;
	}
}
add_action( 'wp_login', 'pmprofpc_redirect_on_login', 10, 2 );

/**
 * Clear the cache when the person's profile is updated.
 *
 * @since 1.0
 * 
 * @param int $user_id The WordPress user ID.
 */
function pmprofpc_clear_incomplete_fields_cache( $user_id ) {
	delete_transient( 'pmprofpc_incomplete_fields_' . $user_id );
}
add_action( 'pmpro_personal_options_update', 'pmprofpc_clear_incomplete_fields_cache' );
add_action( 'profile_update', 'pmprofpc_clear_incomplete_fields_cache' );
