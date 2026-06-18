<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class SZEducate_Search_Widget extends \Elementor\Widget_Base {

	public function get_name() { return 'szeducate_search'; }
	public function get_title() { return 'SZEducate Okos Kereső'; }
	public function get_icon() { return 'eicon-search'; }
	public function get_categories() { return [ 'general' ]; }

	protected function register_controls() {
		
		$this->start_controls_section(
			'content_section',
			[
				'label' => 'Kereső Beállításai',
				'tab'   => \Elementor\Controls_Manager::TAB_CONTENT,
			]
		);

		$this->add_control(
			'placeholder',
			[
				'label'   => 'Helyőrző szöveg',
				'type'    => \Elementor\Controls_Manager::TEXT,
				'default' => 'Keress képzésekre, kulcsszavakra...',
			]
		);

		$this->add_control(
			'archive_url',
			[
				'label'       => 'Szaklista (Archívum) URL',
				'type'        => \Elementor\Controls_Manager::TEXT,
				'placeholder' => 'https://sze.hu/kepzeseink/',
				'description' => 'Ide irányítja a látogatót, ha entert üt a keresőben (az Összes találat megtekintéséhez).',
			]
		);

		$this->end_controls_section();

		// --- STÍLUS: Beviteli Mező ---
		$this->start_controls_section(
			'style_input_section',
			[
				'label' => 'Beviteli Mező Stílusa',
				'tab'   => \Elementor\Controls_Manager::TAB_STYLE,
			]
		);

		$this->add_control(
			'input_bg',
			[
				'label'     => 'Háttérszín',
				'type'      => \Elementor\Controls_Manager::COLOR,
				'default'   => '#F3F4F6',
				'selectors' => [ '{{WRAPPER}} .sz-search-input' => 'background-color: {{VALUE}};' ],
			]
		);

		$this->add_control(
			'input_color',
			[
				'label'     => 'Szövegszín',
				'type'      => \Elementor\Controls_Manager::COLOR,
				'default'   => '#242943',
				'selectors' => [ '{{WRAPPER}} .sz-search-input' => 'color: {{VALUE}};' ],
			]
		);

		$this->add_control(
			'icon_color',
			[
				'label'     => 'Nagyító ikon színe',
				'type'      => \Elementor\Controls_Manager::COLOR,
				'default'   => '#50ADC9',
				'selectors' => [ '{{WRAPPER}} .sz-search-icon' => 'fill: {{VALUE}};' ],
			]
		);

		$this->add_group_control(
			\Elementor\Group_Control_Typography::get_type(),
			[
				'name'     => 'input_typography',
				'selector' => '{{WRAPPER}} .sz-search-input',
			]
		);

		$this->add_control(
			'input_border_radius',
			[
				'label'      => 'Lekerekítés',
				'type'       => \Elementor\Controls_Manager::DIMENSIONS,
				'size_units' => [ 'px', '%' ],
				'default'    => [ 'top' => 30, 'right' => 30, 'bottom' => 30, 'left' => 30, 'unit' => 'px' ],
				'selectors'  => [ '{{WRAPPER}} .sz-search-form' => 'border-radius: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};' ],
			]
		);

		$this->end_controls_section();

		// --- STÍLUS: Legördülő Eredmények ---
		$this->start_controls_section(
			'style_dropdown_section',
			[
				'label' => 'Legördülő Eredmények Stílusa',
				'tab'   => \Elementor\Controls_Manager::TAB_STYLE,
			]
		);

		$this->add_control(
			'dropdown_bg',
			[
				'label'     => 'Háttérszín',
				'type'      => \Elementor\Controls_Manager::COLOR,
				'default'   => '#FFFFFF',
				'selectors' => [ '{{WRAPPER}} .sz-search-results' => 'background-color: {{VALUE}};' ],
			]
		);

		$this->add_control(
			'dropdown_item_color',
			[
				'label'     => 'Találat Szövegszíne',
				'type'      => \Elementor\Controls_Manager::COLOR,
				'default'   => '#242943',
				'selectors' => [ '{{WRAPPER}} .sz-search-item' => 'color: {{VALUE}};' ],
			]
		);

		$this->add_control(
			'dropdown_item_hover',
			[
				'label'     => 'Találat Háttér (Hover)',
				'type'      => \Elementor\Controls_Manager::COLOR,
				'default'   => '#F3F4F6',
				'selectors' => [ '{{WRAPPER}} .sz-search-item:hover' => 'background-color: {{VALUE}};' ],
			]
		);

		$this->end_controls_section();
	}

	protected function render() {
		$settings = $this->get_settings_for_display();
		$placeholder = esc_attr( $settings['placeholder'] );
		$archive_url = esc_url( $settings['archive_url'] );
		$widget_id = $this->get_id();

		// UI Kirajzolása
		?>
		<div class="sz-search-wrapper" style="position:relative; width:100%; font-family:sans-serif;">
			<form action="<?php echo $archive_url; ?>" method="GET" class="sz-search-form" id="sz-search-form-<?php echo $widget_id; ?>" style="display:flex; align-items:center; overflow:hidden; border:1px solid #ddd; background:#F3F4F6;">
				<span style="padding-left:20px; display:flex; align-items:center;">
					<svg class="sz-search-icon" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" width="18" height="18"><path d="M15.5 14h-.79l-.28-.27A6.471 6.471 0 0 0 16 9.5 6.5 6.5 0 1 0 9.5 16c1.61 0 3.09-.59 4.23-1.57l.27.28v.79l5 4.99L20.49 19l-4.99-5zm-6 0C7.01 14 5 11.99 5 9.5S7.01 5 9.5 5 14 7.01 14 9.5 11.99 14 9.5 14z"/></svg>
				</span>
				<input type="text" name="sz_search" class="sz-search-input" id="sz-search-input-<?php echo $widget_id; ?>" placeholder="<?php echo $placeholder; ?>" autocomplete="off" style="flex:1; border:none; background:transparent; padding:15px 15px; outline:none; font-size:16px;">
				<div class="sz-search-spinner" id="sz-spinner-<?php echo $widget_id; ?>" style="display:none; padding-right:20px;">
					<svg width="20" height="20" viewBox="0 0 38 38" xmlns="http://www.w3.org/2000/svg" stroke="#50ADC9"><g fill="none" fill-rule="evenodd"><g transform="translate(1 1)" stroke-width="2"><circle stroke-opacity=".5" cx="18" cy="18" r="18"/><path d="M36 18c0-9.94-8.06-18-18-18"><animateTransform attributeName="transform" type="rotate" from="0 18 18" to="360 18 18" dur="1s" repeatCount="indefinite"/></path></g></g></svg>
				</div>
			</form>
			
			<div class="sz-search-results" id="sz-results-<?php echo $widget_id; ?>" style="display:none; position:absolute; top:100%; left:0; right:0; z-index:9999; margin-top:5px; background:#fff; border:1px solid #ddd; border-radius:8px; box-shadow:0 10px 25px rgba(0,0,0,0.1); overflow:hidden; max-height:400px; overflow-y:auto;">
				<!-- Az eredmények ide jönnek AJAX-szal -->
			</div>
		</div>

		<script>
		document.addEventListener('DOMContentLoaded', function() {
			const input = document.getElementById('sz-search-input-<?php echo $widget_id; ?>');
			const resultsBox = document.getElementById('sz-results-<?php echo $widget_id; ?>');
			const spinner = document.getElementById('sz-spinner-<?php echo $widget_id; ?>');
			let debounceTimer;

			input.addEventListener('input', function() {
				clearTimeout(debounceTimer);
				const query = input.value.trim();

				if (query.length < 2) {
					resultsBox.style.display = 'none';
					spinner.style.display = 'none';
					return;
				}

				spinner.style.display = 'block';

				debounceTimer = setTimeout(() => {
					fetch('/wp-json/szeducate/v1/client/search?q=' + encodeURIComponent(query))
					.then(response => response.json())
					.then(data => {
						spinner.style.display = 'none';
						resultsBox.innerHTML = '';
						
						if (data.length > 0) {
							let html = '<ul style="list-style:none; padding:0; margin:0;">';
							data.forEach(item => {
								const dotColor = item.is_active ? '#50ADC9' : '#D9D9D9';
								html += `
									<li>
										<a href="${item.url}" class="sz-search-item" style="display:flex; align-items:center; padding:12px 20px; text-decoration:none; border-bottom:1px solid #eee; transition:background 0.2s;">
											<span style="display:inline-block; width:8px; height:8px; border-radius:50%; background-color:${dotColor}; margin-right:12px; flex-shrink:0;"></span>
											<span style="font-weight:600; line-height:1.2;">${item.title}</span>
										</a>
									</li>`;
							});
							
							// Plusz "Összes találat" gomb a végére, ha van archívum megadva
							<?php if ( ! empty( $archive_url ) ) : ?>
							html += `
								<li style="text-align:center; padding:10px; background:#f9f9f9;">
									<a href="<?php echo $archive_url; ?>?sz_search=${encodeURIComponent(query)}" style="font-size:13px; color:#50ADC9; text-decoration:underline; font-weight:600;">Összes találat megtekintése</a>
								</li>`;
							<?php endif; ?>
							
							html += '</ul>';
							resultsBox.innerHTML = html;
							resultsBox.style.display = 'block';
						} else {
							resultsBox.innerHTML = '<div style="padding:15px 20px; color:#888; font-style:italic;">Nincs a keresésnek megfelelő képzés.</div>';
							resultsBox.style.display = 'block';
						}
					})
					.catch(() => {
						spinner.style.display = 'none';
					});
				}, 400); // 400ms várakozás gépelés után (kíméli a szervert)
			});

			// Eltünteti a dobozt, ha máshova kattintasz
			document.addEventListener('click', function(e) {
				if (!input.contains(e.target) && !resultsBox.contains(e.target)) {
					resultsBox.style.display = 'none';
				}
			});

			// Ha rákattintasz az inputra és van benne szöveg, újra kinyitja
			input.addEventListener('focus', function() {
				if (input.value.trim().length >= 2 && resultsBox.innerHTML !== '') {
					resultsBox.style.display = 'block';
				}
			});
		});
		</script>
		<?php
	}
}