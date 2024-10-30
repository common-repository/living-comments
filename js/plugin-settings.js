// Notification close
document.addEventListener( 'DOMContentLoaded', () => {
    document.querySelectorAll( '.notification .delete' ).forEach( deleteButton => {
        const notification = deleteButton.parentNode;

        deleteButton.addEventListener( 'click', () => {
            notification.parentNode.removeChild( notification );
        });
    });
});

// Engagement Modes
document.addEventListener( 'DOMContentLoaded', function() {
    // Work with '.plan-item' elements
    document.querySelectorAll( '.plan-item' ).forEach( function( item ) {
        item.addEventListener( 'click', function() {
            const comprehensivePlanValue = 0;
            const selectedPlanValue = parseInt( this.getAttribute( 'data-value' ), 10 );

            // Clear selection from all plan items
            document.querySelectorAll( '.plan-item' ).forEach( function( i ) {
                i.classList.remove( 'selected-plan' );
                i.querySelector( '.dropdown-text' ).style.display = 'none';
                i.querySelector( '.dropdown-post-comment' ).style.display = 'none';
            });

            // Add 'selected-plan' class to clicked item
            this.classList.add( 'selected-plan' );
            document.getElementById( 'livcom_allocation' ).value = selectedPlanValue;

            // Move dropdown menu to the selected plan item
            const dropdown = document.getElementById( 'num-posts-dropdown' );
            const dropdownPlaceholder = this.querySelector( '.dropdown-placeholder' );
            dropdownPlaceholder.appendChild( dropdown );

            // Display dropdown and text for non-comprehensive plans
            if ( selectedPlanValue !== comprehensivePlanValue ) {
                dropdown.style.display = 'inline-block';
                this.querySelector( '.dropdown-text' ).style.display = 'inline-block';
                this.querySelector( '.dropdown-post-comment' ).style.display = 'inline-block';
            } else {
                dropdown.style.display = 'none';
                this.querySelector( '.dropdown-text' ).style.display = 'none';
                this.querySelector( '.dropdown-post-comment' ).style.display = 'none';
            }
        });
    });

    // Check for 'num-posts-dropdown' element before adding event listeners
    const dropdown = document.getElementById( 'num-posts-dropdown' );

    if ( dropdown ) {
        // Stop propagation of click event on the dropdown
        dropdown.addEventListener( 'click', function( event ) {
            event.stopPropagation();
        });

        // Update post count on change
        dropdown.addEventListener( 'change', function() {
            const selectedCount = this.value;
            document.querySelectorAll( '.latest-post-count' ).forEach( function( span ) {
                span.innerHTML = '<strong>' + selectedCount + '</strong>';
            });
        });
    } else {
        console.warn( "Element with ID 'num-posts-dropdown' was not found." );
    }
});

// Comment Ratio
jQuery( document ).ready( function( $ ) {
    function updateCommentMessage( value ) {
        let forceCommentMessage = '';
        let randomNatureMessage = 'Due to its random nature, the outcome may not align precisely with your selected ratio.';

        if ( value <= 0 ) {
            forceCommentMessage = 'Every great conversation starts somewhere. For blog posts without comments, we\'ll generate the first comment to introduce replies.';
            randomNatureMessage = '';
        }

        if ( value === 100 ) {
            randomNatureMessage = 'Tip: A blend of comments and replies can make for a more lively conversation.';
        }

        $( '#force-comment' ).text( forceCommentMessage );
        $( '#random-nature-message' ).text( randomNatureMessage );
    }

    $( '#livcom_plugin_comment_reply_ratio' ).on( 'input', function() {
        $( '#livcom_plugin_comment_reply_ratio_display' ).text( this.value );
        $( '#livcom_plugin_reply_ratio_display' ).text( 100 - this.value );
        updateCommentMessage( this.value );
    });

    $( 'form' ).on( 'submit', function( e ) {
        const minInput = $( '#livcom_plugin_frequency_min' ).val();
        const maxInput = $( '#livcom_plugin_frequency_max' ).val();

        if ( parseInt( maxInput, 10 ) < parseInt( minInput, 10 ) ) {
            e.preventDefault();
            alert( 'Max value cannot be lower than Min value.' );
        }
    });

    $( '#livcom_plugin_frequency' ).on( 'input', function() {
        $( '#livcom_plugin_frequency_display' ).text( this.value );
    });

    // Call the function on page load
    updateCommentMessage( $( '#livcom_plugin_comment_reply_ratio' ).val() );
});

