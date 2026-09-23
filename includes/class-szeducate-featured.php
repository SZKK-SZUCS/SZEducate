<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// A "Kiemelt kategória" a képzések admin-oldali, kézi kategorizálására szolgáló
// taxonómia - NEM azonos a séma is_taxonomy mezőiből generált sz_<kulcs>
// taxonómiákkal (lásd SZEducate_Client::register_dynamic_taxonomies()). Azok a
// sémát szerkesztő Hub-adminé, ez a Kliens oldali tartalom-szerkesztőé: bármikor,
// séma-módosítás nélkül állítható, és nem publikus (nincs archívum-oldala).
//
// Ütközés-védelem: ha valaha egy séma-mező kulcsa is pontosan "kiemelt_kategoria"
// lenne, a register_dynamic_taxonomies() ugyanezt a "sz_kiemelt_kategoria" slugot
// generálná - elhanyagolható, de dokumentált kockázat.
class SZEducate_Featured {

	const TAXONOMY     = 'sz_kiemelt_kategoria';
	const ORDER_META   = 'sz_kiemelt_sorrend';
	const NONCE_ACTION = 'szeducate_featured_nonce';

	public function init() {
		add_action( 'init', array( $this, 'register_taxonomy' ) );

		add_filter( 'bulk_actions-edit-sz_course', array( $this, 'add_bulk_action' ) );
		add_action( 'admin_footer-edit.php', array( $this, 'print_bulk_action_modal' ) );
		add_action( 'wp_ajax_szeducate_assign_featured_category', array( $this, 'ajax_assign_featured_category' ) );

		add_action( 'admin_menu', array( $this, 'add_categories_page' ) );
		add_action( 'admin_post_szeducate_featured_manage_category', array( $this, 'handle_manage_category' ) );
		add_action( 'wp_ajax_szeducate_save_featured_order', array( $this, 'ajax_save_featured_order' ) );
	}

	public function register_taxonomy() {
		register_taxonomy(
			self::TAXONOMY,
			'sz_course',
			array(
				'labels'            => array(
					'name'          => 'Kiemelt kategóriák',
					'singular_name' => 'Kiemelt kategória',
					'menu_name'     => 'Kiemelt kategóriák',
					'add_new_item'  => 'Új kiemelt kategória',
					'edit_item'     => 'Kiemelt kategória szerkesztése',
					'search_items'  => 'Keresés a kiemelt kategóriák közt',
					'not_found'     => 'Nincs ilyen kiemelt kategória.',
				),
				'hierarchical'      => true,
				'public'            => false,
				'show_ui'           => true,
				// A term-CRUD-ot a saját "Kiemelt kategóriák" oldalunk adja (lásd
				// render_categories_page()), ami a sorrend-beállítással egy helyen van -
				// ezért nem engedjük, hogy a WP a natív edit-tags.php-t is külön
				// almenüként felvegye (az duplikálná a funkciót).
				'show_in_menu'      => false,
				'show_in_rest'      => false,
				'show_admin_column' => true,
				'rewrite'           => false,
			)
		);
	}

	// --- Tömeges kategóriába helyezés a Képzések listájában --------------------

	public function add_bulk_action( $bulk_actions ) {
		$bulk_actions['szeducate_assign_featured'] = 'Kiemelt kategóriába helyezés';
		return $bulk_actions;
	}

