<?php declare(strict_types = 0);
/*
** Zabbix
** Copyright (C) 2001-2023 Zabbix SIA
**
** This program is free software; you can redistribute it and/or modify
** it under the terms of the GNU General Public License as published by
** the Free Software Foundation; either version 2 of the License, or
** (at your option) any later version.
**
** This program is distributed in the hope that it will be useful,
** but WITHOUT ANY WARRANTY; without even the implied warranty of
** MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
** GNU General Public License for more details.
**
** You should have received a copy of the GNU General Public License
** along with this program; if not, write to the Free Software
** Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston, MA  02110-1301, USA.
**/


use Zabbix\Widgets\Fields\CWidgetFieldPieChartDataSet;

?>

window.widget_piechart_form = new class {

	init({form_tabs_id, color_palette, templateid}) {
		colorPalette.setThemeColors(color_palette);

		this._$overlay_body = jQuery('.overlay-dialogue-body');
		this._form = document.getElementById('widget-dialogue-form');
		this._templateid = templateid;
		this._dataset_wrapper = document.getElementById('data_sets');

		this._$overlay_body.on('scroll', () => {
			this._$overlay_body.off('scroll');
		});

		jQuery(`#${form_tabs_id}`)
			.on('tabsactivate', () => jQuery.colorpicker('hide'))
			.on('change', 'input, z-select, .multiselect', (e) => this.onChartConfigChange(e));

		this._datasetTabInit();
		this._displayingOptionsTabInit();
		this._toggleDisplayingOptionsFields();
		this._timePeriodTabInit();
		this._legendTabInit();

		this.onChartConfigChange();
	}

	onChartConfigChange() {
		this._updateForm();
	}

	updateVariableOrder(obj, row_selector, var_prefix) {
		for (const k of [10000, 0]) {
			jQuery(row_selector, obj).each(function(i) {
				if (var_prefix === 'ds') {
					jQuery(this).attr('data-set', i);
					jQuery('.single-item-table', this).attr('data-set', i);
				}

				jQuery('.multiselect[data-params]', this).each(function() {
					const name = jQuery(this).multiSelect('getOption', 'name');

					if (name !== null) {
						jQuery(this).multiSelect('modify', {
							name: name.replace(/([a-z]+\[)\d+(]\[[a-z_]+])/, `$1${k + i}$2`)
						});
					}
				});

				jQuery(`[name^="${var_prefix}["]`, this)
					.filter(function () {
						return jQuery(this).attr('name').match(/[a-z]+\[\d+]\[[a-z_]+]/);
					})
					.each(function () {
						jQuery(this).attr('name',
							jQuery(this).attr('name').replace(/([a-z]+\[)\d+(]\[[a-z_]+])/, `$1${k + i}$2`)
						);
					});
			});
		}
	}

	_datasetTabInit() {
		this._updateDatasetsLabel();

		// Initialize vertical accordion.
		jQuery(this._dataset_wrapper)
			.on('focus', '.<?= CMultiSelect::ZBX_STYLE_CLASS ?> input.input', function() {
				jQuery('#data_sets').zbx_vertical_accordion('expandNth',
					jQuery(this).closest('.<?= ZBX_STYLE_LIST_ACCORDION_ITEM ?>').index()
				);
			})
			.on('click', function(e) {
				if (!e.target.classList.contains('color-picker-preview')) {
					jQuery.colorpicker('hide');
				}

				if (e.target.classList.contains('js-click-expend')
					|| e.target.classList.contains('color-picker-preview')
					|| e.target.classList.contains('<?= ZBX_STYLE_BTN_GREY ?>')) {
					jQuery('#data_sets').zbx_vertical_accordion('expandNth',
						jQuery(e.target).closest('.<?= ZBX_STYLE_LIST_ACCORDION_ITEM ?>').index()
					);
				}
			})
			.on('collapse', function(event, data) {
				jQuery('textarea, .multiselect', data.section).scrollTop(0);
				jQuery(window).trigger('resize');
				const dataset = data.section[0];

				if (dataset.dataset.type == '<?= CWidgetFieldPieChartDataSet::DATASET_TYPE_SINGLE_ITEM ?>') {
					const message_block = dataset.querySelector('.no-items-message');

					if (dataset.querySelectorAll('.single-item-table-row').length == 0) {
						message_block.style.display = 'block';
					}
				}
			})
			.on('expand', function(event, data) {
				jQuery(window).trigger('resize');
				const dataset = data.section[0];

				if (dataset.dataset.type == '<?= CWidgetFieldPieChartDataSet::DATASET_TYPE_SINGLE_ITEM ?>') {
					const message_block = dataset.querySelector('.no-items-message');

					if (dataset.querySelectorAll('.single-item-table-row').length == 0) {
						message_block.style.display = 'none';
					}

					widget_piechart_form._initSingleItemSortable(dataset);
				}
			})
			.zbx_vertical_accordion({handler: '.<?= ZBX_STYLE_LIST_ACCORDION_ITEM_TOGGLE ?>'});

		// Initialize pattern fields.
		jQuery('.multiselect', jQuery(this._dataset_wrapper)).each(function() {
			jQuery(this).multiSelect(jQuery(this).data('params'));
		});

		for (const colorpicker of jQuery('.<?= ZBX_STYLE_COLOR_PICKER ?> input')) {
			jQuery(colorpicker).colorpicker({
				onUpdate: function(color) {
					jQuery('.<?= ZBX_STYLE_COLOR_PREVIEW_BOX ?>',
						jQuery(this).closest('.<?= ZBX_STYLE_LIST_ACCORDION_ITEM ?>')
					).css('background-color', `#${color}`);
				},
				appendTo: '.overlay-dialogue-body'
			});
		}

		this._dataset_wrapper.addEventListener('click', (e) => {
			if (e.target.classList.contains('js-add-item')) {
				this._selectItems();
			}

			if (e.target.classList.contains('element-table-remove')) {
				this._removeSingleItem(e.target);
			}

			if (e.target.classList.contains('btn-remove')) {
				this._removeDataSet(e.target);
			}
		});

		document
			.getElementById('dataset-add')
			.addEventListener('click', () => {
				this._addDataset(<?= CWidgetFieldPieChartDataSet::DATASET_TYPE_PATTERN_ITEM ?>);
			});

		document
			.getElementById('dataset-menu')
			.addEventListener('click', (e) => this._addDatasetMenu(e));

		window.addPopupValues = (list) => {
			if (!isset('object', list) || list.object !== 'itemid') {
				return false;
			}

			for (let i = 0; i < list.values.length; i++) {
				this._addSingleItem(list.values[i].itemid, list.values[i].name);
			}


			this._updateSingleItemsLinks();
			this._initSingleItemSortable(this._getOpenedDataset());
		}

		this._updateSingleItemsLinks();
		this._initDataSetSortable();

		this._initSingleItemSortable(this._getOpenedDataset());
	}

	_displayingOptionsTabInit() {
		this._form.querySelector('#displaying_options').onchange = () => {
			this._toggleDisplayingOptionsFields();
		};
	}

	_toggleDisplayingOptionsFields() {
		const draw_type = this._form.querySelector('[name="draw_type"]:checked').value;
		const doughnut_config_fields = this._form.querySelectorAll('#width_label, #width_range, #show_total_fields');
		const is_doughnut = draw_type == <?= PIE_CHART_DRAW_DOUGHNUT ?>;
		const merge = document.getElementById('merge');
		const total_value_fields = this._form.querySelectorAll(
			'#value_size, #decimal_places, #units_show, #units, #value_bold, #value_color'
		);

		for (const element of doughnut_config_fields) {
			element.style.display = is_doughnut ? '' : 'none';
			for (const input of element.querySelectorAll('input')) {
				input.disabled = !is_doughnut;
			}
		}

		jQuery('#width').rangeControl(
			is_doughnut ? 'enable' : 'disable'
		);

		document.getElementById('merge_percent').disabled = !merge.checked;
		document.getElementById('merge_color').disabled = !merge.checked;

		for (const element of total_value_fields) {
			element.disabled = !document.getElementById('total_show').checked;
		}

		document.getElementById('units').disabled = !document.getElementById('units_show').checked;
	}

	_timePeriodTabInit() {
		document.getElementById('graph_time')
			.addEventListener('click', (e) => {
				document.getElementById('time_from').disabled = !e.target.checked;
				document.getElementById('time_to').disabled = !e.target.checked;
				document.getElementById('time_from_calendar').disabled = !e.target.checked;
				document.getElementById('time_to_calendar').disabled = !e.target.checked;
			});
	}

	_legendTabInit() {
		document.getElementById('legend')
			.addEventListener('click', (e) => {
				jQuery('#legend_lines').rangeControl(
					e.target.checked ? 'enable' : 'disable'
				);
				jQuery('#legend_columns').rangeControl(
					e.target.checked ? 'enable' : 'disable'
				);
			});
	}

	_updateDatasetsLabel() {
		for (const dataset of this._dataset_wrapper.querySelectorAll('.<?= ZBX_STYLE_LIST_ACCORDION_ITEM ?>')) {
			this._updateDatasetLabel(dataset);
		}
	}

	_updateDatasetLabel(dataset) {
		const placeholder_text = <?= json_encode(_('Data set')) ?> + ` #${parseInt(dataset.dataset.set) + 1}`;

		const data_set_label = dataset.querySelector('.js-dataset-label');
		const data_set_label_input = dataset.querySelector(`[name="ds[${dataset.dataset.set}][data_set_label]"]`);

		data_set_label.textContent = data_set_label_input.value !== '' ? data_set_label_input.value : placeholder_text;
		data_set_label_input.placeholder = placeholder_text;
	}

	_addDatasetMenu(e) {
		const menu = [
			{
				items: [
					{
						label: <?= json_encode(_('Item pattern')) ?>,
						clickCallback: () => {
							this._addDataset(<?= CWidgetFieldPieChartDataSet::DATASET_TYPE_PATTERN_ITEM ?>);
						}
					},
					{
						label: <?= json_encode(_('Item list')) ?>,
						clickCallback: () => {
							this._addDataset(<?= CWidgetFieldPieChartDataSet::DATASET_TYPE_SINGLE_ITEM ?>);
						}
					}
				]
			},
			{
				items: [
					{
						label: <?= json_encode(_('Clone')) ?>,
						disabled: this._getOpenedDataset() === null,
						clickCallback: () => {
							this._cloneDataset();
						}
					}
				]
			}
		];

		jQuery(e.target).menuPopup(menu, new jQuery.Event(e), {
			position: {
				of: e.target,
				my: 'left top',
				at: 'left bottom',
				within: '.wrapper'
			}
		});
	}

	_addDataset(type) {
		jQuery(this._dataset_wrapper).zbx_vertical_accordion('collapseAll');

		const template = type == <?= CWidgetFieldPieChartDataSet::DATASET_TYPE_SINGLE_ITEM ?>
			? new Template(jQuery('#dataset-single-item-tmpl').html())
			: new Template(jQuery('#dataset-pattern-item-tmpl').html());

		const used_colors = [];

		for (const color of this._form.querySelectorAll('.<?= ZBX_STYLE_COLOR_PICKER ?> input')) {
			if (color.value !== '') {
				used_colors.push(color.value);
			}
		}

		this._dataset_wrapper.insertAdjacentHTML('beforeend', template.evaluate({
			rowNum: this._dataset_wrapper.querySelectorAll('.<?= ZBX_STYLE_LIST_ACCORDION_ITEM ?>').length,
			color: type == <?= CWidgetFieldPieChartDataSet::DATASET_TYPE_SINGLE_ITEM ?>
				? ''
				: colorPalette.getNextColor(used_colors)
		}));

		const dataset = this._getOpenedDataset();

		for (const colorpicker of dataset.querySelectorAll('.<?= ZBX_STYLE_COLOR_PICKER ?> input')) {
			jQuery(colorpicker).colorpicker({appendTo: '.overlay-dialogue-body'});
		}

		for (const multiselect of dataset.querySelectorAll('.multiselect')) {
			jQuery(multiselect).multiSelect(jQuery(multiselect).data('params'));
		}

		this._$overlay_body.scrollTop(Math.max(this._$overlay_body.scrollTop(),
			this._form.scrollHeight - this._$overlay_body.height()
		));

		this._initDataSetSortable();
		this._updateForm();
	}

	_cloneDataset() {
		const dataset = this._getOpenedDataset();

		this._addDataset(dataset.dataset.type);

		const cloned_dataset = this._getOpenedDataset();

		if (dataset.dataset.type == <?= CWidgetFieldPieChartDataSet::DATASET_TYPE_SINGLE_ITEM ?>) {
			for (const row of dataset.querySelectorAll('.single-item-table-row')) {
				this._addSingleItem(
					row.querySelector(`[name^='ds[${dataset.getAttribute('data-set')}][itemids]`).value,
					row.querySelector('.table-col-name a').textContent
				);
			}

			this._updateSingleItemsLinks();
			this._initSingleItemSortable(cloned_dataset);
		}
		else {
			if (this._templateid === null) {
				jQuery('.js-hosts-multiselect', cloned_dataset).multiSelect('addData',
					jQuery('.js-hosts-multiselect', dataset).multiSelect('getData')
				);
			}

			jQuery('.js-items-multiselect', cloned_dataset).multiSelect('addData',
				jQuery('.js-items-multiselect', dataset).multiSelect('getData')
			);
		}

		for (const input of dataset.querySelectorAll('[name^=ds]')) {
			const cloned_name = input.name.replace(/([a-z]+\[)\d+(]\[[a-z_]+])/,
				`$1${cloned_dataset.getAttribute('data-set')}$2`
			);

			if (input.tagName.toLowerCase() === 'z-select') {
				cloned_dataset.querySelector(`[name="${cloned_name}"]`).value = input.value;
			}
			else if (input.type === 'text') {
				cloned_dataset.querySelector(`[name="${cloned_name}"]`).value = input.value;
			}
		}

		this._updateDatasetLabel(cloned_dataset);
	}

	_removeDataSet(obj) {
		obj
			.closest('.list-accordion-item')
			.remove();

		this.updateVariableOrder(jQuery(this._dataset_wrapper), '.<?= ZBX_STYLE_LIST_ACCORDION_ITEM ?>', 'ds');
		this._updateDatasetsLabel();

		const dataset = this._getOpenedDataset();

		if (dataset !== null) {
			this._updateSingleItemsOrder(dataset);
			this._initSingleItemSortable(dataset);
		}

		this._initDataSetSortable();
		this._updateSingleItemsLinks();
		this.onChartConfigChange();
	}

	_getOpenedDataset() {
		return this._dataset_wrapper.querySelector('.<?= ZBX_STYLE_LIST_ACCORDION_ITEM_OPENED ?>[data-set]');
	}

	_initDataSetSortable() {
		const datasets_count = this._dataset_wrapper.querySelectorAll('.<?= ZBX_STYLE_LIST_ACCORDION_ITEM ?>').length;

		for (const drag_icon of this._dataset_wrapper.querySelectorAll('.js-main-drag-icon')) {
			drag_icon.classList.toggle('disabled', datasets_count < 2);
		}

		if (this._sortable_data_set === undefined) {
			this._sortable_data_set = new CSortable(
				document.querySelector('#data_set .<?= ZBX_STYLE_LIST_VERTICAL_ACCORDION ?>'),
				{is_vertical: true}
			);

			this._sortable_data_set.on(SORTABLE_EVENT_DRAG_END, () => {
				this.updateVariableOrder(this._dataset_wrapper, '.<?= ZBX_STYLE_LIST_ACCORDION_ITEM ?>', 'ds');
				this._updateDatasetsLabel();
			});
		}
	}

	_selectItems() {
		if (this._templateid === null) {
			PopUp('popup.generic', {
				srctbl: 'items',
				srcfld1: 'itemid',
				srcfld2: 'name',
				dstfrm: this._form.id,
				numeric: 1,
				writeonly: 1,
				multiselect: 1,
				with_webitems: 1,
				real_hosts: 1
			});
		}
		else {
			PopUp('popup.generic', {
				srctbl: 'items',
				srcfld1: 'itemid',
				srcfld2: 'name',
				dstfrm: this._form.id,
				numeric: 1,
				writeonly: 1,
				multiselect: 1,
				with_webitems: 1,
				hostid: this._templateid,
				hide_host_filter: 1
			});
		}
	}

	_addSingleItem(itemid, name) {
		const dataset = this._getOpenedDataset();
		const items_table = dataset.querySelector('.single-item-table');

		if (items_table.querySelector(`input[value="${itemid}"]`) !== null) {
			return;
		}

		const dataset_index = dataset.getAttribute('data-set');
		const template = new Template(jQuery('#dataset-item-row-tmpl').html());
		const item_next_index = items_table.querySelectorAll('.single-item-table-row').length + 1;

		items_table.querySelector('tbody').insertAdjacentHTML('beforeend', template.evaluate({
			dsNum: dataset_index,
			rowNum: item_next_index,
			name: name,
			itemid: itemid
		}));

		const used_colors = [];

		for (const color of this._form.querySelectorAll('.<?= ZBX_STYLE_COLOR_PICKER ?> input')) {
			if (color.value !== '') {
				used_colors.push(color.value);
			}
		}

		jQuery(`#items_${dataset_index}_${item_next_index}_color`)
			.val(colorPalette.getNextColor(used_colors))
			.colorpicker();
	}

	_removeSingleItem(element) {
		element.closest('.single-item-table-row').remove();

		const dataset = this._getOpenedDataset();

		this._updateSingleItemsOrder(dataset);
		this._updateSingleItemsLinks();
		this._initSingleItemSortable(dataset);
	}

	_initSingleItemSortable(dataset) {
		const item_rows = dataset.querySelectorAll('.single-item-table-row');

		if (item_rows.length < 1) {
			return;
		}

		for (const row of item_rows) {
			row.querySelector('.<?= ZBX_STYLE_DRAG_ICON ?>').classList.toggle('disabled', item_rows.length < 2);
		}

		jQuery(`.single-item-table`, dataset).sortable({
			disabled: item_rows.length < 2,
			items: 'tbody .single-item-table-row',
			axis: 'y',
			containment: 'parent',
			cursor: 'grabbing',
			handle: 'div.<?= ZBX_STYLE_DRAG_ICON ?>',
			tolerance: 'pointer',
			opacity: 0.6,
			update: () => {
				this._updateSingleItemsOrder(dataset);
				this._updateSingleItemsLinks();
			},
			helper: (e, ui) => {
				for (const td of ui.find('>td')) {
					const $td = jQuery(td);
					$td.attr('width', $td.width());
				}

				// When dragging element on safari, it jumps out of the table.
				if (SF) {
					// Move back draggable element to proper position.
					ui.css('left', (ui.offset().left - 2) + 'px');
				}

				return ui;
			},
			stop: (e, ui) => {
				ui.item.find('>td').removeAttr('width');
			},
			start: (e, ui) => {
				jQuery(ui.placeholder).height(jQuery(ui.helper).height());
			}
		});
	}

	_updateSingleItemsLinks() {
		for (const dataset of this._dataset_wrapper.querySelectorAll('.<?= ZBX_STYLE_LIST_ACCORDION_ITEM ?>')) {
			const dataset_index = dataset.getAttribute('data-set');
			const size = dataset.querySelectorAll('.single-item-table-row').length + 1;

			for (let i = 0; i < size; i++) {
				jQuery(`#items_${dataset_index}_${i}_name`).off('click').on('click', () => {
					let ids = [];
					for (let i = 0; i < size; i++) {
						ids.push(jQuery(`#items_${dataset_index}_${i}_input`).val());
					}

					if (this._templateid === null) {
						PopUp('popup.generic', {
							srctbl: 'items',
							srcfld1: 'itemid',
							srcfld2: 'name',
							dstfrm: widget_piechart_form._form.id,
							dstfld1: `items_${dataset_index}_${i}_input`,
							dstfld2: `items_${dataset_index}_${i}_name`,
							numeric: 1,
							writeonly: 1,
							with_webitems: 1,
							real_hosts: 1,
							dialogue_class: 'modal-popup-generic',
							excludeids: ids
						});
					}
					else {
						PopUp('popup.generic', {
							srctbl: 'items',
							srcfld1: 'itemid',
							srcfld2: 'name',
							dstfrm: widget_piechart_form._form.id,
							dstfld1: `items_${dataset_index}_${i}_input`,
							dstfld2: `items_${dataset_index}_${i}_name`,
							numeric: 1,
							writeonly: 1,
							with_webitems: 1,
							hostid: this._templateid,
							hide_host_filter: 1,
							dialogue_class: 'modal-popup-generic',
							excludeids: ids
						});
					}
				});
			}
		}
	}

	_updateSingleItemsOrder(dataset) {
		jQuery.colorpicker('destroy', jQuery('.single-item-table .<?= ZBX_STYLE_COLOR_PICKER ?> input', dataset));

		const dataset_index = dataset.getAttribute('data-set');

		for (const row of dataset.querySelectorAll('.single-item-table-row')) {
			const prefix = `items_${dataset_index}_${row.rowIndex}`;

			row.querySelector('.table-col-no span').textContent = `${row.rowIndex}:`;
			row.querySelector('.table-col-name a').id = `${prefix}_name`;
			row.querySelector('.table-col-action input').id = `${prefix}_input`;

			const colorpicker = row.querySelector('.single-item-table .<?= ZBX_STYLE_COLOR_PICKER ?> input');

			colorpicker.id = `${prefix}_color`;
			jQuery(colorpicker).colorpicker({appendTo: '.overlay-dialogue-body'});
		}
	}

	_updateForm() {

		const dataset = this._getOpenedDataset();

		if (dataset !== null) {
			this._updateDatasetLabel(dataset);
		}

		// Trigger event to update tab indicators.
		document.getElementById('tabs').dispatchEvent(new Event(TAB_INDICATOR_UPDATE_EVENT));
	}
};
