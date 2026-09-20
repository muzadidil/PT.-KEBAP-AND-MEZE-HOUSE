/*
 * Slideshow halaman masuk.
 *
 * JavaScript biasa tanpa kerangka kerja apa pun dan tanpa langkah build —
 * halaman masuk dibuka sebelum Livewire sempat berperan, dan satu berkas
 * kecil begini jauh lebih murah daripada menyeret komponen untuk pekerjaan
 * yang cuma mengganti kelas CSS.
 */
(function () {
    'use strict';

    var INTERVAL = 6000;

    function init() {
        var track = document.querySelector('[data-auth-slides]');

        if (!track) {
            return;
        }

        var slides = Array.prototype.slice.call(track.querySelectorAll('.auth__slide'));

        if (slides.length < 2) {
            return;
        }

        var dots = Array.prototype.slice.call(document.querySelectorAll('[data-auth-dots] .auth__dot'));
        var progress = document.querySelector('[data-auth-progress]');
        var index = 0;
        var timer = null;

        function show(next) {
            index = (next + slides.length) % slides.length;

            slides.forEach(function (slide, i) {
                slide.classList.toggle('is-active', i === index);
            });

            dots.forEach(function (dot, i) {
                dot.classList.toggle('is-active', i === index);
            });

            restartProgress();
        }

        function restartProgress() {
            if (!progress) {
                return;
            }

            // Menyalakan ulang animasi: kelasnya dilepas, layout dipaksa
            // dihitung ulang, baru dipasang lagi. Tanpa langkah tengah itu
            // peramban menganggap tidak ada yang berubah.
            progress.classList.remove('is-running');
            void progress.offsetWidth;
            progress.classList.add('is-running');
        }

        function play() {
            stop();
            timer = window.setInterval(function () {
                show(index + 1);
            }, INTERVAL);
        }

        function stop() {
            if (timer !== null) {
                window.clearInterval(timer);
                timer = null;
            }
        }

        function goTo(next) {
            show(next);
            play();
        }

        dots.forEach(function (dot, i) {
            dot.addEventListener('click', function () {
                goTo(i);
            });
        });

        var prev = document.querySelector('[data-auth-prev]');
        var next = document.querySelector('[data-auth-next]');

        if (prev) {
            prev.addEventListener('click', function () {
                goTo(index - 1);
            });
        }

        if (next) {
            next.addEventListener('click', function () {
                goTo(index + 1);
            });
        }

        // Tab yang tersembunyi tidak perlu berganti slide; membiarkannya
        // berjalan hanya menghabiskan baterai tanpa ada yang melihat.
        document.addEventListener('visibilitychange', function () {
            if (document.hidden) {
                stop();
            } else {
                play();
            }
        });

        // Hormati setelan sistem: kalau pengguna meminta gerak seminimal
        // mungkin, slide pertama saja yang ditampilkan.
        var reduced = window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)');

        if (reduced && reduced.matches) {
            if (progress) {
                progress.remove();
            }

            return;
        }

        restartProgress();
        play();
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }
})();
