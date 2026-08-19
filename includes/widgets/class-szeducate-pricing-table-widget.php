<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// Konkrét, egy célra épített táblázat-widget - a Képzés-sablonba (Single Theme Builder
// template) kerül, és KIZÁRÓLAG az éppen megjelenített Képzés saját adatait mutatja
// (nem az összes Képzést listázza). Az adatforrás egy valódi kétszintű beágyazott séma-
// struktúra: egy "Munkarend-csoportok" Lista mező, aminek MINDEN SORA egy munkarendhez
// (pl. Nappali) tartozik, és tartalmaz egy SAJÁT, beágyazott Listát a hozzá tartozó
// nyelv+finanszírozási forma+ár kombinációkkal. Ez teszi lehetővé, hogy pl. "Nappali"
// csak angolul, "Távoktatás" csak magyarul induljon, méghozzá eltérő áron - anélkül,
// hogy bármit is keresztbe kellene szoroznunk (a csoportosítás már eleve a séma szintjén
// megvan). A munkarend-csoportok fülekként jelennek meg, valódi szűréssel. Ami rugalmas
// benne: az összes felhasznált mező- és al-mező-AZONOSÍTÓ (és a "mi számít államinak"
// összehasonlító érték) Elementor vezérlőkből állítható, hogy ha a séma később
// átnevezésre kerül, kódmódosítás nélkül követhető legyen.
class SZEducate_Pricing_Table_Widget extends \Elementor\Widget_Base {

	public function get_name() { return 'szeducate_pricing_table'; }
	public function get_title() { return 'SZEducate Támogatási Táblázat'; }
	public function get_icon() { return 'eicon-table'; }
	public function get_categories() { return [ 'general' ]; }

