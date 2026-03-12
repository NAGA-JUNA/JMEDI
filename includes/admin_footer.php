        </main>
    </div>
</div>

<div class="scroll-nav">
    <button class="scroll-btn" id="scrollTopBtn" title="Scroll to top" aria-label="Scroll to top" onclick="window.scrollTo({top:0,behavior:'smooth'})">
        <i class="fas fa-arrow-up"></i>
    </button>
    <button class="scroll-btn" id="scrollBottomBtn" title="Scroll to bottom" aria-label="Scroll to bottom" onclick="window.scrollTo({top:document.documentElement.scrollHeight,behavior:'smooth'})">
        <i class="fas fa-arrow-down"></i>
    </button>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>

<!-- Delete Confirmation Modal -->
<div class="modal fade" id="deleteConfirmModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered" style="max-width:420px;">
        <div class="modal-content border-0" style="border-radius:16px;overflow:hidden;box-shadow:0 20px 60px rgba(0,0,0,0.2);">
            <div class="modal-body text-center p-5">
                <div style="width:72px;height:72px;background:#fff0f0;border-radius:50%;display:flex;align-items:center;justify-content:center;margin:0 auto 1.5rem;">
                    <i class="fas fa-trash-alt" style="font-size:1.8rem;color:#dc3545;"></i>
                </div>
                <h5 class="fw-bold mb-2" style="font-size:1.25rem;">Delete Confirmation</h5>
                <p class="text-muted mb-4" id="deleteConfirmText">Are you sure you want to delete this item? This action cannot be undone.</p>
                <div class="d-flex gap-3 justify-content-center">
                    <button type="button" class="btn btn-light px-4 fw-semibold" data-bs-dismiss="modal" style="border-radius:10px;min-width:110px;">
                        <i class="fas fa-times me-1"></i> Cancel
                    </button>
                    <button type="button" id="deleteConfirmBtn" class="btn btn-danger px-4 fw-semibold" style="border-radius:10px;min-width:110px;">
                        <i class="fas fa-trash me-1"></i> Yes, Delete
                    </button>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Admin Toast Notification -->
<div class="position-fixed top-0 end-0 p-3" style="z-index:9999;">
    <div id="adminToast" class="toast align-items-center border-0 text-white" role="alert" aria-live="assertive" style="border-radius:14px;min-width:300px;max-width:380px;box-shadow:0 8px 32px rgba(0,0,0,0.18);">
        <div class="d-flex align-items-start px-3 py-3 gap-2">
            <i id="adminToastIcon" class="fas fa-check-circle mt-1 flex-shrink-0" style="font-size:1.15rem;"></i>
            <div class="flex-grow-1">
                <div id="adminToastTitle" class="fw-700 mb-1" style="font-size:.88rem;"></div>
                <div id="adminToastMsg" class="toast-body p-0" style="font-size:.82rem;opacity:.92;line-height:1.5;"></div>
            </div>
            <button type="button" class="btn-close btn-close-white flex-shrink-0" data-bs-dismiss="toast"></button>
        </div>
    </div>
</div>

<script>
(function() {
    var _pendingForm = null;
    var _modal = null;

    document.addEventListener('DOMContentLoaded', function() {
        _modal = new bootstrap.Modal(document.getElementById('deleteConfirmModal'));

        document.addEventListener('click', function(e) {
            var btn = e.target.closest('[data-delete-trigger]');
            if (!btn) return;
            e.preventDefault();
            e.stopImmediatePropagation();
            _pendingForm = btn.closest('form');
            var label = btn.getAttribute('data-delete-label') || 'this item';
            document.getElementById('deleteConfirmText').textContent =
                'Are you sure you want to delete ' + label + '? This action cannot be undone.';
            _modal.show();
        });

        document.getElementById('deleteConfirmBtn').addEventListener('click', function() {
            _modal.hide();
            if (_pendingForm) {
                _pendingForm.submit();
                _pendingForm = null;
            }
        });

        var params = new URLSearchParams(window.location.search);
        var msg = params.get('msg');
        var messages = {
            'deleted': ['Deleted',  'Item deleted successfully.',    'success'],
            'toggled': ['Updated',  'Status updated successfully.',  'success'],
            'saved':   ['Saved',    'Changes saved successfully.',   'success'],
            'updated': ['Updated',  'Record updated successfully.',  'success'],
            'review_deleted': ['Deleted', 'Review removed.', 'success'],
            'review_added':   ['Added',   'Review added successfully.', 'success'],
        };
        if (msg && messages[msg]) {
            var m = messages[msg];
            window.showAdminToast(m[0], m[1], m[2]);
            var url = new URL(window.location.href);
            url.searchParams.delete('msg');
            window.history.replaceState({}, '', url.toString());
        }
    });
})();

