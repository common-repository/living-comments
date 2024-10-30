// jQuery section for updating selected plan details
jQuery( document ).ready( function( $ ) {
    let selectedPlanId = null;

    function lcGetPlanClass( plan ) {
        switch ( plan ) {
            case 'Lite 300':
                return 'tag is-size-6 is-success';
            case 'Standard 900':
                return 'tag is-size-6 is-info';
            case 'Gold 3000':
                return 'tag is-size-6 is-gold';
            case 'Elite 9000':
                return 'tag is-size-6 is-black';
            default:
                return 'tag is-size-6 is-link is-light';
        }
    }

    $( '.dropdown-item' ).on( 'click', function() {
        selectedPlanId = $( this ).data( 'plan-id' );

        const lcPlans = JSON.parse( lcSubscriptionData.lc_plans );
        const selectedPlanName = lcPlans[selectedPlanId]['name'];
        const selectedPlanPrice = lcPlans[selectedPlanId]['price'];
        const selectedPlanDescription = $( this ).data( 'plan-description' );
        const today = lcSubscriptionData.today;
        const nextMonth = lcSubscriptionData.nextMonth;

        const planClass = lcGetPlanClass( selectedPlanName );

        $( '#selected-plan-name' )
            .html( selectedPlanName )
            .removeClass()
            .addClass( planClass );

        $( '#selected-plan-description' ).html( selectedPlanDescription + ' comments per month' );
        $( '#selected-plan-price' ).html( 'Total: $' + selectedPlanPrice + ' per month' );

        const selectedPlanCommentsPerMonth = $( this ).data( 'plan-description' );
        const costPerComment = parseFloat( ( selectedPlanPrice / selectedPlanCommentsPerMonth ).toFixed( 2 ) );

        $( '#selected-plan-cpc' ).html( 'Rate: $' + costPerComment + ' per comment' );
        $( '#selected-billing-cycle' ).html( today + ' - ' + nextMonth );
    } );
});

// Native JavaScript section for PayPal buttons
document.addEventListener( "DOMContentLoaded", function() {
    function clearPayPalButton( containerId ) {
        const container = document.getElementById( containerId );
        if ( container ) {
            container.innerHTML = '';
        }
    }

    function renderPayPalButton( planID, containerId ) {
        clearPayPalButton( containerId );
        paypal.Buttons({
            style: {
                shape: 'rect',
                color: 'gold',
                layout: 'vertical',
                label: 'paypal'
            },
            createSubscription: function( data, actions ) {
                return actions.subscription.create({
                    'plan_id': 'P-' + planID,
                    'custom_id': paypal_params.custom_id,
                    'application_context': {
                        'shipping_preference': 'NO_SHIPPING'
                    }
                });
            },
            onApprove: function( data, actions ) {
                // Open the success modal
                const successModal = document.getElementById( 'success-modal' );
                if ( successModal ) {
                    successModal.classList.add( 'is-active' );
                }

                // Disable the refresh button for 60 seconds
                const refreshButton = document.getElementById( 'refresh-button' );
                if ( refreshButton ) {
                    refreshButton.setAttribute( 'disabled', 'disabled' );
                    let counter = 60;
                    const interval = setInterval( function() {
                        counter--;
                        if ( counter < 0 ) {
                            clearInterval( interval );
                            refreshButton.removeAttribute( 'disabled' );
                        }
                    }, 1000 );
                }

                // Countdown logic
                const countdownElem = document.getElementById( 'countdown' );
                const spinner = document.getElementById( 'icon-spinner' );
                let counter = 60;

                if ( spinner ) {
                    spinner.classList.add( 'la-spin' ); // Start spinning
                }

                const interval = setInterval( function() {
                    countdownElem.textContent = counter;
                    counter--;

                    if ( counter < 0 ) {
                        clearInterval( interval );
                        
                        if ( spinner ) {
                            spinner.classList.remove( 'la-spin' ); // Stop spinning
                            spinner.remove(); // Remove the spinner element from the DOM
                        }
                        
                        countdownElem.textContent = ''; // Clear countdown
                    }
                }, 1000 );

                // Get the selected plan name
                let selectedPlanName = document.getElementById( 'selected-plan-name' ).textContent;

                // Send an AJAX request to update the user plan
                jQuery.ajax({
                    type: 'POST',
                    url: lcSubscriptionData.admin_ajax_url,
                    data: {
                        action: 'livcom_update_user_plan',
                        new_plan: selectedPlanName,
						livcom_settings_nonce: lcSubscriptionData.nonce
                    },
                    success: function( response ) {
                        if ( response.success ) {
                            console.log( response.data.message );
                        } else {
                            console.error( response.data.message );
                        }
                    }
                });
            },
            onError: function( err ) {
                // Error Modal
                const errorModal = document.getElementById( 'error-modal' );
                if ( errorModal ) {
                    errorModal.classList.add( 'is-active' );
                }
                console.error( err );
            }
        }).render( '#' + containerId );
    }

    const dropdownItems = document.querySelectorAll( '.dropdown-item' );
    dropdownItems.forEach( function( item ) {
        item.addEventListener( 'click', function( e ) {
            e.preventDefault();

            const planID = e.currentTarget.getAttribute( 'data-container-id' ).split( '-' ).pop();
            const containerId = e.currentTarget.getAttribute( 'data-container-id' );

            const modal = document.getElementById( 'change-plan' );
            modal.classList.add( 'is-active' );

            renderPayPalButton( planID, containerId );
        } );
    } );

    const closeModal = document.querySelector( '.modal-close' );
    if ( closeModal ) {
        closeModal.addEventListener( 'click', function() {
            const modal = document.getElementById( 'change-plan' );
            modal.classList.remove( 'is-active' );

            dropdownItems.forEach( function( item ) {
                const containerId = item.getAttribute( 'data-container-id' );
                clearPayPalButton( containerId );
            } );
        } );
    }

    const refreshButton = document.getElementById( 'refresh-button' );
    if ( refreshButton ) {
        refreshButton.addEventListener( 'click', function() {
            this.classList.add( 'is-loading' );

            jQuery.ajax({
                type: 'POST',
                url: lcSubscriptionData.admin_ajax_url,
                data: {
                    action: 'livcom_refresh_actions'
                },
                complete: function() {
                    location.reload();
                }
            });
        } );
    }

    const refreshErrorButton = document.getElementById( 'refresh-error-button' );
    if ( refreshErrorButton ) {
        refreshErrorButton.addEventListener( 'click', function() {
            this.classList.add( 'is-loading' );
            location.reload();
        } );
    }

    const paymentsClose = document.getElementById( 'payments-close' );
    if ( paymentsClose ) {
        paymentsClose.addEventListener( 'click', function() {
            const modal = document.getElementById( 'change-plan' );
            modal.classList.remove( 'is-active' );

            dropdownItems.forEach( function( item ) {
                const containerId = item.getAttribute( 'data-container-id' );
                clearPayPalButton( containerId );
            } );
        } );
    }
});