	protected function register_controls() {

		$this->start_controls_section(
			'data_section',
			[
				'label' => 'Adatforrás (mező azonosítók)',
				'tab'   => \Elementor\Controls_Manager::TAB_CONTENT,
			]
		);

		$this->add_control(
			'groups_key',
			[
				'label'       => 'Munkarend-csoportok mező (Lista)',
				'type'        => \Elementor\Controls_Manager::TEXT,
				'default'     => 'munkarend_csoportok',
				'description' => 'A Séma Tervezőben létrehozott legfelső szintű "Lista" mező azonosítója - minden sora egy munkarend, benne a saját beágyazott lista al-mezővel.',
			]
		);
		$this->add_control(
			'sub_munkarend_key',
			[ 'label' => 'Al-mező: Munkarend', 'type' => \Elementor\Controls_Manager::TEXT, 'default' => 'munkarend' ]
		);
		$this->add_control(
			'sub_variants_key',
			[
				'label'       => 'Al-mező: Beágyazott variáns-lista',
				'type'        => \Elementor\Controls_Manager::TEXT,
				'default'     => 'variansok',
				'description' => 'A Munkarend-csoportok mezőn belüli "Beágyazott lista" típusú al-mező azonosítója.',
			]
		);

		$this->add_control(
			'nested_nyelv_key',
			[ 'label' => 'Beágyazott al-mező: Nyelv', 'type' => \Elementor\Controls_Manager::TEXT, 'default' => 'nyelv', 'separator' => 'before' ]
		);
		$this->add_control(
			'nested_finance_key',
			[ 'label' => 'Beágyazott al-mező: Finanszírozási forma', 'type' => \Elementor\Controls_Manager::TEXT, 'default' => 'finanszirozasi_forma' ]
		);
		$this->add_control(
			'nested_price_type_key',
			[ 'label' => 'Beágyazott al-mező: Ár típusa', 'type' => \Elementor\Controls_Manager::TEXT, 'default' => 'ar_tipus' ]
		);
		$this->add_control(
			'nested_amount_key',
			[ 'label' => 'Beágyazott al-mező: Összeg', 'type' => \Elementor\Controls_Manager::TEXT, 'default' => 'osszeg' ]
		);

		$this->add_control(
			'state_funded_value',
			[
				'label'       => 'Állami finanszírozás azonosító szövege',
				'type'        => \Elementor\Controls_Manager::TEXT,
				'default'     => 'Állami',
				'separator'   => 'before',
				'description' => 'Ha a Finanszírozási forma al-mező értéke ezt tartalmazza, a sor "állami"-nak számít - minden más érték önköltségesnek.',
			]
		);

		$this->add_control(
			'default_lang_value',
			[
				'label'       => 'Nyelv, amihez NEM kell kiegészítés a névnél',
				'type'        => \Elementor\Controls_Manager::TEXT,
				'default'     => 'Magyar',
				'description' => 'Ha a Nyelv al-mező ettől eltér (pl. angol, német), a szak neve mindig megkapja a "(nyelven)" kiegészítést - akkor is, ha az adott munkarendben csak ez az egy nyelv fut.',
			]
		);

		$this->add_control(
			'duration_key',
			[
				'label'       => 'Képzési idő mező',
				'type'        => \Elementor\Controls_Manager::TEXT,
				'default'     => 'felevek_szama',
				'description' => 'Ez a Képzés szintjén (nem soronként) tárolt mező, minden sorban ugyanaz jelenik meg.',
			]
		);

		$this->end_controls_section();

		$this->start_controls_section(
			'labels_section',
			[
				'label' => 'Feliratok',
				'tab'   => \Elementor\Controls_Manager::TAB_CONTENT,
			]
		);

		$this->add_control(
			'col_badge_label',
			[ 'label' => 'Oszlop: Fin. forma', 'type' => \Elementor\Controls_Manager::TEXT, 'default' => 'Fin. forma' ]
		);
		$this->add_control(
			'col_name_label',
			[ 'label' => 'Oszlop: Szak neve', 'type' => \Elementor\Controls_Manager::TEXT, 'default' => 'Szak neve' ]
		);
		$this->add_control(
			'col_cost_label',
			[ 'label' => 'Oszlop: Önköltség', 'type' => \Elementor\Controls_Manager::TEXT, 'default' => 'Önköltség' ]
		);
		$this->add_control(
			'col_duration_label',
			[ 'label' => 'Oszlop: Képzési idő', 'type' => \Elementor\Controls_Manager::TEXT, 'default' => 'Képzési idő' ]
		);

		$this->add_control(
			'state_funded_badge',
			[ 'label' => 'Állami jelvény szövege', 'type' => \Elementor\Controls_Manager::TEXT, 'default' => 'A', 'separator' => 'before' ]
		);
		$this->add_control(
			'self_funded_badge',
			[ 'label' => 'Önköltséges jelvény szövege', 'type' => \Elementor\Controls_Manager::TEXT, 'default' => 'K' ]
		);
		$this->add_control(
			'state_funded_cost_label',
			[ 'label' => 'Állami sor "költség" felirata', 'type' => \Elementor\Controls_Manager::TEXT, 'default' => 'Támogatott' ]
		);

		$this->end_controls_section();

		// ------------------------------------------------------------------
		// Stílus: Fülek
		// ------------------------------------------------------------------
		$this->start_controls_section(
			'style_tabs_section',
			[ 'label' => 'Fülek', 'tab' => \Elementor\Controls_Manager::TAB_STYLE ]
		);

		$this->add_group_control(
			\Elementor\Group_Control_Typography::get_type(),
			[ 'name' => 'tab_typography', 'selector' => '{{WRAPPER}} .sz-pt-tab' ]
		);

		$this->add_control(
			'tab_active_bg',
			[
				'label'     => 'Aktív fül háttér',
				'type'      => \Elementor\Controls_Manager::COLOR,
				'default'   => '#50ADC9',
				'selectors' => [
					'{{WRAPPER}} .sz-pt-tab.sz-pt-tab-active' => 'background-color: {{VALUE}};',
					'{{WRAPPER}} .sz-pt-tab-indicator'        => 'background-color: {{VALUE}};',
				],
			]
		);
		$this->add_control(
			'tab_active_text',
			[ 'label' => 'Aktív fül szöveg', 'type' => \Elementor\Controls_Manager::COLOR, 'default' => '#FFFFFF', 'selectors' => [ '{{WRAPPER}} .sz-pt-tab.sz-pt-tab-active' => 'color: {{VALUE}};' ] ]
		);
		$this->add_control(
			'tab_inactive_bg',
			[ 'label' => 'Inaktív fül háttér', 'type' => \Elementor\Controls_Manager::COLOR, 'default' => '#242943', 'selectors' => [ '{{WRAPPER}} .sz-pt-tab' => 'background-color: {{VALUE}};' ] ]
		);
		$this->add_control(
			'tab_inactive_text',
			[ 'label' => 'Inaktív fül szöveg', 'type' => \Elementor\Controls_Manager::COLOR, 'default' => '#FFFFFF', 'selectors' => [ '{{WRAPPER}} .sz-pt-tab' => 'color: {{VALUE}};' ] ]
		);
		$this->add_control(
			'tab_border_radius',
			[ 'label' => 'Fül lekerekítés', 'type' => \Elementor\Controls_Manager::SLIDER, 'size_units' => [ 'px' ], 'range' => [ 'px' => [ 'min' => 0, 'max' => 40 ] ], 'default' => [ 'unit' => 'px', 'size' => 6 ], 'selectors' => [ '{{WRAPPER}} .sz-pt-tab' => 'border-radius: {{SIZE}}{{UNIT}};', '{{WRAPPER}} .sz-pt-tab-indicator' => 'border-radius: {{SIZE}}{{UNIT}};' ] ]
		);
		$this->add_responsive_control(
			'tab_padding',
			[ 'label' => 'Fül belső margó', 'type' => \Elementor\Controls_Manager::DIMENSIONS, 'size_units' => [ 'px', 'em' ], 'default' => [ 'top' => '14', 'right' => '24', 'bottom' => '14', 'left' => '24', 'unit' => 'px' ], 'selectors' => [ '{{WRAPPER}} .sz-pt-tab' => 'padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};' ] ]
		);
		$this->add_responsive_control(
			'tab_gap',
			[ 'label' => 'Fülek közötti térköz', 'type' => \Elementor\Controls_Manager::SLIDER, 'size_units' => [ 'px' ], 'range' => [ 'px' => [ 'min' => 0, 'max' => 60 ] ], 'default' => [ 'unit' => 'px', 'size' => 12 ], 'selectors' => [ '{{WRAPPER}} .sz-pt-tabs' => 'gap: {{SIZE}}{{UNIT}};' ] ]
		);
		$this->add_responsive_control(
			'tabs_margin_bottom',
			[ 'label' => 'Térköz a táblázat felett', 'type' => \Elementor\Controls_Manager::SLIDER, 'size_units' => [ 'px' ], 'range' => [ 'px' => [ 'min' => 0, 'max' => 80 ] ], 'default' => [ 'unit' => 'px', 'size' => 24 ], 'selectors' => [ '{{WRAPPER}} .sz-pt-tabs' => 'margin-bottom: {{SIZE}}{{UNIT}};' ] ]
		);
		$this->add_control(
			'tab_lift_amount',
			[
				'label'       => 'Fül "megemelés" hover/aktív állapotban',
				'type'        => \Elementor\Controls_Manager::SLIDER,
				'size_units'  => [ 'px' ],
				'range'       => [ 'px' => [ 'min' => 0, 'max' => 15 ] ],
				'default'     => [ 'unit' => 'px', 'size' => 3 ],
				'description' => '0 = a fül nem mozdul el, csak a szín/árnyék jelzi az állapotot.',
				'selectors'   => [
					'{{WRAPPER}} .sz-pt-tab:hover, {{WRAPPER}} .sz-pt-tab.sz-pt-tab-active' => 'transform: translateY(-{{SIZE}}{{UNIT}});',
				],
			]
		);
		$this->add_group_control(
			\Elementor\Group_Control_Box_Shadow::get_type(),
			[
				'name'     => 'tab_active_shadow',
				'label'    => 'Aktív fül árnyéka',
				'selector' => '{{WRAPPER}} .sz-pt-tab.sz-pt-tab-active',
				'default'  => [
					'horizontal' => 0,
					'vertical'   => 8,
					'blur'       => 16,
					'spread'     => -4,
					'color'      => 'rgba(0,0,0,0.25)',
				],
			]
		);
		$this->add_control(
			'tab_indicator_show',
			[
				'label'        => 'Csúszó fény-jelző az aktív fül mögött',
				'type'         => \Elementor\Controls_Manager::SWITCHER,
				'label_on'     => 'Be',
				'label_off'    => 'Ki',
				'default'      => 'yes',
				'description'  => 'Egy elmosott, a fül színével megegyező glow, ami átcsúszik a fülek között váltáskor. Ha ez a "rejtélyes árnyék" amit nem szeretnél, itt kapcsold ki.',
				'separator'    => 'before',
			]
		);
		$this->add_control(
			'tab_indicator_opacity',
			[
				'label'     => 'Fény-jelző átlátszósága',
				'type'      => \Elementor\Controls_Manager::SLIDER,
				'range'     => [ 'px' => [ 'min' => 0, 'max' => 1, 'step' => 0.05 ] ],
				'default'   => [ 'size' => 0.6 ],
				'condition' => [ 'tab_indicator_show' => 'yes' ],
				'selectors' => [ '{{WRAPPER}} .sz-pt-tab-indicator' => 'opacity: {{SIZE}};' ],
			]
		);
		$this->add_control(
			'tab_indicator_blur',
			[
				'label'     => 'Fény-jelző elmosása',
				'type'      => \Elementor\Controls_Manager::SLIDER,
				'size_units' => [ 'px' ],
				'range'     => [ 'px' => [ 'min' => 0, 'max' => 30 ] ],
				'default'   => [ 'unit' => 'px', 'size' => 9 ],
				'condition' => [ 'tab_indicator_show' => 'yes' ],
				'selectors' => [ '{{WRAPPER}} .sz-pt-tab-indicator' => 'filter: blur({{SIZE}}{{UNIT}});' ],
			]
		);

		$this->end_controls_section();

		// ------------------------------------------------------------------
		// Stílus: Táblázat elrendezés
		// ------------------------------------------------------------------
		$this->start_controls_section(
			'style_layout_section',
			[ 'label' => 'Táblázat elrendezés', 'tab' => \Elementor\Controls_Manager::TAB_STYLE ]
		);

		$this->add_control(
			'col_badge_width',
			[ 'label' => 'Oszlopszélesség: Fin. forma', 'type' => \Elementor\Controls_Manager::SLIDER, 'size_units' => [ '%' ], 'range' => [ '%' => [ 'min' => 5, 'max' => 50 ] ], 'default' => [ 'unit' => '%', 'size' => 14 ], 'selectors' => [ '{{WRAPPER}} .sz-pt-col-badge' => 'width: {{SIZE}}{{UNIT}};' ] ]
		);
		$this->add_control(
			'col_name_width',
			[ 'label' => 'Oszlopszélesség: Szak neve', 'type' => \Elementor\Controls_Manager::SLIDER, 'size_units' => [ '%' ], 'range' => [ '%' => [ 'min' => 10, 'max' => 70 ] ], 'default' => [ 'unit' => '%', 'size' => 44 ], 'selectors' => [ '{{WRAPPER}} .sz-pt-col-name' => 'width: {{SIZE}}{{UNIT}};' ] ]
		);
		$this->add_control(
			'col_cost_width',
			[ 'label' => 'Oszlopszélesség: Önköltség', 'type' => \Elementor\Controls_Manager::SLIDER, 'size_units' => [ '%' ], 'range' => [ '%' => [ 'min' => 5, 'max' => 50 ] ], 'default' => [ 'unit' => '%', 'size' => 26 ], 'selectors' => [ '{{WRAPPER}} .sz-pt-col-cost' => 'width: {{SIZE}}{{UNIT}};' ] ]
		);
		$this->add_control(
			'col_duration_width',
			[ 'label' => 'Oszlopszélesség: Képzési idő', 'type' => \Elementor\Controls_Manager::SLIDER, 'size_units' => [ '%' ], 'range' => [ '%' => [ 'min' => 5, 'max' => 50 ] ], 'default' => [ 'unit' => '%', 'size' => 16 ], 'selectors' => [ '{{WRAPPER}} .sz-pt-col-duration' => 'width: {{SIZE}}{{UNIT}};' ] ]
		);
		$this->add_control(
			'row_divider_color',
			[
				'label'       => 'Sorok közötti elválasztó vonal',
				'type'        => \Elementor\Controls_Manager::COLOR,
				'default'     => '',
				'separator'   => 'before',
				'description' => 'Üresen hagyva nincs elválasztó vonal, csak a páros/páratlan háttérszín-váltás jelzi a sorhatárt.',
				'selectors'   => [ '{{WRAPPER}} .sz-pt-table tbody tr' => 'box-shadow: inset 0 -1px 0 0 {{VALUE}};' ],
			]
		);

		$this->end_controls_section();

		// ------------------------------------------------------------------
		// Stílus: Táblázat fejléc
		// ------------------------------------------------------------------
		$this->start_controls_section(
			'style_header_section',
			[ 'label' => 'Táblázat fejléc', 'tab' => \Elementor\Controls_Manager::TAB_STYLE ]
		);

		$this->add_group_control(
			\Elementor\Group_Control_Typography::get_type(),
			[ 'name' => 'header_typography', 'selector' => '{{WRAPPER}} .sz-pt-table thead th', 'fields_options' => [ 'font_weight' => [ 'default' => '700' ] ] ]
		);
		$this->add_control(
			'header_bg',
			[ 'label' => 'Fejléc háttér', 'type' => \Elementor\Controls_Manager::COLOR, 'default' => '#242943', 'selectors' => [ '{{WRAPPER}} .sz-pt-table thead th' => 'background-color: {{VALUE}};' ] ]
		);
		$this->add_control(
			'header_text',
			[ 'label' => 'Fejléc szöveg', 'type' => \Elementor\Controls_Manager::COLOR, 'default' => '#FFFFFF', 'selectors' => [ '{{WRAPPER}} .sz-pt-table thead th' => 'color: {{VALUE}};' ] ]
		);
		$this->add_responsive_control(
			'header_cell_padding',
			[ 'label' => 'Fejléc cella belső margó', 'type' => \Elementor\Controls_Manager::DIMENSIONS, 'size_units' => [ 'px', 'em', '%' ], 'default' => [ 'top' => '14', 'right' => '18', 'bottom' => '14', 'left' => '18', 'unit' => 'px' ], 'selectors' => [ '{{WRAPPER}} .sz-pt-table thead th' => 'padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};' ] ]
		);

		$this->end_controls_section();

		// ------------------------------------------------------------------
		// Stílus: Táblázat sorok
		// ------------------------------------------------------------------
		$this->start_controls_section(
			'style_rows_section',
			[ 'label' => 'Táblázat sorok', 'tab' => \Elementor\Controls_Manager::TAB_STYLE ]
		);

		$this->add_group_control(
			\Elementor\Group_Control_Typography::get_type(),
			[ 'name' => 'row_typography', 'selector' => '{{WRAPPER}} .sz-pt-table tbody td' ]
		);
		$this->add_control(
			'row_text',
			[ 'label' => 'Szöveg szín', 'type' => \Elementor\Controls_Manager::COLOR, 'default' => '#242943', 'selectors' => [ '{{WRAPPER}} .sz-pt-table tbody td' => 'color: {{VALUE}};' ] ]
		);
		$this->add_control(
			'row_bg_odd',
			[ 'label' => 'Páratlan sor háttér', 'type' => \Elementor\Controls_Manager::COLOR, 'default' => '#FFFFFF', 'selectors' => [ '{{WRAPPER}} .sz-pt-table tbody tr:nth-child(odd)' => 'background-color: {{VALUE}};' ] ]
		);
		$this->add_control(
			'row_bg_even',
			[ 'label' => 'Páros sor háttér', 'type' => \Elementor\Controls_Manager::COLOR, 'default' => '#F5F7FA', 'selectors' => [ '{{WRAPPER}} .sz-pt-table tbody tr:nth-child(even)' => 'background-color: {{VALUE}};' ] ]
		);
		$this->add_responsive_control(
			'row_cell_padding',
			[ 'label' => 'Sor cella belső margó', 'type' => \Elementor\Controls_Manager::DIMENSIONS, 'size_units' => [ 'px', 'em', '%' ], 'default' => [ 'top' => '14', 'right' => '18', 'bottom' => '14', 'left' => '18', 'unit' => 'px' ], 'selectors' => [ '{{WRAPPER}} .sz-pt-table tbody td' => 'padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};' ] ]
		);
		$this->add_control(
			'row_hover_enabled',
			[
				'label'     => 'Sor kiemelése egérrel rámutatáskor',
				'type'      => \Elementor\Controls_Manager::SWITCHER,
				'label_on'  => 'Be',
				'label_off' => 'Ki',
				'default'   => 'yes',
				'separator' => 'before',
			]
		);
		$this->add_control(
			'row_hover_intensity',
			[
				'label'     => 'Kiemelés erőssége',
				'type'      => \Elementor\Controls_Manager::SLIDER,
				'range'     => [ 'px' => [ 'min' => 1, 'max' => 30 ] ],
				'default'   => [ 'size' => 4 ],
				'condition' => [ 'row_hover_enabled' => 'yes' ],
			]
		);

		$this->add_control(
			'badge_heading',
			[ 'label' => 'Jelvény (Fin. forma)', 'type' => \Elementor\Controls_Manager::HEADING, 'separator' => 'before' ]
		);
		$this->add_group_control(
			\Elementor\Group_Control_Typography::get_type(),
			[ 'name' => 'badge_typography', 'selector' => '{{WRAPPER}} .sz-pt-badge', 'fields_options' => [ 'font_weight' => [ 'default' => '700' ] ] ]
		);
		$this->add_control(
			'state_badge_color',
			[ 'label' => 'Állami jelvény szöveg', 'type' => \Elementor\Controls_Manager::COLOR, 'default' => '#50ADC9', 'selectors' => [ '{{WRAPPER}} .sz-pt-badge-state' => 'color: {{VALUE}};' ] ]
		);
		$this->add_control(
			'state_badge_bg',
			[ 'label' => 'Állami jelvény háttér', 'type' => \Elementor\Controls_Manager::COLOR, 'default' => '', 'selectors' => [ '{{WRAPPER}} .sz-pt-badge-state' => 'background-color: {{VALUE}};' ] ]
		);
		$this->add_control(
			'self_badge_color',
			[ 'label' => 'Önköltséges jelvény szöveg', 'type' => \Elementor\Controls_Manager::COLOR, 'default' => '#242943', 'selectors' => [ '{{WRAPPER}} .sz-pt-badge-self' => 'color: {{VALUE}};' ] ]
		);
		$this->add_control(
			'self_badge_bg',
			[ 'label' => 'Önköltséges jelvény háttér', 'type' => \Elementor\Controls_Manager::COLOR, 'default' => '', 'selectors' => [ '{{WRAPPER}} .sz-pt-badge-self' => 'background-color: {{VALUE}};' ] ]
		);
		$this->add_control(
			'badge_radius',
			[ 'label' => 'Jelvény lekerekítés', 'type' => \Elementor\Controls_Manager::SLIDER, 'size_units' => [ 'px', '%' ], 'range' => [ 'px' => [ 'min' => 0, 'max' => 40 ], '%' => [ 'min' => 0, 'max' => 50 ] ], 'default' => [ 'unit' => 'px', 'size' => 6 ], 'selectors' => [ '{{WRAPPER}} .sz-pt-badge' => 'border-radius: {{SIZE}}{{UNIT}};' ] ]
		);
		$this->add_control(
			'badge_padding',
			[ 'label' => 'Jelvény belső margó', 'type' => \Elementor\Controls_Manager::DIMENSIONS, 'size_units' => [ 'px', 'em' ], 'default' => [ 'top' => '2', 'right' => '9', 'bottom' => '2', 'left' => '9', 'unit' => 'px' ], 'selectors' => [ '{{WRAPPER}} .sz-pt-badge' => 'padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};' ] ]
		);

		$this->end_controls_section();

		// ------------------------------------------------------------------
		// Stílus: Konténer
		// ------------------------------------------------------------------
		$this->start_controls_section(
			'style_container_section',
			[ 'label' => 'Konténer', 'tab' => \Elementor\Controls_Manager::TAB_STYLE ]
		);

		$this->add_control(
			'container_bg',
			[ 'label' => 'Háttérszín', 'type' => \Elementor\Controls_Manager::COLOR, 'default' => '#EAF3FB', 'selectors' => [ '{{WRAPPER}} .sz-pricing-table' => 'background-color: {{VALUE}};' ] ]
		);
		$this->add_control(
			'container_radius',
			[ 'label' => 'Lekerekítés', 'type' => \Elementor\Controls_Manager::SLIDER, 'size_units' => [ 'px' ], 'range' => [ 'px' => [ 'min' => 0, 'max' => 60 ] ], 'default' => [ 'unit' => 'px', 'size' => 16 ], 'selectors' => [ '{{WRAPPER}} .sz-pricing-table' => 'border-radius: {{SIZE}}{{UNIT}};', '{{WRAPPER}} .sz-pt-table-wrap' => 'border-radius: {{SIZE}}{{UNIT}};' ] ]
		);
		$this->add_responsive_control(
			'container_padding',
			[ 'label' => 'Belső margó', 'type' => \Elementor\Controls_Manager::DIMENSIONS, 'size_units' => [ 'px', 'em' ], 'default' => [ 'top' => '32', 'right' => '32', 'bottom' => '32', 'left' => '32', 'unit' => 'px' ], 'selectors' => [ '{{WRAPPER}} .sz-pricing-table' => 'padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};' ] ]
		);
		$this->add_group_control(
			\Elementor\Group_Control_Border::get_type(),
			[ 'name' => 'container_border', 'selector' => '{{WRAPPER}} .sz-pricing-table' ]
		);
		$this->add_group_control(
			\Elementor\Group_Control_Box_Shadow::get_type(),
			[ 'name' => 'container_shadow', 'selector' => '{{WRAPPER}} .sz-pricing-table' ]
		);

		$this->end_controls_section();

		// ------------------------------------------------------------------
		// Stílus: Animáció
		// ------------------------------------------------------------------
		$this->start_controls_section(
			'style_animation_section',
			[ 'label' => 'Animáció', 'tab' => \Elementor\Controls_Manager::TAB_STYLE ]
		);

		$this->add_control(
			'row_animation_enabled',
			[
				'label'     => 'Sorok belépő animációja',
				'type'      => \Elementor\Controls_Manager::SWITCHER,
				'label_on'  => 'Be',
				'label_off' => 'Ki',
				'default'   => 'yes',
			]
		);
		$this->add_control(
			'row_animation_duration',
			[
				'label'     => 'Időtartam (ms)',
				'type'      => \Elementor\Controls_Manager::SLIDER,
				'range'     => [ 'px' => [ 'min' => 100, 'max' => 1500, 'step' => 10 ] ],
				'default'   => [ 'size' => 550 ],
				'condition' => [ 'row_animation_enabled' => 'yes' ],
			]
		);
		$this->add_control(
			'row_animation_distance',
			[
				'label'     => 'Belépési távolság (px)',
				'type'      => \Elementor\Controls_Manager::SLIDER,
				'range'     => [ 'px' => [ 'min' => 0, 'max' => 60 ] ],
				'default'   => [ 'size' => 16 ],
				'condition' => [ 'row_animation_enabled' => 'yes' ],
			]
		);
		$this->add_control(
			'row_animation_stagger',
			[
				'label'       => 'Soronkénti késleltetés (ms)',
				'type'        => \Elementor\Controls_Manager::SLIDER,
				'range'       => [ 'px' => [ 'min' => 0, 'max' => 200, 'step' => 5 ] ],
				'default'     => [ 'size' => 50 ],
				'description' => 'Ennyivel indul később minden következő sor animációja, hogy lépcsőzetesen jelenjenek meg.',
				'condition'   => [ 'row_animation_enabled' => 'yes' ],
			]
		);

		$this->end_controls_section();
	}

