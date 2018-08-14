function copyToClipboard(text) {
	var aux = document.createElement('input');
	aux.setAttribute('value', text);
	document.body.appendChild(aux);
	aux.select();
	document.execCommand('copy');
	document.body.removeChild(aux);
}

(function ($) {
	$.fn.removeClassWild = function (mask) {
		return this.removeClass(function (index, cls) {
			var re = mask.replace(/\*/g, '\\S+');
			return (cls.match(new RegExp('\\b' + re + '', 'g')) || []).join(' ');
		});
	};
})(jQuery);

(function ($) {
	var origAppend = $.fn.append;
	$.fn.append = function () {
		return origAppend.apply(this, arguments).trigger('append');
	};
})(jQuery);

$(document).ready(() => {

	$('.fa-copy').removeClass('d-none');

	$(document).on('click', '.fa-copy', e => {
		copyToClipboard($(e.target).data('text'));
		toastr.info('Text successfully copied');
	});

	$(document).on('append', '.fa-copy', e => {
		$(e.target).removeClass('d-none');
	});

});
