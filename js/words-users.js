// Custom words management
document.addEventListener( 'DOMContentLoaded', ( event ) => {
    const customWordsFromServer = customWordsData.customWords;
    let allWords = [ ...customWordsFromServer ].map( word => word.toLowerCase() ); // Initialize with server words in lowercase
    const deleteAllButton = document.getElementById( 'livcom_plugin_delete_all_words_button' );
    const container = document.getElementById( 'livcom_plugin_custom_words_container' );

    // Handle Enter key in the input field
    const input = document.getElementById( 'livcom_plugin_custom_word_input' );
    input.addEventListener( 'keydown', function( e ) {
        if ( e.key === 'Enter' ) {
            e.preventDefault();
            document.getElementById( 'livcom_plugin_add_word_button' ).click();
        }
    });

    document.getElementById( 'livcom_plugin_add_word_button' ).addEventListener( 'click', function() {
        const words = input.value.split( ',' ).map( word => word.trim() ).filter( word => word.length >= 3 );

        if ( words.length > 0 ) {
            words.forEach( word => {
                if ( !allWords.includes( word.toLowerCase() ) ) {
                    const element = document.createElement( 'div' );
                    element.classList.add( 'livcom_plugin_custom_word', 'tag', 'is-medium', 'is-warning' );
                    element.innerHTML = `${word} <span class="livcom_plugin_remove_word_button delete is-small">x</span><input type="hidden" name="livcom_plugin_custom_words[]" value="${word}">`;

                    element.querySelector( '.livcom_plugin_remove_word_button' ).addEventListener( 'click', function() {
                        container.removeChild( element );
                        const index = allWords.indexOf( word.toLowerCase() );
                        if ( index > -1 ) {
                            allWords.splice( index, 1 );
                        }
                    });

                    container.prepend( element );
                    allWords.push( word.toLowerCase() );
                }
            });

            input.value = '';
        } else {
            alert( 'Please enter words that are at least 3 characters long.' );
        }
    });

    // "Delete All" button event listener
    deleteAllButton.addEventListener( 'click', function() {
        allWords = [];
        while ( container.firstChild ) {
            container.removeChild( container.firstChild );
        }
    });

    // Add event listeners to existing remove buttons
    document.querySelectorAll( '.livcom_plugin_remove_word_button' ).forEach( function( button ) {
        button.addEventListener( 'click', function() {
            const element = button.parentNode;
            const word = element.querySelector( 'input[type="hidden"]' ).value;
            element.parentNode.removeChild( element );
            const index = allWords.indexOf( word.toLowerCase() );
            if ( index > -1 ) {
                allWords.splice( index, 1 );
            }
        });
    });
});

function updateToggleButtonState() {
    const guestNames = document.querySelectorAll( '.livcom_plugin_guest_name input[type="hidden"]' );
    const dummyUsers = document.querySelectorAll( '.livcom_plugin_dummy_user input[type="hidden"]' );
    const toggleButton = document.getElementById( 'livcom_user_priority' );

    // Enable or disable the toggle button based on the presence of guest names or dummy users
    if ( guestNames.length > 0 || dummyUsers.length > 0 ) {
        toggleButton.disabled = false;
    } else {
        toggleButton.disabled = true;
        toggleButton.checked = false;
    }
}

