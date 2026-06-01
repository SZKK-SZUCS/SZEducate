<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class SZEducate_Schema {

	private $option_name = 'szeducate_schema';

	public function init() {
		add_action( 'admin_menu', array( $this, 'add_schema_page' ) );
		// Nincs szükség külön admin_init-re a mentéshez, mert a render_page-ben lekezeljük
	}

	public function add_schema_page() {
		add_submenu_page(
			'szeducate-settings',
			'Séma Tervező',
			'Séma Tervező',
			'manage_options',
			'szeducate-schema',
			array( $this, 'render_page' )
		);
	}

	public function render_page() {
		// --- 1. TISZTA PHP MENTÉSI LOGIKA ---
		if ( isset( $_POST['szeducate_schema'] ) && isset( $_POST['szeducate_schema_nonce'] ) ) {
			// Biztonsági ellenőrzés
			if ( wp_verify_nonce( $_POST['szeducate_schema_nonce'], 'save_szeducate_schema_action' ) ) {
				$new_schema = wp_unslash( $_POST['szeducate_schema'] );
				update_option( $this->option_name, $new_schema );
				echo '<div class="notice notice-success is-dismissible" style="margin-top:20px;"><p><strong>✅ A Séma sikeresen elmentve a Hub-on!</strong></p></div>';
			} else {
				echo '<div class="notice notice-error is-dismissible" style="margin-top:20px;"><p>Biztonsági hiba (lejárt session). Kérjük frissítse az oldalt!</p></div>';
			}
		}

		// --- 2. JELENLEGI ADATOK LEKÉRÉSE ---
		$schema = get_option( $this->option_name, '[]' );

		// Alapértelmezett séma struktúra, ha teljesen üres lenne
		if ( empty( json_decode( $schema, true ) ) ) {
			$schema = wp_json_encode( [
				[
					'group_id'    => 'alap_adatok',
					'group_label' => 'Alap adatok',
					'is_locked'   => true,
					'fields'      => [
						[ 'key' => 'kepzesi_forma', 'label' => 'Képzési Forma', 'type' => 'select', 'options' => 'BSc, MSc, Osztatlan, Felsőoktatási szakképzés, Szakirányú továbbképzés, Mikroképzés, Előkészítő', 'is_required' => true, 'is_filterable' => true, 'is_locked' => true ],
					]
				]
			] );
		}
		?>
		<div class="wrap">
			<h1>SZEducate Séma Tervező</h1>
			<p>Tervezd meg a képzések adatszerkezetét! Az itt létrehozott mezőket a Kliensek (karok) tölthetik ki.</p>
			
			<div class="notice notice-info" style="margin: 15px 0 25px 0; padding: 15px; border-left-color: #007cba; background: #fff; box-shadow: 0 1px 1px rgba(0,0,0,.04);">
				<p style="margin-top: 0;"><strong>Tipp: Közös mezők több csoportban</strong></p>
				<p style="margin-bottom: 0;">Ha egy mezőt több különböző fülön (csoportban) is meg szeretnél jeleníteni, hozd létre a mezőt mindkét csoportban, és <strong>állítsd be nekik pontosan ugyanazt az "Azonosítót"</strong>! A rendszer ekkor kék "Közös mező" jelvénnyel jelöli őket, és tudni fogja, hogy az értéküket szinkronban kell tartani.</p>
			</div>
			
			<form method="post" id="szeducate-schema-form">
				<?php wp_nonce_field( 'save_szeducate_schema_action', 'szeducate_schema_nonce' ); ?>
				
				<input type="hidden" name="szeducate_schema" id="szeducate_schema_data" value="<?php echo esc_attr( $schema ); ?>">
				
				<div id="schema-builder-container" style="max-width: 1000px;">
					<!-- Dinamikus tartalom -->
				</div>
				
				<button type="button" id="add-group-btn" class="button button-secondary" style="margin-top: 20px;">+ Új Csoport (Fül) Hozzáadása</button>
				
				<div style="margin-top: 30px;">
					<?php submit_button( 'Séma Mentése és Véglegesítése', 'primary', 'submit', false, array('id' => 'save-schema-btn') ); ?>
				</div>
			</form>
		</div>

		<script src="https://cdn.jsdelivr.net/npm/sortablejs@latest/Sortable.min.js"></script>
		
		<style>
			#schema-builder-container { font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Oxygen-Sans, Ubuntu, Cantarell, "Helvetica Neue", sans-serif; }
			.sz-input { border: 1px solid #8c8f94; border-radius: 4px; padding: 6px 10px; font-size: 14px; line-height: 1.4; box-shadow: none; transition: all 0.2s; }
			.sz-input:focus { border-color: #2271b1; outline: none; box-shadow: 0 0 0 1px #2271b1; }
			.sz-select { border: 1px solid #8c8f94; border-radius: 4px; padding: 5px 30px 5px 10px; font-size: 14px; height: 32px; }
			.drag-handle { cursor: grab; font-size: 18px; color: #a7aaad; user-select: none; padding: 5px; margin-right: 5px; display: flex; align-items: center; letter-spacing: -2px; font-weight: bold; }
			.drag-handle:active { cursor: grabbing; color: #2271b1; }

			.szeducate-group { background: #fff; border: 1px solid #c3c4c7; border-radius: 6px; margin-bottom: 24px; box-shadow: 0 1px 3px rgba(0,0,0,0.04); overflow: hidden; transition: box-shadow 0.2s; }
			.szeducate-group:hover { box-shadow: 0 4px 8px rgba(0,0,0,0.05); }
			.szeducate-group-header { display: flex; align-items: center; padding: 15px 20px; background: #f6f7f7; border-bottom: 1px solid #c3c4c7; }
			.szeducate-group.locked .szeducate-group-header { background: #f0f6fc; border-bottom-color: #c8d8e8; }
			.g-inputs { display: flex; gap: 15px; flex: 1; align-items: center; margin-left: 10px; }
			
			.group-conditions { padding: 15px 20px; background: #fafafa; border-bottom: 1px solid #e2e4e7; display: flex; gap: 15px; align-items: center; }
			.group-conditions label { font-weight: 600; color: #50575e; margin-right: 10px; display: flex; align-items: center; gap: 5px; }

			.szeducate-fields-container { padding: 20px; background: #f0f0f1; min-height: 20px; }
			.szeducate-field { background: #fff; border: 1px solid #dcdde1; border-radius: 4px; padding: 15px; margin-bottom: 12px; transition: border-color 0.2s; }
			.szeducate-field:hover { border-color: #a7aaad; }
			.szeducate-field.locked { background: #f9f9f9; border-color: #e2e4e7; }
			
			.sz-field-main { display: flex; gap: 15px; align-items: flex-start; }
			.sz-field-settings { display: flex; gap: 20px; align-items: center; margin-top: 12px; padding-left: 45px; font-size: 13px; color: #646970; }
			.sz-field-settings label { display: flex; align-items: center; gap: 6px; cursor: help; border-bottom: 1px dotted #ccc; }
			.sz-field-settings input[type="checkbox"] { margin: 0; cursor: pointer; }

			.subfields-container { background: #f8f9f9; border: 1px dashed #a7aaad; border-radius: 4px; padding: 15px; margin-top: 15px; margin-left: 45px; }
			.szeducate-subfield { display: flex; gap: 15px; align-items: center; background: #fff; border: 1px solid #dcdde1; padding: 10px; margin-bottom: 8px; border-radius: 3px; }

			.btn-icon { background: none; border: none; cursor: pointer; color: #d63638; font-size: 16px; font-weight: bold; padding: 5px 10px; display: flex; align-items: center; justify-content: center; border-radius: 3px; transition: background 0.2s; }
			.btn-icon:hover { background: #fbeaea; }
			.badge-locked { background: #e5f5fa; color: #007cba; padding: 4px 8px; border-radius: 12px; font-size: 12px; font-weight: 600; }
			.badge-duplicate { background: #e6f0fa; color: #005a9e; padding: 3px 6px; border-radius: 4px; font-size: 11px; font-weight: 600; display: inline-block; border: 1px solid #b3d4fc; text-align: center; }
		</style>

		<script>
		document.addEventListener('DOMContentLoaded', function() {
			const container = document.getElementById('schema-builder-container');
			const addGroupBtn = document.getElementById('add-group-btn');
			const form = document.getElementById('szeducate-schema-form');
			const dataInput = document.getElementById('szeducate_schema_data');
			
			let schemaData = [];
			try { schemaData = JSON.parse(dataInput.value); } catch(e) {}

			const fieldTypes = [
				{ val: 'text', text: 'Rövidszöveg' },
				{ val: 'textarea', text: 'Hosszúszöveg' },
				{ val: 'wysiwyg', text: 'Formázott szöveg (WYSIWYG HTML)' },
				{ val: 'checkbox', text: 'Jelölőnégyzet (Több opció)' },
				{ val: 'radio', text: 'Rádiógomb' },
				{ val: 'select', text: 'Dropdown' },
				{ val: 'multiselect', text: 'Többszörös választó' },
				{ val: 'boolean', text: 'Kapcsoló (Igen/Nem)' },
				{ val: 'number', text: 'Szám' },
				{ val: 'date', text: 'Dátum' },
				{ val: 'url', text: 'Link (1 db URL)' },
				{ val: 'links', text: 'Többszörös Link (URL+Szöveg)' },
				{ val: 'image', text: 'Kép (WP Médiatár)' },
				{ val: 'repeater', text: 'Táblázatos Ismétlődő (Repeater)' }
			];

			const subFieldTypes = [
				{ val: 'text', text: 'Rövidszöveg' },
				{ val: 'number', text: 'Szám' },
				{ val: 'select', text: 'Dropdown' },
				{ val: 'boolean', text: 'Kapcsoló' },
				{ val: 'url', text: 'Link' }
			];

			const lockedGroups = [
				{ id: 'alap_adatok', label: 'Alap adatok' },
				{ id: 'bsc', label: 'BSc' },
				{ id: 'msc', label: 'MSc' },
				{ id: 'osztatlan', label: 'Osztatlan' },
				{ id: 'fsz', label: 'Felsőoktatási szakképzés' },
				{ id: 'sztk', label: 'Szakirányú továbbképzés' },
				{ id: 'mikro', label: 'Mikroképzés' },
				{ id: 'elokeszito', label: 'Előkészítő' }
			];

			lockedGroups.forEach(locked => {
				if (!schemaData.find(g => g.group_id === locked.id)) {
					schemaData.push({ group_id: locked.id, group_label: locked.label, fields: [], is_locked: true });
				} else {
					schemaData.find(g => g.group_id === locked.id).is_locked = true;
				}
			});

			const alapCsoport = schemaData.find(g => g.group_id === 'alap_adatok');
			if (!alapCsoport.fields) alapCsoport.fields = [];
			if (!alapCsoport.fields.find(f => f.key === 'kepzesi_forma')) {
				alapCsoport.fields.unshift({
					key: 'kepzesi_forma', label: 'Képzési Forma', type: 'select',
					options: 'BSc, MSc, Osztatlan, Felsőoktatási szakképzés, Szakirányú továbbképzés, Mikroképzés, Előkészítő',
					is_required: true, is_filterable: true, is_locked: true 
				});
			} else {
				alapCsoport.fields.find(f => f.key === 'kepzesi_forma').is_locked = true;
			}

			function generateId() { return Math.random().toString(36).substr(2, 9); }

			function initSortable(el, options) {
				if (typeof Sortable !== 'undefined' && el) {
					new Sortable(el, options);
				}
			}

			function slugify(text) {
				const a = 'àáäâãåăæąçćčđďèéěėëêęğǵḧìíïîįłḿǹńňñòóöôœøṕŕřßşśšșťțùúüûǘůűūųẃẍÿýźžż·/_,:;';
				const b = 'aaaaaaaaacccddeeeeeeegghiiiiilmnnnnooooooprrsssssttuuuuuuuuuwxyyzzz------';
				const p = new RegExp(a.split('').join('|'), 'g');
				return text.toString().toLowerCase()
					.replace(/\s+/g, '_')
					.replace(p, c => b.charAt(a.indexOf(c)))
					.replace(/&/g, '_and_')
					.replace(/[^\w\-]+/g, '')
					.replace(/\-\-+/g, '_')
					.replace(/^-+/, '')
					.replace(/-+$/, '');
			}

			function generateUniqueKey(baseKey, currentInput) {
				let key = baseKey;
				let counter = 1;
				let isUnique = false;
				
				while (!isUnique) {
					isUnique = true;
					document.querySelectorAll('.f-key, .sf-key').forEach(input => {
						if (input !== currentInput && input.value === key) {
							isUnique = false;
						}
					});
					if (!isUnique) {
						counter++;
						key = baseKey + '_' + counter;
					}
				}
				return key;
			}

			function attachAutoSlugEvent(labelInput, keyInput) {
				labelInput.addEventListener('blur', function() {
					if (!keyInput.value && this.value) {
						let baseKey = slugify(this.value);
						keyInput.value = generateUniqueKey(baseKey, keyInput);
						checkDuplicateKeys();
					}
				});
			}

			function checkDuplicateKeys() {
				const inputs = document.querySelectorAll('.f-key');
				const keys = Array.from(inputs).map(input => input.value.trim()).filter(v => v !== '');
				const counts = {};
				keys.forEach(k => counts[k] = (counts[k] || 0) + 1);

				document.querySelectorAll('.szeducate-field').forEach(field => {
					const keyInput = field.querySelector('.f-key');
					const badge = field.querySelector('.badge-duplicate');
					if (keyInput && badge) {
						const val = keyInput.value.trim();
						if (val && counts[val] > 1) {
							badge.style.display = 'inline-block';
						} else {
							badge.style.display = 'none';
						}
					}
				});
			}

			function createSubFieldRow(subField) {
				const sfDiv = document.createElement('div');
				sfDiv.className = 'szeducate-subfield';
				
				let sfOptions = subFieldTypes.map(t => `<option value="${t.val}" ${subField.type === t.val ? 'selected' : ''}>${t.text}</option>`).join('');
				const showOpts = subField.type === 'select' ? 'block' : 'none';

				// KIVÉVE A REQUIRED!
				sfDiv.innerHTML = `
					<div class="drag-handle subfield-drag-handle" title="Mozgatás">::</div>
					<input type="text" class="sz-input sf-label" placeholder="Oszlop neve" value="${subField.label || ''}" style="flex: 1.5;">
					<input type="text" class="sz-input sf-key" placeholder="Azonosító" value="${subField.key || ''}" style="flex: 1;">
					<select class="sz-select sf-type" style="flex: 1;">${sfOptions}</select>
					<input type="text" class="sz-input sf-options" placeholder="Opciók (vesszővel)" value="${subField.options || ''}" style="display: ${showOpts}; flex: 1.5;">
					<button type="button" class="btn-icon delete-sf-btn" title="Oszlop törlése">X</button>
				`;

				attachAutoSlugEvent(sfDiv.querySelector('.sf-label'), sfDiv.querySelector('.sf-key'));

				sfDiv.querySelector('.sf-type').addEventListener('change', e => {
					sfDiv.querySelector('.sf-options').style.display = e.target.value === 'select' ? 'block' : 'none';
				});
				sfDiv.querySelector('.delete-sf-btn').addEventListener('click', () => {
					sfDiv.remove();
					checkDuplicateKeys();
				});
				return sfDiv;
			}

			function createFieldRow(field) {
				const fDiv = document.createElement('div');
				fDiv.className = `szeducate-field ${field.is_locked ? 'locked' : ''}`;
				fDiv.dataset.type = 'field';

				let typeOptions = fieldTypes.map(t => `<option value="${t.val}" ${field.type === t.val ? 'selected' : ''}>${t.text}</option>`).join('');
				const showOptions = ['select', 'multiselect', 'radio', 'checkbox'].includes(field.type) ? 'block' : 'none';
				const isReadonly = field.is_locked ? 'readonly' : '';

				// KIVÉVE A REQUIRED!
				fDiv.innerHTML = `
					<div class="sz-field-main">
						<div class="drag-handle field-drag-handle" title="Mozgatás" style="margin-top: 5px;">::</div>
						
						<div style="flex: 2; display: flex; flex-direction: column; gap: 5px;">
							<input type="text" class="sz-input f-label" placeholder="Mező neve (pl. Város)" value="${field.label || ''}" ${isReadonly}>
						</div>
						
						<div style="flex: 1; display: flex; flex-direction: column; gap: 5px;">
							<input type="text" class="sz-input f-key" placeholder="Azonosító (pl. varos)" value="${field.key || ''}" ${isReadonly}>
							<span class="badge-duplicate" style="display:none;" title="Ez a mező azonosító több csoportban is szerepel. Az értékek automatikusan szinkronban lesznek!">Közös mező</span>
						</div>

						<select class="sz-select f-type" style="flex: 1.5; margin-top: 2px;" ${field.is_locked ? 'disabled' : ''}>${typeOptions}</select>
						${field.is_locked ? `<input type="hidden" class="f-type-hidden" value="${field.type}">` : ''}
						
						<div style="flex: 2; display: flex; flex-direction: column; gap: 5px;">
							<input type="text" class="sz-input f-options" placeholder="Opciók (vesszővel elválasztva)" value="${field.options || ''}" style="display: ${showOptions};" ${isReadonly}>
						</div>
						
						<div style="margin-top: 2px;">
							${!field.is_locked ? `<button type="button" class="button button-link-delete delete-field-btn" style="color:#d63638; text-decoration:none;">Törlés</button>` : `<span class="badge-locked">Rendszer</span>`}
						</div>
					</div>
					<div class="sz-field-settings">
						<label><input type="checkbox" class="f-required" ${field.is_required ? 'checked' : ''} ${field.is_locked ? 'disabled' : ''}> Kötelező mező</label>
						
						<label title="Kiemeli az adatot a JSON-ből és saját oszlopot kap az adatbázisban. Ez feltétlenül szükséges ahhoz, hogy a weboldalon gyorsan lehessen szűrni/keresni erre a mezőre. Ne pipáld be mindenhol, csak a szűrőknél, hogy ne lassítsd az adatbázist!">
							<input type="checkbox" class="f-filter" ${field.is_filterable ? 'checked' : ''} ${field.is_locked ? 'disabled' : ''}> Kiemelt szűrő / Indexelt [?]
						</label>
						
						<label title="Ebből az adatból a WordPress natív URL-t (taxonómiát) generál, ami segít a SEO-ban (pl. /kepzes-tipus/nappali). Csak legördülő vagy rádiógomb típusoknál ajánlott.">
							<input type="checkbox" class="f-taxonomy" ${field.is_taxonomy ? 'checked' : ''} ${field.is_locked ? 'disabled' : ''}> SEO URL [?]
						</label>
					</div>
					<div class="subfields-container" style="display: ${field.type === 'repeater' ? 'block' : 'none'};">
						<div style="font-weight: 600; font-size: 13px; margin-bottom: 12px; color: #1d2327;">[Lista] Táblázat Oszlopai (Al-mezők)</div>
						<div class="sf-list"></div>
						<button type="button" class="button button-small add-sf-btn" style="margin-top: 12px;">+ Új oszlop</button>
					</div>
				`;

				if (!field.is_locked) {
					attachAutoSlugEvent(fDiv.querySelector('.f-label'), fDiv.querySelector('.f-key'));
					fDiv.querySelector('.f-key').addEventListener('input', checkDuplicateKeys);
				}

				const sfList = fDiv.querySelector('.sf-list');
				if (field.type === 'repeater' && field.sub_fields) {
					field.sub_fields.forEach(sf => sfList.appendChild(createSubFieldRow(sf)));
				}
				initSortable(sfList, { handle: '.subfield-drag-handle', animation: 150 });
				fDiv.querySelector('.add-sf-btn').addEventListener('click', () => sfList.appendChild(createSubFieldRow({ type: 'text' })));

				if (!field.is_locked) {
					fDiv.querySelector('.f-type').addEventListener('change', function(e) {
						const optInput = fDiv.querySelector('.f-options');
						const sfContainer = fDiv.querySelector('.subfields-container');
						optInput.style.display = ['select', 'multiselect', 'radio', 'checkbox'].includes(e.target.value) ? 'block' : 'none';
						sfContainer.style.display = e.target.value === 'repeater' ? 'block' : 'none';
					});
					fDiv.querySelector('.delete-field-btn').addEventListener('click', () => {
						fDiv.remove();
						checkDuplicateKeys();
					});
				}
				return fDiv;
			}

			function createGroupBlock(group) {
				const gDiv = document.createElement('div');
				gDiv.className = `szeducate-group ${group.is_locked ? 'locked' : ''}`;
				gDiv.dataset.type = 'group';

				const isReadonly = group.is_locked ? 'readonly' : '';
				const cond = group.condition || {};

				const conditionHtml = !group.is_locked ? `
					<div class="group-conditions">
						<label>[Láthatóság] Megjelenés feltétele:</label>
						<input type="text" class="sz-input c-field" placeholder="Mező azonosító (pl. kepzesi_forma)" value="${cond.field || ''}" style="flex: 1;">
						<select class="sz-select c-operator" style="flex: 1;">
							<option value="">Mindig látszik (Nincs feltétel)</option>
							<option value="==" ${cond.operator === '==' ? 'selected' : ''}>Egyenlő az értékkel</option>
							<option value="!=" ${cond.operator === '!=' ? 'selected' : ''}>Nem egyenlő</option>
							<option value="not_empty" ${cond.operator === 'not_empty' ? 'selected' : ''}>Nincs üresen</option>
							<option value="empty" ${cond.operator === 'empty' ? 'selected' : ''}>Üres</option>
							<option value="contains" ${cond.operator === 'contains' ? 'selected' : ''}>Tartalmazza</option>
						</select>
						<input type="text" class="sz-input c-value" placeholder="Érték (pl. BSc)" value="${cond.value || ''}" style="flex: 1;">
					</div>
				` : '';

				// KIVÉVE A REQUIRED!
				gDiv.innerHTML = `
					<div class="szeducate-group-header">
						<div class="drag-handle group-drag-handle" title="Csoport mozgatása">::</div>
						<div class="g-inputs">
							<input type="text" class="sz-input g-label" placeholder="Csoport neve" value="${group.group_label || ''}" style="font-weight: 600; width: 300px;" ${isReadonly}>
							<input type="text" class="sz-input g-id" placeholder="csoport_azonosito" value="${group.group_id || generateId()}" style="width: 200px;" ${isReadonly}>
						</div>
						<div style="display: flex; align-items: center; gap: 15px;">
							${!group.is_locked ? `<button type="button" class="button button-link-delete delete-group-btn" style="color:#d63638; text-decoration:none;">Csoport Törlése</button>` : `<span class="badge-locked">Rendszer Csoport</span>`}
							<button type="button" class="button toggle-group-btn" style="min-width: 40px; text-align: center;">v</button>
						</div>
					</div>
					<div class="group-inside">
						${conditionHtml}
						<div class="szeducate-fields-container"></div>
						<div style="padding: 0 20px 20px 20px; background: #f0f0f1;">
							<button type="button" class="button add-field-btn">+ Új Mező Hozzáadása</button>
						</div>
					</div>
				`;

				const fieldsContainer = gDiv.querySelector('.szeducate-fields-container');
				if(group.fields && group.fields.length > 0) {
					group.fields.forEach(f => fieldsContainer.appendChild(createFieldRow(f)));
				}

				initSortable(fieldsContainer, { handle: '.field-drag-handle', animation: 150 });

				gDiv.querySelector('.toggle-group-btn').addEventListener('click', function() {
					const inside = gDiv.querySelector('.group-inside');
					const isHidden = inside.style.display === 'none';
					inside.style.display = isHidden ? 'block' : 'none';
					this.innerText = isHidden ? 'v' : '<';
				});

				gDiv.querySelector('.add-field-btn').addEventListener('click', () => {
					fieldsContainer.appendChild(createFieldRow({ type: 'text' }));
					checkDuplicateKeys();
				});
				
				if (!group.is_locked) {
					gDiv.querySelector('.delete-group-btn').addEventListener('click', () => { 
						if(confirm('Biztosan törlöd a csoportot?')) {
							gDiv.remove();
							checkDuplicateKeys();
						}
					});
				}

				return gDiv;
			}

			initSortable(container, { handle: '.group-drag-handle', animation: 150 });

			schemaData.sort((a, b) => {
				if (a.group_id === 'alap_adatok') return -1;
				if (b.group_id === 'alap_adatok') return 1;
				if (a.is_locked && !b.is_locked) return -1;
				if (!a.is_locked && b.is_locked) return 1;
				return 0;
			});

			schemaData.forEach(group => container.appendChild(createGroupBlock(group)));
			addGroupBtn.addEventListener('click', () => container.appendChild(createGroupBlock({})));

			setTimeout(checkDuplicateKeys, 500);

			form.addEventListener('submit', function(e) {
				// 1. Egyedi JS Validáció a mentés előtt
				let hasError = false;
				const requiredInputs = container.querySelectorAll('.g-label, .g-id, .f-label, .f-key, .sf-label, .sf-key');
				
				requiredInputs.forEach(input => {
					if (!input.value.trim() && !input.readOnly) {
						hasError = true;
						input.style.border = '1px solid #d63638';
						input.style.boxShadow = '0 0 0 1px #d63638';
						
						// Kinyitjuk a fület, ha rejtve lenne
						const groupInside = input.closest('.group-inside');
						if (groupInside && groupInside.style.display === 'none') {
							groupInside.style.display = 'block';
							const toggleBtn = groupInside.parentElement.querySelector('.toggle-group-btn');
							if (toggleBtn) toggleBtn.innerText = 'v';
						}
					} else {
						input.style.border = '1px solid #8c8f94';
						input.style.boxShadow = 'none';
					}
				});

				if (hasError) {
					e.preventDefault(); // Blokkoljuk a mentést!
					alert('Kérlek töltsd ki az összes pirossal kiemelt mezőt! (A Név és az Azonosító mindenhol kötelező)');
					return;
				}

				// 2. Ha minden jó, mentjük a JSON-t
				const newSchema = [];
				const groups = container.querySelectorAll('.szeducate-group');
				
				groups.forEach(g => {
					const gData = {
						group_id: g.querySelector('.g-id').value.trim(),
						group_label: g.querySelector('.g-label').value.trim(),
						is_locked: g.querySelector('.g-id').readOnly,
						fields: []
					};

					const opSelect = g.querySelector('.c-operator');
					if (opSelect && opSelect.value !== '') {
						gData.condition = {
							field: g.querySelector('.c-field').value.trim(),
							operator: opSelect.value,
							value: g.querySelector('.c-value').value.trim()
						};
					}

					const fields = g.querySelectorAll('.szeducate-field');
					fields.forEach(f => {
						const typeEl = f.querySelector('.f-type');
						const typeHiddenEl = f.querySelector('.f-type-hidden');
						const fieldType = typeHiddenEl ? typeHiddenEl.value : typeEl.value;
						
						let fieldData = {
							key: f.querySelector('.f-key').value.trim(),
							label: f.querySelector('.f-label').value.trim(),
							type: fieldType,
							options: f.querySelector('.f-options').value.trim(),
							is_filterable: f.querySelector('.f-filter').checked,
							is_taxonomy: f.querySelector('.f-taxonomy').checked,
							is_required: f.querySelector('.f-required').checked,
							is_locked: f.querySelector('.f-key').readOnly
						};

						if (fieldType === 'repeater') {
							fieldData.sub_fields = [];
							const subfields = f.querySelectorAll('.szeducate-subfield');
							subfields.forEach(sf => {
								fieldData.sub_fields.push({
									key: sf.querySelector('.sf-key').value.trim(),
									label: sf.querySelector('.sf-label').value.trim(),
									type: sf.querySelector('.sf-type').value,
									options: sf.querySelector('.sf-options').value.trim()
								});
							});
						}

						gData.fields.push(fieldData);
					});
					newSchema.push(gData);
				});

				dataInput.value = JSON.stringify(newSchema);
			});
		});
		</script>
		<?php
	}

	public function save_schema() {
		if ( ! isset( $_POST['submit_schema'] ) || ! current_user_can( 'manage_options' ) ) {
			return;
		}
		if ( ! isset( $_POST['szeducate_schema_nonce'] ) || ! wp_verify_nonce( $_POST['szeducate_schema_nonce'], 'save_szeducate_schema' ) ) {
			add_settings_error( 'szeducate_schema', 'security_fail', 'Biztonsági hiba.', 'error' );
			return;
		}

		$raw_data = stripslashes( $_POST['szeducate_schema_data'] );
		$decoded = json_decode( $raw_data, true );

		if ( json_last_error() !== JSON_ERROR_NONE || ! is_array( $decoded ) ) {
			add_settings_error( 'szeducate_schema', 'invalid_json', 'Hibás adatstruktúra!', 'error' );
			return;
		}

		$clean_json = wp_json_encode( $decoded, JSON_UNESCAPED_UNICODE );
		update_option( $this->option_name, $clean_json );

		require_once SZEDUCATE_PLUGIN_DIR . 'includes/class-szeducate-activator.php';
		SZEducate_Activator::update_database_schema();

		add_settings_error( 'szeducate_schema', 'schema_saved', 'A séma és az adatbázis tábla szerkezete sikeresen frissítve!', 'updated' );
	}
}