// Category Select
window.onunload = function() {};

function initializeCategoryCheckboxes() {
    const form = document.querySelector( 'form' );
    const allCheckbox = document.getElementById( 'livcom_plugin_category_all' );
    const categoryCheckboxes = document.querySelectorAll( "input[name='livcom_plugin_category_selected[]']" );

    // Function to check if all checkboxes are selected
    const areAllCategoriesChecked = function() {
        return Array.from( categoryCheckboxes ).every( checkbox => checkbox.checked );
    };

    // This function sets the "All Categories" checkbox to the correct state
    const setAllCategoriesCheckboxState = function() {
        allCheckbox.checked = areAllCategoriesChecked();
    };

    // Call the initialization function
    setAllCategoriesCheckboxState();

    // If "All" checkbox is changed, update all category checkboxes
    allCheckbox.addEventListener( 'change', () => {
        Array.from( categoryCheckboxes ).forEach( checkbox => {
            checkbox.checked = allCheckbox.checked;
        });
    });

    // If any category checkbox is changed, update the "All" checkbox status
    Array.from( categoryCheckboxes ).forEach( checkbox => {
        checkbox.addEventListener( 'change', () => {
            allCheckbox.checked = checkbox.checked ? areAllCategoriesChecked() : false;
        });
    });

    // Before form submission, validate if at least one category is selected
    form.addEventListener( 'submit', e => {
        if ( !allCheckbox.checked && !Array.from( categoryCheckboxes ).some( checkbox => checkbox.checked ) ) {
            e.preventDefault();
            alert( 'Please select at least one category.' );
        }
    });
}

document.addEventListener( 'DOMContentLoaded', initializeCategoryCheckboxes );
window.addEventListener( 'load', initializeCategoryCheckboxes );

// Comment Reply Length
document.addEventListener( 'DOMContentLoaded', () => {
    const buttons = {
        shortLengthButton: document.getElementById( 'shortLengthButton' ),
        mediumLengthButton: document.getElementById( 'mediumLengthButton' ),
        longLengthButton: document.getElementById( 'longLengthButton' )
    };
    const wordLengthInput = document.getElementById( 'livcom_plugin_word_length' );
    const form = document.querySelector( 'form' );

    const updateWordLength = () => {
        const selectedValues = [];
        if ( !buttons.shortLengthButton.classList.contains( 'is-light' ) ) selectedValues.push( 1 );
        if ( !buttons.mediumLengthButton.classList.contains( 'is-light' ) ) selectedValues.push( 2 );
        if ( !buttons.longLengthButton.classList.contains( 'is-light' ) ) selectedValues.push( 3 );

        wordLengthInput.value = selectedValues.join( ',' );
    };

    const toggleButton = ( button ) => {
        button.classList.toggle( 'is-light' );
        updateWordLength();
    };

    const initializeButtonStates = () => {
        const selectedValues = wordLengthInput.value.split( ',' ).map( Number );
        if ( selectedValues.includes( 1 ) ) toggleButton( buttons.shortLengthButton );
        if ( selectedValues.includes( 2 ) ) toggleButton( buttons.mediumLengthButton );
        if ( selectedValues.includes( 3 ) ) toggleButton( buttons.longLengthButton );
    };

    Object.values( buttons ).forEach( button => {
        button.addEventListener( 'click', () => {
            toggleButton( button );
        });
    });

    // Add validation to the form submission
    form.addEventListener( 'submit', e => {
        if ( wordLengthInput.value === '' ) {
            alert( 'Please select at least one word length option.' );
            e.preventDefault();
        }
    });

    // Initialize the button states
    initializeButtonStates();

    // Initialize the hidden input value based on the current button states
    updateWordLength();
});

