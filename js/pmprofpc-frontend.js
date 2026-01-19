jQuery(document).ready(function() {
jQuery('.pmpro_form_field-required').each(function() {
		// Check if there's an asterisk already
		var $firstLabel = jQuery(this).find('.pmpro_form_label').first();
		var $hasAsterisk = $firstLabel.find('.pmpro_asterisk').length > 0;
	
		// If there's no asterisk, add one
		if ( ! $hasAsterisk ) {
			$firstLabel.append('<span class="pmpro_asterisk"> <abbr title="Required Field">*</abbr></span>');
		}

		// Add the aria-required="true" attribute to the input.
		jQuery(this).find('.pmpro_form_input').attr('aria-required', 'true');
	});

	jQuery('.pmpro_form_input-required').each(function() {
		// Check if there's an asterisk already
		var $fieldDiv = jQuery(this).closest('.pmpro_form_field');
		var $firstLabel = $fieldDiv.find('.pmpro_form_label').first();
		var $hasAsterisk = $firstLabel.find('.pmpro_asterisk').length > 0;

		// If there's no asterisk, add one
		if ( ! $hasAsterisk ) {
			$firstLabel.append('<span class="pmpro_asterisk"> <abbr title="Required Field">*</abbr></span>');
		}

		// Add the aria-required="true" attribute to the input.
		jQuery(this).find('.pmpro_form_input').attr('aria-required', 'true');
	});

	//unhighlight error fields when the user edits them
	jQuery('.pmpro_form_input-error').bind("change keyup input", function() {
		jQuery(this).removeClass('pmpro_form_input-error');
	});
});