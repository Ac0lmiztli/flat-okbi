jQuery(document).ready(function($) {
    const $filtersForm = $('#fok-filters-form');
    if (!$filtersForm.length) return;

    const $propertyTypeCheckboxes = $filtersForm.find('input[name="property_types[]"]');
    const $roomFilter = $filtersForm.find('[data-dependency="apartment"]');

    function updateRoomFilterForFix() {
        const isApartmentChecked = $propertyTypeCheckboxes.filter('[value="apartment"]').is(':checked');
        if (isApartmentChecked) {
            $roomFilter.removeClass('fok-disabled').show();
        } else {
            // Add disabled class, and forcefully override any 'display: none' from other scripts
            $roomFilter.addClass('fok-disabled').css('display', 'block'); 
        }
    }

    // Use a MutationObserver to watch for changes on the element's style attribute
    const observer = new MutationObserver(function(mutations) {
        mutations.forEach(function(mutation) {
            if (mutation.type === 'attributes' && mutation.attributeName === 'style') {
                const display = $roomFilter.css('display');
                if (display === 'none') {
                    // If another script tries to hide it, we force it back to block
                    updateRoomFilterForFix();
                }
            }
        });
    });

    if ($roomFilter.length) {
        observer.observe($roomFilter[0], { attributes: true });
    }

    // Also run on checkbox change
    $propertyTypeCheckboxes.on('change', updateRoomFilterForFix);

    // Run on initial load
    setTimeout(updateRoomFilterForFix, 150); // Small delay to run after initial scripts
}); 