/**
 * Setup Guide — highlight the nav link for the section currently in view, and
 * smooth-scroll when a nav link is clicked. Progressive enhancement: with JS
 * off, the nav is still a plain anchor list that jumps to each section.
 */
( function () {
	'use strict';

	var nav = document.querySelector( '.sforge-help-nav' );
	if ( ! nav ) {
		return;
	}

	var links = Array.prototype.slice.call( nav.querySelectorAll( 'a[href^="#"]' ) );
	if ( ! links.length ) {
		return;
	}

	var byId = {};
	var sections = [];
	links.forEach( function ( link ) {
		var id = link.getAttribute( 'href' ).slice( 1 );
		var section = document.getElementById( id );
		if ( section ) {
			byId[ id ] = link;
			sections.push( section );
		}
	} );

	function setActive( id ) {
		links.forEach( function ( link ) {
			link.classList.toggle( 'is-active', link === byId[ id ] );
		} );
	}

	// Smooth scroll on click.
	links.forEach( function ( link ) {
		link.addEventListener( 'click', function ( e ) {
			var id = link.getAttribute( 'href' ).slice( 1 );
			var section = document.getElementById( id );
			if ( ! section ) {
				return;
			}
			e.preventDefault();
			section.scrollIntoView( { behavior: 'smooth', block: 'start' } );
			setActive( id );
			if ( history.replaceState ) {
				history.replaceState( null, '', '#' + id );
			}
		} );
	} );

	// Highlight the section nearest the top of the viewport as the user scrolls.
	if ( 'IntersectionObserver' in window ) {
		var visible = {};
		var observer = new IntersectionObserver( function ( entries ) {
			entries.forEach( function ( entry ) {
				visible[ entry.target.id ] = entry.isIntersecting;
			} );
			// Pick the first section (document order) currently in view.
			for ( var i = 0; i < sections.length; i++ ) {
				if ( visible[ sections[ i ].id ] ) {
					setActive( sections[ i ].id );
					break;
				}
			}
		}, { rootMargin: '-20% 0px -70% 0px', threshold: 0 } );
		sections.forEach( function ( section ) {
			observer.observe( section );
		} );
	}
}() );