// Guest names management
document.addEventListener( 'DOMContentLoaded', ( event ) => {
    let allNames = [ ...guestNamesData.guestNames ]; // Initialize with names from the server
    const ITEMS_PER_PAGE = 100;
    let currentPage = 0;

    const container = document.getElementById( 'livcom_plugin_guest_names_container' );
    const deleteAllButton = document.getElementById( 'livcom_plugin_delete_all_button' );
    const counterElement = document.getElementById( 'livcom_plugin_guest_names_counter' );

    function attachRemoveListener( element, name ) {
        element.querySelector( '.livcom_plugin_remove_guest_button' ).addEventListener( 'click', function() {
            const index = allNames.indexOf( name );
            if ( index > -1 ) {
                allNames.splice( index, 1 );
            }
            element.parentNode.removeChild( element );
            showPage( currentPage ); // Refresh the page
            updateCounter(); // Update the counter
			updateToggleButtonState();
        });
    }

    function createNameElement( name ) {
        if ( !name ) return; // Skip if name is empty or undefined

        const element = document.createElement( 'div' );
        element.classList.add( 'livcom_plugin_guest_name', 'tag', 'is-medium', 'is-info' );
        element.innerHTML = `${name} <span class="livcom_plugin_remove_guest_button delete is-small">x</span><input type="hidden" name="livcom_plugin_guest_names[]" value="${name}">`;

        attachRemoveListener( element, name );
        return element;
    }

    Array.from( document.querySelectorAll( '.livcom_plugin_guest_name' ) ).forEach( el => {
        const name = el.querySelector( 'input[type="hidden"]' ).value;
        attachRemoveListener( el, name );
    });

    function updateContainer() {
        container.innerHTML = ''; // Clear the container
        allNames.forEach( name => {
            const element = createNameElement( name );
            if ( element ) {
                container.appendChild( element );
            }
        });

        const pagination = document.getElementById( 'pagination' );
        pagination.innerHTML = ''; // Clear pagination buttons
		updateToggleButtonState();
    }

    function showPage( pageNumber ) {
        const start = pageNumber * ITEMS_PER_PAGE;
        const end = Math.min( start + ITEMS_PER_PAGE, allNames.length );

        Array.from( container.children ).forEach( ( child, index ) => {
            child.style.display = ( index >= start && index < end ) ? '' : 'none';
        });

        const pagination = document.getElementById( 'pagination' );
        pagination.innerHTML = '';

        const totalPages = Math.ceil( allNames.length / ITEMS_PER_PAGE );
        if ( totalPages > 1 ) {
            for ( let i = 0; i < totalPages; i++ ) {
                const button = document.createElement( 'button' );
                button.innerText = i + 1;
                button.classList.add( 'button', 'is-info', 'is-small' );
                if ( i === pageNumber ) {
                    button.classList.add( 'is-active' );
                }

                button.addEventListener( 'click', function() {
                    currentPage = i;
                    showPage( i );
                });

                pagination.appendChild( button );
            }
        }
    }

    const input = document.getElementById( 'livcom_plugin_guest_name_input' );
    input.addEventListener( 'keydown', function( e ) {
        if ( e.key === 'Enter' ) {
            e.preventDefault();
            document.getElementById( 'livcom_plugin_add_guest_button' ).click();
        }
    });

    document.getElementById( 'livcom_plugin_add_guest_button' ).addEventListener( 'click', function() {
        const names = input.value.split( ',' ).map( name => name.trim() );

        names.forEach( name => {
            if ( !allNames.some( existingName => existingName.toLowerCase() === name.toLowerCase() ) && isValidUsername( name ) ) {
                allNames.unshift( name );
                const element = createNameElement( name );
                container.prepend( element );
            }
        });

        updateContainer();
        currentPage = 0;
        showPage( currentPage );
        input.value = '';
        updateCounter();
		updateToggleButtonState();
    });

    deleteAllButton.addEventListener( 'click', function() {
        allNames = [];
        updateContainer();
        updateCounter();
		updateToggleButtonState();
    });

    function isValidUsername( username ) {
        const valid = /^[a-zA-Z0-9-_ ]+$/;
        const len = username.length;

        if ( !valid.test( username ) ) {
            alert( 'Names can only contain alphanumeric characters, spaces, hyphens, and underscores.' );
            return false;
        } else if ( len < 3 ) {
            alert( 'Names must be at least 3 characters long.' );
            return false;
        } else if ( len > 60 ) {
            alert( 'Names must not exceed 60 characters.' );
            return false;
        }

        return true;
    }

    function updateCounter() {
        const namesCount = allNames.filter( name => name !== '' ).length;
        counterElement.textContent = `Names: ${namesCount}`;
    }

    showPage( currentPage );
    updateCounter();
});

