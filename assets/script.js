jQuery(document).tooltip({
    items: '.image-link, [data-tooltip]',
    show: { effect: "none" },
    hide: { effect: "none" },
    content: function() {
        const element = jQuery(this);
        if (element.is('.image-link')) {
            return `<img class="tooltip-image" width="160" height="160" src="${element.attr("href")}" decode="async" style="object-fit: contain">`;
        }
        if (element.is('[data-tooltip]')) {
            return element.attr("data-tooltip");
        }
    }
});