	public function print_bulk_action_modal() {
		global $typenow;
		if ( $typenow !== 'sz_course' ) {
			return;
		}
		if ( ! current_user_can( 'edit_sz_courses' ) && ! current_user_can( 'manage_options' ) ) {
			return;
		}

		$terms = get_terms(
			array(
				'taxonomy'   => self::TAXONOMY,
				'hide_empty' => false,
			)
		);
		if ( is_wp_error( $terms ) ) {
			$terms = array();
		}
		?>
		<div id="szeducate-featured-modal" style="display:none; position:fixed; z-index:99999; left:0; top:0; width:100%; height:100%; background:rgba(0,0,0,0.6); align-items:center; justify-content:center;">
			<div style="background:#fff; padding:30px; width:420px; max-width:90%; border-radius:8px; box-shadow:0 5px 15px rgba(0,0,0,0.3);">
				<h3 style="margin-top:0;">Kiemelt kategóriába helyezés</h3>
				<p style="color:#666; font-size:13px; margin-bottom:15px;">Válaszd ki, melyik kiemelt kategóriá(k)ba kerüljenek a kijelölt képzések, vagy hozz létre egy újat.</p>

				<label for="szeducate-featured-select" style="font-weight:600; display:block; margin-bottom:5px;">Meglévő kategóriák</label>
				<select id="szeducate-featured-select" multiple="multiple" style="width:100%; margin-bottom:15px;">
					<?php foreach ( $terms as $term ) : ?>
						<option value="<?php echo esc_attr( (string) $term->term_id ); ?>"><?php echo esc_html( $term->name ); ?> (<?php echo intval( $term->count ); ?>)</option>
					<?php endforeach; ?>
				</select>

				<label for="szeducate-featured-new" style="font-weight:600; display:block; margin-bottom:5px;">Új kategória neve (opcionális)</label>
				<input type="text" id="szeducate-featured-new" placeholder="pl. Kezdőlapra" style="width:100%; padding:6px; border:1px solid #8c8f94; border-radius:4px; margin-bottom:15px; box-sizing:border-box;">

				<label style="display:flex; align-items:center; gap:6px; margin-bottom:20px;">
					<input type="checkbox" id="szeducate-featured-replace">
					A meglévő kiemelt kategóriák felülírása (különben hozzáadja)
				</label>

				<div style="display:flex; justify-content:flex-end; gap:10px;">
					<button type="button" class="button" id="szeducate-featured-cancel">Mégse</button>
					<button type="button" class="button button-primary" id="szeducate-featured-confirm">Alkalmaz</button>
				</div>
			</div>
		</div>
		<script type="text/javascript">
			document.addEventListener('DOMContentLoaded', function () {
				var modal = document.getElementById('szeducate-featured-modal');
				if (!modal) return;

				if (window.jQuery && jQuery.fn.select2) {
					jQuery('#szeducate-featured-select').select2({ width: '100%', dropdownParent: jQuery(modal) });
				}

				var currentPostIds = [];

				function openModal(postIds) {
					currentPostIds = postIds;
					modal.style.display = 'flex';
				}
				function closeModal() {
					modal.style.display = 'none';
					document.getElementById('szeducate-featured-new').value = '';
					document.getElementById('szeducate-featured-replace').checked = false;
					if (window.jQuery && jQuery.fn.select2) {
						jQuery('#szeducate-featured-select').val(null).trigger('change');
					}
				}

				document.getElementById('szeducate-featured-cancel').addEventListener('click', closeModal);

				document.getElementById('szeducate-featured-confirm').addEventListener('click', function () {
					var select = document.getElementById('szeducate-featured-select');
					var termIds = Array.from(select.selectedOptions).map(function (o) { return o.value; });
					var newName = document.getElementById('szeducate-featured-new').value.trim();
					var replace = document.getElementById('szeducate-featured-replace').checked;

					if (!termIds.length && !newName) {
						alert('Válassz ki legalább egy kategóriát, vagy adj meg egy újat!');
						return;
					}

					document.body.style.cursor = 'wait';
					var data = new URLSearchParams();
					data.append('action', 'szeducate_assign_featured_category');
					data.append('post_ids', JSON.stringify(currentPostIds));
					data.append('term_ids', JSON.stringify(termIds));
					data.append('new_term_name', newName);
					data.append('replace', replace ? '1' : '0');
					data.append('_ajax_nonce', '<?php echo wp_create_nonce( self::NONCE_ACTION ); ?>');

					fetch(ajaxurl, { method: 'POST', body: data })
						.then(function (res) { return res.json(); })
						.then(function (response) {
							if (response.success) {
								alert(response.data);
								location.reload();
							} else {
								alert('Hiba: ' + response.data);
								document.body.style.cursor = 'default';
							}
						})
						.catch(function () {
							alert('Hálózati hiba történt a kommunikáció során.');
							document.body.style.cursor = 'default';
						});
				});

				function handleBulkAction(e, selectId) {
					var select = document.getElementById(selectId);
					if (!select) return;
					if (select.value !== 'szeducate_assign_featured') return;

					e.preventDefault();
					var checked = document.querySelectorAll('input[name="post[]"]:checked');
					if (!checked.length) {
						alert('Kérlek válassz ki legalább egy képzést!');
						return;
					}
					openModal(Array.from(checked).map(function (cb) { return cb.value; }));
				}

				var btnTop = document.getElementById('doaction');
				var btnBottom = document.getElementById('doaction2');
				if (btnTop) btnTop.addEventListener('click', function (e) { handleBulkAction(e, 'bulk-action-selector-top'); });
				if (btnBottom) btnBottom.addEventListener('click', function (e) { handleBulkAction(e, 'bulk-action-selector-bottom'); });
			});
		</script>
		<?php
	}

