/**
 * Font Awesome Icon Picker for Internal Link Clusters
 */
(function($) {
    'use strict';

    // Common Font Awesome 6 icons (Solid style) - curated list
    const faIcons = [
        'fa-home', 'fa-user', 'fa-users', 'fa-envelope', 'fa-phone', 'fa-calendar', 'fa-clock',
        'fa-search', 'fa-heart', 'fa-star', 'fa-bookmark', 'fa-share', 'fa-download',
        'fa-upload', 'fa-image', 'fa-video', 'fa-music', 'fa-file', 'fa-folder',
        'fa-edit', 'fa-trash', 'fa-save', 'fa-print', 'fa-copy', 'fa-link',
        'fa-arrow-left', 'fa-arrow-right', 'fa-arrow-up', 'fa-arrow-down',
        'fa-chevron-left', 'fa-chevron-right', 'fa-chevron-up', 'fa-chevron-down',
        'fa-angle-left', 'fa-angle-right', 'fa-angle-up', 'fa-angle-down',
        'fa-check', 'fa-times', 'fa-plus', 'fa-minus', 'fa-info', 'fa-question',
        'fa-exclamation', 'fa-warning', 'fa-ban', 'fa-lock', 'fa-unlock',
        'fa-key', 'fa-shield', 'fa-bell', 'fa-comment', 'fa-comments',
        'fa-thumbs-up', 'fa-thumbs-down', 'fa-hand', 'fa-handshake',
        'fa-shopping-cart', 'fa-credit-card', 'fa-dollar-sign', 'fa-tag',
        'fa-gift', 'fa-trophy', 'fa-medal', 'fa-certificate',
        'fa-graduation-cap', 'fa-book', 'fa-newspaper', 'fa-briefcase',
        'fa-building', 'fa-hospital', 'fa-school', 'fa-church',
        'fa-map', 'fa-map-marker', 'fa-globe', 'fa-compass',
        'fa-car', 'fa-plane', 'fa-train', 'fa-bus', 'fa-bicycle',
        'fa-wifi', 'fa-bluetooth', 'fa-power-off',
        'fa-cog', 'fa-wrench', 'fa-tools', 'fa-paint-brush',
        'fa-camera', 'fa-microphone', 'fa-headphones',
        'fa-play', 'fa-pause', 'fa-stop', 'fa-forward', 'fa-backward',
        'fa-volume-up', 'fa-volume-down', 'fa-volume-mute',
        'fa-bars', 'fa-list', 'fa-th', 'fa-grid',
        'fa-table', 'fa-columns', 'fa-external-link',
        'fa-code', 'fa-server', 'fa-database',
        'fa-cloud', 'fa-cloud-upload', 'fa-cloud-download',
        'fa-sun', 'fa-moon', 'fa-star-half',
        'fa-laptop', 'fa-desktop', 'fa-tablet', 'fa-mobile',
        'fa-keyboard', 'fa-mouse',
        'fa-users', 'fa-user-plus', 'fa-user-minus',
        'fa-store', 'fa-shopping-bag', 'fa-shopping-basket',
        'fa-box', 'fa-archive', 'fa-inbox',
        'fa-paper-plane', 'fa-envelope-open',
        'fa-rss', 'fa-podcast',
        'fa-tv', 'fa-film',
        'fa-filter', 'fa-sort',
        'fa-chart-line', 'fa-chart-bar', 'fa-chart-pie',
        'fa-calculator', 'fa-percent',
        'fa-id-card', 'fa-address-card',
        'fa-wallet', 'fa-money-bill',
        'fa-receipt', 'fa-file-invoice',
        'fa-truck', 'fa-warehouse',
        'fa-lightbulb', 'fa-plug',
        'fa-umbrella', 'fa-umbrella-beach',
        'fa-tree', 'fa-leaf',
        'fa-utensils', 'fa-coffee', 'fa-wine-glass',
        'fa-gamepad', 'fa-chess', 'fa-dice',
        'fa-running', 'fa-swimming', 'fa-biking',
        'fa-heartbeat', 'fa-stethoscope', 'fa-pills',
        'fa-rocket', 'fa-robot',
        'fa-magic', 'fa-wand-magic',
        'fa-pen', 'fa-feather',
        'fa-book-open', 'fa-scroll',
        'fa-gem', 'fa-diamond',
        'fa-crown', 'fa-ring'
    ];

    // Initialize icon picker
    function initIconPicker() {
        // Create icon picker modal if it doesn't exist
        if ($('#ilc-icon-picker-modal').length === 0) {
            createIconPickerModal();
        }

        // Add picker button to all icon name inputs
        $('.ilc-icon-name-input').each(function() {
            if (!$(this).next('.ilc-icon-picker-btn').length) {
                const $input = $(this);
                const $btn = $('<button type="button" class="button ilc-icon-picker-btn"><i class="fas fa-icons"></i> ' + ilcIconPickerL10n.pickIcon + '</button>');
                $btn.insertAfter($input);
                
                $btn.on('click', function(e) {
                    e.preventDefault();
                    openIconPicker($input);
                });
            }
        });
    }

    // Create icon picker modal
    function createIconPickerModal() {
        const modal = `
            <div id="ilc-icon-picker-modal" style="display: none;">
                <div class="ilc-icon-picker-overlay"></div>
                <div class="ilc-icon-picker-container">
                    <div class="ilc-icon-picker-header">
                        <h3>${ilcIconPickerL10n.selectIcon}</h3>
                        <button type="button" class="ilc-icon-picker-close">&times;</button>
                    </div>
                    <div class="ilc-icon-picker-search">
                        <input type="text" class="ilc-icon-picker-search-input" placeholder="${ilcIconPickerL10n.searchIcons}">
                    </div>
                    <div class="ilc-icon-picker-icons"></div>
                    <div class="ilc-icon-picker-footer">
                        <button type="button" class="button ilc-icon-picker-cancel">${ilcIconPickerL10n.cancel}</button>
                    </div>
                </div>
            </div>
        `;
        $('body').append(modal);
        renderIcons();
        bindModalEvents();
    }

    // Get correct icon class format - use FA 4/5 format (fa fa-icon) since that's what works
    function getIconClass(iconName) {
        // Ensure icon name has fa- prefix
        if (!iconName.startsWith('fa-')) {
            iconName = 'fa-' + iconName;
        }
        
        // Use Font Awesome 4/5 format (fa fa-icon) - this is what Visual Composer uses and works on this site
        return 'fa ' + iconName;
    }

    // Render icons in the modal
    function renderIcons(filter = '') {
        const $container = $('.ilc-icon-picker-icons');
        $container.empty();

        const filteredIcons = faIcons.filter(icon => 
            icon.toLowerCase().includes(filter.toLowerCase())
        );

        filteredIcons.forEach(icon => {
            const iconClass = getIconClass(icon);
            const displayName = icon.replace(/^fa-/, '');
            const $icon = $('<div class="ilc-icon-item" data-icon="' + icon + '">' +
                '<i class="' + iconClass + '"></i>' +
                '<span class="ilc-icon-name">' + displayName + '</span>' +
                '</div>');
            $container.append($icon);
        });

        // Bind click events
        $('.ilc-icon-item').on('click', function() {
            const iconName = $(this).data('icon');
            selectIcon(iconName);
        });
    }

    // Bind modal events
    function bindModalEvents() {
        // Close on overlay click
        $('.ilc-icon-picker-overlay, .ilc-icon-picker-close, .ilc-icon-picker-cancel').on('click', function() {
            closeIconPicker();
        });

        // Search functionality
        $('.ilc-icon-picker-search-input').on('keyup', function() {
            const filter = $(this).val();
            renderIcons(filter);
        });

        // Close on ESC key
        $(document).on('keydown', function(e) {
            if (e.keyCode === 27 && $('#ilc-icon-picker-modal').is(':visible')) {
                closeIconPicker();
            }
        });
    }

    // Open icon picker
    function openIconPicker($input) {
        $('#ilc-icon-picker-modal').data('target-input', $input).fadeIn(200);
        $('.ilc-icon-picker-search-input').val('').focus();
        
        // Render icons immediately
        renderIcons();
    }

    // Close icon picker
    function closeIconPicker() {
        $('#ilc-icon-picker-modal').fadeOut(200);
    }

    // Select icon
    function selectIcon(iconName) {
        const $input = $('#ilc-icon-picker-modal').data('target-input');
        if ($input && $input.length) {
            $input.val(iconName).trigger('change');
        }
        closeIconPicker();
    }

    // Initialize on document ready
    $(document).ready(function() {
        // Wait for Font Awesome to fully load
        if (document.readyState === 'complete') {
            setTimeout(function() {
                initIconPicker();
            }, 200);
        } else {
            $(window).on('load', function() {
                setTimeout(function() {
                    initIconPicker();
                }, 200);
            });
        }
    });

    // Re-initialize when new rows are added (for dynamic forms)
    $(document).on('DOMNodeInserted', function(e) {
        if ($(e.target).find('.ilc-icon-name-input').length) {
            initIconPicker();
        }
    });

})(jQuery);


