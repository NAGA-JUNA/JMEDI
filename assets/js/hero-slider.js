document.addEventListener('DOMContentLoaded', function() {
    const heroCarousel = document.getElementById('heroSlider');
    if (!heroCarousel) return;

    const animKeyframes = {
        'fadeIn': 'heroFadeIn',
        'slideUp': 'heroSlideUp',
        'slideLeft': 'heroSlideLeft',
        'zoomIn': 'heroZoomIn'
    };

    function triggerAnimations(item) {
        const anim = item.getAttribute('data-animation') || 'fadeIn';
        const keyframe = animKeyframes[anim] || 'heroFadeIn';

        const elements = item.querySelectorAll('.slide-subtitle, .slide-title, .slide-description, .slide-buttons');
        const delays = [0, 0.15, 0.35, 0.5];

        elements.forEach((el, idx) => {
            el.style.animation = 'none';
            el.style.opacity = '0';
        });

        void item.offsetWidth;

        elements.forEach((el, idx) => {
            el.style.animation = keyframe + ' 0.7s ease ' + delays[idx] + 's forwards';
        });
    }

    function initParallaxBg(item) {
        const bg = item.querySelector('.slide-bg');
        if (bg) {
            bg.style.transition = 'none';
            bg.style.transform = 'scale(1)';
            void bg.offsetWidth;
            bg.style.transition = 'transform 8s linear';
            bg.style.transform = 'scale(1.08)';
        }
    }

    function applyTransitionEffect() {
        var active = heroCarousel.querySelector('.carousel-item.active');
        if (!active) return;
        var effect = active.getAttribute('data-transition') || 'slide';
        heroCarousel.classList.remove('carousel-fade', 'carousel-zoom');
        if (effect === 'fade') {
            heroCarousel.classList.add('carousel-fade');
        } else if (effect === 'zoom') {
            heroCarousel.classList.add('carousel-zoom');
        }
    }

    var firstItem = heroCarousel.querySelector('.carousel-item.active');
    if (firstItem) {
        triggerAnimations(firstItem);
        initParallaxBg(firstItem);
        applyTransitionEffect();
    }

    heroCarousel.addEventListener('slide.bs.carousel', function(e) {
        var nextItem = e.relatedTarget;
        if (nextItem) {
            var effect = nextItem.getAttribute('data-transition') || 'slide';
            heroCarousel.classList.remove('carousel-fade', 'carousel-zoom');
            if (effect === 'fade') {
                heroCarousel.classList.add('carousel-fade');
            } else if (effect === 'zoom') {
                heroCarousel.classList.add('carousel-zoom');
            }
        }
    });

    heroCarousel.addEventListener('slid.bs.carousel', function() {
        var active = heroCarousel.querySelector('.carousel-item.active');
        if (!active) return;
        triggerAnimations(active);
        initParallaxBg(active);
    });

    var ticking = false;
    window.addEventListener('scroll', function() {
        if (!ticking) {
            requestAnimationFrame(function() {
                var scrollY = window.pageYOffset;
                if (scrollY < 800) {
                    var activeBg = heroCarousel.querySelector('.carousel-item.active .slide-bg');
                    if (activeBg) {
                        activeBg.style.transform = 'scale(1.08) translateY(' + (scrollY * 0.2) + 'px)';
                    }
                }
                ticking = false;
            });
            ticking = true;
        }
    }, { passive: true });
});