	public function ajax_assign_featured_category() {
		check_ajax_referer( self::NONCE_ACTION );

		if ( ! current_user_can( 'edit_sz_courses' ) && ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( 'Nincs jogosultságod.' );
		}

		$post_ids = json_decode( isset( $_POST['post_ids'] ) ? wp_unslash( $_POST['post_ids'] ) : '[]', true );
		$term_ids = json_decode( isset( $_POST['term_ids'] ) ? wp_unslash( $_POST['term_ids'] ) : '[]', true );
		$new_name = isset( $_POST['new_term_name'] ) ? sanitize_text_field( wp_unslash( $_POST['new_term_name'] ) ) : '';
		$replace  = isset( $_POST['replace'] ) && $_POST['replace'] === '1';

		if ( empty( $post_ids ) || ! is_array( $post_ids ) ) {
			wp_send_json_error( 'Nincsenek kijelölt elemek.' );
		}
		if ( ! is_array( $term_ids ) ) {
			$term_ids = array();
		}
		$term_ids = array_map( 'intval', $term_ids );

		if ( $new_name !== '' ) {
			$existing = get_term_by( 'name', $new_name, self::TAXONOMY );
			if ( $existing ) {
				$term_ids[] = $existing->term_id;
			} else {
				$created = wp_insert_term( $new_name, self::TAXONOMY );
				if ( is_wp_error( $created ) ) {
					wp_send_json_error( $created->get_error_message() );
				}
				$term_ids[] = intval( $created['term_id'] );
			}
		}

		$term_ids = array_values( array_unique( array_filter( $term_ids ) ) );
		if ( empty( $term_ids ) ) {
			wp_send_json_error( 'Nincs kiválasztott vagy megadott kategória.' );
		}

		$updated = 0;
		foreach ( $post_ids as $pid ) {
			$post_id = intval( $pid );
			if ( ! $post_id || get_post_type( $post_id ) !== 'sz_course' ) {
				continue;
			}

			wp_set_object_terms( $post_id, $term_ids, self::TAXONOMY, ! $replace );
			++$updated;
		}

		wp_send_json_success( "Sikeresen módosítva: $updated db képzés kiemelt kategóriája." );
	}

	// --- "Kiemelt kategóriák" admin oldal (kategória-kezelés + sorrend, EGY helyen) ---

	public function add_categories_page() {
		add_submenu_page(
			'edit.php?post_type=sz_course',
			'Kiemelt kategóriák',
			'Kiemelt kategóriák',
			'edit_sz_courses',
			'szeducate-featured-categories',
			array( $this, 'render_categories_page' )
		);
	}

