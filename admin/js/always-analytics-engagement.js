/**
 * Always Analytics — Engagement page.
 *
 * Handles: period selector, KPI rendering, time chart,
 * scroll-depth distribution, per-page engagement score table.
 *
 * Depends on: alwaysAnalyticsAdmin (wp_localize_script), Chart.js
 *
 * @package Always_Analytics
 */

( function () {
	'use strict';

	// ── Bootstrap: wait for alwaysAnalyticsAdmin to be available ─────────

	function waitForConfig( cb ) {
		if ( typeof alwaysAnalyticsAdmin !== 'undefined' ) {
			cb();
			return;
		}
		var attempts = 0;
		var interval = setInterval( function () {
			attempts++;
			if ( typeof alwaysAnalyticsAdmin !== 'undefined' ) {
				clearInterval( interval );
				cb();
			} else if ( attempts > 20 ) {
				clearInterval( interval );
				console.warn( '[Always Analytics] alwaysAnalyticsAdmin not found.' );
			}
		}, 100 );
	}

	waitForConfig( function () {

		var API   = alwaysAnalyticsAdmin.restBase;
		var NONCE = alwaysAnalyticsAdmin.nonce;

		var state = {
			from: dateOffset( 0 ),
			to:   dateOffset( 0 ),
		};

		var engChart       = null;
		var currentDataset = 'engaged';

		// ── Init ─────────────────────────────────────────────────

		function init() {
			bindPeriodSelector();
			bindChartToggles();
			loadAll();
		}

		if ( document.readyState === 'loading' ) {
			document.addEventListener( 'DOMContentLoaded', init );
		} else {
			init();
		}

		// ── Period selector ───────────────────────────────────────────

		function buildPeriodMap() {
			var today = dateOffset( 0 );
			return {
				today:     { from: today,                               to: today },
				yesterday: { from: dateOffset( -1 ),                    to: dateOffset( -1 ) },
				'7days':   { from: dateOffset( -7 ),                    to: today },
				'30days':  { from: dateOffset( -30 ),                   to: today },
				'90days':  { from: dateOffset( -90 ),                   to: today },
				year:      { from: new Date().getFullYear() + '-01-01', to: today },
			};
		}

		function bindPeriodSelector() {
			var sel = document.getElementById( 'eng-period' );
			if ( ! sel ) { return; }

			sel.addEventListener( 'change', function () {
				var map    = buildPeriodMap();
				var period = map[ this.value ];
				if ( period ) {
					state.from = period.from;
					state.to   = period.to;
					loadAll();
				}
			} );
		}

		function bindChartToggles() {
			document.querySelectorAll( '[data-eng-dataset]' ).forEach( function ( btn ) {
				btn.addEventListener( 'click', function () {
					document.querySelectorAll( '[data-eng-dataset]' ).forEach( function ( b ) {
						b.classList.remove( 'active' );
					} );
					this.classList.add( 'active' );
					currentDataset = this.getAttribute( 'data-eng-dataset' );
					if ( engChart ) { updateEngChartDataset(); }
				} );
			} );
		}

		// ─ API fetch ───────────────────────────────────────────────

		function apiFetch( endpoint, extra, cb ) {
			var qs = 'from=' + enc( state.from ) + '&to=' + enc( state.to ) + '&_t=' + Date.now();
			if ( extra ) { qs += '&' + extra; }
			var sep = API.indexOf('?') !== -1 ? '&' : '?';
			fetch( API + endpoint + sep + qs, {
				cache: 'no-store',
				headers: {
					'X-WP-Nonce':     NONCE,
					'Cache-Control':  'no-cache, no-store',
					'Pragma':         'no-cache',
				},
			} )
				.then( function ( r ) { return r.json(); } )
				.then( cb )
				.catch( function ( e ) { console.error( '[AA Engagement]', e ); } );
		}

		// ── Load all ────────────────────────────────────────────────

		var _overviewPageViews = 0;

		function loadAll() {
			apiFetch( 'engagement', null, renderMain );
			apiFetch( 'engagement/pages', 'limit=50', renderPagesTable );
			apiFetch( 'reader-profiles', null, renderReaderProfiles );
			apiFetch( 'overview', null, function ( d ) {
				_overviewPageViews = d.page_views || 0;
				if ( window._lastScrollDist ) {
					renderScrollDist( window._lastScrollDist, _overviewPageViews );
				}
			} );
		}

		// ── KPIs + main chart ──────────────────────────────────────────

		function renderMain( d ) {
			var k = d.kpis || {};
			setText( 'eng-kpi-rate',     ( k.engagement_rate     || 0 ) + '%' );
			setText( 'eng-kpi-duration', fmtDuration( k.avg_duration     || 0 ) );
			setText( 'eng-kpi-pages',    parseFloat( k.avg_pages         || 0 ).toFixed( 1 ) );
			setText( 'eng-kpi-scroll',   parseFloat( k.avg_scroll_depth  || 0 ).toFixed( 0 ) + '%' );
			setText( 'eng-kpi-deepread', ( k.deep_read_rate      || 0 ) + '%' );

			renderEngChart( d.chart || [] );
			renderScrollDist( d.scroll_distribution || {}, _overviewPageViews );
		}

		// ─ Engagement time chart ─────────────────────────────────────

		function renderEngChart( data ) {
			var ctx = document.getElementById( 'eng-chart' );
			if ( ! ctx ) { return; }
			if ( engChart ) { engChart.destroy(); }

			var labels   = data.map( function ( d ) { return d.label; } );
			var colors   = { engaged: '#6c63ff', avg_dur: '#10b981', avg_scroll: '#f59e0b' };
			var titles   = {
				engaged:    'Sessions engagées',
				avg_dur:    'Dure moy. (s)',
				avg_scroll: 'Scroll moyen (%)',
			};
			var datasets = {
				engaged:    data.map( function ( d ) { return d.future ? null : d.engaged; } ),
				avg_dur:    data.map( function ( d ) { return d.future ? null : d.avg_dur; } ),
				avg_scroll: data.map( function ( d ) { return d.future ? null : d.avg_scroll; } ),
			};

			engChart = new Chart( ctx, {
				type: 'line',
				data: {
					labels:   labels,
					datasets: Object.keys( datasets ).map( function ( key ) {
						return {
							label:            titles[ key ],
							data:             datasets[ key ],
							borderColor:      colors[ key ],
							backgroundColor:  colors[ key ] + '18',
							fill:             true,
							tension:          0.4,
							borderWidth:      2.5,
							hidden:           key !== currentDataset,
							spanGaps:         false,
							pointRadius:      0,
							pointHoverRadius: 5,
						};
					} ),
				},
				options: {
					responsive:          true,
					maintainAspectRatio: false,
					interaction:         { intersect: false, mode: 'index' },
					scales: {
						x: {
							grid:   { display: false },
							border: { display: false },
							ticks:  { font: { size: 11 } },
						},
						y: {
							beginAtZero: true,
							border:      { display: false },
							grid:        { color: 'rgba(0,0,0,0.04)' },
							ticks:       { font: { size: 11 } },
						},
					},
					plugins: {
						legend:  { display: false },
						tooltip: {
							backgroundColor: '#1d2327',
							cornerRadius:    8,
							padding:         10,
							callbacks: {
								label: function ( ctx ) {
									if ( ctx.parsed.y === null ) { return null; }
									if ( currentDataset === 'avg_dur' ) {
										return ctx.dataset.label + ': ' + fmtDuration( ctx.parsed.y );
									}
									if ( currentDataset === 'avg_scroll' ) {
										return ctx.dataset.label + ': ' + ctx.parsed.y + '%';
									}
									return ctx.dataset.label + ': ' + ctx.parsed.y;
								},
							},
						},
					},
				},
			} );
		}

		function updateEngChartDataset() {
			if ( ! engChart ) { return; }
			var labelMap = {
				engaged:    'Sessions engagées',
				avg_dur:    'Durée moy. (s)',
				avg_scroll: 'Scroll moyen (%)',
			};
			engChart.data.datasets.forEach( function ( ds ) {
				ds.hidden = ( ds.label !== labelMap[ currentDataset ] );
			} );
			engChart.update();
		}

		// ─ Scroll depth distribution ──────────────────────────────────

		function renderScrollDist( dist, totalPageViews ) {
			window._lastScrollDist = dist;

			var container = document.getElementById( 'eng-scroll-dist' );
			if ( ! container ) { return; }

			var measured   = ( dist[10] || 0 ) + ( dist[25] || 0 ) + ( dist[50] || 0 )
			               + ( dist[75] || 0 ) + ( dist[100] || 0 );
			var grandTotal = totalPageViews > 0 ? totalPageViews : measured;
			var noScroll   = Math.max( 0, grandTotal - measured );

			var buckets = [
				{ label: 'Non mesuré',    val: noScroll,        color: '#e2e4e7', faded: true  },
				{ label: '< 25 %',        val: dist[10]  || 0,  color: '#94a3b8', faded: false },
				{ label: '25  49 %',     val: dist[25]  || 0,  color: '#60a5fa', faded: false },
				{ label: '50 – 74 %',     val: dist[50]  || 0,  color: '#34d399', faded: false },
				{ label: '75 – 99 %',     val: dist[75]  || 0,  color: '#10b981', faded: false },
				{ label: '100 %',         val: dist[100] || 0,  color: '#059669', faded: false },
			];

			var max  = Math.max.apply( null, buckets.map( function ( b ) { return b.val; } ) ) || 1;
			var html = '<div class="aa-scroll-dist">';

			buckets.forEach( function ( b ) {
				var pct      = Math.round( ( b.val / max ) * 100 );
				var sharePct = grandTotal > 0
					? ' (' + Math.round( b.val / grandTotal * 100 ) + '%)'
					: '';
				var labelCls = 'aa-scroll-dist__label' + ( b.faded ? ' aa-scroll-dist__label--faded' : '' );
				var countCls = 'aa-scroll-dist__count' + ( b.faded ? ' aa-scroll-dist__count--faded' : '' );
				var fillCls  = 'aa-scroll-dist__fill'  + ( b.faded ? ' aa-scroll-dist__fill--faded'  : '' );

				html += '<div class="aa-scroll-dist__item">'
					  +   '<div class="aa-scroll-dist__head">'
					  +     '<span class="' + labelCls + '">' + b.label + '</span>'
					  +     '<span class="' + countCls + '">'
					  +       b.val.toLocaleString( 'fr-FR' ) + ' pages vues' + sharePct
					  +     '</span>'
					  +   '</div>'
					  +   '<div class="aa-scroll-dist__bar">'
					  +     '<div class="' + fillCls + '" style="width:' + pct + '%;background:' + b.color + ';"></div>'
					  +   '</div>'
					  + '</div>';
			} );

			html += '</div>';
			html += '<p class="aa-scroll-dist__note">'
				  + 'Chaque page vue est comptée <strong>une seule fois</strong> '
				  + 'dans la tranche la plus haute atteinte.'
				  + '</p>';

			container.innerHTML = html;
		}

		// ─ Reader profiles widget ─────────────────────────────────────

		function renderReaderProfiles( data ) {
			var container = document.getElementById( 'eng-reader-profiles' );
			if ( ! container ) { return; }

			var profiles        = data.profiles || [];
			var total           = data.total_sessions || 0;
			var velocitySeuil   = data.median_velocity || 0;

			if ( ! profiles.length || total === 0 ) {
				container.innerHTML = '<p class="aa-no-data">Aucune donnée pour cette période</p>';
				return;
			}

			// Textes professionnels marketing par profil
			var copy = {
				zappeur:       {
					insight: 'Taux de rebond élevé',
    action: 'Contenu non engageant pour ce segment, ou ciblage inadéquat. Il pourrait sagir de visiteurs arrivés peu intéressés.',		},
				curieux:       {
					insight: 'Lecture partielle du contenu',
    action: 'Le visiteur parcourt pour se faire une idée rapide sans engagement profond.',				},
				compulsif:     {
					insight: 'Recherche d\u2019une information pr\u00E9cise',
action: 'Utilisateurs en phase décisionnelle ou de comparaison, très efficaces dans leur recherche.',				},
				super_lecteur: {
					insight: 'Lecture complète et engagement fort',
action: 'Ce segment est pleinement capté par le contenu. Indique une bonne adéquation avec les attentes et potentiel de conversion.',				},
			};

			// ── SVG Lucide par profil ──────────────────────────────────
			var profileIcons = {
				zappeur:       '<svg viewBox="0 0 24 24"><polyline points="1 4 1 10 7 10"/><path d="M3.51 15a9 9 0 1 0 .49-3.5"/></svg>',
				curieux:       '<svg viewBox="0 0 24 24"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>',
				compulsif:     '<svg viewBox="0 0 24 24"><polygon points="13 2 3 14 12 14 11 22 21 10 12 10 13 2"/></svg>',
				super_lecteur: '<svg viewBox="0 0 24 24"><path d="M2 3h6a4 4 0 0 1 4 4v14a3 3 0 0 0-3-3H2z"/><path d="M22 3h-6a4 4 0 0 0-4 4v14a3 3 0 0 1 3-3h7z"/></svg>',
			};

			// 4 cartes — sans barre de répartition
			var cardsHtml = '<div class="aa-rp-cards">'
				+ profiles.map( function ( p ) {
					var c       = copy[ p.key ] || { insight: '', action: '' };
					var isEmpty = p.count === 0;
					return '<div class="aa-rp-card aa-rp-card--' + p.key + ( isEmpty ? ' aa-rp-card--empty' : '' ) + '">'
						+   '<div class="aa-rp-card__header">'
						+     '<span class="aa-rp-card__label"><span class="aa-rp-card__icon">' + ( profileIcons[ p.key ] || '' ) + '</span>' + htmlEscape( p.label ) + '</span>'
						+     '<span class="aa-rp-card__range aa-rp-badge--' + p.key + '">' + htmlEscape( p.range ) + '</span>'
						+   '</div>'
						+   '<div class="aa-rp-card__pct">'
						+     ( isEmpty ? '' : p.pct + '\u202f%' )
						+   '</div>'
						+   '<div class="aa-rp-card__count">'
						+     ( isEmpty ? 'Aucune session' : fmtInt( p.count ) + ' session' + ( p.count > 1 ? 's' : '' ) )
						+   '</div>'
						+   '<div class="aa-rp-card__copy">'
						+     '<p class="aa-rp-card__insight">' + htmlEscape( c.insight ) + '</p>'
						+     '<p class="aa-rp-card__action">' + htmlEscape( c.action ) + '</p>'
						+   '</div>'
						+ '</div>';
				} ).join( '' )
				+ '</div>';


			container.innerHTML = cardsHtml ;
		}

		// ─ Per-page engagement table (paginée) ─────────────────────────────

		var _pagesData   = [];
		var _visibleRows = 10;
		var PAGE_SIZE    = 10;

		function buildRowHtml( p, idx ) {
			var sig   = p.score_signals || {};
			var title = p.page_title || p.page_url || '—';
			var rank  = idx < 3 ? ( idx + 1 ) + '. ' : '';

			// ── Durée moyenne ────────────────────────────────────────────
			var durScore = parseFloat( ( sig.duration || {} ).score || 0 );
			var durCls   = durScore >= 70 ? 'aa-metric--good' : durScore >= 40 ? 'aa-metric--mid' : 'aa-metric--low';
			var durHtml  = '<div class="aa-metric ' + durCls + '">'
				+ '<span class="aa-metric__val">' + fmtDuration( p.avg_duration || 0 ) + '</span>'
				+ '</div>';

			// ─ Scroll moyen ─────────────────────────────────────────────
			var scrollRaw   = ( sig.scroll && ( sig.scroll.raw != null ) ) ? sig.scroll.raw : null;
			var scrollScore = parseFloat( ( sig.scroll || {} ).score || 0 );
			var scrollCls   = scrollScore >= 70 ? 'aa-metric--good' : scrollScore >= 40 ? 'aa-metric--mid' : 'aa-metric--low';
			var scrollHtml  = '<div class="aa-metric ' + scrollCls + '">'
				+ '<span class="aa-metric__val">' + ( scrollRaw !== null ? scrollRaw + '%' : '—' ) + '</span>'
				+ '</div>';

			// ── Profil lecteur dominant ────────────────────────────────────
			// Dérivé du scroll moyen de la page (même seuils que reader-profiles global)
			var profileHtml;
			if ( scrollRaw === null ) {
				profileHtml = '<span class="aa-profile aa-profile--unknown">—</span>';
			} else {
				var profileKey, profileLabel, profileIcon;
				if ( scrollRaw < 20 ) {
					profileKey   = 'zappeur';
					profileLabel = 'Zappeur';
					profileIcon  = '<svg viewBox="0 0 24 24" width="11" height="11" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" style="vertical-align:middle;margin-top:-2px"><polyline points="1 4 1 10 7 10"/><path d="M3.51 15a9 9 0 1 0 .49-3.5"/></svg>';
				} else if ( scrollRaw < 75 ) {
					profileKey   = 'curieux';
					profileLabel = 'Curieux';
					profileIcon  = '<svg viewBox="0 0 24 24" width="11" height="11" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" style="vertical-align:middle;margin-top:-2px"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>';
				} else {
					profileKey   = 'super_lecteur';
					profileLabel = 'Super-Lecteur';
					profileIcon  = '<svg viewBox="0 0 24 24" width="11" height="11" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" style="vertical-align:middle;margin-top:-2px"><path d="M2 3h6a4 4 0 0 1 4 4v14a3 3 0 0 0-3-3H2z"/><path d="M22 3h-6a4 4 0 0 0-4 4v14a3 3 0 0 1 3-3h7z"/></svg>';
				}
				profileHtml = '<span class="aa-profile aa-profile--' + profileKey + '"'
					+ ' title="Scroll moyen : ' + scrollRaw + '%">'
					+ profileIcon + ' ' + profileLabel
					+ '</span>';
			}

			// ── Wilson / fiabilit ─────────────────────────────────────────
			var wilsonScore = parseFloat( ( sig.confidence || {} ).score || 0 );
			var wilsonCls   = wilsonScore >= 70 ? 'aa-wilson--high'
			                : wilsonScore >= 40 ? 'aa-wilson--mid'
			                : 'aa-wilson--low';
			var wilsonHtml  = '<div class="aa-wilson ' + wilsonCls + '"'
				+ ' title="' + fmtInt( p.total_sessions ) + ' sessions · fiabilité : ' + Math.round( wilsonScore ) + ' %">'
				+ '<div class="aa-wilson-bar"><div class="aa-wilson-bar__fill" style="width:' + Math.round( wilsonScore ) + '%;"></div></div>'
				+ '<span class="aa-wilson__pct">' + Math.round( wilsonScore ) + ' %</span>'
				+ '</div>';

			// ── Lien éditeur WordPress ────────────────────────────────────
			var adminBase = ( typeof alwaysAnalyticsAdmin !== 'undefined' && alwaysAnalyticsAdmin.adminUrl )
				? alwaysAnalyticsAdmin.adminUrl
				: '/wp-admin/';
			var editLink = p.post_id > 0
				? adminBase + 'post.php?post=' + p.post_id + '&action=edit'
				: null;

			var titleHtml = editLink
				? '<a class="aa-page-edit-link" href="' + editLink + '" target="_blank" title="Éditer dans WordPress">'
					+ rank + htmlEscape( title )
					+ '<svg class="aa-edit-icon" xmlns="http://www.w3.org/2000/svg" width="11" height="11" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"/><path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"/></svg>'
					+ '</a>'
				: rank + htmlEscape( title );

			return '<tr class="aa-eng-row">'
				+ '<td class="aa-eng-cell-page">'
				+   '<div class="aa-page-detail-title" title="' + htmlEscape( p.page_url ) + '">'
				+     titleHtml
				+   '</div>'
				+   '<div class="aa-page-detail-url">' + htmlEscape( p.page_url ) + '</div>'
				+   '<div class="aa-page-detail-views">'
				+     fmtInt( p.page_views ) + ' vues · ' + fmtInt( p.total_sessions ) + ' sessions'
				+   '</div>'
				+ '</td>'
				+ '<td class="aa-eng-cell-duration">' + durHtml + '</td>'
				+ '<td class="aa-eng-cell-scroll">' + scrollHtml + '</td>'
				+ '<td class="aa-eng-cell-profile">' + profileHtml + '</td>'
				+ '<td class="aa-eng-cell-wilson">' + wilsonHtml + '</td>'
				+ '</tr>';
		}

		function renderPagesTableVisible() {
			var tbody    = document.querySelector( '#eng-pages-table tbody' );
			var wrapBtn  = document.getElementById( 'eng-show-more-wrap' );
			var remSpan  = document.getElementById( 'eng-show-more-remaining' );
			if ( ! tbody ) { return; }

			var visible = _pagesData.slice( 0, _visibleRows );
			tbody.innerHTML = visible.map( function ( p, i ) {
				return buildRowHtml( p, i );
			} ).join( '' );

			var remaining = _pagesData.length - _visibleRows;
			if ( wrapBtn ) {
				wrapBtn.style.display = remaining > 0 ? '' : 'none';
			}
			if ( remSpan ) {
				remSpan.textContent = remaining > 0 ? '(' + remaining + ' restantes)' : '';
			}
		}

		function renderPagesTable( data ) {
			var tbody = document.querySelector( '#eng-pages-table tbody' );
			if ( ! tbody ) { return; }

			if ( ! data || ! data.length ) {
				tbody.innerHTML = '<tr><td colspan="3" class="aa-no-data">Aucune donnée pour cette période</td></tr>';
				var wrapBtn = document.getElementById( 'eng-show-more-wrap' );
				if ( wrapBtn ) { wrapBtn.style.display = 'none'; }
				return;
			}

			_pagesData   = data;
			_visibleRows = PAGE_SIZE;
			renderPagesTableVisible();
			bindShowMore();
			bindExplainerToggle();
		}

		function bindShowMore() {
			var btn = document.getElementById( 'eng-show-more-btn' );
			if ( ! btn || btn._boundShowMore ) { return; }
			btn._boundShowMore = true;
			btn.addEventListener( 'click', function () {
				_visibleRows += PAGE_SIZE;
				renderPagesTableVisible();
			} );
		}

		function bindExplainerToggle() {
			var btn = document.getElementById( 'eng-score-toggle' );
			var box = document.getElementById( 'eng-score-explainer' );
			if ( ! btn || ! box || btn._bound ) { return; }
			btn._bound = true;

			// Sur mobile : fermer le panel par défaut au chargement
			if ( window.innerWidth <= 768 ) {
				box.hidden = true;
				btn.setAttribute( 'aria-expanded', 'false' );
			}

			btn.addEventListener( 'click', function () {
				// Sur desktop le toggle ne fait rien (le panel est toujours visible via CSS)
				if ( window.innerWidth > 768 ) { return; }
				var isOpen = ! box.hidden;
				box.hidden = isOpen;
				btn.setAttribute( 'aria-expanded', String( ! isOpen ) );
				btn.querySelector( '.aa-score-info-chevron' ).classList.toggle( 'is-open', ! isOpen );
			} );
		}

		// ── Helpers ─────────────────────────────────────────────────

		function setText( id, v ) {
			var el = document.getElementById( id );
			if ( el ) { el.textContent = v; }
		}

		function fmtInt( n ) {
			return ( parseInt( n, 10 ) || 0 ).toLocaleString( 'fr-FR' );
		}

		function fmtDuration( s ) {
			s = Math.round( +s || 0 );
			if ( s <= 0 ) { return '0s'; }
			var m = Math.floor( s / 60 );
			return m > 0 ? m + 'm ' + ( s % 60 ) + 's' : s + 's';
		}

		function dateOffset( d ) {
			var dt = new Date();
			dt.setDate( dt.getDate() + d );
			return dt.getFullYear() + '-'
				+ String( dt.getMonth() + 1 ).padStart( 2, '0' ) + '-'
				+ String( dt.getDate() ).padStart( 2, '0' );
		}

		function enc( s ) {
			return encodeURIComponent( s || '' );
		}

		function htmlEscape( s ) {
			var div = document.createElement( 'div' );
			div.appendChild( document.createTextNode( s || '' ) );
			return div.innerHTML;
		}

	} ); // end waitForConfig

}() );
