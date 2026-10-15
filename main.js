// main.js — DS4Y Attendance Portal

document.addEventListener('DOMContentLoaded', () => {

    // ── Auto-focus first input on register page ──────────────────────────────
    const nameInput = document.getElementById('name');
    if (nameInput) nameInput.focus();

    // ── Hamburger nav toggle ──────────────────────────────────────────────────
    const navToggle = document.getElementById('navToggle');
    const mainNav   = document.getElementById('mainNav');
    const navIcon   = document.getElementById('navIcon');

    if (navToggle && mainNav) {
        navToggle.addEventListener('click', () => {
            const isOpen = mainNav.classList.toggle('open');
            navToggle.setAttribute('aria-expanded', isOpen);
            navIcon.className = isOpen ? 'fa-solid fa-xmark' : 'fa-solid fa-bars';
        });

        // Close on outside click
        document.addEventListener('click', (e) => {
            if (!navToggle.contains(e.target) && !mainNav.contains(e.target)) {
                mainNav.classList.remove('open');
                navToggle.setAttribute('aria-expanded', 'false');
                navIcon.className = 'fa-solid fa-bars';
            }
        });
    }

    // ── Auto-dismiss alert notifications after 5 s ───────────────────────────
    document.querySelectorAll('.alert').forEach(alert => {
        setTimeout(() => {
            alert.style.transition = 'opacity .5s ease, transform .5s ease';
            alert.style.opacity = '0';
            alert.style.transform = 'translateY(-8px)';
            setTimeout(() => alert.remove(), 500);
        }, 5000);
    });

    // ── Live search (client-side, dashboard / archive) ───────────────────────
    const searchInput = document.getElementById('live-search');
    const tableRows   = document.querySelectorAll('.data-table tbody tr:not(.empty-row)');

    if (searchInput && tableRows.length) {
        searchInput.addEventListener('input', () => {
            const q = searchInput.value.toLowerCase().trim();
            let visible = 0;

            tableRows.forEach(row => {
                const text = row.textContent.toLowerCase();
                const show = !q || text.includes(q);
                row.style.display = show ? '' : 'none';
                if (show) visible++;
            });

            // Show/hide no-results row
            let noRow = document.getElementById('js-no-results');
            if (visible === 0 && tableRows.length > 0) {
                if (!noRow) {
                    noRow = document.createElement('tr');
                    noRow.id = 'js-no-results';
                    noRow.innerHTML = `<td colspan="7" class="empty-state">
                        <i class="fa-solid fa-magnifying-glass"></i>
                        <strong>No matches</strong>
                        <span>No records match your search.</span>
                    </td>`;
                    document.querySelector('.data-table tbody').appendChild(noRow);
                }
                noRow.style.display = '';
            } else if (noRow) {
                noRow.style.display = 'none';
            }
        });
    }

    // ── Date filter: "Today" button sets today's date ─────────────────────────
    const todayBtn    = document.querySelector('.today-btn');
    const dateInput   = document.getElementById('date-filter');
    if (todayBtn && dateInput) {
        todayBtn.addEventListener('click', (e) => {
            e.preventDefault();
            const today = new Date().toISOString().split('T')[0];
            dateInput.value = today;
            dateInput.closest('form').submit();
        });
    }

    // ── Secure Modal Triggers using Data Attributes ──────────────────────────
    document.addEventListener('click', (e) => {
        const returnBtn = e.target.closest('.action-return');
        if (returnBtn) {
            const id = returnBtn.getAttribute('data-id');
            const name = returnBtn.getAttribute('data-name');
            if (typeof openReturnModal === 'function') {
                openReturnModal(id, name);
            }
        }

        const deleteBtn = e.target.closest('.action-delete');
        if (deleteBtn) {
            const id = deleteBtn.getAttribute('data-id');
            const name = deleteBtn.getAttribute('data-name');
            if (typeof openDeleteModal === 'function') {
                openDeleteModal(id, name);
            }
        }

        const accordionHeader = e.target.closest('.accordion-header');
        if (accordionHeader) {
            const targetId = `accordion-${accordionHeader.getAttribute('data-date-group')}`;
            const accordionContent = document.getElementById(targetId);
            if (accordionContent) {
                const isExpanded = accordionHeader.getAttribute('aria-expanded') === 'true';
                // Use strings so [aria-expanded="true"] CSS selector matches correctly
                accordionHeader.setAttribute('aria-expanded', isExpanded ? 'false' : 'true');
                if (isExpanded) {
                    accordionContent.style.maxHeight = '0';
                } else {
                    accordionContent.style.maxHeight = accordionContent.scrollHeight + 'px';
                }
                accordionHeader.classList.toggle('active', !isExpanded);
            }
        }
    });

});