	public function render_categories_page() {
		if ( ! current_user_can( 'edit_sz_courses' ) && ! current_user_can( 'manage_options' ) ) {
			wp_die( 'Nincs jogosultságod ehhez az oldalhoz.' );
		}

		$terms = get_terms(
			array(
				'taxonomy'   => self::TAXONOMY,
				'hide_empty' => false,
			)
		);
		if ( is_wp_error( $terms ) ) {
			$terms = array();
		}

		$notice = isset( $_GET['msg'] ) ? sanitize_text_field( wp_unslash( $_GET['msg'] ) ) : '';
		$known  = array(
			'added'   => 'Kategória létrehozva.',
			'renamed' => 'Kategória átnevezve.',
			'deleted' => 'Kategória törölve.',
		);
		?>
		<div class="wrap">
			<h1>Kiemelt kategóriák</h1>
			<p>Itt hozhatod létre, nevezheted át vagy törölheted a kiemelt kategóriákat, és állíthatod be soronként a bennük szereplő képzések sorrendjét. A képzéseket a <a href="<?php echo esc_url( admin_url( 'edit.php?post_type=sz_course' ) ); ?>">Képzések listájában</a>, a "Kiemelt kategóriába helyezés" tömeges művelettel sorolhatod be egy-egy kategóriába.</p>

			<?php if ( $notice !== '' ) : ?>
				<?php if ( isset( $known[ $notice ] ) ) : ?>
					<div class="notice notice-success is-dismissible"><p><?php echo esc_html( $known[ $notice ] ); ?></p></div>
				<?php else : ?>
					<div class="notice notice-error is-dismissible"><p><?php echo esc_html( $notice ); ?></p></div>
				<?php endif; ?>
			<?php endif; ?>

			<h2>Kategóriák kezelése</h2>
			<?php if ( ! empty( $terms ) ) : ?>
				<table class="widefat striped" style="max-width:760px;">
					<thead>
						<tr>
							<th>Név</th>
							<th style="width:90px;">Képzések</th>
							<th style="width:280px;">Műveletek</th>
						</tr>
					</thead>
					<tbody>
						<?php foreach ( $terms as $term ) : ?>
							<tr>
								<td>
									<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" style="display:flex; gap:6px;">
										<?php wp_nonce_field( self::NONCE_ACTION ); ?>
										<input type="hidden" name="action" value="szeducate_featured_manage_category">
										<input type="hidden" name="op" value="rename">
										<input type="hidden" name="term_id" value="<?php echo esc_attr( (string) $term->term_id ); ?>">
										<input type="text" name="name" value="<?php echo esc_attr( $term->name ); ?>" style="flex:1;">
										<button type="submit" class="button">Átnevezés</button>
									</form>
								</td>
								<td><?php echo intval( $term->count ); ?></td>
								<td>
									<a href="#sz-order-<?php echo esc_attr( (string) $term->term_id ); ?>" class="button">Sorrend szerkesztése</a>
									<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" style="display:inline;" onsubmit="return confirm('Biztosan törlöd a(z) &quot;<?php echo esc_js( $term->name ); ?>&quot; kategóriát? A hozzá tartozó képzések besorolása megszűnik, maguk a képzések nem törlődnek.');">
										<?php wp_nonce_field( self::NONCE_ACTION ); ?>
										<input type="hidden" name="action" value="szeducate_featured_manage_category">
										<input type="hidden" name="op" value="delete">
										<input type="hidden" name="term_id" value="<?php echo esc_attr( (string) $term->term_id ); ?>">
										<button type="submit" class="button" style="color:#d63638; border-color:#d63638;">Törlés</button>
									</form>
								</td>
							</tr>
						<?php endforeach; ?>
					</tbody>
				</table>
			<?php else : ?>
				<p style="color:#666; font-style:italic;">Még nincs egyetlen kiemelt kategória sem.</p>
			<?php endif; ?>

			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" style="margin-top:15px; display:flex; gap:8px; max-width:520px;">
				<?php wp_nonce_field( self::NONCE_ACTION ); ?>
				<input type="hidden" name="action" value="szeducate_featured_manage_category">
				<input type="hidden" name="op" value="add">
				<input type="text" name="name" placeholder="Új kategória neve, pl. Kezdőlapra" style="flex:1;" required>
				<button type="submit" class="button button-primary">+ Új kategória</button>
			</form>

			<hr style="margin:30px 0;">

			<h2>Sorrend beállítása</h2>
			<p>A húzáshoz fogd meg a <span class="dashicons dashicons-menu" aria-hidden="true" style="vertical-align:text-bottom;"></span> ikont - a sorrend kategóriánként, automatikusan mentésre kerül.</p>

			<?php if ( empty( $terms ) ) : ?>
				<p style="color:#666; font-style:italic;">Hozz létre legalább egy kategóriát a sorrend beállításához.</p>
			<?php else : ?>
				<?php foreach ( $terms as $term ) : ?>
					<?php $ordered_posts = self::get_ordered_course_posts( $term->term_id ); ?>
					<div id="sz-order-<?php echo esc_attr( (string) $term->term_id ); ?>" style="margin-bottom:35px;">
						<h3><?php echo esc_html( $term->name ); ?></h3>
						<?php if ( empty( $ordered_posts ) ) : ?>
							<p style="color:#666; font-style:italic;">Ehhez a kategóriához jelenleg nincs hozzárendelt (publikált) képzés.</p>
						<?php else : ?>
							<ul class="sz-featured-order-list" style="max-width:600px; list-style:none; margin:0; padding:0;" data-term-id="<?php echo esc_attr( (string) $term->term_id ); ?>">
								<?php foreach ( $ordered_posts as $post_id ) : ?>
									<li data-post-id="<?php echo esc_attr( (string) $post_id ); ?>" style="display:flex; align-items:center; gap:10px; background:#fff; border:1px solid #dcdcde; border-radius:4px; padding:10px 14px; margin-bottom:8px; cursor:move;">
										<span class="dashicons dashicons-menu" aria-hidden="true" style="color:#8c8f94;"></span>
										<?php if ( has_post_thumbnail( $post_id ) ) : ?>
											<?php echo get_the_post_thumbnail( $post_id, array( 40, 40 ), array( 'style' => 'border-radius:4px; object-fit:cover;' ) ); ?>
										<?php endif; ?>
										<span><?php echo esc_html( get_the_title( $post_id ) ); ?></span>
									</li>
								<?php endforeach; ?>
							</ul>
							<p class="sz-featured-order-status" style="color:#666; font-style:italic;"></p>
						<?php endif; ?>
					</div>
				<?php endforeach; ?>
			<?php endif; ?>
		</div>

		<script src="https://cdn.jsdelivr.net/npm/sortablejs@latest/Sortable.min.js"></script>
		<script type="text/javascript">
			document.addEventListener('DOMContentLoaded', function () {
				document.querySelectorAll('.sz-featured-order-list').forEach(function (list) {
					if (typeof Sortable === 'undefined') return;
					var status = list.nextElementSibling;

					new Sortable(list, {
						handle: '.dashicons-menu',
						animation: 150,
						onEnd: function () {
							var postIds = Array.from(list.querySelectorAll('li')).map(function (li) { return li.getAttribute('data-post-id'); });

							if (status) status.textContent = 'Mentés...';
							var data = new URLSearchParams();
							data.append('action', 'szeducate_save_featured_order');
							data.append('term_id', list.getAttribute('data-term-id'));
							data.append('post_ids', JSON.stringify(postIds));
							data.append('_ajax_nonce', '<?php echo wp_create_nonce( self::NONCE_ACTION ); ?>');

							fetch(ajaxurl, { method: 'POST', body: data })
								.then(function (res) { return res.json(); })
								.then(function (response) {
									if (status) status.textContent = response.success ? 'Sorrend mentve.' : 'Hiba: ' + response.data;
								})
								.catch(function () {
									if (status) status.textContent = 'Hálózati hiba történt a mentés során.';
								});
						}
					});
				});
			});
		</script>
		<?php
	}