// Tone selection buttons
(function( $ ) {
    $( document ).ready( function() {
        // Initialize data-base-class attribute to store the initial base color class
        $( '.tone-button' ).each( function() {
            const colorClasses = [ 'is-primary', 'is-warning', 'is-danger' ];
            const baseClass = colorClasses.find( c => $( this ).hasClass( c ) );
            $( this ).attr( 'data-base-class', baseClass );
        });

        // Function to check the initial state of buttons and update "Select All" button
        function checkInitialButtonState() {
            const allButtons = $( '.tone-button' );
            const selectAllButton = $( '#livcom_plugin_tone_all' );

            // Check if all buttons are selected
            const allSelected = allButtons.length === allButtons.filter( '.is-light.is-active' ).length;

            // Update "Select All" button class
            selectAllButton.toggleClass( 'is-primary is-light is-active', allSelected );

            // Update individual tone buttons based on whether they are selected
            allButtons.each( function() {
                const baseClass = $( this ).attr( 'data-base-class' );
                const isSelected = $( this ).hasClass( 'is-light is-active' );

                if ( isSelected ) {
                    $( this ).addClass( `${baseClass} is-light` );
                } else {
                    $( this ).removeClass( `${baseClass} is-light is-active` );
                }
            });
        }

        // Event listener for "Save All Changes" button
        $( '#submit' ).on( 'click', function( event ) {
            const selectedTones = $( '.tone-button.is-light.is-active' );

            if ( selectedTones.length === 0 ) {
                event.preventDefault();
                alert( 'Please select at least one tone.' );
            }
        });

        checkInitialButtonState();

        // Event listener for tone buttons
        $( '.tone-button' ).on( 'click', function() {
            const selectAllButton = $( '#livcom_plugin_tone_all' );
            const baseClass = $( this ).attr( 'data-base-class' );
            
            // Toggle classes
            $( this ).toggleClass( `${baseClass} is-light is-active` );

            // Update "Select All" button class
            const allButtons = $( '.tone-button' );
            const allSelected = allButtons.length === allButtons.filter( '.is-light.is-active' ).length;
            selectAllButton.toggleClass( 'is-primary is-light is-active', allSelected );

            updateSelectedTones();
            $( this ).blur();
        });

        // Event listener for "Select All" button
        $( '#livcom_plugin_tone_all' ).on( 'click', function() {
            const allButtons = $( '.tone-button' );
            const selectAllButton = $( this );
            const isAllSelected = allButtons.hasClass( 'is-light is-active' );

            // Toggle selection state of all buttons
            allButtons.each( function() {
                const baseClass = $( this ).attr( 'data-base-class' );
                $( this ).toggleClass( `${baseClass} is-light is-active`, !isAllSelected );
            });

            // Update "Select All" button class
            selectAllButton.toggleClass( 'is-primary is-light is-active', !isAllSelected );

            updateSelectedTones();
            $( this ).blur();
        });

        function updateSelectedTones() {
            // Clear out existing hidden fields
            $( '#livcom_plugin_tones_container' ).empty();

            // Get selected tones
            const selectedTones = $( '.tone-button.is-light.is-active' ).map( function() {
                return $( this ).data( 'tone' );
            }).get();

            // Loop through selected tones
            for ( let i = 0; i < selectedTones.length; i++ ) {
                // Append a new hidden input for each selected tone
                $( '#livcom_plugin_tones_container' ).append( `<input type="hidden" name="livcom_plugin_tones_selected[]" value="${selectedTones[i]}">` );
            }
        }

        // Call the function to check initial button state
        checkInitialButtonState();
        updateSelectedTones();

    });
})( jQuery );

// Daily estimates
const calculateCommentsPerDay = () => {
    const frequencyMin = document.getElementById( 'livcom_plugin_frequency_min' ).value;
    const frequencyMax = document.getElementById( 'livcom_plugin_frequency_max' ).value;

    const averageFrequency = ( parseInt( frequencyMin, 10 ) + parseInt( frequencyMax, 10 ) ) / 2;
    const commentsPerDay = Math.round( 1440 / averageFrequency );

    let recommendedPlan = '';
    if ( commentsPerDay >= 100 ) {
        recommendedPlan = 'Elite 9000';
    } else if ( commentsPerDay >= 30 ) {
        recommendedPlan = 'Gold 3000';
    } else if ( commentsPerDay >= 10 ) {
        recommendedPlan = 'Standard 900';
    } else {
        recommendedPlan = 'Lite 300';
    }

    document.getElementById( 'commentsPerDay' ).innerHTML = `Daily Estimates: <strong>~${commentsPerDay}</strong> comments/replies per day`;
    document.getElementById( 'recommendedPlan' ).innerHTML = `Recommended Plan: <strong>${recommendedPlan}</strong>`;
};

