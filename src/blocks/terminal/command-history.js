/**
 * Terminal input command history (ArrowUp / ArrowDown).
 *
 * @param {HTMLInputElement} inputEl Command input element.
 */
export function createCommandHistory( inputEl ) {
	const history = [];
	let index = -1;
	let draft = '';

	function push( rawCommand ) {
		const cmd = String( rawCommand || '' ).trim();
		if ( ! cmd ) {
			return;
		}
		if ( history[ history.length - 1 ] === cmd ) {
			return;
		}
		history.push( cmd );
		index = -1;
		draft = '';
	}

	function attach() {
		inputEl.addEventListener( 'keydown', ( e ) => {
			if ( e.key !== 'ArrowUp' && e.key !== 'ArrowDown' ) {
				return;
			}
			if ( inputEl.disabled || ! history.length ) {
				return;
			}

			e.preventDefault();

			if ( e.key === 'ArrowUp' ) {
				if ( index === -1 ) {
					draft = inputEl.value;
					index = history.length;
				}
				if ( index > 0 ) {
					index -= 1;
					inputEl.value = history[ index ];
				}
				return;
			}

			if ( index === -1 ) {
				return;
			}
			if ( index < history.length - 1 ) {
				index += 1;
				inputEl.value = history[ index ];
				return;
			}
			index = -1;
			inputEl.value = draft;
		} );
	}

	function clear() {
		history.length = 0;
		index = -1;
		draft = '';
	}

	return { push, attach, clear };
}
