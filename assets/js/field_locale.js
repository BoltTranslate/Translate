$(function() {
	// Prepend translation icons to translatable fields.
	$($('#translatable-fields').val()).closest('fieldset').find('label').prepend('<i class="fa fa-language icon"></i> ');

	// Move locale switcher to tab navigation
	$("#locale-select").detach().appendTo('#filtertabs');

	$('#locale-select a').click(function(e) {
		e.preventDefault();

		var el = $(this),
			locale = el.data('locale');

		if(el.parent().hasClass('disabled')) {
			return false;
		}

		lockInputFields(true);

		$('#locale-select button .selected').html(el.text());

		el.parent()
			.addClass('disabled')
			.siblings()
			.removeClass('disabled');

		$.get(el.attr('href'), {
				locale: locale,
				content_type: $('#contenttype').val(),
				content_type_id: $('#id').val()
			}, function(data) {
				$.each(data, function() {
					setValue(this.field, this.value);
				})

				$('#locale').val(locale);
				lockInputFields(false);
			})
			.fail(function() {
				// show warning
				lockInputFields(false);
			});
	});

	$('#locale').change(function() {
		alert('yo');
	});
});

function lockInputFields(status) {
	$('#editcontent').find('input, select, textarea').prop('disabled', status);
	$.each(CKEDITOR.instances, function() {
		this.setReadOnly(status);
	});
}

function setValue(field, value) {
	var el = $('#' + field);

	switch (el.getType()) {
		case 'textarea':
			if(el.hasClass('ckeditor')) {
				CKEDITOR.instances[el.attr('name')].setData(value);
			} else {
				el.html(value);
			}
			break;
		default:
			el.val(value);
	}
}

// See http://stackoverflow.com/a/9116746/709769
$.fn.getType = function() {
	return this[0].tagName == 'INPUT' ? this[0].type.toLowerCase() : this[0].tagName.toLowerCase();
}
