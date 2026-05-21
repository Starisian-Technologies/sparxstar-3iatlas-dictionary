/**
 * AccessoryBar — Mandinka special-character bar for typed games.
 *
 * Required in Listen & Write and Complete the Sentence.  Pinned above the
 * soft keyboard on mobile using window.visualViewport resize events.
 * Each button inserts its character at the cursor position in the currently
 * focused input / textarea.
 */
import React, { useEffect, useState } from 'react';

/** Mandinka characters not on a standard keyboard. */
const CHARS = [ 'ŋ', 'ɓ', 'ɗ', 'ñ', 'ɲ', 'ʔ', 'á', 'é', 'í', 'ó', 'ú' ];

function insertAtCursor( char ) {
    const el = document.activeElement;
    if ( ! el ) return;

    const tag = el.tagName.toLowerCase();
    if ( tag !== 'input' && tag !== 'textarea' ) return;

    const start = el.selectionStart ?? el.value.length;
    const end   = el.selectionEnd   ?? el.value.length;

    // Use execCommand where available (handles undo stack) otherwise splice.
    if ( document.execCommand ) {
        el.focus();
        el.setSelectionRange( start, end );
        document.execCommand( 'insertText', false, char );
    } else {
        const val  = el.value;
        el.value   = val.slice( 0, start ) + char + val.slice( end );
        el.setSelectionRange( start + char.length, start + char.length );
        // Dispatch synthetic input event so React picks up the change.
        el.dispatchEvent( new Event( 'input', { bubbles: true } ) );
    }
}

export default function AccessoryBar() {
    const [ bottom, setBottom ] = useState( 0 );

    useEffect( () => {
        if ( ! window.visualViewport ) return undefined;

        function onResize() {
            const vv  = window.visualViewport;
            const off = window.innerHeight - vv.height - vv.offsetTop;
            setBottom( Math.max( 0, off ) );
        }

        window.visualViewport.addEventListener( 'resize', onResize );
        window.visualViewport.addEventListener( 'scroll', onResize );
        onResize();

        return () => {
            window.visualViewport.removeEventListener( 'resize', onResize );
            window.visualViewport.removeEventListener( 'scroll', onResize );
        };
    }, [] );

    return (
        <div
            className="fixed left-0 right-0 z-[9000] flex gap-1 px-2 py-2 overflow-x-auto scrollbar-hide"
            style={ {
                bottom,
                background: '#1a1a1a',
                borderTop:  '1px solid #333',
            } }
            aria-label="Mandinka special characters"
        >
            { CHARS.map( ( char ) => (
                <button
                    key={ char }
                    type="button"
                    onMouseDown={ ( e ) => {
                        // Prevent focus loss from input
                        e.preventDefault();
                        insertAtCursor( char );
                    } }
                    onTouchEnd={ ( e ) => {
                        e.preventDefault();
                        insertAtCursor( char );
                    } }
                    className="shrink-0 min-w-[2.5rem] h-10 rounded-lg font-bold text-white text-base flex items-center justify-center transition-opacity active:opacity-70"
                    style={ { background: '#E91E8C' } }
                    aria-label={ `Insert ${char}` }
                >
                    { char }
                </button>
            ) ) }
        </div>
    );
}