document.getElementById( 'livcom_plugin_frequency_min' ).addEventListener( 'change', calculateCommentsPerDay );
document.getElementById( 'livcom_plugin_frequency_max' ).addEventListener( 'change', calculateCommentsPerDay );

calculateCommentsPerDay();

// Custom domain
const toggleDomainInput = ( value ) => {
    const customDomainInput = document.getElementById( 'livcom_custom_domain' );
    if ( value === 'custom' ) {
        customDomainInput.style.display = 'inline-block';
        customDomainInput.style.width = '550px';
    } else {
        customDomainInput.style.display = 'none';
    }
};

window.onload = () => {
    toggleDomainInput( document.getElementById( 'livcom_email_domain_option' ).value );
};

document.querySelector( 'form' ).addEventListener( 'submit', ( e ) => {
    const customDomainInput = document.getElementById( 'livcom_custom_domain' );
    const domainOption = document.getElementById( 'livcom_email_domain_option' );

    if ( domainOption.value === 'custom' ) {
        if ( customDomainInput.value.trim() === '' ) {
            alert( 'Custom domain field is empty.' );
            e.preventDefault();
        } else {
            const domainRegEx = /^([a-z0-9]([a-z0-9\-]{0,61}[a-z0-9])?\.)+[a-z0-9]{2,6}$/;
            if ( !domainRegEx.test( customDomainInput.value.trim().toLowerCase() ) ) {
                alert( 'Please enter a valid domain format.' );
                e.preventDefault();
            }
        }
    }
});

// FAQ Toggle
document.getElementById( 'toggleAll' ).addEventListener( 'click', function() {
    const faqs = document.querySelectorAll( '.faq-answer' );
    const chevrons = document.querySelectorAll( '.faq-chevron i' );
    let allExpanded = true;

    faqs.forEach( faq => {
        if ( faq.classList.contains( 'is-hidden' ) ) {
            allExpanded = false;
        }
    });

    if ( allExpanded ) {
        faqs.forEach( faq => {
            faq.classList.add( 'is-hidden' );
        });
        chevrons.forEach( chevron => {
            chevron.classList.replace( 'la-angle-up', 'la-angle-down' );
        });
        this.innerHTML = "Expand all <i class='las la-expand'></i>";
    } else {
        faqs.forEach( faq => {
            faq.classList.remove( 'is-hidden' );
        });
        chevrons.forEach( chevron => {
            chevron.classList.replace( 'la-angle-down', 'la-angle-up' );
        });
        this.innerHTML = "Collapse all <i class='las la-arrows-alt-v'></i>"; 
    }
});

function toggleContent( index ) {
    const answer = document.querySelectorAll( '.faq-answer' )[ index ];
    const chevron = document.querySelector( '#faq-chevron-' + index + ' i' );

    answer.classList.toggle( 'is-hidden' );
    chevron.classList.toggle( 'la-angle-down' );
    chevron.classList.toggle( 'la-angle-up' );
}

