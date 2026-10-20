/*
function showsum()
{
    let x=5;
    let y=25;
    document.getElementById("result").innerHTML=x+y;
}

<button type="button" onclick="showsum()">cllick me</button>

  str=5;
            str++;
            printthook();
            function printthook(){
                document.write('thoook');
            }
             document.write(' shaan dabhi')
*/



const loginForm = document.getElementById("loginForm");

if (loginForm) {
    loginForm.addEventListener("submit", function(event) {
        event.preventDefault();

        let username = document.getElementById("username").value;
        let password = document.getElementById("password").value;

        if (username.length < 4) {
            alert("Username must be at least 4 characters!");
            return;
        }

        if (password.length < 6) {
            alert("Password must be at least 6 characters!");
            return;
        }

        alert("Login successful!");
    });
}

const adminForm = document.getElementById("adminform");

if (adminForm) {
    adminForm.addEventListener("submit", function(event) {
        event.preventDefault();

        let username = document.getElementById("adminusername").value;
        let password = document.getElementById("adminpassword").value;

        if (username.length < 4) {
            alert("Admin username must be at least 4 characters!");
            return;
        }

        if (password.length < 6) {
            alert("Admin password must be at least 6 characters!");
            return;
        }

        alert("Login successful!");
    });
}

// ==========================================
// Practical 4: Interactive UI Components Logic
// ==========================================

// 1. Light/Dark Theme Switcher with localStorage
const themeToggleBtn = document.getElementById("themeToggleBtn");

function setTheme(theme) {
    if (theme === "dark") {
        document.body.classList.add("dark-mode");
        if (themeToggleBtn) themeToggleBtn.textContent = "☀️ Light Mode";
    } else {
        document.body.classList.remove("dark-mode");
        if (themeToggleBtn) themeToggleBtn.textContent = "🌙 Dark Mode";
    }
}

// Restore theme from localStorage on page load
const savedTheme = localStorage.getItem("studenthub-theme") || "light";
setTheme(savedTheme);

if (themeToggleBtn) {
    themeToggleBtn.addEventListener("click", function() {
        const isDark = document.body.classList.contains("dark-mode");
        const nextTheme = isDark ? "light" : "dark";
        setTheme(nextTheme);
        localStorage.setItem("studenthub-theme", nextTheme);
    });
}

// 2. Notification Banner (Close & Session Memory)
const notificationBanner = document.getElementById("notificationBanner");
const closeBannerBtn = document.getElementById("closeBannerBtn");

if (closeBannerBtn && notificationBanner) {
    closeBannerBtn.addEventListener("click", function() {
        notificationBanner.classList.add("hidden");
        sessionStorage.setItem("studenthub-banner-closed", "true");
    });

    if (sessionStorage.getItem("studenthub-banner-closed") === "true") {
        notificationBanner.classList.add("hidden");
    }
}

// 3. Hamburger Navigation Menu Toggle
const hamburgerBtn = document.getElementById("hamburgerBtn");
const mobileMenu = document.getElementById("mobileMenu");

if (hamburgerBtn && mobileMenu) {
    hamburgerBtn.addEventListener("click", function() {
        mobileMenu.classList.toggle("active");
    });
}

// 4. Campus Highlights Image / Content Slider
const sliderTrack = document.getElementById("sliderTrack");
const slides = document.querySelectorAll(".slide-item");
const prevSlideBtn = document.getElementById("prevSlideBtn");
const nextSlideBtn = document.getElementById("nextSlideBtn");
const dots = document.querySelectorAll(".dot");

if (sliderTrack && slides.length > 0) {
    let currentSlide = 0;
    const total = slides.length;

    function goToSlide(index) {
        currentSlide = (index + total) % total;
        sliderTrack.style.transform = `translateX(-${currentSlide * 100}%)`;
        dots.forEach((dot, idx) => {
            dot.classList.toggle("active", idx === currentSlide);
        });
    }

    if (nextSlideBtn) {
        nextSlideBtn.addEventListener("click", () => goToSlide(currentSlide + 1));
    }
    if (prevSlideBtn) {
        prevSlideBtn.addEventListener("click", () => goToSlide(currentSlide - 1));
    }

    dots.forEach((dot) => {
        dot.addEventListener("click", function() {
            const idx = parseInt(this.getAttribute("data-index"), 10);
            goToSlide(idx);
        });
    });

    // Auto-advance slider every 5 seconds
    let autoSlide = setInterval(() => goToSlide(currentSlide + 1), 5000);
    const sliderContainer = document.querySelector(".slider-container");
    if (sliderContainer) {
        sliderContainer.addEventListener("mouseenter", () => clearInterval(autoSlide));
        sliderContainer.addEventListener("mouseleave", () => {
            autoSlide = setInterval(() => goToSlide(currentSlide + 1), 5000);
        });
    }
}

// 5. Collapsible FAQ Accordion
const faqAccordion = document.getElementById("faqAccordion");

if (faqAccordion) {
    faqAccordion.addEventListener("click", function(event) {
        const questionBtn = event.target.closest(".faq-question");
        if (!questionBtn) return;

        const parentItem = questionBtn.parentElement;
        const isActive = parentItem.classList.contains("active");

        // Close all other FAQ items
        document.querySelectorAll(".faq-item").forEach((item) => {
            item.classList.remove("active");
        });

        // Toggle clicked item
        if (!isActive) {
            parentItem.classList.add("active");
        }
    });
}

// 6. Modal Popup & Form Handling
const inquiryModal = document.getElementById("inquiryModal");
const openModalBtn = document.getElementById("openModalBtn");
const closeModalBtn = document.getElementById("closeModalBtn");
const quickInquiryForm = document.getElementById("quickInquiryForm");

if (inquiryModal && openModalBtn && closeModalBtn) {
    openModalBtn.addEventListener("click", function() {
        inquiryModal.classList.add("open");
    });

    closeModalBtn.addEventListener("click", function() {
        inquiryModal.classList.remove("open");
    });

    // Close on clicking outside the modal content
    inquiryModal.addEventListener("click", function(event) {
        if (event.target === inquiryModal) {
            inquiryModal.classList.remove("open");
        }
    });

    // Close on Escape key press
    document.addEventListener("keydown", function(event) {
        if (event.key === "Escape" && inquiryModal.classList.contains("open")) {
            inquiryModal.classList.remove("open");
        }
    });
}

if (quickInquiryForm) {
    quickInquiryForm.addEventListener("submit", function(event) {
        event.preventDefault();
        const studentName = document.getElementById("modalStudentName").value;
        alert(`Thank you, ${studentName}! Your inquiry has been submitted.`);
        quickInquiryForm.reset();
        inquiryModal.classList.remove("open");
    });
}

    function greet(name) {
    console.log("Hello " + name);
}

greet("Shaan");
    console.log("script js")