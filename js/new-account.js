document.addEventListener( 'DOMContentLoaded', function() {
    const setupButton = document.querySelector( '#setupButton' );
    const modal = document.querySelector( '#modal' );
    const modalClose = document.querySelector( '.modal-close' );
    const cancelButton = document.querySelector( '#cancelButton' );
    const emailField = document.querySelector( '#email' );
    const countryField = document.querySelector( '#countrySelect' );
    const websiteCategoryField = document.querySelector( '#websiteCategory' );
    const newsletterField = document.querySelector( '#newsletter' );
    const signupButton = document.querySelector( '#signupButton' );
    const adminSelectorField = document.querySelector( '#administratorSelect' );

    const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;

    if ( setupButton ) {
        setupButton.addEventListener( 'click', function( event ) {
            event.preventDefault();
            modal.classList.add( 'is-active' );
        } );
    }

    if ( modalClose ) {
        modalClose.addEventListener( 'click', function( event ) {
            event.preventDefault();
            modal.classList.remove( 'is-active' );
        } );
    }

    if ( cancelButton ) {
        cancelButton.addEventListener( 'click', function( event ) {
            event.preventDefault();
            modal.classList.remove( 'is-active' );
        } );
    }

    if ( signupButton ) {
        signupButton.addEventListener( 'click', function( event ) {
            event.preventDefault();
            signupButton.classList.add( 'is-loading' );

            if ( !emailRegex.test( emailField.value ) ) {
                alert( 'Please enter a valid email.' );
                signupButton.classList.remove( 'is-loading' );
                return;
            }

            const websiteCategory = websiteCategoryField.value;
            const newsletter = newsletterField.checked ? 1 : 0;

            fetch( lcNewAccountData.adminAjaxUrl, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
				body: new URLSearchParams({
					'action': 'livcom_send_user_data',
					'email': emailField.value,
					'newsletter': newsletter,
					'country': countryField.value,
					'website_category': websiteCategory,
					'administrator': adminSelectorField.value,
					'livcom_settings_nonce': lcNewAccountData.nonce
				} )
            } )
            .then( response => response.json() )
            .then( data => {
                const modalContent = document.querySelector( '.modal-content' );
                if ( data && data.success ) {
                    modalContent.innerHTML = `
                        <header class="modal-card-head">
                            <p class="modal-card-title"><i class="las la-check-circle"></i> Welcome to Living Comments!</p>
                        </header>
                        <section class="modal-card-body">
                            <p class="is-size-6">Kindly refresh the page to view your account and start generating comments for your blog.</p>
                        </section>
                        <footer class="modal-card-foot">
                            <button class="button is-primary" id="refreshButton">Refresh</button>
                        </footer>`;

                    const refreshButton = document.querySelector( '#refreshButton' );
                    if ( refreshButton ) {
                        refreshButton.addEventListener( 'click', function() {
                            location.reload();
                        } );
                    }
                } else {
                    alert( 'Something went wrong. Please try again later or contact support.' );
                }
                signupButton.classList.remove( 'is-loading' );
            } )
            .catch( ( error ) => {
                console.error( 'Error:', error );
                signupButton.classList.remove( 'is-loading' );
            } );
        } );
    }
} );
