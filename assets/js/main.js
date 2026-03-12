document.addEventListener('DOMContentLoaded', function() {
    const navbar = document.querySelector('.navbar');
    if (navbar) {
        window.addEventListener('scroll', function() {
            if (window.scrollY > 50) {
                navbar.classList.add('scrolled');
            } else {
                navbar.classList.remove('scrolled');
            }
        });
    }

    const testimonialSwiper = document.querySelector('.testimonial-swiper');
    if (testimonialSwiper) {
        new Swiper('.testimonial-swiper', {
            slidesPerView: 1.1,
            spaceBetween: 16,
            loop: true,
            autoplay: { delay: 5000, disableOnInteraction: false },
            pagination: { el: '.swiper-pagination', clickable: true },
            breakpoints: {
                576: { slidesPerView: 1.2, spaceBetween: 20 },
                768: { slidesPerView: 2, spaceBetween: 24 },
                1024: { slidesPerView: 3, spaceBetween: 30 }
            }
        });
    }

    const videoNewsSwiper = document.querySelector('.video-news-swiper');
    if (videoNewsSwiper) {
        new Swiper('.video-news-swiper', {
            slidesPerView: 1,
            spaceBetween: 24,
            loop: false,
            pagination: { el: '.vid-news-pagination', clickable: true },
            breakpoints: {
                576: { slidesPerView: 2 },
                992: { slidesPerView: 3 }
            }
        });
    }

    /* ── Mobile-only section swipers ── */
    const mobSwiperConfig = (paginationEl, cols3 = false) => ({
        slidesPerView: 1.15,
        spaceBetween: 16,
        loop: false,
        centeredSlides: false,
        pagination: { el: paginationEl, clickable: true },
        breakpoints: {
            576: { slidesPerView: 1.6, spaceBetween: 18 },
            768: { slidesPerView: cols3 ? 2 : 2, spaceBetween: 20 },
            992: { slidesPerView: cols3 ? 3 : 4, spaceBetween: 24 }
        }
    });

    if (document.querySelector('.dept-swiper')) {
        new Swiper('.dept-swiper', mobSwiperConfig('.dept-pagination', true));
    }
    if (document.querySelector('.team-swiper')) {
        new Swiper('.team-swiper', mobSwiperConfig('.team-pagination', false));
    }
    if (document.querySelector('.process-swiper')) {
        new Swiper('.process-swiper', mobSwiperConfig('.process-pagination', false));
    }
    if (document.querySelector('.articles-swiper')) {
        new Swiper('.articles-swiper', mobSwiperConfig('.articles-pagination', true));
    }

    const deptFilter = document.getElementById('deptFilter');
    if (deptFilter) {
        deptFilter.addEventListener('change', function() {
            const url = new URL(window.location);
            if (this.value) {
                url.searchParams.set('department', this.value);
            } else {
                url.searchParams.delete('department');
            }
            window.location = url;
        });
    }

    const doctorSelect = document.getElementById('doctor_id');
    const departmentSelect = document.getElementById('department_id');
    if (departmentSelect && doctorSelect) {
        departmentSelect.addEventListener('change', function() {
            const deptId = this.value;
            fetch('/public/api/doctors-by-dept.php?department_id=' + deptId)
                .then(r => r.json())
                .then(doctors => {
                    doctorSelect.innerHTML = '<option value="">Select Doctor</option>';
                    doctors.forEach(doc => {
                        doctorSelect.innerHTML += `<option value="${doc.doctor_id}">${doc.name}</option>`;
                    });
                });
        });
    }

    const forms = document.querySelectorAll('.needs-validation');
    forms.forEach(form => {
        form.addEventListener('submit', function(event) {
            if (!form.checkValidity()) {
                event.preventDefault();
                event.stopPropagation();
            }
            form.classList.add('was-validated');
        });
    });

    const observerOptions = {
        threshold: 0.1,
        rootMargin: '0px 0px -50px 0px'
    };

    const observer = new IntersectionObserver(function(entries) {
        entries.forEach(entry => {
            if (entry.isIntersecting) {
                entry.target.classList.add('visible');
                observer.unobserve(entry.target);
            }
        });
    }, observerOptions);

    document.querySelectorAll('.fade-in, .fade-in-left, .fade-in-right, .scale-in').forEach(el => {
        observer.observe(el);
    });

    document.querySelectorAll('.counter-number').forEach(counter => {
        const target = parseInt(counter.getAttribute('data-target'));
        const duration = 2000;
        const step = target / (duration / 16);
        let current = 0;

        const counterObserver = new IntersectionObserver(function(entries) {
            entries.forEach(entry => {
                if (entry.isIntersecting) {
                    const updateCounter = () => {
                        current += step;
                        if (current < target) {
                            counter.textContent = Math.ceil(current);
                            requestAnimationFrame(updateCounter);
                        } else {
                            counter.textContent = target;
                        }
                    };
                    updateCounter();
                    counterObserver.unobserve(entry.target);
                }
            });
        }, { threshold: 0.5 });

        counterObserver.observe(counter);
    });

    const mobDrawer = document.getElementById('mobileNavDrawer');
    const mobBtn    = document.getElementById('mobMenuBtn');
    if (mobDrawer && mobBtn) {
        mobDrawer.addEventListener('show.bs.offcanvas',   () => mobBtn.classList.add('is-open'));
        mobDrawer.addEventListener('hidden.bs.offcanvas', () => mobBtn.classList.remove('is-open'));

        mobDrawer.querySelectorAll('a[href]').forEach(link => {
            const href = link.getAttribute('href');
            if (!href || href === '#') return;
            link.addEventListener('click', function(e) {
                const dest = this.getAttribute('href');
                const instance = bootstrap.Offcanvas.getInstance(mobDrawer);
                if (instance) {
                    e.preventDefault();
                    mobDrawer.addEventListener('hidden.bs.offcanvas', () => {
                        window.location.href = dest;
                    }, { once: true });
                    instance.hide();
                }
            });
        });
    }
});