// Delete Comment Handling
document.addEventListener( 'DOMContentLoaded', () => {
    let selectedCommentId = null;

    document.querySelectorAll('.delete-comment-btn').forEach(button => {
        button.addEventListener('click', () => {
            const classList = button.className.split(/\s+/);
            const commentIdClass = classList.find(className => className.startsWith('comment-id-'));
            selectedCommentId = commentIdClass ? commentIdClass.split('-').pop() : null;

            if (selectedCommentId) {
                document.querySelector('.delete-comment-modal').classList.add('is-active');
            }
        });
    });

    document.querySelectorAll( '.cancel-delete-comment-btn, .delete-comment-modal-close' ).forEach( button => {
        button.addEventListener( 'click', () => {
            document.querySelector( '.delete-comment-modal' ).classList.remove( 'is-active' );
            selectedCommentId = null;
        });
    });

    document.querySelector( '.confirm-delete-comment-btn' ).addEventListener( 'click', () => {
        if ( selectedCommentId ) {
            const confirmDeleteButton = document.querySelector( '.confirm-delete-comment-btn' );
            const cancelDeleteButton = document.querySelector( '.cancel-delete-comment-btn' );

            confirmDeleteButton.classList.add( 'is-loading' );
            cancelDeleteButton.setAttribute( 'disabled', true );

			const postData = new URLSearchParams({
				'action': 'livcom_delete_comment',
				'comment_id': selectedCommentId,
				'livcom_settings_nonce': deleteCommentData.nonce
			});

            fetch( deleteCommentData.deleteCommentURL, {
                method: 'POST',
                body: postData
            })
            .then( response => response.json() )
            .then( response => {
                if ( response.success ) {
                    window.location.href = '?page=living-comments-history&comment-deleted=true';
                } else {
                    confirmDeleteButton.classList.remove( 'is-loading' );
                    cancelDeleteButton.removeAttribute( 'disabled' );
                    alert( 'Failed to delete the comment.' );
                }
            });
        }
    });

    // Delete notifications
    document.querySelectorAll( '.notification .delete' ).forEach( button => {
        button.addEventListener( 'click', () => {
            button.parentNode.remove();
        });
    });

    // Remove specific URL parameters after showing the notice
    const newUrl = new URL( window.location.href );
    ['comment-deleted', 'settings-updated'].forEach( param => {
        if ( newUrl.searchParams.has( param ) ) {
            newUrl.searchParams.delete( param );
        }
    });
    window.history.replaceState( {}, document.title, newUrl.toString() );
});

// Report Comment Handling
document.addEventListener( 'DOMContentLoaded', () => {
    let selectedUnhappyCommentId = null;

    document.querySelectorAll('.unhappy-comment-btn').forEach(button => {
        button.addEventListener('click', () => {
            const classList = button.className.split(/\s+/);
            const commentIdClass = classList.find(className => className.startsWith('comment-id-'));
            selectedUnhappyCommentId = commentIdClass ? commentIdClass.split('-').pop() : null;

            if (selectedUnhappyCommentId) {
                document.querySelector('.unhappy-comment-modal').classList.add('is-active');
            }
        });
    });

    document.querySelectorAll( '.cancel-unhappy-comment-btn, .unhappy-comment-modal-close' ).forEach( button => {
        button.addEventListener( 'click', () => {
            document.querySelector( '.unhappy-comment-modal' ).classList.remove( 'is-active' );
            selectedUnhappyCommentId = null;
        });
    });

    document.getElementById( 'report-refresh-button' ).addEventListener( 'click', () => {
        window.location.href = '?page=living-comments-history';
    });

    document.querySelector( '.confirm-unhappy-comment-btn' ).addEventListener( 'click', () => {
        if ( selectedUnhappyCommentId ) {
            const confirmButton = document.querySelector( '.confirm-unhappy-comment-btn' );
            const cancelButton = document.querySelector( '.cancel-unhappy-comment-btn' );

            confirmButton.classList.add( 'is-loading' );
            cancelButton.setAttribute( 'disabled', true );

            const postData = new URLSearchParams({
                'action': 'livcom_send_unhappy_comment',
                'comment_id': selectedUnhappyCommentId,
				'livcom_settings_nonce': unhappyCommentData.nonce
            });

            fetch( unhappyCommentData.ajaxURL, {
                method: 'POST',
                body: postData
            })
            .then( response => response.json() )
            .then( response => {
                if ( response.success ) {
                    document.getElementById( 'report-success-modal' ).classList.add( 'is-active' );
                } else {
                    alert( response.data );
                }

                confirmButton.classList.remove( 'is-loading' );
                cancelButton.removeAttribute( 'disabled' );
                document.querySelector( '.unhappy-comment-modal' ).classList.remove( 'is-active' );
                selectedUnhappyCommentId = null;
            });
        }
    });
});