<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class SZEducate_Schema {

	private $option_name = 'szeducate_schema';

	public function init() {
		add_action( 'admin_menu', array( $this, 'add_schema_page' ) );
		add_action( 'admin_init', array( $this, 'save_schema' ) );
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
		$schema = get_option( $this->option_name, '[]' );

		if ( empty( json_decode( $schema, true ) ) ) {
			$schema = wp_json_encode( [
				[
					'group_id'    => 'alap_adatok',
					'group_label' => 'Alap adatok',
					'fields'      => [
						[ 'key' => 'kategoria', 'label' => 'Kategória', 'type' => 'select', 'options' => 'Alapképzés, Mesterképzés, FOSZK', 'is_filterable' => true ],
					]
				]
			] );
		}
		?>
		<div class="wrap">
			<h1>Központi Séma Tervező</h1>
			
			<?php settings_errors( 'szeducate_schema' ); ?>

			<p>Építsd fel a szakok adatlapját. Listás elemeknél (dropdown, rádiógomb) automatikusan megjelenik egy mező az opciók megadására.</p>

			<form method="post" action="" id="szeducate-schema-form">
				<?php wp_nonce_field( 'save_szeducate_schema', 'szeducate_schema_nonce' ); ?>
				
				<input type="hidden" name="szeducate_schema_data" id="szeducate_schema_data" value="<?php echo esc_attr( $schema ); ?>">

				<div id="schema-builder-container" style="margin-top: 20px;"></div>

				<p>
					<button type="button" class="button button-secondary" id="add-group-btn">+ Új Csoport Hozzáadása</button>
				</p>

				<hr>
				<p class="submit">
					<input type="submit" name="submit_schema" class="button button-primary button-hero" value="Séma Mentése (Adatbázis frissítése)">
				</p>
			</form>
		</div>

		<style>
			.szeducate-group { background: #fff; border: 1px solid #ccd0d4; padding: 15px; margin-bottom: 20px; box-shadow: 0 1px 1px rgba(0,0,0,.04); }
			.szeducate-group-header { display: flex; gap: 10px; margin-bottom: 15px; padding-bottom: 15px; border-bottom: 1px solid #eee; }
			.szeducate-field { display: flex; gap: 10px; align-items: center; background: #f9f9f9; padding: 10px; border: 1px dashed #ccc; margin-bottom: 10px; flex-wrap: wrap; }
			.szeducate-field input[type="text"] { width: 150px; }
			.szeducate-field label { font-size: 12px; margin-left: 5px; }
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
				{ val: 'checkbox', text: 'Jelölőnégyzet (Több opció)' },
				{ val: 'radio', text: 'Rádiógomb' },
				{ val: 'select', text: 'Dropdown' },
				{ val: 'multiselect', text: 'Többszörös választó' },
				{ val: 'boolean', text: 'Kapcsoló (Igen/Nem)' },
				{ val: 'number', text: 'Szám' },
				{ val: 'date', text: 'Dátum' }
			];

			function generateId() { return Math.random().toString(36).substr(2, 9); }

			function createFieldRow(field) {
				const fDiv = document.createElement('div');
				fDiv.className = 'szeducate-field';
				fDiv.dataset.type = 'field';

				let typeOptions = fieldTypes.map(t => `<option value="${t.val}" ${field.type === t.val ? 'selected' : ''}>${t.text}</option>`).join('');
				const showOptions = ['select', 'multiselect', 'radio', 'checkbox'].includes(field.type) ? 'inline-block' : 'none';

				fDiv.innerHTML = `
					<input type="text" class="f-label" placeholder="Mező neve (pl. Város)" value="${field.label || ''}" required>
					<input type="text" class="f-key" placeholder="Azonosító (pl. varos)" value="${field.key || ''}" required>
					<select class="f-type">${typeOptions}</select>
					<input type="text" class="f-options" placeholder="Opciók (vesszővel: Egy, Kettő)" value="${field.options || ''}" style="display: ${showOptions}; width: 220px;">
					<label><input type="checkbox" class="f-filter" ${field.is_filterable ? 'checked' : ''}> Indexelt</label>
					<label style="color:#0071a1; font-weight:bold;"><input type="checkbox" class="f-taxonomy" ${field.is_taxonomy ? 'checked' : ''}> SEO URL (Taxonómia)</label>
					<button type="button" class="button button-link-delete delete-field-btn" style="color:#a00; margin-left:auto;">Törlés</button>
				`;

				fDiv.querySelector('.f-type').addEventListener('change', function(e) {
					const optInput = fDiv.querySelector('.f-options');
					if (['select', 'multiselect', 'radio', 'checkbox'].includes(e.target.value)) {
						optInput.style.display = 'inline-block';
					} else {
						optInput.style.display = 'none';
					}
				});

				fDiv.querySelector('.delete-field-btn').addEventListener('click', () => fDiv.remove());
				return fDiv;
			}

			function createGroupBlock(group) {
				const gDiv = document.createElement('div');
				gDiv.className = 'szeducate-group';
				gDiv.dataset.type = 'group';

				gDiv.innerHTML = `
					<div class="szeducate-group-header">
						<input type="text" class="g-label" placeholder="Csoport neve (pl. Pénzügyi adatok)" value="${group.group_label || ''}" required style="font-weight:bold; width:300px;">
						<input type="text" class="g-id" placeholder="csoport_azonosito" value="${group.group_id || generateId()}" required>
						<button type="button" class="button button-link-delete delete-group-btn" style="color:#a00; margin-left:auto;">Csoport Törlése</button>
					</div>
					<div class="szeducate-fields-container"></div>
					<button type="button" class="button add-field-btn" style="margin-top:10px;">+ Mező hozzáadása</button>
				`;

				const fieldsContainer = gDiv.querySelector('.szeducate-fields-container');
				if(group.fields && group.fields.length > 0) {
					group.fields.forEach(f => fieldsContainer.appendChild(createFieldRow(f)));
				}

				gDiv.querySelector('.add-field-btn').addEventListener('click', () => fieldsContainer.appendChild(createFieldRow({ type: 'text' })));
				gDiv.querySelector('.delete-group-btn').addEventListener('click', () => { if(confirm('Biztosan törlöd a csoportot?')) gDiv.remove(); });

				return gDiv;
			}

			schemaData.forEach(group => container.appendChild(createGroupBlock(group)));
			addGroupBtn.addEventListener('click', () => container.appendChild(createGroupBlock({})));

			form.addEventListener('submit', function() {
				const newSchema = [];
				const groups = container.querySelectorAll('.szeducate-group');
				
				groups.forEach(g => {
					const gData = {
						group_id: g.querySelector('.g-id').value.trim(),
						group_label: g.querySelector('.g-label').value.trim(),
						fields: []
					};

					const fields = g.querySelectorAll('.szeducate-field');
					fields.forEach(f => {
						gData.fields.push({
							key: f.querySelector('.f-key').value.trim(),
							label: f.querySelector('.f-label').value.trim(),
							type: f.querySelector('.f-type').value,
							options: f.querySelector('.f-options').value.trim(),
							is_filterable: f.querySelector('.f-filter').checked,
							is_taxonomy: f.querySelector('.f-taxonomy').checked // ÚJ MEZŐ
						});
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