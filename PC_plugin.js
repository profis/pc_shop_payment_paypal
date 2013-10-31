
PC.utils.localize('mod.pc_shop', {
	lt: {
		import_method: {
			car_part: 'Automobilių detalių importas'
		}
	},
	ru: {
		import_method: {
			car_part: 'Импорт запчастей для автомобилей'
		}
	},
	en: {
		import_method: {
			car_part: 'Car parts import'
		}
	}
});

//Plugin_automera.ln = PC.i18n.mod[CurrentlyParsing];

PC.hooks.Register('plugin/pc_shop/category_tab_for_properties_', function(tab) {
	var fields_configs = {
		'_hot': {
			fieldLabel: 'Tai automobilis'
		}
	}
	var total_items = tab.items.length;
	for(var i = 0; i < total_items; i++) {
		if (fields_configs[tab.items[i].ref]) {
			Ext.apply(tab.items[i], fields_configs[tab.items[i].ref]);
		}
	};
});