	private function format_cost( $val, $price_type = '' ) {
		if ( $val === '' || $val === null ) return '';
		$numeric = is_numeric( $val ) ? $val : preg_replace( '/[^0-9.,-]/', '', (string) $val );
		$formatted = ( $numeric !== '' && is_numeric( $numeric ) ) ? number_format( (float) $numeric, 0, ',', ' ' ) . ' Ft' : (string) $val;
		if ( $price_type !== '' ) {
			$formatted .= ' / ' . $price_type;
		}
		return $formatted;
	}

	private function find_field_by_key( $schema, $key ) {
		if ( ! is_array( $schema ) ) return null;
		foreach ( $schema as $group ) {
			if ( empty( $group['fields'] ) ) continue;
			foreach ( $group['fields'] as $field ) {
				if ( $field['key'] === $key ) return $field;
			}
		}
		return null;
	}

	private function find_subfield_by_key( $sub_fields, $key ) {
		if ( ! is_array( $sub_fields ) ) return null;
		foreach ( $sub_fields as $sf ) {
			if ( $sf['key'] === $key ) return $sf;
		}
		return null;
	}

	private function options_from_field_def( $field_def ) {
		if ( empty( $field_def['options'] ) ) return array();
		return array_map( 'trim', explode( ';', $field_def['options'] ) );
	}