window.showAdminToast = function(title, message, type) {
    type = type || 'success';
    var toastEl   = document.getElementById('adminToast');
    var titleEl   = document.getElementById('adminToastTitle');
    var msgEl     = document.getElementById('adminToastMsg');
    var iconEl    = document.getElementById('adminToastIcon');
    var styles = {
        success: { bg: 'linear-gradient(135deg,#198754,#20c997)', icon: 'fa-check-circle' },
        error:   { bg: 'linear-gradient(135deg,#dc3545,#e85d6b)', icon: 'fa-exclamation-circle' },
        info:    { bg: 'linear-gradient(135deg,#0D6EFD,#0891b2)', icon: 'fa-info-circle' },
        warning: { bg: 'linear-gradient(135deg,#f59e0b,#f97316)', icon: 'fa-exclamation-triangle' },
    };
    var s = styles[type] || styles.success;
    toastEl.style.background = s.bg;
    iconEl.className = 'fas ' + s.icon + ' mt-1 flex-shrink-0';
    titleEl.textContent  = title   || '';
    msgEl.textContent    = message || '';
    var toast = bootstrap.Toast.getOrCreateInstance(toastEl, { delay: type === 'error' ? 7000 : 4500 });
    toast.show();
};
</script>

<script>
(function() {
    var sidebar    = document.getElementById('adminSidebar');
    var toggleBtn  = document.getElementById('sidebarToggle');   // topbar hamburger
    var collapseBtn= document.getElementById('sbCollapseBtn');   // sidebar chevron button
    var isMobile   = function() { return window.innerWidth < 992; };

    /* ---- Always start expanded; only collapse when user explicitly clicks the chevron ---- */
    sidebar.classList.remove('sb-collapsed');
    localStorage.removeItem('sbCollapsed');

    window.addEventListener('resize', function() {
        if (isMobile()) { sidebar.classList.remove('sb-collapsed'); }
    });

    /* ---- Topbar hamburger — mobile: show/hide, desktop: expand (if collapsed) ---- */
    if (toggleBtn) {
        toggleBtn.addEventListener('click', function(e) {
            e.preventDefault();
            if (isMobile()) {
                sidebar.classList.toggle('show');
            } else {
                sidebar.classList.remove('sb-collapsed');
                localStorage.setItem('sbCollapsed', 'false');
            }
        });
    }

    /* ---- Sidebar chevron button — collapse on desktop ---- */
    if (collapseBtn) {
        collapseBtn.addEventListener('click', function(e) {
            e.preventDefault();
            sidebar.classList.add('sb-collapsed');
            localStorage.setItem('sbCollapsed', 'true');
        });
    }

    /* ---- Close on outside click (mobile) ---- */
    document.addEventListener('click', function(e) {
        if (isMobile() && sidebar.classList.contains('show')
            && !sidebar.contains(e.target)
            && !(toggleBtn && toggleBtn.contains(e.target))) {
            sidebar.classList.remove('show');
        }
    });

    /* ---- Sidebar search filter ---- */
    var searchInput = document.getElementById('sidebarSearchInput');
    if (searchInput) {
        searchInput.addEventListener('input', function() {
            var q = this.value.toLowerCase().trim();
            document.querySelectorAll('.sidebar-nav li').forEach(function(li) {
                var txt = (li.querySelector('.nav-label') || li.querySelector('a'))?.textContent.toLowerCase() || '';
                li.style.display = (!q || txt.includes(q)) ? '' : 'none';
            });
            document.querySelectorAll('.sidebar-section-label').forEach(function(lbl) {
                var key = lbl.getAttribute('data-section');
                var list = document.querySelector('[data-section-list="' + key + '"]');
                if (!list) return;
                if (q) {
                    list.classList.remove('section-hidden');
                    lbl.classList.remove('collapsed');
                }
                var anyVisible = Array.from(list.querySelectorAll('li')).some(li => li.style.display !== 'none');
                lbl.style.display = anyVisible ? '' : 'none';
            });
        });
    }

    var scrollTopBtn = document.getElementById('scrollTopBtn');
    var scrollBottomBtn = document.getElementById('scrollBottomBtn');

    function updateScrollBtns() {
        var scrollY = window.scrollY || window.pageYOffset;
        var docHeight = document.documentElement.scrollHeight;
        var winHeight = window.innerHeight;
        var atTop = scrollY < 200;
        var atBottom = scrollY + winHeight >= docHeight - 100;
        var hasScroll = docHeight > winHeight + 200;

        if (hasScroll && !atTop) {
            scrollTopBtn.classList.add('visible');
        } else {
            scrollTopBtn.classList.remove('visible');
        }

        if (hasScroll && !atBottom) {
            scrollBottomBtn.classList.add('visible');
        } else {
            scrollBottomBtn.classList.remove('visible');
        }
    }

    window.addEventListener('scroll', updateScrollBtns);
    window.addEventListener('resize', updateScrollBtns);
    updateScrollBtns();

    var sectionLabels = document.querySelectorAll('.sidebar-section-label[data-section]');
    var savedSections = {};
    try { savedSections = JSON.parse(localStorage.getItem('sidebarSections') || '{}'); } catch(e) {}

    sectionLabels.forEach(function(label) {
        var sectionKey = label.getAttribute('data-section');
        var list = document.querySelector('[data-section-list="' + sectionKey + '"]');
        if (!list) return;

        if (savedSections[sectionKey] === false) {
            label.classList.add('collapsed');
            list.classList.add('section-hidden');
            label.setAttribute('aria-expanded', 'false');
        }

        function toggleSection() {
            label.classList.toggle('collapsed');
            list.classList.toggle('section-hidden');
            var isExpanded = !label.classList.contains('collapsed');
            label.setAttribute('aria-expanded', isExpanded);
            savedSections[sectionKey] = isExpanded;
            localStorage.setItem('sidebarSections', JSON.stringify(savedSections));
        }

        label.addEventListener('click', toggleSection);
        label.addEventListener('keydown', function(e) {
            if (e.key === 'Enter' || e.key === ' ') {
                e.preventDefault();
                toggleSection();
            }
        });
    });
})();