// Dummy users management
document.addEventListener( 'DOMContentLoaded', ( event ) => {
    let allUsers = [ ...dummyUsersData.dummyUsers ]; // Initialize with users from the server
    const ITEMS_PER_PAGE = 100;
    let currentPage = 0;

    const container = document.getElementById( 'livcom_plugin_dummy_users_container' );
    const deleteAllButton = document.getElementById( 'livcom_plugin_delete_all_dummy_button' );
    const counterElement = document.getElementById( 'livcom_plugin_dummy_users_counter' );

    function attachRemoveListener( element, user ) {
        element.querySelector( '.livcom_plugin_remove_dummy_button' ).addEventListener( 'click', function() {
            const index = allUsers.indexOf( user );
            if ( index > -1 ) {
                allUsers.splice( index, 1 );
            }
            element.parentNode.removeChild( element );
            showPage( currentPage ); // Refresh the page
            updateCounter(); // Update the counter
			updateToggleButtonState();
        });
    }

    function createUserElement( user ) {
        if ( !user ) return; // Skip if user is empty or undefined

        const element = document.createElement( 'div' );
        element.classList.add( 'livcom_plugin_dummy_user', 'tag', 'is-medium', 'is-link' );
        element.innerHTML = `${user} <span class="livcom_plugin_remove_dummy_button delete is-small">x</span><input type="hidden" name="livcom_plugin_dummy_users[]" value="${user}">`;

        attachRemoveListener( element, user );
        return element;
    }

    Array.from( document.querySelectorAll( '.livcom_plugin_dummy_user' ) ).forEach( el => {
        const user = el.querySelector( 'input[type="hidden"]' ).value;
        attachRemoveListener( el, user );
    });

    function updateContainer() {
        container.innerHTML = ''; // Clear the container
        allUsers.forEach( user => {
            const element = createUserElement( user );
            if ( element ) {
                container.appendChild( element );
            }
        });

        const pagination = document.getElementById( 'dummy_pagination' );
        pagination.innerHTML = ''; // Clear pagination buttons
		updateToggleButtonState();
    }

    function showPage( pageNumber ) {
        const start = pageNumber * ITEMS_PER_PAGE;
        const end = Math.min( start + ITEMS_PER_PAGE, allUsers.length );

        Array.from( container.children ).forEach( ( child, index ) => {
            child.style.display = ( index >= start && index < end ) ? '' : 'none';
        });

        const pagination = document.getElementById( 'dummy_pagination' );
        pagination.innerHTML = '';

        const totalPages = Math.ceil( allUsers.length / ITEMS_PER_PAGE );
        if ( totalPages > 1 ) {
            for ( let i = 0; i < totalPages; i++ ) {
                const button = document.createElement( 'button' );
                button.innerText = i + 1;
                button.classList.add( 'button', 'is-link', 'is-small' );
                if ( i === pageNumber ) {
                    button.classList.add( 'is-active' );
                }

                button.addEventListener( 'click', function() {
                    currentPage = i;
                    showPage( i );
                });

                pagination.appendChild( button );
            }
        }
    }

    const input = document.getElementById( 'livcom_plugin_dummy_user_input' );
    input.addEventListener( 'keydown', function( e ) {
        if ( e.key === 'Enter' ) {
            e.preventDefault();
            document.getElementById( 'livcom_plugin_add_dummy_button' ).click();
        }
    });

    document.getElementById( 'livcom_plugin_add_dummy_button' ).addEventListener( 'click', async function() {
        this.classList.add( 'is-loading' );
        input.disabled = true;
        document.getElementById( 'livcom_plugin_username_check_message' ).style.display = 'block';
        document.getElementById( 'livcom_plugin_username_check_cancel' ).style.display = 'inline-block';

        let shouldCancel = false;
        document.getElementById( 'livcom_plugin_username_check_cancel' ).addEventListener( 'click', function() {
            shouldCancel = true;
        });

        let users = input.value.split( ',' ).map( user => user.trim().toLowerCase() );
        users = [ ...new Set( users ) ]; // Remove duplicates

        let isValid = true;
        for ( const user of users ) {
            if ( !isValidUsername( user ) ) {
                isValid = false;
                break;
            }
            if ( shouldCancel ) {
                break;
            }
        }

		if ( !shouldCancel && isValid ) {
			for ( const user of users ) {
				const usernameExists = await new Promise( resolve => {
					jQuery.post( ajaxurl, {
						action: 'livcom_check_username',
						username: user,
						livcom_settings_nonce: dummyUsersData.nonce
					}, function( response ) {
						resolve( response.success );
					});
				});

                if ( usernameExists && !allUsers.includes( user ) ) {
                    allUsers.unshift( user );
                    const element = createUserElement( user );
                    container.prepend( element );
                    updateContainer();
                    currentPage = 0;
                    showPage( currentPage );
                    input.value = '';
                    updateCounter();
					updateToggleButtonState();
                }

                if ( shouldCancel ) {
                    break;
                }
            }
        }

        this.classList.remove( 'is-loading' );
        input.disabled = false;
        document.getElementById( 'livcom_plugin_username_check_message' ).style.display = 'none';
        document.getElementById( 'livcom_plugin_username_check_cancel' ).style.display = 'none';
    });

    deleteAllButton.addEventListener( 'click', function() {
        allUsers = [];
        updateContainer();
        updateCounter();
		updateToggleButtonState();
    });

    function isValidUsername( username ) {
        const valid = /^[a-z0-9-_]+$/;
        if ( !valid.test( username ) ) {
            alert( 'Usernames can only contain lowercase alphanumeric characters, hyphens, and underscores.' );
            return false;
        } else if ( username.length < 3 ) {
            alert( 'Usernames must be at least 3 characters long.' );
            return false;
        } else if ( username.length > 60 ) {
            alert( 'Usernames must not exceed 60 characters.' );
            return false;
        }

        return true;
    }

    function updateCounter() {
        const usersCount = allUsers.filter( user => user !== '' ).length;
        counterElement.textContent = `Users: ${usersCount}`;
    }

    showPage( currentPage );
    updateCounter();
});
