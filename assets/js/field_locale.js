/* global $ */
$(function() {
	// Prepend translation icons to translatable fields.
	$($('#translatable-fields').val()).closest('fieldset').each(function(i, el){
		$(el).find('label').first().prepend('<i class="fa fa-language icon"></i> ');
	})
	
	// Move locale switcher to tab navigation
	$("#locale-select").detach().appendTo('#filtertabs');
	
	var currentLocale;
	
	$(Bolt).on('done.bolt.content.save', function(){
		$('#locale-select [data-locale="'+currentLocale+'"]').trigger('click', true);
	});
	
	$('#locale-select a').click(function(e, force) {
		e.preventDefault();
		var el = $(this),
			locale = el.data('locale');
			
		currentLocale = locale;

		if(el.parent().hasClass('disabled') && !force) {
			return false;
		}

		lockInputFields(true);

		$('#locale-select button .selected').html(el.text());

		el.parent()
			.addClass('disabled')
			.siblings()
			.removeClass('disabled');
		
		var boltField = $('[data-bolt-field="slug"]');
		var fconf = boltField.data('bolt-fconf');
		$.each(fconf.uses, function (i, name) {
			$('#' + name).off('propertychange.bolt input.bolt change.bolt');
		});
		
		$.get(el.attr('href'), {
			locale: locale,
			content_type: $('#contenttype').val(),
			content_type_id: $('#id').val()
		}, function(data) {
			console.log(data)
			$.each(data, function() {
				if(this.field == "templatefields"){
					try{
						var val = JSON.parse(this.value);
						for (var field in val) {
							setValue('templatefields-' + field, val[field]);
						}
						console.log(val)
					} catch (e) {
						console.log(e)
					}
				}else{
					setValue(this.field, this.value);
				}
			})
			$('#locale').val(locale);
			lockInputFields(false);
			//Bolt.app.run()
		})
		.fail(function() {
			// show warning
			lockInputFields(false);
		});
	});
});

function lockInputFields(status) {
	$('#editcontent').find('input, select, textarea').prop('disabled', status);
	$.each(CKEDITOR.instances, function() {
		this.setReadOnly(status);
	});
}

function setValue(field, value) {
	var el = $('#' + field + ', #field-' + field + ', [data-bolt-field="' + field + '"], #' + field + '-video, #gridfield-' + field);
	var parent = el.closest('.form-group[data-fieldtype]');
	if(parent.length == 0 && field == 'seo'){
		parent = $('#seo').closest('.tab-pane').find('[data-fieldtype="seo"]')
	}
	var type = parent.attr('data-fieldtype');
	var boltField = $('[data-bolt-field="slug"]');
	var fconf = boltField.data('bolt-fconf');
	switch (type) {
		case 'integer':
		case 'float':
		case 'textarea':
		case 'text':
			el.val(value);
			break;
		case 'html':
			CKEDITOR.instances[el.attr('name')].setData(value);
			break;
		case 'markdown':
			parent.find('.CodeMirror').get(0).CodeMirror.setValue(value);
			break;
		case 'checkbox':
			if(value == 1){
				el.prop('checked', true);
			}else{
				el.prop('checked', false);
			}
			break;
		case 'slug':
			if (value.trim() == ''){
				fconf.isEmpty = true;
			} else {
				fconf.isEmpty = false;
				parent.find('.input-group').removeClass('unlocked').addClass('locked');
				$.each(fconf.uses, function (i, name) {
					$('#' + name).off('propertychange.bolt input.bolt change.bolt');
				});
			}
			boltField.get(0).dataset.boltFconf = JSON.stringify(fconf);
			parent.find('input').val(value);
			parent.find('em').html(value);
			Bolt.fields.slug.init(boltField, fconf);
			break;
		case 'datetime':
		case 'date':
			el.val(value);
			Bolt.datetime.update();
			break;
		case 'select':
			if(fconf.uses.indexOf(field)){
				el.off('propertychange.bolt input.bolt change.bolt');
			}
			if(el.attr('multiple')){
				try {
					el.val(JSON.parse(value))
				} catch (e) {
					console.log('not valid json for field ' + field)
				}
			}else{
				el.val(value);
			}
			break;
		case 'geolocation':
			parent.find('input').val('');
			try {
				value = JSON.parse(value);
				for (var key in value) {
					$('[name="'+field+'['+key+']"]').val(value[key]).trigger('input')
				}
			} catch (e) {
				console.log('not valid json for field ' + field)
			}
			break;
		case 'image':
			parent.find('input').val('');
			try {
				value = JSON.parse(value);
				for (var key in value) {
					$('[name="'+field+'['+key+']"]').val(value[key])
				}
				$('#thumbnail-' + field).html('<img src="' + Bolt.conf('paths.root') + 'thumbs/200x150c/' +
                            encodeURI(value.file) + '" width="200" height="150">');
			} catch (e) {
				parent.find('.content-preview img').attr('src','/app/view/img/default_empty_4x3.png')
				console.log('not valid json for field ' + field)
			}
			break;
		case 'video':
			try {
				value = JSON.parse(value);
				$('[name="'+field+'[url]"]').val(value.url).trigger('input');
			} catch (e) {
				$('[name="'+field+'[url]"]').val('').trigger('input');
				console.log('not valid json for field ' + field)
			}
			break;
		case 'filelist':
		case 'imagelist':
			Bolt[type][field].list.reset();
			Bolt[type][field].render();
			try {
				value = JSON.parse(value);
				for (var key in value) {
					Bolt[type][field].add(value[key].filename, value[key].title)
				}
			} catch (e) {
				console.log('not valid json for field ' + field)
			}
			
			break;
		case 'seo':
			try {
				value = JSON.parse(value);
				$.each(value, function (subindex, subitem) {
					$('#seofields-' + subindex).val(subitem).trigger('input').trigger('blur');
				});
				el.val(value).trigger('input').trigger('blur');
			} catch (e) {
				console.log('Error on SEO field: ', field, value);
				el.val(value);
			}
			break;
		case 'grid':
			if(value == "" || value == "[]"){
				value = "[[],[]]"
			}
			el.val(value);
			document.getElementById('hot-'+field).hot.loadData(JSON.parse(value));
			break;
		case 'faiconpicker':
			el.val(value);
			parent.find('[title=".'+value+'"]').trigger('click');
			break;
		case 'bootstrapcolorpicker':
			el.val(value);
			el.colorpicker('setValue', value);
			break;
		default:
			try {
				value = JSON.parse(value);
				if ($.type(value) === 'object') {
					$.each(value, function (subindex, subitem) {
						$(':input[name="' + field + '[' + subindex + ']"]').val(subitem).trigger('input').trigger('blur');
					});
				}else{
					console.log('Unkown field with non-objects, trying to set with val: ', field, value);
					el.val(value).trigger('input').trigger('blur');
				}
			} catch (e) {
				el.val(value).trigger('input').trigger('blur');
			}
			break;
	}
}

// See http://stackoverflow.com/a/9116746/709769
$.fn.getType = function() {
	return this[0].tagName == 'INPUT' ? this[0].type.toLowerCase() : this[0].tagName.toLowerCase();
}