/* ---- Dark / Light mode toggle ---- */
(function() {
    var btn  = document.getElementById('darkModeToggle');
    var icon = document.getElementById('darkModeIcon');
    function applyDark(on) {
        document.body.classList.toggle('dark-mode', on);
        if (icon) {
            icon.className = on ? 'fas fa-sun' : 'fas fa-moon';
        }
    }
    applyDark(localStorage.getItem('adminDarkMode') === 'true');
    if (btn) {
        btn.addEventListener('click', function() {
            var next = !document.body.classList.contains('dark-mode');
            localStorage.setItem('adminDarkMode', next);
            applyDark(next);
        });
    }
})();

function toggleFullscreen() {
    var icon = document.getElementById('fullscreenIcon');
    if (!document.fullscreenElement) {
        document.documentElement.requestFullscreen().then(function() {
            icon.classList.remove('fa-expand');
            icon.classList.add('fa-compress');
        }).catch(function() {});
    } else {
        document.exitFullscreen().then(function() {
            icon.classList.remove('fa-compress');
            icon.classList.add('fa-expand');
        }).catch(function() {});
    }
}

document.addEventListener('fullscreenchange', function() {
    var icon = document.getElementById('fullscreenIcon');
    if (icon) {
        if (document.fullscreenElement) {
            icon.classList.remove('fa-expand');
            icon.classList.add('fa-compress');
        } else {
            icon.classList.remove('fa-compress');
            icon.classList.add('fa-expand');
        }
    }
});
</script>
</body>
</html>
