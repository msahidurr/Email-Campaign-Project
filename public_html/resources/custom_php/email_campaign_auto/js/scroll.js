// scroll.js - Auto-scroll progress list
// Version: 2025-04-30-01-Fix-Scroll

document.addEventListener('DOMContentLoaded', function () {
    console.log('scroll.js loaded, version: 2025-04-30-01-Fix-Scroll');
    const progressList = document.getElementById('progress-list');
    if (!progressList) {
        console.warn('Progress list element not found');
        return;
    }

    // Use MutationObserver to detect changes in progress-list
    const observer = new MutationObserver(function (mutations) {
        mutations.forEach(function () {
            progressList.scrollTop = progressList.scrollHeight;
            console.log('Progress list auto-scrolled');
        });
    });

    observer.observe(progressList, {
        childList: true,
        subtree: true
    });

    console.log('MutationObserver set up for progress-list');
});
