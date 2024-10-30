// Tabs Handler
document.addEventListener( 'DOMContentLoaded', function() {
    // Check if the script is within the context of the specific form
    const form = document.getElementById( 'lc-plugin-settings' );
    if ( !form ) return;

    const tabs = document.querySelectorAll( '.tabs li' );
    const tabContents = document.querySelectorAll( '.tab-content .tab-pane' );

    const tabIdToPageMap = {
        'overview-tab': 'living-comments',
        'settings-tab': 'living-comments-settings',
        'user-tab': 'living-comments-user',
        'history-tab': 'living-comments-history',
        'billing-tab': 'living-comments-billing',
        'faq-tab': 'living-comments-faq'
    };

    function activateTab( tabIndex ) {
        if ( tabs.length === 0 || tabContents.length === 0 ) {
            return;
        }

        tabs.forEach( function( tab, idx ) {
            tab.classList.remove( 'is-active' );
            tabContents[idx].style.display = 'none';
        });

        tabs[tabIndex].classList.add( 'is-active' );
        tabContents[tabIndex].style.display = 'block';
        sessionStorage.setItem( 'lastActiveTab', tabs[tabIndex].id );
        updateUrlWithTab( tabs[tabIndex].id );
    }

    function findTabIndex( id ) {
        return Array.from( tabs ).findIndex( function( tab ) {
            return tab.id === id;
        });
    }

    function updateUrlWithTab( tabId ) {
        const pageParam = tabIdToPageMap[tabId] || 'living-comments';
        const newUrl = new URL( window.location.href );
        newUrl.searchParams.set( 'page', pageParam );
        window.history.replaceState( null, null, newUrl );
    }

    const urlParams = new URLSearchParams( window.location.search );
    const page = urlParams.get( 'page' );
    let activeTabId = tabIdToPageMap[page];

    if ( !activeTabId ) {
        activeTabId = sessionStorage.getItem( 'lastActiveTab' ) || 'overview-tab';
    }

    const tabIndexToActivate = findTabIndex( activeTabId );
    activateTab( tabIndexToActivate >= 0 ? tabIndexToActivate : 0 );

    tabs.forEach( function( tab, index ) {
        tab.addEventListener( 'click', function( event ) {
            event.preventDefault();
            activateTab( index );
        });
    });
});

// Menu Handler
document.addEventListener( 'DOMContentLoaded', function() {
    const basePageParam = 'living-comments';
    const pageToTabMap = {
        'living-comments': 'overview-tab',
        'living-comments-settings': 'settings-tab',
        'living-comments-user': 'user-tab',
        'living-comments-history': 'history-tab',
        'living-comments-billing': 'billing-tab',
        'living-comments-faq': 'faq-tab'
    };

    const updateTabSelection = ( tabId ) => {
        sessionStorage.setItem( 'lastActiveTab', tabId );
    };

    // Event delegation for menu items
    const adminMenu = document.querySelector( '#adminmenu' );
    if ( adminMenu ) {
        adminMenu.addEventListener( 'click', ( event ) => {
            const menuItem = event.target.closest( 'a[href*="admin.php?page="]' );
            if ( menuItem ) {
                // Extract the page parameter from the href attribute
                const urlParams = new URLSearchParams( menuItem.search );
                const page = urlParams.get( 'page' );
                if ( pageToTabMap[page] ) {
                    updateTabSelection( pageToTabMap[page] );
                } else if ( !menuItem.href.includes( `admin.php?page=${basePageParam}` ) ) {
                    sessionStorage.removeItem( 'lastActiveTab' );
                }
            }
        });
    }

    const urlParams = new URLSearchParams( window.location.search );
    const page = urlParams.get( 'page' );
    const activeTabId = pageToTabMap[page] || sessionStorage.getItem( 'lastActiveTab' ) || 'overview-tab';
    updateTabSelection( activeTabId );
});