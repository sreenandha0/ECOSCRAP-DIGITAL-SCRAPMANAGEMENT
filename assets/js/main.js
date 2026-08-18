const intro = document.getElementById("intro");
const website = document.getElementById("website");

const logoVideo = document.getElementById("logoVideo");
const skipIntro = document.getElementById("skipIntro");
const introProgress = document.getElementById("introProgress");


/* ==========================================
   SHOW WEBSITE
========================================== */

function showWebsite() {

    if (!intro) return;

    intro.classList.add("hide");

    website.classList.add("visible");

}


/* ==========================================
   VIDEO FINISHED
========================================== */

logoVideo.addEventListener("ended", function () {

    showWebsite();

});



/* ==========================================
   SKIP INTRO
========================================== */

skipIntro.addEventListener("click", function () {

    logoVideo.pause();

    showWebsite();

});



/* ==========================================
   VIDEO PROGRESS
========================================== */

logoVideo.addEventListener("timeupdate", function () {

    if (!logoVideo.duration) return;

    const percentage =
        (logoVideo.currentTime /
            logoVideo.duration) * 100;

    introProgress.style.width =
        percentage + "%";

});



/* ==========================================
   AUTOPLAY FALLBACK
========================================== */

logoVideo.play().catch(function () {

    console.log(
        "Autoplay was blocked by browser."
    );

});



/* ==========================================
   SCROLL ANIMATIONS
========================================== */

const revealElements =
    document.querySelectorAll(".reveal");


const observer =
    new IntersectionObserver(

        function (entries) {

            entries.forEach(function (entry) {

                if (entry.isIntersecting) {

                    entry.target.classList.add("show");

                    observer.unobserve(
                        entry.target
                    );

                }

            });

        },

        {
            threshold: 0.12
        }

    );


revealElements.forEach(function (element) {

    observer.observe(element);

});