	// A "Kategóriák kezelése" tábla és az "Új kategória" űrlap közös feldolgozója -
	// egyszerű form-post + redirect (mint a séma-oldal és a Beállítások oldal
	// szinkron-gombjai), nem AJAX, mert ez ritkán használt admin-művelet, ahol az
	// oldal-újratöltés semmilyen UX-hátránnyal nem jár.
	public function handle_manage_category() {
		check_admin_referer( self::NONCE_ACTION );

		if ( ! current_user_can( 'edit_sz_courses' ) && ! current_user_can( 'manage_options' ) ) {
			wp_die( 'Nincs jogosultságod.' );
		}

		$op      = isset( $_POST['op'] ) ? sanitize_text_field( wp_unslash( $_POST['op'] ) ) : '';
		$term_id = isset( $_POST['term_id'] ) ? intval( $_POST['term_id'] ) : 0;
		$name    = isset( $_POST['name'] ) ? sanitize_text_field( wp_unslash( $_POST['name'] ) ) : '';
		$msg     = 'Érvénytelen kérés.';

		if ( 'add' === $op && $name !== '' ) {
			$result = wp_insert_term( $name, self::TAXONOMY );
			$msg    = is_wp_error( $result ) ? $result->get_error_message() : 'added';
		} elseif ( 'rename' === $op && $term_id && $name !== '' ) {
			$result = wp_update_term( $term_id, self::TAXONOMY, array( 'name' => $name ) );
			$msg    = is_wp_error( $result ) ? $result->get_error_message() : 'renamed';
		} elseif ( 'delete' === $op && $term_id ) {
			$result = wp_delete_term( $term_id, self::TAXONOMY );
			if ( is_wp_error( $result ) ) {
				$msg = $result->get_error_message();
			} else {
				$msg = $result ? 'deleted' : 'A törlés nem sikerült.';
			}
		}

		wp_safe_redirect( add_query_arg( 'msg', urlencode( $msg ), admin_url( 'edit.php?post_type=sz_course&page=szeducate-featured-categories' ) ) );
		exit;
	}

