<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class SZEducate_Clients {

	private $table_name;

	public function __construct() {
		global $wpdb;
		$this->table_name = $wpdb->prefix . 'szeducate_clients';
	}

	public function init() {
		add_action( 'admin_menu', array( $this, 'add_clients_page' ) );
		add_action( 'admin_init', array( $this, 'handle_actions' ) );
		$this->check_table();
	}

	private function check_table() {
		global $wpdb;
		if ( $wpdb->get_var( "SHOW TABLES LIKE '{$this->table_name}'" ) != $this->table_name ) {
			$charset_collate = $wpdb->get_charset_collate();
			$sql = "CREATE TABLE {$this->table_name} (
				id mediumint(9) NOT NULL AUTO_INCREMENT,
				client_name varchar(255) NOT NULL,
				client_url varchar(255) NOT NULL DEFAULT '',
				api_token varchar(64) NOT NULL,
				permissions longtext NOT NULL,
				created_at datetime DEFAULT CURRENT_TIMESTAMP NOT NULL,
				PRIMARY KEY  (id),
				UNIQUE KEY api_token (api_token)
			) $charset_collate;";
			require_once( ABSPATH . 'wp-admin/includes/upgrade.php' );
			dbDelta( $sql );
		} else {
			$column = $wpdb->get_results( "SHOW COLUMNS FROM {$this->table_name} LIKE 'client_url'" );
			if ( empty( $column ) ) {
				$wpdb->query( "ALTER TABLE {$this->table_name} ADD client_url varchar(255) NOT NULL DEFAULT '' AFTER client_name" );
			}
		}
	}

	public function add_clients_page() {
		add_submenu_page(
			'szeducate-settings',
			'Kliensek és Jogosultságok',
			'Kliensek',
			'manage_options',
			'szeducate-clients',
			array( $this, 'render_page' )
		);
	}

	public function handle_actions() {
		if ( isset( $_POST['szeducate_add_client'] ) && check_admin_referer( 'szeducate_client_action' ) ) {
			$client_name = sanitize_text_field( wp_unslash( $_POST['client_name'] ) );
			$client_url  = esc_url_raw( wp_unslash( $_POST['client_url'] ) );
			
			if ( ! empty( $client_name ) ) {
				global $wpdb;
				
				$raw_token = bin2hex( random_bytes( 24 ) ); 
				$hashed_token = hash( 'sha256', $raw_token );
				
				$default_permissions = wp_json_encode( array(
					'actions' => array( 'create' => true, 'edit' => true, 'delete' => false ),
					'conditions' => array( 'logical_operator' => 'AND', 'rules' => array() ),
					'fields' => array()
				) );

				$wpdb->insert(
					$this->table_name,
					array(
						'client_name' => $client_name,
						'client_url'  => rtrim($client_url, '/'),
						'api_token'   => $hashed_token,
						'permissions' => $default_permissions
					)
				);

				set_transient( 'szeducate_new_client_token', $raw_token, 60 );
				wp_redirect( admin_url( 'admin.php?page=szeducate-clients&message=added' ) );
				exit;
			}
		}

		if ( isset( $_POST['szeducate_save_permissions'] ) && check_admin_referer( 'szeducate_permissions_action' ) ) {
			global $wpdb;
			$client_id = intval( $_POST['client_id'] );
			$permissions_json = wp_unslash( $_POST['permissions_json'] );

			$wpdb->update(
				$this->table_name,
				array( 'permissions' => $permissions_json ),
				array( 'id' => $client_id )
			);

			$client = $wpdb->get_row( $wpdb->prepare( "SELECT client_url FROM {$this->table_name} WHERE id = %d", $client_id ) );
			if ( $client && ! empty( $client->client_url ) ) {
				$webhook_url = rtrim( $client->client_url, '/' ) . '/wp-json/szeducate/v1/client/sync';
				wp_remote_post( $webhook_url, array(
					'blocking' => false,
					'timeout'  => 5
				) );
			}

			wp_redirect( admin_url( 'admin.php?page=szeducate-clients&message=permissions_saved' ) );
			exit;
		}

		if ( isset( $_GET['action'] ) && $_GET['action'] === 'delete_client' && isset( $_GET['client_id'] ) ) {
			check_admin_referer( 'delete_client_' . $_GET['client_id'] );
			global $wpdb;
			$wpdb->delete( $this->table_name, array( 'id' => intval( $_GET['client_id'] ) ) );
			wp_redirect( admin_url( 'admin.php?page=szeducate-clients&message=deleted' ) );
			exit;
		}
	}

	public function render_page() {
		if ( ! current_user_can( 'manage_options' ) ) return;

		global $wpdb;
		$clients = $wpdb->get_results( "SELECT * FROM {$this->table_name} ORDER BY id DESC" );
		$schema_json = get_option( 'szeducate_schema', '[]' );
		
		$new_token = get_transient( 'szeducate_new_client_token' );

		?>
		<div class="wrap">
			<h1>Kliensek és Jogosultságok</h1>
			<p>Kezeld a hálózathoz csatlakozó karokat és állítsd be, hogy pontosan mikhez férhetnek hozzá a Hub-on lévő adatokból.</p>

			<?php 
			if ( $new_token ) {
				echo '<div class="notice notice-success is-dismissible" style="border-left-color: #46b450; padding: 15px;"><p style="font-size: 16px; margin-top: 0;"><strong>ÚJ TOKEN GENERÁLVA!</strong></p><p>Kérlek, másold ki most ezt a titkos kulcsot a Kliens oldal számára, mert biztonsági okokból többé nem lesz látható:</p><p><code style="font-size: 20px; background: #e5f5fa; padding: 10px; display: inline-block; border: 1px solid #007cba; user-select: all;">' . esc_html( $new_token ) . '</code></p></div>';
				delete_transient( 'szeducate_new_client_token' );
			}

			if ( isset( $_GET['message'] ) ) {
				if ( $_GET['message'] === 'added' && !$new_token ) echo '<div class="notice notice-success is-dismissible"><p>Kliens sikeresen hozzáadva.</p></div>';
				if ( $_GET['message'] === 'deleted' ) echo '<div class="notice notice-success is-dismissible"><p>Kliens és a hozzá tartozó hozzáférés véglegesen törölve.</p></div>';
				if ( $_GET['message'] === 'permissions_saved' ) echo '<div class="notice notice-success is-dismissible"><p>Jogosultsági Mátrix sikeresen frissítve. (A Kliens automatikusan szinkronizált a háttérben).</p></div>';
			}
			?>

			<div style="display: flex; gap: 30px; margin-top: 20px; align-items: flex-start;">
				
				<div style="flex: 1; background: #fff; border: 1px solid #c3c4c7; padding: 20px; border-radius: 4px; box-shadow: 0 1px 3px rgba(0,0,0,0.04);">
					<h2 style="margin-top: 0;">Új Kliens Regisztrálása</h2>
					<form method="post" action="">
						<?php wp_nonce_field( 'szeducate_client_action' ); ?>
						<table class="form-table" role="presentation">
							<tbody>
								<tr>
									<th scope="row"><label for="client_name">Kliens (Kar) Neve</label></th>
									<td>
										<input name="client_name" type="text" id="client_name" class="regular-text" required style="width: 100%;">
									</td>
								</tr>
								<tr>
									<th scope="row"><label for="client_url">Kliens Webcíme (URL)</label></th>
									<td>
										<input name="client_url" type="url" id="client_url" placeholder="pl. https://felveteli.sze.hu" class="regular-text" required style="width: 100%;">
										<p class="description">Ide küldjük az automatikus szinkronizációs jelet.</p>
									</td>
								</tr>
							</tbody>
						</table>
						<p class="submit">
							<input type="submit" name="szeducate_add_client" class="button button-primary" value="Regisztráció és Token generálása">
						</p>
					</form>
				</div>

				<div style="flex: 2;">
					<table class="wp-list-table widefat fixed striped table-view-list">
						<thead>
							<tr>
								<th style="width: 50px;">ID</th>
								<th>Kliens Neve</th>
								<th>API Token</th>
								<th style="text-align: right;">Műveletek</th>
							</tr>
						</thead>
						<tbody>
							<?php if ( empty( $clients ) ) : ?>
								<tr><td colspan="4">Nincsenek regisztrált kliensek a rendszerben.</td></tr>
							<?php else : ?>
								<?php foreach ( $clients as $client ) : ?>
									<tr>
										<td>#<?php echo esc_html( $client->id ); ?></td>
										<td>
											<strong><?php echo esc_html( $client->client_name ); ?></strong><br>
											<a href="<?php echo esc_attr( $client->client_url ); ?>" target="_blank" style="font-size: 11px; text-decoration: none; color: #666;"><?php echo esc_html( $client->client_url ); ?></a>
										</td>
										<td><code style="color: #888; background: transparent;">&bull;&bull;&bull;&bull;&bull;&bull;&bull;&bull;&bull;&bull;&bull;&bull;&bull;&bull; (Rejtett)</code></td>
										<td style="text-align: right;">
											<button type="button" class="button button-small open-permissions-btn" data-id="<?php echo esc_attr( $client->id ); ?>" data-name="<?php echo esc_attr( $client->client_name ); ?>" data-permissions="<?php echo esc_attr( $client->permissions ); ?>" style="margin-right: 5px;">Jogosultságok (Mátrix)</button>
											<a href="<?php echo esc_url( wp_nonce_url( admin_url( 'admin.php?page=szeducate-clients&action=delete_client&client_id=' . $client->id ), 'delete_client_' . $client->id ) ); ?>" class="button button-small button-link-delete" style="color: #d63638;" onclick="return confirm('Biztosan törlöd ezt a klienst? Ezzel a tokenje érvénytelenné válik!');">Törlés</a>
										</td>
									</tr>
								<?php endforeach; ?>
							<?php endif; ?>
						</tbody>
					</table>
				</div>
			</div>
		</div>

		<div id="sz-permissions-modal" style="display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.6); z-index: 99999; align-items: center; justify-content: center;">
			<div style="background: #fff; width: 900px; max-width: 95%; max-height: 90vh; overflow-y: auto; border-radius: 8px; box-shadow: 0 10px 30px rgba(0,0,0,0.2); display: flex; flex-direction: column;">
				
				<div style="padding: 20px; border-bottom: 1px solid #ccd0d4; display: flex; justify-content: space-between; align-items: center; background: #f6f7f7; border-radius: 8px 8px 0 0;">
					<h2 style="margin: 0;">Jogosultságok: <span id="modal-client-name" style="color: #2271b1;"></span></h2>
					<button type="button" id="close-modal-btn" style="background: none; border: none; font-size: 24px; cursor: pointer; color: #888;">&times;</button>
				</div>

				<div style="padding: 20px; overflow-y: auto;">
					
					<div style="margin-bottom: 30px;">
						<h3 style="margin-top: 0; border-bottom: 1px solid #eee; padding-bottom: 10px;">1. Engedélyezett Műveletek</h3>
						<p style="color: #666; font-size: 13px;">Képes-e ez a kar új szakokat beküldeni, meglévőket felülírni, vagy törölni? (A létrehozás és törlés feltétele a szerkesztési jog).</p>
						<div style="display: flex; gap: 20px;">
							<label><input type="checkbox" id="perm-edit"> Meglévő képzés szerkesztése</label>
							<label><input type="checkbox" id="perm-create"> Új képzés létrehozása</label>
							<label><input type="checkbox" id="perm-delete"> Képzés törlése (Archiválás)</label>
						</div>
					</div>

					<div style="margin-bottom: 30px;">
						<h3 style="margin-top: 0; border-bottom: 1px solid #eee; padding-bottom: 10px;">2. Képzés Hozzáférés (Szűrés)</h3>
						<p style="color: #666; font-size: 13px;">Építs logikai feltételeket. Mely szakokat láthatja és szerkesztheti ez a kar? (Ha üresen hagyod, az összes szakhoz hozzáfér)</p>
						<div id="conditions-root-container" style="background: #f9f9f9; padding: 15px; border: 1px solid #ddd; border-radius: 4px;">
						</div>
					</div>

					<div id="fields-section-wrapper" style="transition: opacity 0.3s;">
						<div style="display: flex; justify-content: space-between; align-items: flex-end; border-bottom: 1px solid #eee; padding-bottom: 10px; margin-bottom: 10px;">
							<div>
								<h3 style="margin: 0;">3. Mezőszintű Jogosultságok</h3>
								<p style="color: #666; font-size: 13px; margin: 5px 0 0 0;">Állítsd be, hogy a kar mely mezőket szerkesztheti.</p>
							</div>
							<div style="display: flex; gap: 10px;">
								<button type="button" id="set-all-edit-btn" class="button button-small">Mind Szerkeszthető</button>
								<button type="button" id="set-all-readonly-btn" class="button button-small">Mind Csak olvasható</button>
							</div>
						</div>
						
						<table class="wp-list-table widefat striped" style="margin-top: 10px;">
							<thead><tr><th>Mező Neve</th><th>Azonosító (Key)</th><th style="width: 200px;">Jogosultság</th></tr></thead>
							<tbody id="fields-container"></tbody>
						</table>
					</div>

				</div>

				<div style="padding: 20px; border-top: 1px solid #ccd0d4; background: #f6f7f7; border-radius: 0 0 8px 8px; display: flex; justify-content: flex-end; gap: 10px;">
					<form method="post" id="permissions-form">
						<?php wp_nonce_field( 'szeducate_permissions_action' ); ?>
						<input type="hidden" name="szeducate_save_permissions" value="1">
						<input type="hidden" name="client_id" id="modal-client-id" value="">
						<input type="hidden" name="permissions_json" id="modal-permissions-json" value="">
						<button type="button" id="cancel-modal-btn" class="button">Mégse</button>
						<button type="button" id="save-permissions-btn" class="button button-primary">Mátrix Mentése</button>
					</form>
				</div>

			</div>
		</div>

		<script>
			const szSchemaData = <?php echo $schema_json; ?>;
		</script>

		<style>
			.qb-group { border: 1px solid #ccd0d4; padding: 12px; margin-bottom: 12px; border-radius: 4px; background: #fff; border-left: 4px solid #007cba; }
			.qb-group-header { display: flex; gap: 10px; margin-bottom: 12px; align-items: center; }
			.qb-rules { padding-left: 20px; border-left: 2px dashed #e2e4e7; margin-left: 5px; display: flex; flex-direction: column; gap: 10px; }
			.cond-row { display: flex; gap: 10px; align-items: center; background: #f0f6fc; padding: 8px; border: 1px solid #c8d8e8; border-radius: 3px; }
			.cond-row input, .cond-row select, .qb-group-header select { padding: 4px 8px; border: 1px solid #8c8f94; border-radius: 4px; }
		</style>

		<script>
		document.addEventListener('DOMContentLoaded', function() {
			const modal = document.getElementById('sz-permissions-modal');
			const conditionsRoot = document.getElementById('conditions-root-container');
			const fieldsContainer = document.getElementById('fields-container');
			const form = document.getElementById('permissions-form');
			
			const permEdit = document.getElementById('perm-edit');
			const permCreate = document.getElementById('perm-create');
			const permDelete = document.getElementById('perm-delete');
			const fieldsSectionWrapper = document.getElementById('fields-section-wrapper');

			function toggleCrudLogic() {
				const canEdit = permEdit.checked;
				
				permCreate.disabled = !canEdit;
				permDelete.disabled = !canEdit;
				
				if (!canEdit) {
					permCreate.checked = false;
					permDelete.checked = false;
					fieldsSectionWrapper.style.opacity = '0.4';
					fieldsSectionWrapper.style.pointerEvents = 'none';
				} else {
					fieldsSectionWrapper.style.opacity = '1';
					fieldsSectionWrapper.style.pointerEvents = 'auto';
				}
			}
			permEdit.addEventListener('change', toggleCrudLogic);

			document.getElementById('set-all-edit-btn').addEventListener('click', () => {
				document.querySelectorAll('.field-status').forEach(sel => sel.value = 'edit');
			});
			document.getElementById('set-all-readonly-btn').addEventListener('click', () => {
				document.querySelectorAll('.field-status').forEach(sel => sel.value = 'readonly');
			});
			
			let allFields = [];
			szSchemaData.forEach(group => {
				if(group.fields) {
					group.fields.forEach(f => {
						if(!allFields.find(ext => ext.key === f.key)) {
							allFields.push(f);
						}
					});
				}
			});

			function escAttr(str) {
				return String(str).replace(/&/g, '&amp;').replace(/"/g, '&quot;').replace(/</g, '&lt;').replace(/>/g, '&gt;');
			}

			function getFieldDef(key) {
				return allFields.find(f => f.key === key);
			}

			// A séma mezőjének típusától függően vagy egy legördülő listát adunk vissza
			// (a mező pontos, meglévő opcióival feltöltve), vagy - ha a mezőnek nincsenek
			// előre definiált opciói - egy szabad szöveges mezőt, mint eddig.
			function buildValueControl(fieldKey, currentValue) {
				const fieldDef = getFieldDef(fieldKey);
				currentValue = currentValue || '';

				if (fieldDef && ['select', 'radio', 'checkbox'].includes(fieldDef.type) && fieldDef.options) {
					const opts = fieldDef.options.split(';').map(o => o.trim()).filter(o => o !== '');
					let optionsHtml = '<option value="">-- válassz értéket --</option>';
					opts.forEach(o => {
						optionsHtml += `<option value="${escAttr(o)}" ${o === currentValue ? 'selected' : ''}>${escAttr(o)}</option>`;
					});
					return `<select class="c-val" style="flex: 2;">${optionsHtml}</select>`;
				}

				if (fieldDef && (fieldDef.type === 'boolean' || fieldDef.type === 'true_false')) {
					return `<select class="c-val" style="flex: 2;">
						<option value="">-- válassz értéket --</option>
						<option value="1" ${currentValue === '1' ? 'selected' : ''}>Igen</option>
						<option value="0" ${currentValue === '0' ? 'selected' : ''}>Nem</option>
					</select>`;
				}

				return `<input type="text" class="c-val" placeholder="Érték..." value="${escAttr(currentValue)}" style="flex: 2;">`;
			}

			function buildRuleUI(ruleData = {}) {
				const row = document.createElement('div');
				row.className = 'cond-row';
				row.dataset.type = 'rule';

				let fieldOpts = '<option value="">Válassz mezőt...</option>';
				allFields.forEach(f => {
					fieldOpts += `<option value="${f.key}" ${ruleData.field === f.key ? 'selected' : ''}>${f.label} (${f.key})</option>`;
				});

				row.innerHTML = `
					<select class="c-field" style="flex: 2;">${fieldOpts}</select>
					<select class="c-op" style="flex: 1;">
						<option value="==" ${ruleData.operator === '==' ? 'selected' : ''}>Egyenlő ezzel</option>
						<option value="!=" ${ruleData.operator === '!=' ? 'selected' : ''}>Nem egyenlő</option>
						<option value="contains" ${ruleData.operator === 'contains' ? 'selected' : ''}>Tartalmazza</option>
					</select>
					${buildValueControl(ruleData.field, ruleData.value)}
					<button type="button" class="button button-small delete-cond-btn" style="color: #d63638;">Törlés</button>
				`;

				// Ha az admin más mezőt választ, az érték-mezőt is újraépítjük az új mező típusához illően.
				row.querySelector('.c-field').addEventListener('change', (e) => {
					const oldValEl = row.querySelector('.c-val');
					const temp = document.createElement('div');
					temp.innerHTML = buildValueControl(e.target.value, '');
					oldValEl.replaceWith(temp.firstElementChild);
				});

				row.querySelector('.delete-cond-btn').addEventListener('click', () => row.remove());
				return row;
			}

			function buildGroupUI(groupData = {}, isRoot = false) {
				const groupDiv = document.createElement('div');
				groupDiv.className = 'qb-group';
				groupDiv.dataset.type = 'group';

				const op = groupData.logical_operator || 'AND';
				
				const header = document.createElement('div');
				header.className = 'qb-group-header';
				header.innerHTML = `
					<select class="qb-logical-op">
						<option value="AND" ${op === 'AND' ? 'selected' : ''}>ÉS (Minden szabály igaz)</option>
						<option value="OR" ${op === 'OR' ? 'selected' : ''}>VAGY (Bármelyik igaz)</option>
					</select>
					<button type="button" class="button button-small add-rule-btn">+ Szabály hozzáadása</button>
					<button type="button" class="button button-small add-group-btn">+ Al-csoport</button>
					${!isRoot ? `<button type="button" class="button button-link-delete delete-group-btn" style="color:#d63638; margin-left:auto;">Csoport Törlése</button>` : ''}
				`;

				const rulesContainer = document.createElement('div');
				rulesContainer.className = 'qb-rules';

				if (groupData.rules && Array.isArray(groupData.rules)) {
					groupData.rules.forEach(rule => {
						if (rule.logical_operator) {
							rulesContainer.appendChild(buildGroupUI(rule, false));
						} else {
							rulesContainer.appendChild(buildRuleUI(rule));
						}
					});
				}

				header.querySelector('.add-rule-btn').addEventListener('click', () => rulesContainer.appendChild(buildRuleUI()));
				header.querySelector('.add-group-btn').addEventListener('click', () => rulesContainer.appendChild(buildGroupUI({ logical_operator: 'AND', rules: [] }, false)));
				
				if (!isRoot) {
					header.querySelector('.delete-group-btn').addEventListener('click', () => groupDiv.remove());
				}

				groupDiv.appendChild(header);
				groupDiv.appendChild(rulesContainer);
				return groupDiv;
			}

			function serializeQueryBuilder(element) {
				if (element.dataset.type === 'group') {
					const logicalOp = element.querySelector(':scope > .qb-group-header .qb-logical-op').value;
					const rules = [];
					const children = element.querySelector(':scope > .qb-rules').children;
					
					for (let child of children) {
						const serialized = serializeQueryBuilder(child);
						if (serialized !== null) rules.push(serialized);
					}
					
					if (rules.length > 0) {
						return { logical_operator: logicalOp, rules: rules };
					}
					return null;
				} 
				else if (element.dataset.type === 'rule') {
					const field = element.querySelector('.c-field').value;
					const op = element.querySelector('.c-op').value;
					const val = element.querySelector('.c-val').value;
					if (field && val) {
						return { field: field, operator: op, value: val };
					}
					return null;
				}
				return null;
			}

			document.querySelectorAll('.open-permissions-btn').forEach(btn => {
				btn.addEventListener('click', function() {
					const clientId = this.dataset.id;
					const clientName = this.dataset.name;
					let perms = { actions: { create: true, edit: true, delete: false }, conditions: { logical_operator: 'AND', rules: [] }, fields: {} };
					
					try { perms = JSON.parse(this.dataset.permissions); } catch(e) {}
					if(!perms.actions) perms.actions = { create: true, edit: true, delete: false };
					
					if(Array.isArray(perms.conditions)) {
						perms.conditions = { logical_operator: 'AND', rules: perms.conditions };
					}
					if(!perms.conditions) perms.conditions = { logical_operator: 'AND', rules: [] };
					if(!perms.fields) perms.fields = {};

					document.getElementById('modal-client-name').innerText = clientName;
					document.getElementById('modal-client-id').value = clientId;

					permCreate.checked = !!perms.actions.create;
					permEdit.checked = !!perms.actions.edit;
					permDelete.checked = !!perms.actions.delete;
					toggleCrudLogic();

					conditionsRoot.innerHTML = '';
					conditionsRoot.appendChild(buildGroupUI(perms.conditions, true));

					fieldsContainer.innerHTML = '';
					allFields.forEach(f => {
						const tr = document.createElement('tr');
						const status = perms.fields[f.key] || 'edit';
						tr.innerHTML = `
							<td><strong>${f.label}</strong></td>
							<td><code>${f.key}</code></td>
							<td>
								<select class="field-status" data-key="${f.key}">
									<option value="edit" ${status === 'edit' ? 'selected' : ''}>Szerkesztheti</option>
									<option value="readonly" ${status === 'readonly' ? 'selected' : ''}>Csak olvasható (Read-only)</option>
								</select>
							</td>
						`;
						fieldsContainer.appendChild(tr);
					});

					modal.style.display = 'flex';
				});
			});

			function closeModal() { modal.style.display = 'none'; }
			document.getElementById('close-modal-btn').addEventListener('click', closeModal);
			document.getElementById('cancel-modal-btn').addEventListener('click', closeModal);

			document.getElementById('save-permissions-btn').addEventListener('click', () => {
				const rootGroupElement = conditionsRoot.querySelector('.qb-group');
				const conditionsData = serializeQueryBuilder(rootGroupElement) || { logical_operator: 'AND', rules: [] };

				const finalPerms = {
					actions: {
						create: permCreate.checked,
						edit: permEdit.checked,
						delete: permDelete.checked
					},
					conditions: conditionsData,
					fields: {}
				};

				document.querySelectorAll('.field-status').forEach(select => {
					if(select.value !== 'edit') {
						finalPerms.fields[select.dataset.key] = select.value;
					}
				});

				document.getElementById('modal-permissions-json').value = JSON.stringify(finalPerms);
				form.submit();
			});
		});
		</script>
		<?php
	}
}