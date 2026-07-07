<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class SZEducate_Schema {

	private $option_name = 'szeducate_schema';

	public function init() {
		add_action( 'admin_menu', array( $this, 'add_schema_page' ) );
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
		if ( isset( $_POST['szeducate_schema'] ) && isset( $_POST['szeducate_schema_nonce'] ) ) {
			if ( wp_verify_nonce( $_POST['szeducate_schema_nonce'], 'save_szeducate_schema_action' ) ) {
				$new_schema = wp_unslash( $_POST['szeducate_schema'] );
				update_option( $this->option_name, $new_schema );

				require_once SZEDUCATE_PLUGIN_DIR . 'includes/class-szeducate-activator.php';
				SZEducate_Activator::update_database_schema();

				global $wpdb;
				$table_name = $wpdb->prefix . 'szeducate_clients';
				
				if ( $wpdb->get_var( "SHOW TABLES LIKE '{$table_name}'" ) == $table_name ) {
					$clients = $wpdb->get_results( "SELECT client_url, api_token FROM {$table_name} WHERE client_url != ''" );
					foreach ( $clients as $client ) {
						$webhook_url = rtrim( $client->client_url, '/' ) . '/wp-json/szeducate/v1/client/sync';
						wp_remote_post( $webhook_url, array(
							'blocking' => false,
							'timeout'  => 5,
							'headers'  => array( 'X-SZEducate-Auth' => $client->api_token ),
						) );
					}
				}
				echo '<div class="notice notice-success is-dismissible" style="margin-top:20px;"><p><strong>A Séma sikeresen elmentve a Hub-on, és a Kliensek automatikusan szinkronizálva lettek a háttérben!</strong></p></div>';
			} else {
				echo '<div class="notice notice-error is-dismissible" style="margin-top:20px;"><p>Biztonsági hiba (lejárt session). Kérjük frissítse az oldalt!</p></div>';
			}
		}

		$schema = get_option( $this->option_name, '[]' );
		if ( empty( json_decode( $schema, true ) ) ) {
			$schema = wp_json_encode( [
				[
					'group_id'    => 'alap_adatok',
					'group_label' => 'Alap adatok',
					'is_locked'   => true,
					'fields'      => [
						[ 'key' => 'kepzesi_forma', 'label' => 'Képzési Forma', 'type' => 'select', 'options' => 'BSc, MSc, Osztatlan, Felsőoktatási szakképzés, Szakirányú továbbképzés, Mikroképzés, Előkészítő', 'is_required' => true, 'is_filterable' => true, 'is_locked' => true, 'help_text' => 'Válassza ki a képzés típusát.' ],
					]
				]
			] );
		}
		?>
		<div class="wrap" style="max-width: 1600px; padding-right: 20px;">
			<h1>SZEducate Séma Tervező</h1>
			<p>Tervezd meg a képzések adatszerkezetét! Az itt létrehozott mezőket a Kliensek (karok) tölthetik ki.</p>
			
			<div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 15px;">
				<div class="notice notice-info" style="margin: 0; padding: 15px; border-left-color: #007cba; background: #fff; box-shadow: 0 1px 1px rgba(0,0,0,.04); flex: 1; margin-right: 20px;">
					<p style="margin-top: 0;"><strong>Tipp: Közös mezők szinkronja</strong></p>
					<p style="margin-bottom: 0;">Ha egy mezőt több különböző fülön (csoportban) is meg szeretnél jeleníteni, állítsd be nekik pontosan ugyanazt az "Azonosítót"! A rendszer automatikusan kék "Közös mező" jelvénnyel látja el őket. <strong>Ha az egyiket módosítod, a rendszer valós időben frissíti a többi ugyanilyen azonosítójú mezőt is!</strong></p>
				</div>
				<div style="display: flex; gap: 10px;">
					<input type="file" id="import-schema-file" accept=".json" style="display: none;">
					<button type="button" id="import-schema-btn" class="button button-secondary">Séma Importálása (.json)</button>
					<button type="button" id="export-schema-btn" class="button button-secondary">Séma Exportálása (.json)</button>
				</div>
			</div>
			
			<form method="post" id="szeducate-schema-form">
				<?php wp_nonce_field( 'save_szeducate_schema_action', 'szeducate_schema_nonce' ); ?>
				<input type="hidden" name="szeducate_schema" id="szeducate_schema_data" value="<?php echo esc_attr( $schema ); ?>">
				<div id="schema-builder-container" style="width: 100%;"></div>
				<button type="button" id="add-group-btn" class="button button-secondary" style="margin-top: 20px;">+ Új Csoport (Fül) Hozzáadása</button>
				<div style="margin-top: 30px; padding-bottom: 50px;">
					<?php submit_button( 'Séma Mentése és Véglegesítése', 'primary', 'submit', false, array('id' => 'save-schema-btn', 'style' => 'padding: 5px 30px; font-size: 15px;') ); ?>
				</div>
			</form>
		</div>

		<script src="https://cdn.jsdelivr.net/npm/sortablejs@latest/Sortable.min.js"></script>
		
		<style>
			#schema-builder-container { font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Oxygen-Sans, Ubuntu, Cantarell, "Helvetica Neue", sans-serif; }
			
			.sz-input, .sz-select { 
				width: 100%; 
				box-sizing: border-box; 
				border: 1px solid #8c8f94; 
				border-radius: 4px; 
				padding: 6px 10px; 
				font-size: 13px; 
				line-height: 1.4; 
				box-shadow: none; 
				transition: all 0.2s; 
				min-height: 32px;
			}
			.sz-input:focus, .sz-select:focus { border-color: #2271b1; outline: none; box-shadow: 0 0 0 1px #2271b1; }
			.sz-select { padding: 4px 30px 4px 10px; }
			
			.drag-handle { cursor: grab; font-size: 18px; color: #a7aaad; user-select: none; padding: 5px; margin-right: 5px; display: flex; align-items: center; letter-spacing: -2px; font-weight: bold; }
			.drag-handle:active { cursor: grabbing; color: #2271b1; }

			.szeducate-group { background: #fff; border: 1px solid #c3c4c7; border-radius: 6px; margin-bottom: 24px; box-shadow: 0 1px 3px rgba(0,0,0,0.04); overflow: hidden; transition: box-shadow 0.2s; }
			.szeducate-group:hover { box-shadow: 0 4px 8px rgba(0,0,0,0.05); }
			
			.szeducate-group-header { display: flex; align-items: flex-start; padding: 15px 20px; background: #f6f7f7; border-bottom: 1px solid #c3c4c7; }
			.szeducate-group.locked .szeducate-group-header { background: #f0f6fc; border-bottom-color: #c8d8e8; }
			.szeducate-group-header .drag-handle { margin-top: 15px; }

			.sz-field-col { display: flex; flex-direction: column; gap: 4px; min-width: 0; }
			.sz-field-col label { font-size: 11px; font-weight: 600; color: #646970; text-transform: uppercase; letter-spacing: 0.5px; }
			
			.g-inputs { display: flex; gap: 20px; flex: 1; margin-left: 10px; }
			
			.group-conditions { padding: 15px 20px; background: #fafafa; border-bottom: 1px solid #e2e4e7; display: flex; gap: 20px; align-items: flex-end; }
			
			.szeducate-fields-container { padding: 20px; background: #f0f0f1; min-height: 20px; }
			.szeducate-field { background: #fff; border: 1px solid #dcdde1; border-radius: 4px; padding: 15px 20px; margin-bottom: 15px; transition: all 0.2s; }
			.szeducate-field:hover { border-color: #a7aaad; box-shadow: 0 2px 5px rgba(0,0,0,0.05); }
			.szeducate-field.locked { background: #f9f9f9; border-color: #e2e4e7; }
			.szeducate-field.archived-field { opacity: 0.6; background: #e5e5e5; filter: grayscale(100%); }
			
			.sz-field-main { display: flex; gap: 20px; align-items: flex-start; }
			.sz-field-main .drag-handle { margin-top: 15px; }
			
			.sz-field-settings { margin-top: 15px; padding-top: 15px; border-top: 1px dashed #dcdde1; padding-left: 35px; }
			.sz-field-settings-checkboxes { display: flex; flex-wrap: wrap; gap: 25px; align-items: center; margin-bottom: 12px; font-size: 13px; color: #50575e; }
			.sz-field-settings-checkboxes label { display: flex; align-items: center; gap: 6px; cursor: pointer; }
			.sz-field-settings-checkboxes input[type="checkbox"] { margin: 0; cursor: pointer; }

			.subfields-container { background: #f8f9f9; border: 1px dashed #a7aaad; border-radius: 4px; padding: 15px; margin-top: 15px; margin-left: 35px; }
			.szeducate-subfield { display: flex; gap: 15px; align-items: center; background: #fff; border: 1px solid #dcdde1; padding: 10px; margin-bottom: 8px; border-radius: 3px; }

			.btn-icon { background: none; border: none; cursor: pointer; color: #d63638; font-size: 16px; font-weight: bold; padding: 5px 10px; display: flex; align-items: center; justify-content: center; border-radius: 3px; transition: background 0.2s; }
			.btn-icon:hover { background: #fbeaea; }
			.badge-locked { background: #e5f5fa; color: #007cba; padding: 4px 8px; border-radius: 12px; font-size: 12px; font-weight: 600; display: inline-block; }
			.badge-duplicate { background: #e6f0fa; color: #005a9e; padding: 2px 6px; border-radius: 4px; font-size: 10px; font-weight: 600; display: inline-block; border: 1px solid #b3d4fc; float: right; margin-top: -2px; }
		</style>

		<script>
		document.addEventListener('DOMContentLoaded', function() {
			const container = document.getElementById('schema-builder-container');
			const addGroupBtn = document.getElementById('add-group-btn');
			const form = document.getElementById('szeducate-schema-form');
			const dataInput = document.getElementById('szeducate_schema_data');

			document.getElementById('export-schema-btn').addEventListener('click', () => {
				const data = dataInput.value;
				if (!data || data === '[]') { alert('Üres a séma, nincs mit exportálni.'); return; }
				
				const blob = new Blob([data], { type: 'application/json' });
				const url = URL.createObjectURL(blob);
				const a = document.createElement('a');
				a.href = url;
				a.download = 'szeducate_schema_export_' + new Date().toISOString().slice(0,10) + '.json';
				document.body.appendChild(a);
				a.click();
				document.body.removeChild(a);
				URL.revokeObjectURL(url);
			});

			document.getElementById('import-schema-btn').addEventListener('click', () => {
				document.getElementById('import-schema-file').click();
			});

			document.getElementById('import-schema-file').addEventListener('change', (e) => {
				const file = e.target.files[0];
				if (!file) return;
				
				const reader = new FileReader();
				reader.onload = (ev) => {
					try {
						const json = JSON.parse(ev.target.result);
						if (!Array.isArray(json)) throw new Error("A JSON struktúra nem megfelelő (nem tömb).");
						
						dataInput.value = JSON.stringify(json);
						if (confirm("Az importált fájl betöltése azonnal felülírja a jelenlegi sémát. Véglegesítjük a mentést?")) {
							HTMLFormElement.prototype.submit.call(form);
						} else {
							e.target.value = '';
						}
					} catch (err) {
						alert('Hiba történt az importálás során! Ellenőrizze, hogy a fájl érvényes SZEducate JSON séma-e. Részletek: ' + err.message);
					}
				};
				reader.readAsText(file);
			});

			let schemaData = [];
			try { schemaData = JSON.parse(dataInput.value); } catch(e) {}

			const originalTypes = {};
			schemaData.forEach(g => {
				if(g.fields) g.fields.forEach(f => originalTypes[f.key] = f.type);
			});

			const fieldTypes = [
				{ val: 'text', text: 'Rövidszöveg' },
				{ val: 'textarea', text: 'Hosszúszöveg' },
				{ val: 'wysiwyg', text: 'Formázott szöveg (WYSIWYG)' },
				{ val: 'email', text: 'Email cím' },
				{ val: 'checkbox', text: 'Jelölőnégyzet (Több opció)' },
				{ val: 'radio', text: 'Rádiógomb' },
				{ val: 'select', text: 'Dropdown' },
				{ val: 'boolean', text: 'Kapcsoló (Igen/Nem)' },
				{ val: 'number', text: 'Szám' },
				{ val: 'date', text: 'Dátum' },
				{ val: 'url', text: 'Link (1 db URL)' },
				{ val: 'links', text: 'Többszörös Link (URL+Szöveg)' },
				{ val: 'image', text: 'Kép (Médiatár)' },
				{ val: 'repeater', text: 'Táblázatos Ismétlődő' }
			];

			const subFieldTypes = [
				{ val: 'text', text: 'Rövidszöveg' },
				{ val: 'number', text: 'Szám' },
				{ val: 'select', text: 'Dropdown' },
				{ val: 'boolean', text: 'Kapcsoló' },
				{ val: 'url', text: 'Link' }
			];

			function generateId() { return Math.random().toString(36).substr(2, 9); }
			function initSortable(el, options) { if (typeof Sortable !== 'undefined' && el) new Sortable(el, options); }
			function slugify(text) {
				const a = 'àáäâãåăæąçćčđďèéěėëêęğǵḧìíïîįłḿǹńňñòóöôœøṕŕřßşśšșťțùúüûǘůűūųẃẍÿýźžż·/_,:;';
				const b = 'aaaaaaaaacccddeeeeeeegghiiiiilmnnnnooooooprrsssssttuuuuuuuuuwxyyzzz------';
				const p = new RegExp(a.split('').join('|'), 'g');
				return text.toString().toLowerCase().replace(/\s+/g, '_').replace(p, c => b.charAt(a.indexOf(c))).replace(/&/g, '_and_').replace(/[^\w\-]+/g, '').replace(/\-\-+/g, '_').replace(/^-+/, '').replace(/-+$/, '');
			}

			function generateUniqueKey(baseKey, currentInput) {
				let key = baseKey;
				let counter = 1;
				let isUnique = false;
				while (!isUnique) {
					isUnique = true;
					document.querySelectorAll('.f-key, .sf-key').forEach(input => {
						if (input !== currentInput && input.value === key) isUnique = false;
					});
					if (!isUnique) { counter++; key = baseKey + '_' + counter; }
				}
				return key;
			}

			function checkDuplicateKeys() {
				const inputs = document.querySelectorAll('.f-key');
				const keys = Array.from(inputs).map(i => i.value.trim()).filter(v => v !== '');
				const counts = {};
				keys.forEach(k => counts[k] = (counts[k] || 0) + 1);
				document.querySelectorAll('.szeducate-field').forEach(field => {
					const keyInput = field.querySelector('.f-key');
					const badge = field.querySelector('.badge-duplicate');
					if (keyInput && badge) badge.style.display = (keyInput.value.trim() && counts[keyInput.value.trim()] > 1) ? 'inline-block' : 'none';
				});
			}

			let isSyncing = false;
			function syncCommonFields(sourceField) {
				if (isSyncing) return;
				
				const sourceKey = sourceField.querySelector('.f-key').value.trim();
				if (!sourceKey) return;

				const badge = sourceField.querySelector('.badge-duplicate');
				if (!badge || badge.style.display === 'none') return;

				isSyncing = true;
				
				const sourceLabel = sourceField.querySelector('.f-label').value;
				const sourceType = sourceField.querySelector('.f-type').value;
				const sourceOptions = sourceField.querySelector('.f-options').value;
				const sourceHelp = sourceField.querySelector('.f-help').value;
				const sourceReq = sourceField.querySelector('.f-required').checked;
				const sourceFilter = sourceField.querySelector('.f-filter').checked;
				const sourceTax = sourceField.querySelector('.f-taxonomy').checked;

				const allFields = document.querySelectorAll('.szeducate-field');
				allFields.forEach(fDiv => {
					if (fDiv === sourceField || fDiv.classList.contains('locked')) return;
					
					const currentKey = fDiv.querySelector('.f-key').value.trim();
					if (currentKey === sourceKey) {
						fDiv.querySelector('.f-label').value = sourceLabel;
						
						const typeEl = fDiv.querySelector('.f-type');
						if (typeEl.value !== sourceType) {
							typeEl.value = sourceType;
							typeEl.dispatchEvent(new Event('change'));
						}
						
						fDiv.querySelector('.f-options').value = sourceOptions;
						fDiv.querySelector('.f-help').value = sourceHelp;
						fDiv.querySelector('.f-required').checked = sourceReq;
						fDiv.querySelector('.f-filter').checked = sourceFilter;
						fDiv.querySelector('.f-taxonomy').checked = sourceTax;
					}
				});
				
				isSyncing = false;
			}

			function createSubFieldRow(subField) {
				const sfDiv = document.createElement('div');
				sfDiv.className = 'szeducate-subfield';
				let sfOptions = subFieldTypes.map(t => `<option value="${t.val}" ${subField.type === t.val ? 'selected' : ''}>${t.text}</option>`).join('');
				const showOpts = subField.type === 'select' ? 'flex' : 'none';

				sfDiv.innerHTML = `
					<div class="drag-handle subfield-drag-handle" title="Mozgatás">::</div>
					<div class="sz-field-col" style="flex: 2;">
						<input type="text" class="sz-input sf-label" placeholder="Oszlop neve" value="${subField.label || ''}">
					</div>
					<div class="sz-field-col" style="flex: 1.5;">
						<input type="text" class="sz-input sf-key" placeholder="Azonosító" value="${subField.key || ''}">
					</div>
					<div class="sz-field-col" style="flex: 1.5;">
						<select class="sz-select sf-type">${sfOptions}</select>
					</div>
					<div class="sz-field-col sf-options-wrapper" style="flex: 2; display: ${showOpts};">
						<input type="text" class="sz-input sf-options" placeholder="Opciók (pontosvesszővel)" value="${subField.options || ''}">
					</div>
					<button type="button" class="btn-icon delete-sf-btn" title="Oszlop törlése">X</button>
				`;
				sfDiv.querySelector('.sf-label').addEventListener('blur', function() {
					const ki = sfDiv.querySelector('.sf-key');
					if (!ki.value && this.value) { ki.value = generateUniqueKey(slugify(this.value), ki); checkDuplicateKeys(); }
				});
				sfDiv.querySelector('.sf-type').addEventListener('change', function(e) {
					sfDiv.querySelector('.sf-options-wrapper').style.display = e.target.value === 'select' ? 'flex' : 'none';
				});
				sfDiv.querySelector('.delete-sf-btn').addEventListener('click', () => { sfDiv.remove(); checkDuplicateKeys(); });
				return sfDiv;
			}

			function createFieldRow(field) {
				const isArchived = field.is_archived || false;
				const fDiv = document.createElement('div');
				fDiv.className = `szeducate-field ${field.is_locked ? 'locked' : ''} ${isArchived ? 'archived-field' : ''}`;
				fDiv.dataset.type = 'field';

				let typeOptions = fieldTypes.map(t => `<option value="${t.val}" ${field.type === t.val ? 'selected' : ''}>${t.text}</option>`).join('');
				const showOptions = ['select', 'radio', 'checkbox'].includes(field.type) ? 'flex' : 'none';
				const isReadonly = field.is_locked ? 'readonly' : '';

				fDiv.innerHTML = `
					<input type="hidden" class="f-archived" value="${isArchived ? 'true' : ''}">
					<div class="sz-field-main">
						<div class="drag-handle field-drag-handle" title="Mozgatás">::</div>
						
						<div class="sz-field-col" style="flex: 2.5;">
							<label>Mező neve (Címke)</label>
							<input type="text" class="sz-input f-label" placeholder="pl. Képzés helye" value="${field.label || ''}" ${isReadonly}>
						</div>
						
						<div class="sz-field-col" style="flex: 1.5;">
							<label>Azonosító (Key) <span class="badge-duplicate" style="display:none;" title="Közös mező!">Közös mező</span></label>
							<input type="text" class="sz-input f-key" placeholder="pl. kepzes_helye" value="${field.key || ''}" ${isReadonly}>
						</div>
						
						<div class="sz-field-col" style="flex: 1.5;">
							<label>Mező Típusa</label>
							<select class="sz-select f-type" ${field.is_locked ? 'disabled' : ''}>${typeOptions}</select>
							${field.is_locked ? `<input type="hidden" class="f-type-hidden" value="${field.type}">` : ''}
						</div>
						
						<div class="sz-field-col f-options-wrapper" style="flex: 3; display: ${showOptions};">
							<label>Opciók (Pontosvesszővel elválasztva)</label>
							<input type="text" class="sz-input f-options" placeholder="pl. Nappali; Levelező" value="${field.options || ''}" ${isReadonly}>
						</div>
						
						<div class="sz-field-col" style="flex: 0 0 auto; padding-top: 18px;">
							<div style="display: flex; gap: 8px;">
								${!field.is_locked ? `
									<button type="button" class="button toggle-archive-btn">${isArchived ? 'Visszaállítás' : 'Archiválás'}</button>
									<button type="button" class="button button-link-delete delete-field-btn" style="color:#d63638; text-decoration:none;" title="Végleges törlés a felületről">Törlés</button>
								` : `<span class="badge-locked">Rendszermező</span>`}
							</div>
						</div>
					</div>
					
					<div class="sz-field-settings">
						<div class="sz-field-settings-checkboxes">
							<label><input type="checkbox" class="f-required" ${field.is_required ? 'checked' : ''} ${field.is_locked ? 'disabled' : ''}> Kötelező kitölteni</label>
							<label><input type="checkbox" class="f-filter" ${field.is_filterable ? 'checked' : ''} ${field.is_locked ? 'disabled' : ''}> Kiemelt Szűrő (Indexelt)</label>
							<label><input type="checkbox" class="f-taxonomy" ${field.is_taxonomy ? 'checked' : ''} ${field.is_locked ? 'disabled' : ''}> SEO URL (Archívum)</label>
						</div>
						<div class="sz-field-col" style="width: 100%;">
							<label style="font-size: 10px;">Súgó szöveg a szerkesztőbe (Útmutató a Klienseknek)</label>
							<input type="text" class="sz-input f-help" placeholder="pl. Add meg a hivatalos e-mail címet a @sze.hu végződéssel..." value="${field.help_text || ''}" ${field.is_locked ? 'readonly' : ''}>
						</div>
					</div>
					
					<div class="subfields-container" style="display: ${field.type === 'repeater' ? 'block' : 'none'};">
						<div style="font-weight: 600; font-size: 13px; margin-bottom: 12px; color: #1d2327;">[Lista] Táblázat Oszlopai (Al-mezők)</div>
						<div class="sf-list"></div>
						<button type="button" class="button button-small add-sf-btn" style="margin-top: 12px;">+ Új oszlop</button>
					</div>
				`;

				if (!field.is_locked) {
					const triggerSync = () => syncCommonFields(fDiv);
					
					fDiv.querySelector('.f-label').addEventListener('input', triggerSync);
					fDiv.querySelector('.f-options').addEventListener('input', triggerSync);
					fDiv.querySelector('.f-help').addEventListener('input', triggerSync);
					fDiv.querySelector('.f-required').addEventListener('change', triggerSync);
					fDiv.querySelector('.f-filter').addEventListener('change', triggerSync);
					fDiv.querySelector('.f-taxonomy').addEventListener('change', triggerSync);

					fDiv.querySelector('.f-type').addEventListener('change', function(e) {
						fDiv.querySelector('.f-options-wrapper').style.display = ['select', 'radio', 'checkbox'].includes(e.target.value) ? 'flex' : 'none';
						fDiv.querySelector('.subfields-container').style.display = e.target.value === 'repeater' ? 'block' : 'none';
						triggerSync();
					});
					
					fDiv.querySelector('.f-label').addEventListener('blur', function() {
						const ki = fDiv.querySelector('.f-key');
						if (!ki.value && this.value) { ki.value = generateUniqueKey(slugify(this.value), ki); checkDuplicateKeys(); }
					});
					fDiv.querySelector('.f-key').addEventListener('input', checkDuplicateKeys);
				}

				const sfList = fDiv.querySelector('.sf-list');
				if (field.type === 'repeater' && field.sub_fields) field.sub_fields.forEach(sf => sfList.appendChild(createSubFieldRow(sf)));
				initSortable(sfList, { handle: '.subfield-drag-handle', animation: 150 });
				fDiv.querySelector('.add-sf-btn').addEventListener('click', () => sfList.appendChild(createSubFieldRow({ type: 'text' })));

				if (!field.is_locked) {
					fDiv.querySelector('.toggle-archive-btn').addEventListener('click', function() {
						const archInput = fDiv.querySelector('.f-archived');
						const willBeArchived = archInput.value !== 'true';
						archInput.value = willBeArchived ? 'true' : '';
						fDiv.classList.toggle('archived-field', willBeArchived);
						this.innerText = willBeArchived ? 'Visszaállítás' : 'Archiválás';
					});

					fDiv.querySelector('.delete-field-btn').addEventListener('click', () => {
						if(confirm('A törléssel a mező eltűnik a felületről. Biztonságosabb az Archiválás használata. Biztosan véglegesen törlöd?')) {
							fDiv.remove(); checkDuplicateKeys();
						}
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
						<div class="sz-field-col" style="flex: 0 0 auto;">
							<label style="color: #2271b1;">[Feltétel] Láthatóság:</label>
							<div style="font-size: 13px; font-weight: 600; line-height: 32px;">A fül megjelenik, ha</div>
						</div>
						<div class="sz-field-col" style="flex: 2;">
							<label>Mező (Azonosító)</label>
							<input type="text" class="sz-input c-field" placeholder="pl. kepzesi_forma" value="${cond.field || ''}">
						</div>
						<div class="sz-field-col" style="flex: 2;">
							<label>Operátor</label>
							<select class="sz-select c-operator">
								<option value="">Nincs feltétel (Mindig látszik)</option>
								<option value="==" ${cond.operator === '==' ? 'selected' : ''}>Egyenlő</option>
								<option value="!=" ${cond.operator === '!=' ? 'selected' : ''}>Nem egyenlő</option>
								<option value="not_empty" ${cond.operator === 'not_empty' ? 'selected' : ''}>Nincs üresen</option>
								<option value="empty" ${cond.operator === 'empty' ? 'selected' : ''}>Üres</option>
								<option value="contains" ${cond.operator === 'contains' ? 'selected' : ''}>Tartalmazza</option>
							</select>
						</div>
						<div class="sz-field-col" style="flex: 2;">
							<label>Érték</label>
							<input type="text" class="sz-input c-value" placeholder="pl. BSc" value="${cond.value || ''}">
						</div>
					</div>` : '';

				gDiv.innerHTML = `
					<div class="szeducate-group-header">
						<div class="drag-handle group-drag-handle" title="Mozgatás">::</div>
						<div class="g-inputs" style="max-width: 600px;">
							<div class="sz-field-col" style="flex: 3;">
								<label>Csoport (Fül) Megnevezése</label>
								<input type="text" class="sz-input g-label" placeholder="pl. Szakirányú Továbbképzés" value="${group.group_label || ''}" style="font-weight: 600;" ${isReadonly}>
							</div>
							<div class="sz-field-col" style="flex: 2;">
								<label>Rendszer Azonosító</label>
								<input type="text" class="sz-input g-id" placeholder="pl. sztk_ful" value="${group.group_id || generateId()}" ${isReadonly}>
							</div>
						</div>
						<div style="display: flex; align-items: center; gap: 15px; margin-top: 18px; margin-left: auto;">
							${!group.is_locked ? `<button type="button" class="button button-link-delete delete-group-btn" style="color:#d63638; text-decoration:none; white-space: nowrap;">Csoport Törlése</button>` : `<span class="badge-locked" style="white-space: nowrap;">Zárt Rendszerfül</span>`}
							<button type="button" class="button toggle-group-btn" style="min-width: 40px;">&lt;</button>
						</div>
					</div>
					<div class="group-inside" style="display: none;">
						${conditionHtml}
						<div class="szeducate-fields-container"></div>
						<div style="padding: 0 20px 20px 20px; background: #f0f0f1;">
							<button type="button" class="button add-field-btn">+ Új Mező Hozzáadása a csoporthoz</button>
						</div>
					</div>
				`;

				const fieldsContainer = gDiv.querySelector('.szeducate-fields-container');
				if(group.fields && group.fields.length > 0) group.fields.forEach(f => fieldsContainer.appendChild(createFieldRow(f)));
				initSortable(fieldsContainer, { handle: '.field-drag-handle', animation: 150 });

				gDiv.querySelector('.toggle-group-btn').addEventListener('click', function() {
					const inside = gDiv.querySelector('.group-inside');
					const isHidden = inside.style.display === 'none';
					inside.style.display = isHidden ? 'block' : 'none';
					this.innerText = isHidden ? 'v' : '<';
				});

				gDiv.querySelector('.add-field-btn').addEventListener('click', () => { fieldsContainer.appendChild(createFieldRow({ type: 'text' })); checkDuplicateKeys(); });
				if (!group.is_locked) gDiv.querySelector('.delete-group-btn').addEventListener('click', () => { if(confirm('Biztosan törlöd a teljes csoportot az összes mezővel együtt?')) { gDiv.remove(); checkDuplicateKeys(); } });
				return gDiv;
			}

			initSortable(container, { handle: '.group-drag-handle', animation: 150 });
			schemaData.forEach(group => container.appendChild(createGroupBlock(group)));
			addGroupBtn.addEventListener('click', () => container.appendChild(createGroupBlock({})));
			setTimeout(checkDuplicateKeys, 500);

			form.addEventListener('submit', function(e) {
				const newSchema = [];
				let typeWarnings = [];
				const groups = container.querySelectorAll('.szeducate-group');
				
				groups.forEach(g => {
					const gData = {
						group_id: g.querySelector('.g-id').value.trim(),
						group_label: g.querySelector('.g-label').value.trim(),
						is_locked: g.querySelector('.g-id').readOnly,
						fields: []
					};
					const opSelect = g.querySelector('.c-operator');
					if (opSelect && opSelect.value !== '') gData.condition = { field: g.querySelector('.c-field').value.trim(), operator: opSelect.value, value: g.querySelector('.c-value').value.trim() };

					const fields = g.querySelectorAll('.szeducate-field');
					fields.forEach(f => {
						const key = f.querySelector('.f-key').value.trim();
						const typeEl = f.querySelector('.f-type');
						const typeHiddenEl = f.querySelector('.f-type-hidden');
						const fieldType = typeHiddenEl ? typeHiddenEl.value : typeEl.value;
						const isArchived = f.querySelector('.f-archived').value === 'true';

						if (originalTypes[key] && originalTypes[key] !== fieldType && !isArchived) {
							typeWarnings.push(`- ${f.querySelector('.f-label').value.trim()} (${key}): ${originalTypes[key]} -> ${fieldType}`);
						}

						let fieldData = {
							key: key,
							label: f.querySelector('.f-label').value.trim(),
							type: fieldType,
							options: f.querySelector('.f-options').value.trim(),
							is_filterable: f.querySelector('.f-filter').checked,
							is_taxonomy: f.querySelector('.f-taxonomy').checked,
							is_required: f.querySelector('.f-required').checked,
							is_locked: f.querySelector('.f-key').readOnly,
							is_archived: isArchived,
							help_text: f.querySelector('.f-help').value.trim()
						};

						if (fieldType === 'repeater') {
							fieldData.sub_fields = [];
							const subfields = f.querySelectorAll('.szeducate-subfield');
							subfields.forEach(sf => fieldData.sub_fields.push({
								key: sf.querySelector('.sf-key').value.trim(), label: sf.querySelector('.sf-label').value.trim(),
								type: sf.querySelector('.sf-type').value, options: sf.querySelector('.sf-options').value.trim()
							}));
						}
						gData.fields.push(fieldData);
					});
					newSchema.push(gData);
				});

				if (typeWarnings.length > 0) {
					const msg = "FIGYELEM! Az alábbi mezők típusa megváltozott:\n\n" + typeWarnings.join("\n") + "\n\nAdatbázis szinten az adatok nem vesznek el, a Kliens rendszer (React) pedig futásidőben megpróbálja automatikusan konvertálni az inkompatibilis típusokat (pl. szövegből lista). Biztosan véglegesíted?";
					if (!confirm(msg)) {
						e.preventDefault();
						return;
					}
				}

				dataInput.value = JSON.stringify(newSchema);
			});
		});
		</script>
		<?php
	}
}