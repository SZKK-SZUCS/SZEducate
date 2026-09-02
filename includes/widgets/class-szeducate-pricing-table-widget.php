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
			[ 'label' => 'Beágyazott al-mező: Finanszírozási forma', 'type' => \Elementor\Controls_Manager::TEXT, 'default' => 'finanszirozasi-forma' ]
		);
		$this->add_control(
			'nested_price_type_key',
			[ 'label' => 'Beágyazott al-mező: Ár típusa', 'type' => \Elementor\Controls_Manager::TEXT, 'default' => 'ar-tipusa' ]
		);
		$this->add_control(
			'nested_amount_key',
			[ 'label' => 'Beágyazott al-mező: Összeg', 'type' => \Elementor\Controls_Manager::TEXT, 'default' => 'osszeg' ]
		);
		$this->add_control(
			'nested_specialization_key',
			[
				'label'       => 'Beágyazott al-mező: Szakosodás',
				'type'        => \Elementor\Controls_Manager::TEXT,
				'default'     => 'szakosodas',
				'description' => 'Ha ez a soronkénti al-mező ki van töltve, a szak neve "Szak neve - Szakosodás" formában jelenik meg (a nyelvi kiegészítés elé fűzve).',
			]
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

		$this->add_control(
			'legend_show',
			[
				'label'     => 'Jelmagyarázat (?) a Fin. forma fejlécben',
				'type'      => \Elementor\Controls_Manager::SWITCHER,
				'label_on'  => 'Be',
				'label_off' => 'Ki',
				'default'   => 'yes',
				'separator' => 'before',
			]
		);
		$this->add_control(
			'legend_state_text',
			[
				'label'     => 'Jelmagyarázat: "A" jelentése',
				'type'      => \Elementor\Controls_Manager::TEXT,
				'default'   => 'Államilag finanszírozott',
				'condition' => [ 'legend_show' => 'yes' ],
			]
		);
		$this->add_control(
			'legend_self_text',
			[
				'label'     => 'Jelmagyarázat: "K" jelentése',
				'type'      => \Elementor\Controls_Manager::TEXT,
				'default'   => 'Önköltséges',
				'condition' => [ 'legend_show' => 'yes' ],
			]
		);

		$this->end_controls_section();

		// ------------------------------------------------------------------
		// Tartalom: Lapozás
		// ------------------------------------------------------------------
		$this->start_controls_section(
			'pagination_section',
			[ 'label' => 'Lapozás', 'tab' => \Elementor\Controls_Manager::TAB_CONTENT ]
		);

		$this->add_control(
			'pagination_enabled',
			[
				'label'     => 'Hosszú táblázat lapozása',
				'type'      => \Elementor\Controls_Manager::SWITCHER,
				'label_on'  => 'Be',
				'label_off' => 'Ki',
				'default'   => 'yes',
			]
		);
		$this->add_control(
			'pagination_threshold',
			[
				'label'       => 'Egy oldalra fér',
				'type'        => \Elementor\Controls_Manager::NUMBER,
				'min'         => 1,
				'max'         => 50,
				'default'     => 8,
				'description' => 'Eddig a sorszámig a munkarend egy oldalon marad. Efölött: oldalak száma = felfelé kerekít(sorok / ez az érték), a sorok pedig egyenletesen oszlanak el az oldalak közt (pl. 8 fölött 9-16 sor = 2 oldal).',
				'condition'   => [ 'pagination_enabled' => 'yes' ],
			]
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
		// Stílus: Lapozó
		// ------------------------------------------------------------------
		$this->start_controls_section(
			'style_pager_section',
			[ 'label' => 'Lapozó', 'tab' => \Elementor\Controls_Manager::TAB_STYLE ]
		);

		$this->add_control(
			'pager_align',
			[
				'label'     => 'Igazítás',
				'type'      => \Elementor\Controls_Manager::CHOOSE,
				'options'   => [
					'flex-start' => [ 'title' => 'Balra', 'icon' => 'eicon-text-align-left' ],
					'center'     => [ 'title' => 'Középre', 'icon' => 'eicon-text-align-center' ],
					'flex-end'   => [ 'title' => 'Jobbra', 'icon' => 'eicon-text-align-right' ],
				],
				'default'   => 'flex-end',
				'selectors' => [ '{{WRAPPER}} .sz-pt-pager' => 'justify-content: {{VALUE}};' ],
			]
		);
		$this->add_control(
			'pager_active_bg',
			[ 'label' => 'Aktív oldal háttér', 'type' => \Elementor\Controls_Manager::COLOR, 'default' => '#50ADC9', 'selectors' => [ '{{WRAPPER}} .sz-pt-pager-btn.is-active' => 'background-color: {{VALUE}}; border-color: {{VALUE}};' ] ]
		);
		$this->add_control(
			'pager_active_text',
			[ 'label' => 'Aktív oldal szöveg', 'type' => \Elementor\Controls_Manager::COLOR, 'default' => '#FFFFFF', 'selectors' => [ '{{WRAPPER}} .sz-pt-pager-btn.is-active' => 'color: {{VALUE}};' ] ]
		);
		$this->add_control(
			'pager_text',
			[ 'label' => 'Inaktív gomb szöveg', 'type' => \Elementor\Controls_Manager::COLOR, 'default' => '#242943', 'selectors' => [ '{{WRAPPER}} .sz-pt-pager-btn' => 'color: {{VALUE}};' ] ]
		);
		$this->add_control(
			'pager_border_color',
			[ 'label' => 'Gomb keret', 'type' => \Elementor\Controls_Manager::COLOR, 'default' => '#C7D3DD', 'selectors' => [ '{{WRAPPER}} .sz-pt-pager-btn' => 'border-color: {{VALUE}};' ] ]
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

	// Rendezési kulcs a szak/szakosodás névhez: kisbetűs, ékezet nélküli alak, hogy a
	// magyar ábécé szerinti sorrend a szerver LC_COLLATE beállításától függetlenül stimmeljen.
	private function sort_fold( $str ) {
		$str = mb_strtolower( trim( (string) $str ), 'UTF-8' );
		return strtr( $str, array(
			'á' => 'a', 'é' => 'e', 'í' => 'i', 'ó' => 'o', 'ö' => 'o',
			'ő' => 'o', 'ú' => 'u', 'ü' => 'u', 'ű' => 'u',
		) );
	}

	// Hány oldalra bontsuk egy munkarend $n sorát, és oldalanként hány sor jusson.
	// Küszöb ($threshold) alatt minden egy oldalon marad; fölötte az oldalak száma
	// felfelé kerekít($n / $threshold), a sorok pedig kiegyenlítve oszlanak el
	// (felfelé kerekít($n / oldalak)) - így 8-as küszöbnél 9-16 sor = 2 oldal (5-8/oldal),
	// 17-24 = 3 oldal, stb. A tényleges oldalindex sosem lépi túl az oldalszámot.
	private function paginate_plan( $n, $threshold ) {
		$threshold = max( 1, (int) $threshold );
		if ( $n <= $threshold ) {
			return array( 'pages' => 1, 'per_page' => max( 1, $n ) );
		}
		$pages    = (int) ceil( $n / $threshold );
		$per_page = (int) ceil( $n / $pages );
		return array( 'pages' => $pages, 'per_page' => $per_page );
	}

	protected function render() {
		$settings = $this->get_settings_for_display();

		$groups_key         = ! empty( $settings['groups_key'] ) ? trim( $settings['groups_key'] ) : 'munkarend_csoportok';
		$sub_munkarend_key  = ! empty( $settings['sub_munkarend_key'] ) ? trim( $settings['sub_munkarend_key'] ) : 'munkarend';
		$sub_variants_key   = ! empty( $settings['sub_variants_key'] ) ? trim( $settings['sub_variants_key'] ) : 'variansok';
		$nested_nyelv_key   = ! empty( $settings['nested_nyelv_key'] ) ? trim( $settings['nested_nyelv_key'] ) : 'nyelv';
		$nested_finance_key = ! empty( $settings['nested_finance_key'] ) ? trim( $settings['nested_finance_key'] ) : 'finanszirozasi-forma';
		$nested_price_key   = ! empty( $settings['nested_price_type_key'] ) ? trim( $settings['nested_price_type_key'] ) : 'ar-tipusa';
		$nested_amount_key  = ! empty( $settings['nested_amount_key'] ) ? trim( $settings['nested_amount_key'] ) : 'osszeg';
		$nested_spec_key    = ! empty( $settings['nested_specialization_key'] ) ? trim( $settings['nested_specialization_key'] ) : 'szakosodas';
		$state_value        = isset( $settings['state_funded_value'] ) ? trim( $settings['state_funded_value'] ) : 'Állami';
		$duration_key       = ! empty( $settings['duration_key'] ) ? trim( $settings['duration_key'] ) : 'felevek_szama';

		$default_lang_value = isset( $settings['default_lang_value'] ) ? trim( $settings['default_lang_value'] ) : 'Magyar';

		$state_badge      = $settings['state_funded_badge'] !== '' ? $settings['state_funded_badge'] : 'A';
		$self_badge       = $settings['self_funded_badge'] !== '' ? $settings['self_funded_badge'] : 'K';
		$state_cost_label = $settings['state_funded_cost_label'] !== '' ? $settings['state_funded_cost_label'] : 'Támogatott';

		$legend_show       = ( ! isset( $settings['legend_show'] ) ) || $settings['legend_show'] === 'yes';
		$legend_state_text = isset( $settings['legend_state_text'] ) && $settings['legend_state_text'] !== '' ? $settings['legend_state_text'] : 'Államilag finanszírozott';
		$legend_self_text  = isset( $settings['legend_self_text'] ) && $settings['legend_self_text'] !== '' ? $settings['legend_self_text'] : 'Önköltséges';

		$pagination_enabled   = ( ! isset( $settings['pagination_enabled'] ) ) || $settings['pagination_enabled'] === 'yes';
		$pagination_threshold = isset( $settings['pagination_threshold'] ) && intval( $settings['pagination_threshold'] ) > 0 ? intval( $settings['pagination_threshold'] ) : 8;

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

			$rows = array();
			foreach ( $nested_rows as $nr ) {
				if ( ! is_array( $nr ) ) continue;
				$lang = isset( $nr[ $nested_nyelv_key ] ) ? trim( (string) $nr[ $nested_nyelv_key ] ) : '';
				$finance_val = isset( $nr[ $nested_finance_key ] ) ? (string) $nr[ $nested_finance_key ] : '';
				$is_state = ( $finance_val !== '' && $state_value !== '' && mb_stripos( $finance_val, $state_value, 0, 'UTF-8' ) !== false );

				$price_type = isset( $nr[ $nested_price_key ] ) ? trim( (string) $nr[ $nested_price_key ] ) : '';
				$amount = isset( $nr[ $nested_amount_key ] ) ? $nr[ $nested_amount_key ] : '';
				$cost_display = $is_state ? $state_cost_label : $this->format_cost( $amount, $price_type );

				// A név felépítése: "Szak neve - Szakosodás (nyelv nyelven)". A szakosodás
				// csak akkor jelenik meg, ha az adott variáns-soron ki van töltve; a nyelvi
				// kiegészítés pedig csak a nem-alap nyelvnél - a kettő egymástól függetlenül.
				$spec = isset( $nr[ $nested_spec_key ] ) ? trim( (string) $nr[ $nested_spec_key ] ) : '';
				$name_with_spec = $spec !== '' ? ( $title . ' - ' . $spec ) : $title;

				$is_default_lang = ( $lang === '' ) || ( $default_lang_value !== '' && mb_strtolower( $lang, 'UTF-8' ) === mb_strtolower( $default_lang_value, 'UTF-8' ) );
				$display_title = ! $is_default_lang ? ( $name_with_spec . ' (' . mb_strtolower( $lang, 'UTF-8' ) . ' nyelven)' ) : $name_with_spec;

				$lang_idx = array_search( $lang, $nyelv_opts, true );

				$rows[] = array(
					'display_title' => $display_title,
					'is_state'      => $is_state,
					'cost_display'  => $cost_display,
					// Rendezési kulcsok (lásd a lentebbi usort-ot): 1. szak/szakosodás ABC,
					// 2. nyelv (alap nyelv előre, utána séma-sorrend), 3. állami a önköltség előtt.
					'_sort_name'    => $this->sort_fold( $name_with_spec ),
					'_sort_lang'    => $is_default_lang ? 0 : 1,
					'_sort_langidx' => $lang_idx === false ? 999 : $lang_idx,
					'_sort_state'   => $is_state ? 0 : 1,
				);
			}

			if ( ! isset( $tables_by_form[ $form ] ) ) {
				$forms[] = $form;
				$tables_by_form[ $form ] = array();
			}
			$tables_by_form[ $form ] = array_merge( $tables_by_form[ $form ], $rows );
		}

		// A megjelenítési sorrend: szak/szakosodás ABC (magyar ábécé, ékezet-független),
		// azon belül a magyar (alap) nyelvű előre, majd a séma szerinti nyelvsorrend, és
		// legbelül az állami finanszírozású sor az önköltséges elé. A szerkesztőben megadott
		// sorrend NEM számít (a Kliens szerkesztő erre figyelmeztet is).
		foreach ( $tables_by_form as $form_key => $form_rows ) {
			usort( $form_rows, function( $a, $b ) {
				$c = strnatcmp( $a['_sort_name'], $b['_sort_name'] );
				if ( $c !== 0 ) return $c;
				if ( $a['_sort_lang'] !== $b['_sort_lang'] ) return $a['_sort_lang'] <=> $b['_sort_lang'];
				if ( $a['_sort_langidx'] !== $b['_sort_langidx'] ) return $a['_sort_langidx'] <=> $b['_sort_langidx'];
				return $a['_sort_state'] <=> $b['_sort_state'];
			} );
			$tables_by_form[ $form_key ] = $form_rows;
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
			<?php
			$badge_label    = $settings['col_badge_label'] ?: 'Fin. forma';
			$name_label     = $settings['col_name_label'] ?: 'Szak neve';
			$cost_label     = $settings['col_cost_label'] ?: 'Önköltség';
			$duration_label = $settings['col_duration_label'] ?: 'Képzési idő';
			?>
			<?php foreach ( $forms as $i => $form ) :
				$rows        = $tables_by_form[ $form ];
				$panel_slug  = sanitize_title( $form );
				$plan        = $pagination_enabled
					? $this->paginate_plan( count( $rows ), $pagination_threshold )
					: array( 'pages' => 1, 'per_page' => max( 1, count( $rows ) ) );
				$has_pager   = $plan['pages'] > 1;
			?>
				<div class="sz-pt-panel" data-sz-panel="<?php echo esc_attr( $panel_slug ); ?>" <?php echo $i !== 0 ? 'style="display:none;"' : ''; ?>>
					<div class="sz-pt-table-wrap">
					<table class="sz-pt-table">
						<thead>
							<tr>
								<th class="sz-pt-col-badge"><?php echo esc_html( $badge_label ); ?><?php if ( $legend_show ) : ?><span class="sz-pt-legend" tabindex="0" role="button" aria-label="Finanszírozási forma jelmagyarázat">?<span class="sz-pt-legend-pop"><b><?php echo esc_html( $state_badge ); ?></b> = <?php echo esc_html( $legend_state_text ); ?><br><b><?php echo esc_html( $self_badge ); ?></b> = <?php echo esc_html( $legend_self_text ); ?></span></span><?php endif; ?></th>
								<th class="sz-pt-col-name"><?php echo esc_html( $name_label ); ?></th>
								<th class="sz-pt-col-cost"><?php echo esc_html( $cost_label ); ?></th>
								<th class="sz-pt-col-duration"><?php echo esc_html( $duration_label ); ?></th>
							</tr>
						</thead>
						<tbody>
							<?php foreach ( $rows as $row_index => $r ) :
								$page_no   = (int) floor( $row_index / $plan['per_page'] ) + 1;
								// A lépcsőzetes animáció késleltetése az oldalon belüli pozíció szerint
								// megy, nem a teljes listabeli indexszel - így a 2. oldal sorai is
								// elölről kezdik a lépcsőt, amikor a lapozó előhozza őket.
								$row_style = ( $row_anim_enabled && $row_anim_stagger > 0 )
									? ' style="animation-delay:' . esc_attr( round( ( $row_index % $plan['per_page'] ) * $row_anim_stagger ) ) . 'ms;"'
									: '';
							?>
								<tr class="sz-pt-row<?php echo ( $has_pager && $page_no !== 1 ) ? ' sz-pt-row-hidden' : ''; ?>" data-sz-pt-page="<?php echo esc_attr( $page_no ); ?>"<?php echo $row_style; ?>>
									<td class="sz-pt-col-badge" data-label="<?php echo esc_attr( $badge_label ); ?>"><span class="sz-pt-badge<?php echo $r['is_state'] ? ' sz-pt-badge-state' : ' sz-pt-badge-self'; ?>"><?php echo esc_html( $r['is_state'] ? $state_badge : $self_badge ); ?></span></td>
									<td class="sz-pt-col-name" data-label="<?php echo esc_attr( $name_label ); ?>"><?php echo esc_html( $r['display_title'] ); ?></td>
									<td class="sz-pt-col-cost" data-label="<?php echo esc_attr( $cost_label ); ?>"><?php echo esc_html( $r['cost_display'] ); ?></td>
									<td class="sz-pt-col-duration" data-label="<?php echo esc_attr( $duration_label ); ?>"><?php echo esc_html( $duration ); ?></td>
								</tr>
							<?php endforeach; ?>
						</tbody>
					</table>
					</div>
					<?php if ( $has_pager ) : ?>
						<div class="sz-pt-pager" data-sz-pager="<?php echo esc_attr( $panel_slug ); ?>" data-sz-pages="<?php echo esc_attr( $plan['pages'] ); ?>">
							<button type="button" class="sz-pt-pager-btn sz-pt-pager-prev" aria-label="Előző oldal" disabled>&lsaquo;</button>
							<?php for ( $p = 1; $p <= $plan['pages']; $p++ ) : ?>
								<button type="button" class="sz-pt-pager-btn sz-pt-pager-num<?php echo $p === 1 ? ' is-active' : ''; ?>" data-sz-page="<?php echo esc_attr( $p ); ?>" aria-label="<?php echo esc_attr( $p . '. oldal' ); ?>"<?php echo $p === 1 ? ' aria-current="true"' : ''; ?>><?php echo esc_html( $p ); ?></button>
							<?php endfor; ?>
							<button type="button" class="sz-pt-pager-btn sz-pt-pager-next" aria-label="Következő oldal">&rsaquo;</button>
						</div>
					<?php endif; ?>
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

			/* Lapozó által elrejtett sorok - a mobil media query display:block szabályát is
			   felül kell írnia, ezért kap az azonos elemszelektor + extra osztály nagyobb
			   specificitást. */
			#<?php echo esc_attr( $widget_id ); ?> .sz-pt-table tr.sz-pt-row-hidden { display: none; }

			/* Jelmagyarázat "?" a Fin. forma fejlécben. A színek itt fixek (sötét fejléc,
			   világos buborék) - szándékosan NEM Elementor-vezéreltek, hogy egy be nem
			   állított Stílus-szekció se hagyja olvashatatlanul. */
			#<?php echo esc_attr( $widget_id ); ?> .sz-pt-col-badge { position: relative; }
			#<?php echo esc_attr( $widget_id ); ?> .sz-pt-legend { display: inline-flex; align-items: center; justify-content: center; width: 16px; height: 16px; margin-left: 6px; border: 1px solid currentColor; border-radius: 50%; font-size: 11px; font-weight: 700; line-height: 1; cursor: help; vertical-align: middle; text-transform: none; }
			#<?php echo esc_attr( $widget_id ); ?> .sz-pt-legend:focus-visible { outline: 2px solid currentColor; outline-offset: 2px; }
			#<?php echo esc_attr( $widget_id ); ?> .sz-pt-legend-pop { position: absolute; top: calc(100% + 8px); left: 0; min-width: 220px; max-width: 280px; padding: 10px 12px; background: #ffffff; color: #242943; font-size: 12px; font-weight: 400; line-height: 1.5; text-align: left; text-transform: none; letter-spacing: normal; border-radius: 8px; box-shadow: 0 8px 24px rgba(0,0,0,.18); opacity: 0; visibility: hidden; transform: translateY(-4px); transition: opacity .15s ease, transform .15s ease; z-index: 30; pointer-events: none; }
			#<?php echo esc_attr( $widget_id ); ?> .sz-pt-legend-pop b { font-weight: 700; }
			#<?php echo esc_attr( $widget_id ); ?> .sz-pt-legend:hover .sz-pt-legend-pop,
			#<?php echo esc_attr( $widget_id ); ?> .sz-pt-legend:focus-within .sz-pt-legend-pop { opacity: 1; visibility: visible; transform: translateY(0); }

			/* Lapozó - a gombszínek Elementor-vezéreltek (Stílus > Lapozó), itt csak a szerkezet. */
			#<?php echo esc_attr( $widget_id ); ?> .sz-pt-pager { display: flex; flex-wrap: wrap; align-items: center; gap: 6px; margin-top: 14px; }
			#<?php echo esc_attr( $widget_id ); ?> .sz-pt-pager-btn { cursor: pointer; font: inherit; min-width: 34px; height: 34px; padding: 0 9px; border: 1px solid; border-radius: 6px; background: transparent; display: inline-flex; align-items: center; justify-content: center; line-height: 1; transition: background-color .15s ease, color .15s ease, border-color .15s ease; }
			#<?php echo esc_attr( $widget_id ); ?> .sz-pt-pager-btn:disabled { opacity: .4; cursor: default; }
			#<?php echo esc_attr( $widget_id ); ?> .sz-pt-pager-btn.is-active { cursor: default; }
			#<?php echo esc_attr( $widget_id ); ?> .sz-pt-pager-btn:focus-visible { outline: 2px solid currentColor; outline-offset: 2px; }

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

			// --- Lapozó munkarend-fülönként --------------------------------------------
			// Minden sor a szerver által kiszámolt oldalszámot hordozza (data-sz-pt-page);
			// itt csak láthatóságot kapcsolgatunk, nincs újrarendezés. Fülváltáskor a
			// panel lapozója visszaáll az 1. oldalra.
			var pagers = Array.prototype.slice.call( root.querySelectorAll('.sz-pt-pager') );

			pagers.forEach(function(pager){
				var panel = pager.closest('.sz-pt-panel');
				if ( ! panel ) return;
				var pages = parseInt( pager.getAttribute('data-sz-pages'), 10 ) || 1;
				var rows = Array.prototype.slice.call( panel.querySelectorAll('.sz-pt-row') );
				var numBtns = Array.prototype.slice.call( pager.querySelectorAll('.sz-pt-pager-num') );
				var prevBtn = pager.querySelector('.sz-pt-pager-prev');
				var nextBtn = pager.querySelector('.sz-pt-pager-next');
				var firstRun = true;

				function showPage( n ) {
					n = Math.max( 1, Math.min( pages, n ) );
					pager._szPage = n;

					rows.forEach(function(r){
						var onPage = parseInt( r.getAttribute('data-sz-pt-page'), 10 ) === n;
						r.classList.toggle('sz-pt-row-hidden', ! onPage);
						if ( onPage && ! firstRun ) {
							// Az animáció újrajátszása a most előhozott sorokon (a delay inline
							// marad, csak a name-et pörgetjük újra egy reflow-val). Az első
							// megjelenítéskor nem kell - a CSS animáció már betöltéskor lefut.
							r.style.animationName = 'none';
							void r.offsetWidth;
							r.style.animationName = '';
						}
					});
					firstRun = false;

					numBtns.forEach(function(b){
						var active = parseInt( b.getAttribute('data-sz-page'), 10 ) === n;
						b.classList.toggle('is-active', active);
						if ( active ) { b.setAttribute('aria-current', 'true'); }
						else { b.removeAttribute('aria-current'); }
					});
					if ( prevBtn ) prevBtn.disabled = ( n === 1 );
					if ( nextBtn ) nextBtn.disabled = ( n === pages );
				}

				pager._szShowPage = showPage;

				numBtns.forEach(function(b){
					b.addEventListener('click', function(){
						showPage( parseInt( b.getAttribute('data-sz-page'), 10 ) || 1 );
					});
				});
				if ( prevBtn ) prevBtn.addEventListener('click', function(){ showPage( ( pager._szPage || 1 ) - 1 ); });
				if ( nextBtn ) nextBtn.addEventListener('click', function(){ showPage( ( pager._szPage || 1 ) + 1 ); });

				showPage( 1 );
			});

			tabs.forEach(function(tab){
				tab.addEventListener('click', function(){
					tabs.forEach(function(t){ t.classList.remove('sz-pt-tab-active'); t.setAttribute('aria-selected', 'false'); });
					tab.classList.add('sz-pt-tab-active');
					tab.setAttribute('aria-selected', 'true');

					var selected = tab.getAttribute('data-sz-tab');
					panels.forEach(function(p){
						p.style.display = ( p.getAttribute('data-sz-panel') === selected ) ? '' : 'none';
					});

					pagers.forEach(function(pg){ if ( pg._szShowPage ) pg._szShowPage( 1 ); });

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