	// A megadott kiemelt kategóriához tartozó, PUBLIKÁLT képzések ID-jai, a mentett
	// egyedi sorrend (term-meta) szerint - a még nem sorrendezett (pl. újonnan
	// hozzáadott) képzések a végén, cím szerint. Az Elementor widget is ezt hívja.
	public static function get_ordered_course_posts( $term_id ) {
		$term_id = intval( $term_id );
		if ( ! $term_id ) {
			return array();
		}

		$post_ids = get_posts(
			array(
				'post_type'      => 'sz_course',
				'post_status'    => 'publish',
				'posts_per_page' => -1,
				'fields'         => 'ids',
				'orderby'        => 'title',
				'order'          => 'ASC',
				'tax_query'      => array(
					array(
						'taxonomy' => self::TAXONOMY,
						'field'    => 'term_id',
						'terms'    => $term_id,
					),
				),
			)
		);

		$saved_order = get_term_meta( $term_id, self::ORDER_META, true );
		if ( ! is_array( $saved_order ) ) {
			$saved_order = array();
		}

		$post_ids_set = array_flip( $post_ids );
		$ordered      = array();
		foreach ( $saved_order as $pid ) {
			$pid = intval( $pid );
			if ( isset( $post_ids_set[ $pid ] ) ) {
				$ordered[] = $pid;
				unset( $post_ids_set[ $pid ] );
			}
		}
		foreach ( $post_ids as $pid ) {
			if ( isset( $post_ids_set[ $pid ] ) ) {
				$ordered[] = $pid;
			}
		}

		return $ordered;
	}

	public function ajax_save_featured_order() {
		check_ajax_referer( self::NONCE_ACTION );

		if ( ! current_user_can( 'edit_sz_courses' ) && ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( 'Nincs jogosultságod.' );
		}

		$term_id  = isset( $_POST['term_id'] ) ? intval( $_POST['term_id'] ) : 0;
		$post_ids = json_decode( isset( $_POST['post_ids'] ) ? wp_unslash( $_POST['post_ids'] ) : '[]', true );

		if ( ! $term_id || ! term_exists( $term_id, self::TAXONOMY ) ) {
			wp_send_json_error( 'Érvénytelen kategória.' );
		}
		if ( ! is_array( $post_ids ) ) {
			wp_send_json_error( 'Érvénytelen adat.' );
		}

		// Csak a ténylegesen ehhez a termhez tartozó, publikált sz_course posztok ID-jait
		// fogadjuk el - ez zárja ki, hogy egy kézzel összerakott kéréssel valaki más
		// posztokat soroljon be ide.
		$valid_ids     = get_posts(
			array(
				'post_type'      => 'sz_course',
				'post_status'    => 'publish',
				'posts_per_page' => -1,
				'fields'         => 'ids',
				'tax_query'      => array(
					array(
						'taxonomy' => self::TAXONOMY,
						'field'    => 'term_id',
						'terms'    => $term_id,
					),
				),
			)
		);
		$valid_ids_set = array_flip( $valid_ids );

		$clean_ids = array();
		foreach ( $post_ids as $pid ) {
			$pid = intval( $pid );
			if ( isset( $valid_ids_set[ $pid ] ) ) {
				$clean_ids[] = $pid;
			}
		}

		update_term_meta( $term_id, self::ORDER_META, $clean_ids );
		wp_send_json_success( 'Sorrend elmentve.' );
	}
}