document.addEventListener( 'DOMContentLoaded', () => {
    const modal = document.querySelector( '#cancel-subscription-modal' );
    const cancelSubscriptionButton = document.querySelector( '#cancel-subscription' );
    const closeModalButton = modal.querySelector( '.delete' );
    const keepPlanButton = modal.querySelector( '#keep-plan' );
    const confirmCancelButton = modal.querySelector( '#confirm-cancel' );
    const cancelSuccessModal = document.querySelector( '#cancel-success-modal' );
    const cancelRefreshButton = cancelSuccessModal.querySelector( '#cancel-refresh-button' );

    let subscriptionId;

    if ( cancelSubscriptionButton ) {
        cancelSubscriptionButton.addEventListener( 'click', () => {
            subscriptionId = cancelSubscriptionButton.dataset.subscriptionId;
            modal.classList.add( 'is-active' );
        } );
    }

    closeModalButton.addEventListener( 'click', () => {
        modal.classList.remove( 'is-active' );
    } );

    keepPlanButton.addEventListener( 'click', () => {
        modal.classList.remove( 'is-active' );
    } );

    confirmCancelButton.addEventListener( 'click', () => {
        confirmCancelButton.classList.add( 'is-loading' );
        keepPlanButton.classList.add( 'is-loading' );

        const data = {
            'action': 'livcom_cancel_subscription',
            'subscriptionId': subscriptionId,
			'livcom_settings_nonce': lcSubscriptionData.nonce
        };

        fetch( lcSubscriptionData.admin_ajax_url, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded'
            },
            body: new URLSearchParams( data )
        } )
        .then( response => response.json() )
        .then( response => {
            if ( response.error ) {
				window.location.href = '?page=living-comments-billing';
            } else {
                modal.classList.remove( 'is-active' );
                cancelSuccessModal.classList.add( 'is-active' );
            }

            confirmCancelButton.classList.remove( 'is-loading' );
            keepPlanButton.classList.remove( 'is-loading' );
        } );
    } );

    cancelRefreshButton.addEventListener( 'click', () => {
        cancelRefreshButton.classList.add( 'is-loading' );
        fetch( lcSubscriptionData.admin_ajax_url, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8'
            },
            body: new URLSearchParams({
                action: 'livcom_refresh_actions'
            })
        } )
        .then( response => {
            if ( !response.ok ) {
			window.location.href = '?page=living-comments-billing';
            }
            return response.text();
        } )
        .then( data => {
			window.location.href = '?page=living-comments-billing';
        } )
        .catch( error => {
			window.location.href = '?page=living-comments-billing';
        } );
    } );
} );


