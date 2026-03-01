document.addEventListener('click', function (event) {
	const trigger = event.target.closest('[data-mws-copy-target]');

	if (!trigger) {
		return;
	}

	const sourceName = trigger.getAttribute('data-mws-copy-target');
	const source = document.querySelector('[data-mws-copy-source="' + sourceName + '"]');

	if (!source) {
		return;
	}

	source.select();
	source.setSelectionRange(0, source.value.length);

	if (navigator.clipboard && window.isSecureContext) {
		navigator.clipboard.writeText(source.value);
	} else {
		document.execCommand('copy');
	}

	trigger.textContent = 'Copied';
	window.setTimeout(function () {
		trigger.textContent = 'Copy snippet';
	}, 1200);
});

document.addEventListener('click', function (event) {
	const addButton = event.target.closest('[data-mws-add-row]');
	const removeButton = event.target.closest('[data-mws-remove-row]');

	if (addButton) {
		const wrapper = document.querySelector('[data-mws-sites]');
		const list = wrapper ? wrapper.querySelector('[data-mws-sites-list]') : null;
		const template = document.querySelector('[data-mws-site-template]');

		if (!list || !template) {
			return;
		}

		const index = list.querySelectorAll('[data-mws-site-row]').length;
		const html = template.innerHTML.replaceAll('__INDEX__', String(index));
		list.insertAdjacentHTML('beforeend', html);
		return;
	}

	if (removeButton) {
		const row = removeButton.closest('[data-mws-site-row]');

		if (row) {
			row.remove();
		}
	}
});