	private function reorder_by_options( $values, $options ) {
		if ( empty( $options ) ) return $values;
		usort( $values, function( $a, $b ) use ( $options ) {
			$ia = array_search( $a, $options, true ); $ia = $ia === false ? 999 : $ia;
			$ib = array_search( $b, $options, true ); $ib = $ib === false ? 999 : $ib;
			return $ia <=> $ib;
		} );
		return $values;
	}

	protected function render() {
		$settings = $this->get_settings_for_display();

		$groups_key         = ! empty( $settings['groups_key'] ) ? trim( $settings['groups_key'] ) : 'munkarend_csoportok';
		$sub_munkarend_key  = ! empty( $settings['sub_munkarend_key'] ) ? trim( $settings['sub_munkarend_key'] ) : 'munkarend';
		$sub_variants_key   = ! empty( $settings['sub_variants_key'] ) ? trim( $settings['sub_variants_key'] ) : 'variansok';
		$nested_nyelv_key   = ! empty( $settings['nested_nyelv_key'] ) ? trim( $settings['nested_nyelv_key'] ) : 'nyelv';
		$nested_finance_key = ! empty( $settings['nested_finance_key'] ) ? trim( $settings['nested_finance_key'] ) : 'finanszirozasi_forma';
		$nested_price_key   = ! empty( $settings['nested_price_type_key'] ) ? trim( $settings['nested_price_type_key'] ) : 'ar_tipus';
		$nested_amount_key  = ! empty( $settings['nested_amount_key'] ) ? trim( $settings['nested_amount_key'] ) : 'osszeg';
		$state_value        = isset( $settings['state_funded_value'] ) ? trim( $settings['state_funded_value'] ) : 'Állami';
		$duration_key       = ! empty( $settings['duration_key'] ) ? trim( $settings['duration_key'] ) : 'felevek_szama';

		$default_lang_value = isset( $settings['default_lang_value'] ) ? trim( $settings['default_lang_value'] ) : 'Magyar';

		$state_badge      = $settings['state_funded_badge'] !== '' ? $settings['state_funded_badge'] : 'A';
		$self_badge       = $settings['self_funded_badge'] !== '' ? $settings['self_funded_badge'] : 'K';
		$state_cost_label = $settings['state_funded_cost_label'] !== '' ? $settings['state_funded_cost_label'] : 'Támogatott';

		// Az Elementor SWITCHER "kikapcsolt" állapotának tényleges tárolt értéke üres string
		// ('') - NEM a szöveges 'no' - ezért itt kifejezetten a 'yes'-re kell vizsgálni,
		// különben a kikapcsolás nem tudja letiltani a hozzá tartozó markup/CSS kimenetet.
		$show_indicator = ( ! isset( $settings['tab_indicator_show'] ) ) || $settings['tab_indicator_show'] === 'yes';

		$row_hover_enabled    = ( ! isset( $settings['row_hover_enabled'] ) ) || $settings['row_hover_enabled'] === 'yes';
		$row_hover_intensity  = isset( $settings['row_hover_intensity']['size'] ) ? floatval( $settings['row_hover_intensity']['size'] ) : 4;
		$row_hover_brightness = max( 0, 1 - ( $row_hover_intensity / 100 ) );

		$row_anim_enabled  = ( ! isset( $settings['row_animation_enabled'] ) ) || $settings['row_animation_enabled'] === 'yes';
		$row_anim_duration = isset( $settings['row_animation_duration']['size'] ) ? floatval( $settings['row_animation_duration']['size'] ) : 550;
		$row_anim_distance = isset( $settings['row_animation_distance']['size'] ) ? floatval( $settings['row_animation_distance']['size'] ) : 16;
		$row_anim_stagger  = isset( $settings['row_animation_stagger']['size'] ) ? floatval( $settings['row_animation_stagger']['size'] ) : 50;

		$post_id = get_the_ID();
		if ( ! $post_id || get_post_type( $post_id ) !== 'sz_course' ) {
			if ( \Elementor\Plugin::$instance->editor->is_edit_mode() ) {
				echo '<div style="background:#f0f0f1; padding:10px; border:1px dashed #ccc; text-align:center;">SZEducate Támogatási Táblázat (csak egy Képzés oldalán jelenik meg)</div>';
			}
			return;
		}

		require_once SZEDUCATE_PLUGIN_DIR . 'includes/class-szeducate-client.php';
		$data = SZEducate_Client::get_course_data_for_post( $post_id );
		if ( ! is_array( $data ) ) return;

		$title = get_the_title( $post_id );
		$duration = isset( $data[ $duration_key ] ) ? trim( (string) $data[ $duration_key ] ) : '';

		$raw_groups = isset( $data[ $groups_key ] ) && is_array( $data[ $groups_key ] ) ? $data[ $groups_key ] : array();

		if ( empty( $raw_groups ) ) {
			if ( \Elementor\Plugin::$instance->editor->is_edit_mode() ) {
				echo '<p style="color:#666; font-style:italic;">Nincs kitöltve a "' . esc_html( $groups_key ) . '" (Munkarend-csoportok) lista mező ezen a Képzésen.</p>';
			}
			return;
		}

		$schema_json = get_option( 'szeducate_local_schema', '[]' );
		$schema = json_decode( $schema_json, true );

		$top_field       = $this->find_field_by_key( $schema, $groups_key );
		$top_sub_fields  = ! empty( $top_field['sub_fields'] ) ? $top_field['sub_fields'] : array();
		$munkarend_def   = $this->find_subfield_by_key( $top_sub_fields, $sub_munkarend_key );
		$munkarend_opts  = $munkarend_def ? $this->options_from_field_def( $munkarend_def ) : array();

		$variants_def       = $this->find_subfield_by_key( $top_sub_fields, $sub_variants_key );
		$nested_sub_fields  = ! empty( $variants_def['sub_fields'] ) ? $variants_def['sub_fields'] : array();
		$nyelv_def          = $this->find_subfield_by_key( $nested_sub_fields, $nested_nyelv_key );
		$nyelv_opts         = $nyelv_def ? $this->options_from_field_def( $nyelv_def ) : array();

		$forms = array();
		$tables_by_form = array();

		foreach ( $raw_groups as $group ) {
			if ( ! is_array( $group ) ) continue;
			$form = isset( $group[ $sub_munkarend_key ] ) ? trim( (string) $group[ $sub_munkarend_key ] ) : '';
			if ( $form === '' ) continue;

			$nested_rows = isset( $group[ $sub_variants_key ] ) && is_array( $group[ $sub_variants_key ] ) ? $group[ $sub_variants_key ] : array();
			if ( empty( $nested_rows ) ) continue;

			// Sorrend: nyelv (séma szerint), azon belül állami sor előbb.
			usort( $nested_rows, function( $a, $b ) use ( $nested_nyelv_key, $nested_finance_key, $nyelv_opts, $state_value ) {
				$la = isset( $a[ $nested_nyelv_key ] ) ? trim( (string) $a[ $nested_nyelv_key ] ) : '';
				$lb = isset( $b[ $nested_nyelv_key ] ) ? trim( (string) $b[ $nested_nyelv_key ] ) : '';
				$ia = array_search( $la, $nyelv_opts, true ); $ia = $ia === false ? 999 : $ia;
				$ib = array_search( $lb, $nyelv_opts, true ); $ib = $ib === false ? 999 : $ib;
				if ( $ia !== $ib ) return $ia <=> $ib;

				$fa = isset( $a[ $nested_finance_key ] ) ? (string) $a[ $nested_finance_key ] : '';
				$fb = isset( $b[ $nested_finance_key ] ) ? (string) $b[ $nested_finance_key ] : '';
				$sa = ( $state_value !== '' && mb_stripos( $fa, $state_value, 0, 'UTF-8' ) !== false ) ? 1 : 0;
				$sb = ( $state_value !== '' && mb_stripos( $fb, $state_value, 0, 'UTF-8' ) !== false ) ? 1 : 0;
				return $sb - $sa;
			} );

			$rows = array();
			foreach ( $nested_rows as $nr ) {
				if ( ! is_array( $nr ) ) continue;
				$lang = isset( $nr[ $nested_nyelv_key ] ) ? trim( (string) $nr[ $nested_nyelv_key ] ) : '';
				$finance_val = isset( $nr[ $nested_finance_key ] ) ? (string) $nr[ $nested_finance_key ] : '';
				$is_state = ( $finance_val !== '' && $state_value !== '' && mb_stripos( $finance_val, $state_value, 0, 'UTF-8' ) !== false );

				$price_type = isset( $nr[ $nested_price_key ] ) ? trim( (string) $nr[ $nested_price_key ] ) : '';
				$amount = isset( $nr[ $nested_amount_key ] ) ? $nr[ $nested_amount_key ] : '';
				$cost_display = $is_state ? $state_cost_label : $this->format_cost( $amount, $price_type );

				$is_default_lang = ( $lang === '' ) || ( $default_lang_value !== '' && mb_strtolower( $lang, 'UTF-8' ) === mb_strtolower( $default_lang_value, 'UTF-8' ) );
				$display_title = ! $is_default_lang ? ( $title . ' (' . mb_strtolower( $lang, 'UTF-8' ) . ' nyelven)' ) : $title;

				$rows[] = array(
					'display_title' => $display_title,
					'is_state'      => $is_state,
					'cost_display'  => $cost_display,
				);
			}

			if ( ! isset( $tables_by_form[ $form ] ) ) {
				$forms[] = $form;
				$tables_by_form[ $form ] = array();
			}
			$tables_by_form[ $form ] = array_merge( $tables_by_form[ $form ], $rows );
		}

		if ( empty( $forms ) ) {
			if ( \Elementor\Plugin::$instance->editor->is_edit_mode() ) {
				echo '<p style="color:#666; font-style:italic;">A "' . esc_html( $groups_key ) . '" sorokban nincs kitöltve a "' . esc_html( $sub_munkarend_key ) . '" al-mező, vagy hiányzik a beágyazott "' . esc_html( $sub_variants_key ) . '" lista.</p>';
			}
			return;
		}

		$forms = $this->reorder_by_options( $forms, $munkarend_opts );

		$widget_id = 'sz-pt-' . $this->get_id();
		?>
		<div class="sz-pricing-table" id="<?php echo esc_attr( $widget_id ); ?>">
			<?php if ( count( $forms ) > 1 ) : ?>
				<div class="sz-pt-tabs" role="tablist">
					<?php if ( $show_indicator ) : ?>
						<span class="sz-pt-tab-indicator" aria-hidden="true"></span>
					<?php endif; ?>
					<?php foreach ( $forms as $i => $form ) : ?>
						<button type="button" class="sz-pt-tab<?php echo $i === 0 ? ' sz-pt-tab-active' : ''; ?>" data-sz-tab="<?php echo esc_attr( sanitize_title( $form ) ); ?>" role="tab" aria-selected="<?php echo $i === 0 ? 'true' : 'false'; ?>"><?php echo esc_html( $form ); ?></button>
					<?php endforeach; ?>
				</div>
			<?php endif; ?>
			<?php foreach ( $forms as $i => $form ) : $rows = $tables_by_form[ $form ]; ?>
				<div class="sz-pt-table-wrap sz-pt-panel" data-sz-panel="<?php echo esc_attr( sanitize_title( $form ) ); ?>" <?php echo $i !== 0 ? 'style="display:none;"' : ''; ?>>
					<table class="sz-pt-table">
						<thead>
							<tr>
								<th class="sz-pt-col-badge"><?php echo esc_html( $settings['col_badge_label'] ?: 'Fin. forma' ); ?></th>
								<th class="sz-pt-col-name"><?php echo esc_html( $settings['col_name_label'] ?: 'Szak neve' ); ?></th>
								<th class="sz-pt-col-cost"><?php echo esc_html( $settings['col_cost_label'] ?: 'Önköltség' ); ?></th>
								<th class="sz-pt-col-duration"><?php echo esc_html( $settings['col_duration_label'] ?: 'Képzési idő' ); ?></th>
							</tr>
						</thead>
						<tbody>
							<?php
							$badge_label    = $settings['col_badge_label'] ?: 'Fin. forma';
							$name_label     = $settings['col_name_label'] ?: 'Szak neve';
							$cost_label     = $settings['col_cost_label'] ?: 'Önköltség';
							$duration_label = $settings['col_duration_label'] ?: 'Képzési idő';
							?>
							<?php foreach ( $rows as $row_index => $r ) :
								$row_style = ( $row_anim_enabled && $row_anim_stagger > 0 )
									? ' style="animation-delay:' . esc_attr( round( $row_index * $row_anim_stagger ) ) . 'ms;"'
									: '';
							?>
								<tr class="sz-pt-row"<?php echo $row_style; ?>>
									<td class="sz-pt-col-badge" data-label="<?php echo esc_attr( $badge_label ); ?>"><span class="sz-pt-badge<?php echo $r['is_state'] ? ' sz-pt-badge-state' : ' sz-pt-badge-self'; ?>"><?php echo esc_html( $r['is_state'] ? $state_badge : $self_badge ); ?></span></td>
									<td class="sz-pt-col-name" data-label="<?php echo esc_attr( $name_label ); ?>"><?php echo esc_html( $r['display_title'] ); ?></td>
									<td class="sz-pt-col-cost" data-label="<?php echo esc_attr( $cost_label ); ?>"><?php echo esc_html( $r['cost_display'] ); ?></td>
									<td class="sz-pt-col-duration" data-label="<?php echo esc_attr( $duration_label ); ?>"><?php echo esc_html( $duration ); ?></td>
								</tr>
							<?php endforeach; ?>
						</tbody>
					</table>
				</div>
			<?php endforeach; ?>
		</div>
		<style>
			/* Lapváltáskor/betöltéskor a táblázat magassága ugrásszerűen változhat, ami az
			   oldal függőleges görgetősávját ki/be kapcsolhatja - enélkül ez a látható terület
			   szélességét módosítja, és minden jobbra/balra ugrik. A gutter mindig lefoglalva
			   marad, így a sáv fel-/eltűnése nem tolja el a tartalmat. */
			html { scrollbar-gutter: stable; }
			#<?php echo esc_attr( $widget_id ); ?> .sz-pt-tabs { display: flex; flex-wrap: wrap; justify-content: center; gap: 12px; position: relative; padding-bottom: 8px; }
			#<?php echo esc_attr( $widget_id ); ?> .sz-pt-tab { cursor: pointer; border: none; font: inherit; position: relative; z-index: 1; transition: background-color .25s ease, color .25s ease, transform .25s cubic-bezier(.22,1,.36,1), box-shadow .25s ease; }
			#<?php echo esc_attr( $widget_id ); ?> .sz-pt-tab:focus-visible { outline: 2px solid currentColor; outline-offset: 2px; }
			#<?php echo esc_attr( $widget_id ); ?> .sz-pt-tab-indicator { position: absolute; top: 0; left: 0; z-index: 0; pointer-events: none; transition: transform .45s cubic-bezier(.22,1,.36,1), width .35s cubic-bezier(.22,1,.36,1), height .35s cubic-bezier(.22,1,.36,1); }
			#<?php echo esc_attr( $widget_id ); ?> .sz-pt-table-wrap { overflow-x: auto; overflow-y: hidden; }
			#<?php echo esc_attr( $widget_id ); ?> .sz-pt-table { width: 100%; border-collapse: collapse; table-layout: fixed; }
			#<?php echo esc_attr( $widget_id ); ?> .sz-pt-table th,
			#<?php echo esc_attr( $widget_id ); ?> .sz-pt-table td { text-align: left; word-wrap: break-word; overflow-wrap: break-word; }
			#<?php echo esc_attr( $widget_id ); ?> .sz-pt-badge { display: inline-block; transition: transform .15s ease; }
			<?php if ( $row_hover_enabled ) : ?>
			#<?php echo esc_attr( $widget_id ); ?> .sz-pt-table tbody tr { transition: filter .15s ease; }
			#<?php echo esc_attr( $widget_id ); ?> .sz-pt-table tbody tr:hover { filter: brightness(<?php echo esc_attr( $row_hover_brightness ); ?>); }
			#<?php echo esc_attr( $widget_id ); ?> .sz-pt-table tbody tr:hover .sz-pt-badge { transform: scale(1.12); }
			<?php endif; ?>
			<?php if ( $row_anim_enabled ) : ?>
			#<?php echo esc_attr( $widget_id ); ?> .sz-pt-row {
				animation: sz-pt-row-in-<?php echo esc_attr( $widget_id ); ?> <?php echo esc_attr( $row_anim_duration ); ?>ms cubic-bezier(.34,1.56,.64,1) both;
			}
			@keyframes sz-pt-row-in-<?php echo esc_attr( $widget_id ); ?> {
				from { opacity: 0; transform: translateY(<?php echo esc_attr( $row_anim_distance ); ?>px); }
				to   { opacity: 1; transform: translateY(0); }
			}
			<?php endif; ?>
			@media (max-width: 640px) {
				#<?php echo esc_attr( $widget_id ); ?> .sz-pt-table thead { display: none; }
				#<?php echo esc_attr( $widget_id ); ?> .sz-pt-table,
				#<?php echo esc_attr( $widget_id ); ?> .sz-pt-table tbody,
				#<?php echo esc_attr( $widget_id ); ?> .sz-pt-table tr,
				#<?php echo esc_attr( $widget_id ); ?> .sz-pt-table td { display: block; width: auto; }
				#<?php echo esc_attr( $widget_id ); ?> .sz-pt-table tr { margin-bottom: 14px; border-radius: 10px; overflow: hidden; box-shadow: 0 1px 4px rgba(0,0,0,.1); }
				#<?php echo esc_attr( $widget_id ); ?> .sz-pt-table tr:last-child { margin-bottom: 0; }
				#<?php echo esc_attr( $widget_id ); ?> .sz-pt-table td { display: flex; align-items: center; justify-content: space-between; gap: 12px; text-align: right; border-bottom: 1px solid rgba(0,0,0,.07); }
				#<?php echo esc_attr( $widget_id ); ?> .sz-pt-table td:last-child { border-bottom: none; }
				#<?php echo esc_attr( $widget_id ); ?> .sz-pt-table td::before { content: attr(data-label); font-weight: 700; text-align: left; opacity: .7; margin-right: 12px; white-space: nowrap; }
				#<?php echo esc_attr( $widget_id ); ?> .sz-pt-tabs { justify-content: stretch; }
				#<?php echo esc_attr( $widget_id ); ?> .sz-pt-tab { flex: 1 1 auto; text-align: center; }
			}
			@media (prefers-reduced-motion: reduce) {
				#<?php echo esc_attr( $widget_id ); ?> * { animation: none !important; transition: none !important; }
			}
		</style>
		<script>
		(function(){
			var root = document.getElementById(<?php echo wp_json_encode( $widget_id ); ?>);
			if ( ! root ) return;
			var tabs = Array.prototype.slice.call( root.querySelectorAll('.sz-pt-tab') );
			var panels = Array.prototype.slice.call( root.querySelectorAll('.sz-pt-panel') );
			var tabsWrap = root.querySelector('.sz-pt-tabs');
			var indicator = root.querySelector('.sz-pt-tab-indicator');

			// A jelzősávot csak a méret/pozíció (transform+width/height) mozgatja - ezek
			// kompozitor-barát tulajdonságok, nincs kényszerített reflow, nincs villanás.
			var INDICATOR_PAD = 7; // a lágy glow ennyivel nyúlik túl az aktív fülön, hogy a blur ne az átlátszatlan gomb alatt vesszen el

			function positionIndicator( tab, animate ) {
				if ( ! indicator || ! tab || ! tabsWrap ) return;
				var tabRect = tab.getBoundingClientRect();
				var wrapRect = tabsWrap.getBoundingClientRect();
				var x = tabRect.left - wrapRect.left - INDICATOR_PAD;
				var y = tabRect.top - wrapRect.top - INDICATOR_PAD;
				if ( ! animate ) indicator.style.transitionDuration = '0s';
				indicator.style.width = ( tabRect.width + INDICATOR_PAD * 2 ) + 'px';
				indicator.style.height = ( tabRect.height + INDICATOR_PAD * 2 ) + 'px';
				indicator.style.transform = 'translate(' + x + 'px,' + y + 'px)';
				if ( ! animate ) {
					void indicator.offsetWidth;
					indicator.style.transitionDuration = '';
				}
			}

			tabs.forEach(function(tab){
				tab.addEventListener('click', function(){
					tabs.forEach(function(t){ t.classList.remove('sz-pt-tab-active'); t.setAttribute('aria-selected', 'false'); });
					tab.classList.add('sz-pt-tab-active');
					tab.setAttribute('aria-selected', 'true');

					var selected = tab.getAttribute('data-sz-tab');
					panels.forEach(function(p){
						p.style.display = ( p.getAttribute('data-sz-panel') === selected ) ? '' : 'none';
					});

					positionIndicator( tab, true );
				});
			});

			var activeTab = root.querySelector('.sz-pt-tab.sz-pt-tab-active') || tabs[0];
			if ( activeTab ) positionIndicator( activeTab, false );

			window.addEventListener('resize', function(){
				var current = root.querySelector('.sz-pt-tab.sz-pt-tab-active');
				if ( current ) positionIndicator( current, false );
			});
		})();
		</script>
		<?php
	}
}
