// ==========================================
// Gundshop.id - script.js (vanilla JS, no framework)
// ==========================================

document.addEventListener("DOMContentLoaded", function () {
    initCarousel();
    initQtyControls();
    initFlashAutoHide();
    initMobileNav();
});

// ---------- CAROUSEL ----------
function initCarousel() {
    const track = document.querySelector(".carousel-track");
    if (!track) return;
    const slides = track.querySelectorAll(".carousel-slide");
    const dotsWrap = document.querySelector(".carousel-dots");
    let index = 0;
    let timer;

    slides.forEach((_, i) => {
        const dot = document.createElement("span");
        if (i === 0) dot.classList.add("active");
        dot.addEventListener("click", () => goTo(i));
        dotsWrap.appendChild(dot);
    });

    function update() {
        track.style.transform = `translateX(-${index * 100}%)`;
        dotsWrap.querySelectorAll("span").forEach((d, i) => d.classList.toggle("active", i === index));
    }

    function goTo(i) {
        index = (i + slides.length) % slides.length;
        update();
        resetTimer();
    }

    function next() { goTo(index + 1); }
    function prev() { goTo(index - 1); }

    function resetTimer() {
        clearInterval(timer);
        timer = setInterval(next, 5000);
    }

    document.querySelector(".carousel-arrow.next")?.addEventListener("click", next);
    document.querySelector(".carousel-arrow.prev")?.addEventListener("click", prev);

    // Touch swipe support for carousel
    let touchStartX = 0;
    let touchEndX = 0;
    track.addEventListener('touchstart', (e) => {
        touchStartX = e.changedTouches[0].screenX;
    }, { passive: true });
    track.addEventListener('touchend', (e) => {
        touchEndX = e.changedTouches[0].screenX;
        const diff = touchStartX - touchEndX;
        if (Math.abs(diff) > 50) {
            if (diff > 0) next(); else prev();
        }
    }, { passive: true });

    resetTimer();
}

// ---------- QUANTITY SELECTOR (product detail / cart) ----------
function initQtyControls() {
    document.querySelectorAll("[data-qty-wrap]").forEach((wrap) => {
        const input = wrap.querySelector("input[type=number]");
        const min = parseInt(input.min || 1);
        const max = parseInt(input.max || 9999);

        wrap.querySelector("[data-qty-minus]")?.addEventListener("click", () => {
            input.value = Math.max(min, parseInt(input.value || 1) - 1);
            input.dispatchEvent(new Event("change"));
        });
        wrap.querySelector("[data-qty-plus]")?.addEventListener("click", () => {
            input.value = Math.min(max, parseInt(input.value || 1) + 1);
            input.dispatchEvent(new Event("change"));
        });
    });
}

// Auto-submit cart quantity form when qty changes (debounced)
function submitQtyForm(form) {
    clearTimeout(form._t);
    form._t = setTimeout(() => form.submit(), 500);
}

// ---------- FLASH MESSAGE AUTO HIDE ----------
function initFlashAutoHide() {
    document.querySelectorAll(".flash-message").forEach((el) => {
        setTimeout(() => {
            el.style.transition = "opacity .4s";
            el.style.opacity = "0";
            setTimeout(() => el.remove(), 400);
        }, 4000);
    });
}

// ---------- MOBILE NAV ----------
function initMobileNav() {
    const hamburger = document.getElementById('mobileHamburger');
    const overlay = document.getElementById('mobileNavOverlay');
    const drawer = document.getElementById('mobileNavDrawer');
    if (!hamburger || !overlay || !drawer) return;

    function openDrawer() {
        hamburger.classList.add('active');
        overlay.classList.add('open');
        drawer.classList.add('open');
        document.body.style.overflow = 'hidden';
    }

    function closeDrawer() {
        hamburger.classList.remove('active');
        overlay.classList.remove('open');
        drawer.classList.remove('open');
        document.body.style.overflow = '';
    }

    hamburger.addEventListener('click', () => {
        if (drawer.classList.contains('open')) {
            closeDrawer();
        } else {
            openDrawer();
        }
    });

    overlay.addEventListener('click', closeDrawer);

    // Close drawer when a link inside is clicked
    drawer.querySelectorAll('a').forEach(link => {
        link.addEventListener('click', closeDrawer);
    });

    // Close on escape key
    document.addEventListener('keydown', (e) => {
        if (e.key === 'Escape') closeDrawer();
    });
}

// ---------- PRINT HELPERS ----------
function printInvoice() {
    window.print();
}

// ---------- CONFIRM DELETE ----------
function confirmDelete(message) {
    return confirm(message || "Yakin ingin menghapus data ini?");
}