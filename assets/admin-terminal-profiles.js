/**
 * Terminal Profiles admin — one JSON file per profile + live preview.
 */
( function () {
	const cfg = window.forwpAcTerminalAdmin;
	if ( ! cfg || ! window.wp?.apiFetch ) {
		return;
	}

	const editorEl = document.getElementById( 'forwp-ac-terminal-editor' );
	const editorTitle = document.getElementById( 'forwp-ac-terminal-editor-title' );
	const editorHint = document.getElementById( 'forwp-ac-terminal-editor-hint' );
	const slugEl = document.getElementById( 'forwp-ac-terminal-slug' );
	const jsonEl = document.getElementById( 'forwp-ac-terminal-json' );
	const statusEl = document.getElementById( 'forwp-ac-terminal-json-status' );
	const previewEl = document.getElementById( 'forwp-ac-terminal-preview' );
	const saveBtn = document.getElementById( 'forwp-ac-terminal-save' );
	const cancelBtn = document.getElementById( 'forwp-ac-terminal-cancel' );
	const addBtns = document.querySelectorAll( '.forwp-ac-terminal-add-new' );
	const schemaHintEl = document.getElementById( 'forwp-ac-terminal-schema-hint' );
	const previewNoteEl = document.getElementById( 'forwp-ac-terminal-preview-note' );

	if ( ! editorEl || ! slugEl || ! jsonEl || ! saveBtn ) {
		return;
	}

	let editingSlug = '';
	let editingType = cfg.activeTab === 'practice-cases' ? 'challenge' : 'manual';
	let previewTimer = null;

	function escapeHtml( text ) {
		return String( text )
			.replace( /&/g, '&amp;' )
			.replace( /</g, '&lt;' )
			.replace( />/g, '&gt;' )
			.replace( /"/g, '&quot;' );
	}

	function setStatus( message, isError ) {
		if ( ! statusEl ) {
			return;
		}
		statusEl.textContent = message || '';
		statusEl.style.color = isError ? '#b32d2e' : '#007017';
	}

	function isChallengeType( type ) {
		return type === 'challenge';
	}

	function schemaForType( type ) {
		return isChallengeType( type )
			? cfg.schemaHintChallenge || cfg.schemaHint
			: cfg.schemaHintManual || cfg.schemaHint;
	}

	function defaultSlugForType( type ) {
		return isChallengeType( type ) ? 'challenge-my-case' : 'my-instruction-profile';
	}

	function updateSchemaHint( type ) {
		if ( schemaHintEl ) {
			schemaHintEl.textContent = schemaForType( type );
		}
		if ( previewNoteEl ) {
			previewNoteEl.textContent = isChallengeType( type )
				? cfg.i18n.previewChallenge
				: cfg.i18n.previewNote;
		}
	}

	function renderPreview() {
		if ( ! previewEl ) {
			return;
		}

		let data;
		try {
			data = JSON.parse( jsonEl.value || '{}' );
		} catch ( e ) {
			previewEl.className =
				'forwp-ac-terminal-admin__preview forwp-ac-terminal-admin__preview--error';
			previewEl.textContent = cfg.i18n.previewInvalid;
			return;
		}

		const profileType =
			data.type === 'challenge' || isChallengeType( editingType )
				? 'challenge'
				: 'manual';

		if ( profileType === 'challenge' ) {
			renderChallengePreview( data );
			return;
		}

		previewEl.className = 'forwp-ac-terminal-admin__preview';

		const title = data.title || data.profile || 'terminal';
		const prompt = data.prompt || '$';
		const profile = data.profile || slugEl.value || '—';
		const categories = Array.isArray( data.categories ) ? data.categories : [];
		const commands = Array.isArray( data.commands ) ? data.commands : [];

		let sidebarHtml = '';
		if ( categories.length ) {
			categories.forEach( function ( cat ) {
				const color = cat.color || '#569cd6';
				sidebarHtml += '<div class="forwp-ac-preview__cat">';
				sidebarHtml +=
					'<div class="forwp-ac-preview__cat-head"><span class="forwp-ac-preview__cat-dot" style="background:' +
					escapeHtml( color ) +
					'"></span>' +
					escapeHtml( cat.name || cat.id || 'Category' ) +
					'</div><div class="forwp-ac-preview__cat-cmds">';
				( cat.commands || [] ).slice( 0, 6 ).forEach( function ( item ) {
					sidebarHtml +=
						'<span class="forwp-ac-preview__cmd">' +
						escapeHtml( item.cmd || '' ) +
						'</span>';
					if ( item.desc ) {
						sidebarHtml +=
							'<span class="forwp-ac-preview__cmd-desc">' +
							escapeHtml( item.desc ) +
							'</span>';
					}
				} );
				if ( ( cat.commands || [] ).length > 6 ) {
					sidebarHtml +=
						'<span class="forwp-ac-preview__cmd-desc">+' +
						( cat.commands.length - 6 ) +
						' more</span>';
				}
				sidebarHtml += '</div></div>';
			} );
		} else {
			sidebarHtml =
				'<p class="forwp-ac-preview__line forwp-ac-preview__line--system">' +
				escapeHtml( cfg.i18n.previewEmpty ) +
				'</p>';
		}

		let commandsHtml = '';
		commands.slice( 0, 5 ).forEach( function ( cmd ) {
			const label = cmd.command || cmd.pattern || 'command';
			const out = String( cmd.output || '' ).split( '\n' )[ 0 ];
			commandsHtml +=
				'<div class="forwp-ac-preview__cmd-row">' +
				'<span class="forwp-ac-preview__prompt">' +
				escapeHtml( prompt ) +
				'</span> ' +
				escapeHtml( label ) +
				'<div class="forwp-ac-preview__line forwp-ac-preview__line--system">' +
				escapeHtml( out ) +
				'</div></div>';
		} );
		if ( commands.length > 5 ) {
			commandsHtml +=
				'<div class="forwp-ac-preview__line forwp-ac-preview__line--system">+' +
				( commands.length - 5 ) +
				' commands</div>';
		}

		previewEl.innerHTML =
			'<div class="forwp-ac-preview__header">' +
			'<span class="forwp-ac-preview__dot forwp-ac-preview__dot--red"></span>' +
			'<span class="forwp-ac-preview__dot forwp-ac-preview__dot--yellow"></span>' +
			'<span class="forwp-ac-preview__dot forwp-ac-preview__dot--green"></span>' +
			'<span class="forwp-ac-preview__title">' +
			escapeHtml( title ) +
			'</span></div>' +
			'<div class="forwp-ac-preview__body">' +
			'<div class="forwp-ac-preview__main">' +
			'<div class="forwp-ac-preview__meta">' +
			'<span class="forwp-ac-preview__badge">profile: ' +
			escapeHtml( profile ) +
			'</span>' +
			'<span class="forwp-ac-preview__badge">' +
			commands.length +
			' commands</span>' +
			'<span class="forwp-ac-preview__badge">' +
			categories.length +
			' categories</span></div>' +
			'<div class="forwp-ac-preview__line forwp-ac-preview__line--system">Type "help" or click sidebar commands</div>' +
			( commandsHtml
				? '<div class="forwp-ac-preview__commands-head">Sample responses</div>' +
				  commandsHtml
				: '' ) +
			'</div>' +
			'<div class="forwp-ac-preview__sidebar">' +
			'<div class="forwp-ac-preview__sidebar-head">Categories</div>' +
			sidebarHtml +
			'</div></div>';
	}

	function renderChallengePreview( data ) {
		previewEl.className = 'forwp-ac-terminal-admin__preview';

		const title = data.title || data.profile || 'challenge';
		const profile = data.profile || slugEl.value || '—';
		const steps = Array.isArray( data.steps ) ? data.steps : [];
		const caseSlug = data.slug || '—';
		const difficulty = data.difficulty || '—';

		let stepsHtml = '';
		steps.slice( 0, 4 ).forEach( function ( step ) {
			stepsHtml +=
				'<div class="forwp-ac-preview__cmd-row">' +
				'<div class="forwp-ac-preview__line forwp-ac-preview__line--system">' +
				escapeHtml( step.hint || '' ) +
				'</div></div>';
		} );
		if ( steps.length > 4 ) {
			stepsHtml +=
				'<div class="forwp-ac-preview__line forwp-ac-preview__line--system">+' +
				( steps.length - 4 ) +
				' steps</div>';
		}

		previewEl.innerHTML =
			'<div class="forwp-ac-preview__header">' +
			'<span class="forwp-ac-preview__dot forwp-ac-preview__dot--red"></span>' +
			'<span class="forwp-ac-preview__dot forwp-ac-preview__dot--yellow"></span>' +
			'<span class="forwp-ac-preview__dot forwp-ac-preview__dot--green"></span>' +
			'<span class="forwp-ac-preview__title">' +
			escapeHtml( title ) +
			'</span></div>' +
			'<div class="forwp-ac-preview__body">' +
			'<div class="forwp-ac-preview__main">' +
			'<div class="forwp-ac-preview__meta">' +
			'<span class="forwp-ac-preview__badge">type: challenge</span>' +
			'<span class="forwp-ac-preview__badge">profile: ' +
			escapeHtml( profile ) +
			'</span>' +
			'<span class="forwp-ac-preview__badge">case: ' +
			escapeHtml( caseSlug ) +
			'</span>' +
			'<span class="forwp-ac-preview__badge">' +
			steps.length +
			' steps</span>' +
			'<span class="forwp-ac-preview__badge">' +
			escapeHtml( difficulty ) +
			'</span></div>' +
			'<div class="forwp-ac-preview__line forwp-ac-preview__line--system">' +
			escapeHtml( data.instructions || data.description || cfg.i18n.previewChallenge ) +
			'</div>' +
			( stepsHtml
				? '<div class="forwp-ac-preview__commands-head">Step hints</div>' + stepsHtml
				: '' ) +
			'</div></div>';
	}

	function schedulePreview() {
		window.clearTimeout( previewTimer );
		previewTimer = window.setTimeout( renderPreview, 250 );
	}

	function profilesDirHint() {
		const dir = cfg.profilesDir || 'forwp-advanced-code/terminal/';
		return dir.endsWith( '/' ) ? dir : dir + '/';
	}

	function openEditor( mode, slug, profileType ) {
		editorEl.hidden = false;
		editingSlug = slug || '';
		editingType = profileType || editingType;
		const dirHint = profilesDirHint();

		updateSchemaHint( editingType );
		editorEl.scrollIntoView( { behavior: 'smooth', block: 'start' } );

		if ( 'new' === mode ) {
			editorTitle.textContent = isChallengeType( editingType )
				? cfg.i18n.newPracticeCase
				: cfg.i18n.newInstruction;
			editorHint.textContent =
				'Creates a new file in ' + dirHint + '{slug}.json';
			slugEl.value = defaultSlugForType( editingType );
			slugEl.disabled = false;
			jsonEl.value = schemaForType( editingType );
			setStatus( '', false );
			renderPreview();
			slugEl.focus();
			return;
		}

		const fileName = slug ? slug + '.json' : '';
		editorTitle.textContent = cfg.i18n.editing + ' ' + fileName;
		editorHint.textContent =
			'Saving writes ' + profilesDirHint() + fileName;
		slugEl.value = slug;
		slugEl.disabled = false;
	}

	function closeEditor() {
		editorEl.hidden = true;
		editingSlug = '';
		setStatus( '', false );
	}

	function loadProfile( slug, profileType ) {
		const safe = String( slug || '' )
			.trim()
			.toLowerCase()
			.replace( /[^a-z0-9-]/g, '' );
		if ( ! safe ) {
			return;
		}

		editingType = profileType || editingType;
		openEditor( 'edit', safe, editingType );
		setStatus( '', false );

		return window.wp.apiFetch( {
			path: '/' + cfg.restNamespace + '/terminal/' + safe,
		} )
			.then( function ( data ) {
				jsonEl.value = JSON.stringify( data, null, 2 );
				if ( data.type === 'challenge' ) {
					editingType = 'challenge';
				} else {
					editingType = 'manual';
				}
				updateSchemaHint( editingType );
				setStatus( cfg.i18n.loaded, false );
				renderPreview();
			} )
			.catch( function () {
				setStatus( cfg.i18n.loadFailed, true );
			} );
	}

	function syncJsonProfileSlug( slug ) {
		const safe = String( slug || '' )
			.trim()
			.toLowerCase()
			.replace( /[^a-z0-9-]/g, '' );
		if ( ! safe ) {
			return null;
		}

		let data;
		try {
			data = JSON.parse( jsonEl.value || '{}' );
		} catch ( e ) {
			return null;
		}

		data.profile = safe;
		if ( isChallengeType( editingType ) ) {
			data.type = 'challenge';
		}
		jsonEl.value = JSON.stringify( data, null, 2 );
		schedulePreview();

		return data;
	}

	jsonEl.addEventListener( 'input', schedulePreview );

	slugEl.addEventListener( 'input', function () {
		syncJsonProfileSlug( slugEl.value );
	} );

	saveBtn.addEventListener( 'click', function () {
		const slug = slugEl.value.trim().toLowerCase().replace( /[^a-z0-9-]/g, '' );
		if ( ! slug ) {
			setStatus( cfg.i18n.needSlug, true );
			return;
		}

		const data = syncJsonProfileSlug( slug );
		if ( ! data ) {
			setStatus( cfg.i18n.invalidJson, true );
			return;
		}

		saveBtn.disabled = true;
		setStatus( '', false );

		window.wp.apiFetch( {
			path: '/' + cfg.restNamespace + '/terminal/' + slug,
			method: 'POST',
			data: { json: jsonEl.value },
		} )
			.then( function () {
				setStatus( cfg.i18n.saved, false );
				window.location.reload();
			} )
			.catch( function ( err ) {
				setStatus( err?.message || err?.data?.message || cfg.i18n.saveFailed, true );
			} )
			.finally( function () {
				saveBtn.disabled = false;
			} );
	} );

	if ( cancelBtn ) {
		cancelBtn.addEventListener( 'click', closeEditor );
	}

	if ( addBtns.length ) {
		addBtns.forEach( function ( btn ) {
			btn.addEventListener( 'click', function () {
				openEditor(
					'new',
					'',
					btn.getAttribute( 'data-forwp-profile-type' ) || 'manual'
				);
			} );
		} );
	}

	document.querySelectorAll( '.forwp-ac-terminal-load-profile' ).forEach( function ( btn ) {
		btn.addEventListener( 'click', function () {
			loadProfile(
				btn.getAttribute( 'data-forwp-profile' ),
				btn.getAttribute( 'data-forwp-profile-type' )
			);
		} );
	} );

	document.querySelectorAll( '.forwp-ac-terminal-duplicate-profile' ).forEach( function ( btn ) {
		btn.addEventListener( 'click', function () {
			const source = btn.getAttribute( 'data-forwp-profile' );
			const newSlug = window.prompt(
				cfg.i18n.needNewSlug,
				source ? source + '-copy' : ''
			);
			if ( ! newSlug ) {
				return;
			}

			window.wp.apiFetch( {
				path:
					'/' +
					cfg.restNamespace +
					'/terminal/' +
					source +
					'/duplicate',
				method: 'POST',
				data: { new_slug: newSlug },
			} )
				.then( function () {
					window.location.reload();
				} )
				.catch( function ( err ) {
					window.alert(
						err?.message || err?.data?.message || cfg.i18n.duplicateFail
					);
				} );
		} );
	} );

	document.querySelectorAll( '.forwp-ac-terminal-delete-profile' ).forEach( function ( btn ) {
		btn.addEventListener( 'click', function () {
			const slug = btn.getAttribute( 'data-forwp-profile' );
			if ( ! slug || ! window.confirm( cfg.i18n.confirmDelete ) ) {
				return;
			}

			window.wp.apiFetch( {
				path: '/' + cfg.restNamespace + '/terminal/' + slug,
				method: 'DELETE',
			} )
				.then( function () {
					window.location.reload();
				} )
				.catch( function ( err ) {
					window.alert(
						err?.message || err?.data?.message || cfg.i18n.deleteFailed
					);
				} );
		} );
	} );

	const importDialog = document.getElementById( 'forwp-ac-terminal-import-dialog' );
	const importProfileLabel = document.getElementById(
		'forwp-ac-terminal-import-profile-label'
	);
	const importPostTypeEl = document.getElementById(
		'forwp-ac-terminal-import-post-type'
	);
	const importForceEl = document.getElementById( 'forwp-ac-terminal-import-force' );
	const importStatusEl = document.getElementById( 'forwp-ac-terminal-import-status' );
	const importSubmitBtn = document.getElementById( 'forwp-ac-terminal-import-submit' );
	const importCancelBtn = document.getElementById( 'forwp-ac-terminal-import-cancel' );
	let importProfileSlug = '';

	function setImportStatus( message, isError ) {
		if ( ! importStatusEl ) {
			return;
		}
		importStatusEl.textContent = message || '';
		importStatusEl.className = 'forwp-ac-terminal-admin__import-status';
		if ( message ) {
			importStatusEl.classList.add(
				isError
					? 'forwp-ac-terminal-admin__import-status--error'
					: 'forwp-ac-terminal-admin__import-status--success'
			);
		}
	}

	function openImportDialog( slug, title ) {
		if ( ! importDialog ) {
			return;
		}
		importProfileSlug = slug || '';
		if ( importProfileLabel ) {
			importProfileLabel.textContent = title
				? title + ' (' + slug + '.json)'
				: slug + '.json';
		}
		if ( importForceEl ) {
			importForceEl.checked = false;
		}
		setImportStatus( '', false );
		importDialog.hidden = false;
	}

	function closeImportDialog() {
		if ( ! importDialog ) {
			return;
		}
		importDialog.hidden = true;
		importProfileSlug = '';
		setImportStatus( '', false );
	}

	document.querySelectorAll( '.forwp-ac-terminal-import-profile' ).forEach( function ( btn ) {
		btn.addEventListener( 'click', function () {
			openImportDialog(
				btn.getAttribute( 'data-forwp-profile' ),
				btn.getAttribute( 'data-forwp-profile-title' )
			);
		} );
	} );

	if ( importCancelBtn ) {
		importCancelBtn.addEventListener( 'click', closeImportDialog );
	}

	if ( importDialog ) {
		importDialog.addEventListener( 'click', function ( event ) {
			if ( event.target === importDialog ) {
				closeImportDialog();
			}
		} );
	}

	if ( importSubmitBtn ) {
		importSubmitBtn.addEventListener( 'click', function () {
			if ( ! importProfileSlug ) {
				return;
			}

			const postType = importPostTypeEl ? importPostTypeEl.value : 'practice_case';
			const force = importForceEl ? importForceEl.checked : false;

			importSubmitBtn.disabled = true;
			setImportStatus( '', false );

			window.wp.apiFetch( {
				path:
					'/' +
					cfg.restNamespace +
					'/terminal/' +
					importProfileSlug +
					'/import',
				method: 'POST',
				data: {
					post_type: postType,
					force: force,
				},
			} )
				.then( function ( response ) {
					setImportStatus( cfg.i18n.importSuccess, false );
					if ( response?.edit_url ) {
						window.setTimeout( function () {
							window.location.href = response.edit_url;
						}, 600 );
						return;
					}
					window.setTimeout( function () {
						window.location.reload();
					}, 800 );
				} )
				.catch( function ( err ) {
					importSubmitBtn.disabled = false;
					setImportStatus(
						err?.message || err?.data?.message || cfg.i18n.importFailed,
						true
					);
				} );
		} );
	}
} )();
