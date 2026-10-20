/**
 * StudentHub - Practical 5: Registration Form Validation & Interactivity
 * Features:
 *  - HTML5 & JS Form Validation using Regular Expressions
 *  - Password Strength Meter with dynamic score and checklist
 *  - Intermediate Extension: Real-time validation on input/keyup/change
 *  - Advanced Extension: HTML5 Canvas Custom CAPTCHA Generator & Verification
 *  - Accessible Error Handling & Keyboard Navigation Support
 */

document.addEventListener("DOMContentLoaded", () => {
    // ----------------------------------------------------
    // 1. DOM Element References
    // ----------------------------------------------------
    const form = document.getElementById("registrationForm");
    const fullNameInput = document.getElementById("fullName");
    const emailInput = document.getElementById("email");
    const mobileInput = document.getElementById("mobile");
    const passwordInput = document.getElementById("password");
    const confirmPasswordInput = document.getElementById("confirmPassword");
    const courseSelect = document.getElementById("course");
    const termsCheckbox = document.getElementById("terms");
    const captchaInput = document.getElementById("captchaInput");
    const captchaCanvas = document.getElementById("captchaCanvas");
    const refreshCaptchaBtn = document.getElementById("refreshCaptchaBtn");
    const togglePasswordBtn = document.getElementById("togglePasswordBtn");
    const toggleConfirmPasswordBtn = document.getElementById("toggleConfirmPasswordBtn");
    const successBanner = document.getElementById("formSuccessMessage");
    const successDetails = document.getElementById("successDetails");
    const themeToggleBtn = document.getElementById("themeToggleBtn");

    // Password Meter elements
    const meterBar = document.getElementById("meterBar");
    const meterText = document.getElementById("meterText");
    const ruleLength = document.getElementById("ruleLength");
    const ruleUpper = document.getElementById("ruleUpper");
    const ruleLower = document.getElementById("ruleLower");
    const ruleNumber = document.getElementById("ruleNumber");
    const ruleSpecial = document.getElementById("ruleSpecial");

    // ----------------------------------------------------
    // 2. Regular Expressions for Validation
    // ----------------------------------------------------
    const REGEX = {
        // Name: Letters and spaces only, 3 to 50 characters
        name: /^[A-Za-z\s]{3,50}$/,
        
        // Email: Standard RFC 5322 compatible regex pattern
        email: /^[a-zA-Z0-9._%+-]+@[a-zA-Z0-9.-]+\.[a-zA-Z]{2,}$/,
        
        // Mobile: 10 digits starting with 6, 7, 8, or 9 (Indian telecom standard)
        mobile: /^[6-9]\d{9}$/,
        
        // Password component checks
        hasUpper: /[A-Z]/,
        hasLower: /[a-z]/,
        hasNumber: /\d/,
        hasSpecial: /[@$!%*?&#^~_]/
    };

    let generatedCaptchaCode = "";

    // ----------------------------------------------------
    // 3. Theme Persistence (Sync with StudentHub)
    // ----------------------------------------------------
    function applySavedTheme() {
        const savedTheme = localStorage.getItem("studenthub-theme") || "light";
        if (savedTheme === "dark") {
            document.body.classList.add("dark-mode");
            if (themeToggleBtn) themeToggleBtn.textContent = "☀️ Light Mode";
        } else {
            document.body.classList.remove("dark-mode");
            if (themeToggleBtn) themeToggleBtn.textContent = "🌙 Dark Mode";
        }
    }
    applySavedTheme();

    if (themeToggleBtn) {
        themeToggleBtn.addEventListener("click", () => {
            const isDark = document.body.classList.contains("dark-mode");
            const nextTheme = isDark ? "light" : "dark";
            localStorage.setItem("studenthub-theme", nextTheme);
            applySavedTheme();
            generateCanvasCaptcha(); // re-draw captcha with appropriate contrast
        });
    }

    // ----------------------------------------------------
    // 4. Advanced Extension: Custom Canvas CAPTCHA
    // ----------------------------------------------------
    function generateCanvasCaptcha() {
        if (!captchaCanvas) return;
        const ctx = captchaCanvas.getContext("2d");
        const width = captchaCanvas.width;
        const height = captchaCanvas.height;

        // Clear canvas
        ctx.clearRect(0, 0, width, height);

        // Generate 6 random alphanumeric characters (omitting confusing chars like 0, O, I, l)
        const chars = "ABCDEFGHJKLMNPQRSTUVWXYZabcdefghijkmnpqrstuvwxyz23456789";
        generatedCaptchaCode = "";
        for (let i = 0; i < 6; i++) {
            generatedCaptchaCode += chars.charAt(Math.floor(Math.random() * chars.length));
        }

        // Background styling
        const isDark = document.body.classList.contains("dark-mode");
        ctx.fillStyle = isDark ? "#242438" : "#fdf2f4";
        ctx.fillRect(0, 0, width, height);

        // Random background interference lines
        for (let i = 0; i < 5; i++) {
            ctx.strokeStyle = isDark ? "rgba(226, 130, 152, 0.4)" : "rgba(147, 59, 85, 0.25)";
            ctx.lineWidth = Math.random() * 2 + 1;
            ctx.beginPath();
            ctx.moveTo(Math.random() * width, Math.random() * height);
            ctx.lineTo(Math.random() * width, Math.random() * height);
            ctx.stroke();
        }

        // Random background dots
        for (let i = 0; i < 30; i++) {
            ctx.fillStyle = isDark ? "rgba(240, 240, 248, 0.3)" : "rgba(140, 42, 68, 0.25)";
            ctx.beginPath();
            ctx.arc(Math.random() * width, Math.random() * height, 1.2, 0, Math.PI * 2);
            ctx.fill();
        }

        // Draw rotated and distorted text characters
        const charSpacing = width / 7;
        for (let i = 0; i < generatedCaptchaCode.length; i++) {
            const char = generatedCaptchaCode[i];
            ctx.save();
            ctx.font = `bold ${Math.floor(Math.random() * 4 + 20)}px 'Courier New', monospace`;
            ctx.fillStyle = isDark ? "#f7a8b8" : "#8c2a44";
            
            const x = charSpacing * (i + 0.8);
            const y = height / 2 + Math.random() * 6 + 2;
            const angle = (Math.random() - 0.5) * 0.4; // slight rotation

            ctx.translate(x, y);
            ctx.rotate(angle);
            ctx.fillText(char, 0, 0);
            ctx.restore();
        }
    }

    if (refreshCaptchaBtn) {
        refreshCaptchaBtn.addEventListener("click", () => {
            generateCanvasCaptcha();
            if (captchaInput) {
                captchaInput.value = "";
                clearFieldState(captchaInput, "captchaError");
            }
        });
    }

    // Initial CAPTCHA creation
    generateCanvasCaptcha();

    // ----------------------------------------------------
    // 5. Helper Validation UI Functions
    // ----------------------------------------------------
    function setFieldError(inputElement, errorElementId, message) {
        const group = inputElement.closest(".form-group") || inputElement.closest(".fieldset-group");
        const errorElem = document.getElementById(errorElementId);
        
        if (group) {
            group.classList.add("has-error");
            group.classList.remove("has-success");
        }
        
        if (inputElement.setAttribute) {
            inputElement.setAttribute("aria-invalid", "true");
        }

        if (errorElem) {
            errorElem.textContent = message;
            errorElem.style.display = "block";
        }
        return false;
    }

    function setFieldSuccess(inputElement, errorElementId) {
        const group = inputElement.closest(".form-group") || inputElement.closest(".fieldset-group");
        const errorElem = document.getElementById(errorElementId);
        
        if (group) {
            group.classList.remove("has-error");
            group.classList.add("has-success");
        }
        
        if (inputElement.setAttribute) {
            inputElement.setAttribute("aria-invalid", "false");
        }

        if (errorElem) {
            errorElem.textContent = "";
            errorElem.style.display = "none";
        }
        return true;
    }

    function clearFieldState(inputElement, errorElementId) {
        const group = inputElement.closest(".form-group") || inputElement.closest(".fieldset-group");
        const errorElem = document.getElementById(errorElementId);
        
        if (group) {
            group.classList.remove("has-error");
            group.classList.remove("has-success");
        }
        
        if (inputElement.setAttribute) {
            inputElement.removeAttribute("aria-invalid");
        }

        if (errorElem) {
            errorElem.textContent = "";
            errorElem.style.display = "none";
        }
    }

    // ----------------------------------------------------
    // 6. Individual Field Validators
    // ----------------------------------------------------
    function validateFullName() {
        const val = fullNameInput.value.trim();
        if (!val) {
            return setFieldError(fullNameInput, "fullNameError", "Full Name is required.");
        }
        if (val.length < 3) {
            return setFieldError(fullNameInput, "fullNameError", "Name must be at least 3 characters long.");
        }
        if (!REGEX.name.test(val)) {
            return setFieldError(fullNameInput, "fullNameError", "Name can only contain alphabetic letters and spaces.");
        }
        return setFieldSuccess(fullNameInput, "fullNameError");
    }

    function validateEmail() {
        const val = emailInput.value.trim();
        if (!val) {
            return setFieldError(emailInput, "emailError", "Email address is required.");
        }
        if (!REGEX.email.test(val)) {
            return setFieldError(emailInput, "emailError", "Please enter a valid email format (e.g., student@domain.com).");
        }
        return setFieldSuccess(emailInput, "emailError");
    }

    function validateMobile() {
        const val = mobileInput.value.trim();
        if (!val) {
            return setFieldError(mobileInput, "mobileError", "Mobile number is required.");
        }
        if (!/^\d+$/.test(val)) {
            return setFieldError(mobileInput, "mobileError", "Mobile number must contain digits only.");
        }
        if (val.length !== 10) {
            return setFieldError(mobileInput, "mobileError", "Mobile number must be exactly 10 digits.");
        }
        if (!REGEX.mobile.test(val)) {
            return setFieldError(mobileInput, "mobileError", "Mobile number must start with 6, 7, 8, or 9.");
        }
        return setFieldSuccess(mobileInput, "mobileError");
    }

    // Password Strength Evaluator
    function evaluatePasswordStrength(val) {
        let score = 0;
        const checks = {
            length: val.length >= 8,
            upper: REGEX.hasUpper.test(val),
            lower: REGEX.hasLower.test(val),
            number: REGEX.hasNumber.test(val),
            special: REGEX.hasSpecial.test(val)
        };

        // Update checklist UI
        updateRuleUI(ruleLength, checks.length);
        updateRuleUI(ruleUpper, checks.upper);
        updateRuleUI(ruleLower, checks.lower);
        updateRuleUI(ruleNumber, checks.number);
        updateRuleUI(ruleSpecial, checks.special);

        if (checks.length) score += 1;
        if (checks.upper) score += 1;
        if (checks.lower) score += 1;
        if (checks.number) score += 1;
        if (checks.special) score += 1;
        if (val.length >= 12) score += 1; // bonus for length

        meterBar.className = "meter-bar";
        meterText.className = "meter-text";

        if (val.length === 0) {
            meterBar.style.width = "0%";
            meterText.textContent = "None";
            return { score: 0, isValid: false };
        } else if (score < 3) {
            meterBar.classList.add("weak");
            meterText.classList.add("weak");
            meterText.textContent = "Weak (Needs more variety)";
            return { score, isValid: false };
        } else if (score < 5) {
            meterBar.classList.add("medium");
            meterText.classList.add("medium");
            meterText.textContent = "Medium (Good, can be stronger)";
            return { score, isValid: checks.length && (checks.upper || checks.lower) && checks.number };
        } else {
            meterBar.classList.add("strong");
            meterText.classList.add("strong");
            meterText.textContent = "Strong (Excellent password)";
            return { score, isValid: true };
        }
    }

    function updateRuleUI(elem, isValid) {
        if (!elem) return;
        if (isValid) {
            elem.classList.add("valid");
            elem.querySelector("span").textContent = "✓";
        } else {
            elem.classList.remove("valid");
            elem.querySelector("span").textContent = "✕";
        }
    }

    function validatePassword() {
        const val = passwordInput.value;
        const evaluation = evaluatePasswordStrength(val);

        if (!val) {
            return setFieldError(passwordInput, "passwordError", "Password is required.");
        }
        if (val.length < 8) {
            return setFieldError(passwordInput, "passwordError", "Password must be at least 8 characters.");
        }
        if (!REGEX.hasUpper.test(val)) {
            return setFieldError(passwordInput, "passwordError", "Password must include at least one uppercase letter (A-Z).");
        }
        if (!REGEX.hasLower.test(val)) {
            return setFieldError(passwordInput, "passwordError", "Password must include at least one lowercase letter (a-z).");
        }
        if (!REGEX.hasNumber.test(val)) {
            return setFieldError(passwordInput, "passwordError", "Password must include at least one number (0-9).");
        }
        if (!REGEX.hasSpecial.test(val)) {
            return setFieldError(passwordInput, "passwordError", "Password must include at least one special character (@$!%*?&#).");
        }

        return setFieldSuccess(passwordInput, "passwordError");
    }

    function validateConfirmPassword() {
        const pass = passwordInput.value;
        const confirmPass = confirmPasswordInput.value;

        if (!confirmPass) {
            return setFieldError(confirmPasswordInput, "confirmPasswordError", "Please confirm your password.");
        }
        if (confirmPass !== pass) {
            return setFieldError(confirmPasswordInput, "confirmPasswordError", "Passwords do not match.");
        }
        return setFieldSuccess(confirmPasswordInput, "confirmPasswordError");
    }

    function validateCourse() {
        const val = courseSelect.value;
        if (!val) {
            return setFieldError(courseSelect, "courseError", "Please select your enrolled course/degree program.");
        }
        return setFieldSuccess(courseSelect, "courseError");
    }

    function validateYear() {
        const selectedYear = document.querySelector('input[name="year"]:checked');
        const fieldset = document.getElementById("groupYear");
        if (!selectedYear) {
            return setFieldError(fieldset, "yearError", "Please select your academic year.");
        }
        return setFieldSuccess(fieldset, "yearError");
    }

    function validateGender() {
        const selectedGender = document.querySelector('input[name="gender"]:checked');
        const fieldset = document.getElementById("groupGender");
        if (!selectedGender) {
            return setFieldError(fieldset, "genderError", "Please select your gender.");
        }
        return setFieldSuccess(fieldset, "genderError");
    }

    function validateCaptcha() {
        const val = captchaInput.value.trim();
        if (!val) {
            return setFieldError(captchaInput, "captchaError", "Security CAPTCHA code is required.");
        }
        if (val.toLowerCase() !== generatedCaptchaCode.toLowerCase()) {
            return setFieldError(captchaInput, "captchaError", "Incorrect CAPTCHA code. Please try again.");
        }
        return setFieldSuccess(captchaInput, "captchaError");
    }

    function validateTerms() {
        if (!termsCheckbox.checked) {
            return setFieldError(termsCheckbox, "termsError", "You must accept the Terms of Service to register.");
        }
        return setFieldSuccess(termsCheckbox, "termsError");
    }

    // ----------------------------------------------------
    // 7. Intermediate Extension: Real-Time Event Listeners
    // ----------------------------------------------------
    fullNameInput.addEventListener("input", () => {
        if (fullNameInput.value.length > 0) validateFullName();
    });
    fullNameInput.addEventListener("blur", validateFullName);

    emailInput.addEventListener("input", () => {
        if (emailInput.value.length > 0) validateEmail();
    });
    emailInput.addEventListener("blur", validateEmail);

    mobileInput.addEventListener("input", (e) => {
        // Enforce digits only during typing
        e.target.value = e.target.value.replace(/\D/g, "");
        if (mobileInput.value.length > 0) validateMobile();
    });
    mobileInput.addEventListener("blur", validateMobile);

    passwordInput.addEventListener("input", () => {
        evaluatePasswordStrength(passwordInput.value);
        if (passwordInput.value.length > 0) validatePassword();
        if (confirmPasswordInput.value.length > 0) validateConfirmPassword();
    });
    passwordInput.addEventListener("blur", validatePassword);

    confirmPasswordInput.addEventListener("input", () => {
        if (confirmPasswordInput.value.length > 0) validateConfirmPassword();
    });
    confirmPasswordInput.addEventListener("blur", validateConfirmPassword);

    courseSelect.addEventListener("change", validateCourse);

    document.querySelectorAll('input[name="year"]').forEach(radio => {
        radio.addEventListener("change", validateYear);
    });

    document.querySelectorAll('input[name="gender"]').forEach(radio => {
        radio.addEventListener("change", validateGender);
    });

    captchaInput.addEventListener("input", () => {
        if (captchaInput.value.length === 6) validateCaptcha();
    });
    captchaInput.addEventListener("blur", validateCaptcha);

    termsCheckbox.addEventListener("change", validateTerms);

    // ----------------------------------------------------
    // 8. Password Toggle Visibility Feature
    // ----------------------------------------------------
    if (togglePasswordBtn) {
        togglePasswordBtn.addEventListener("click", () => {
            const isPassword = passwordInput.getAttribute("type") === "password";
            passwordInput.setAttribute("type", isPassword ? "text" : "password");
            togglePasswordBtn.textContent = isPassword ? "🙈" : "👁️";
        });
    }

    if (toggleConfirmPasswordBtn) {
        toggleConfirmPasswordBtn.addEventListener("click", () => {
            const isPassword = confirmPasswordInput.getAttribute("type") === "password";
            confirmPasswordInput.setAttribute("type", isPassword ? "text" : "password");
            toggleConfirmPasswordBtn.textContent = isPassword ? "🙈" : "👁️";
        });
    }

    // ----------------------------------------------------
    // 9. Reset Button Handling
    // ----------------------------------------------------
    form.addEventListener("reset", () => {
        setTimeout(() => {
            document.querySelectorAll(".form-group, .fieldset-group").forEach(group => {
                group.classList.remove("has-error", "has-success");
            });
            document.querySelectorAll(".error-msg").forEach(span => {
                span.textContent = "";
                span.style.display = "none";
            });
            document.querySelectorAll(".rule-item").forEach(item => {
                item.classList.remove("valid");
                item.querySelector("span").textContent = "✕";
            });
            meterBar.className = "meter-bar";
            meterBar.style.width = "0%";
            meterText.className = "meter-text";
            meterText.textContent = "None";
            successBanner.classList.add("hidden");
            generateCanvasCaptcha();
        }, 10);
    });

    // ----------------------------------------------------
    // 10. Form Submission & Comprehensive Validation
    // ----------------------------------------------------
    form.addEventListener("submit", (event) => {
        event.preventDefault();

        // Run all field validations
        const isNameValid = validateFullName();
        const isEmailValid = validateEmail();
        const isMobileValid = validateMobile();
        const isPasswordValid = validatePassword();
        const isConfirmValid = validateConfirmPassword();
        const isCourseValid = validateCourse();
        const isYearValid = validateYear();
        const isGenderValid = validateGender();
        const isCaptchaValid = validateCaptcha();
        const isTermsValid = validateTerms();

        const isFormValid = (
            isNameValid &&
            isEmailValid &&
            isMobileValid &&
            isPasswordValid &&
            isConfirmValid &&
            isCourseValid &&
            isYearValid &&
            isGenderValid &&
            isCaptchaValid &&
            isTermsValid
        );

        if (!isFormValid) {
            // Accessible UX: Focus first invalid field
            const firstInvalidInput = form.querySelector('[aria-invalid="true"]');
            if (firstInvalidInput) {
                firstInvalidInput.focus();
                firstInvalidInput.scrollIntoView({ behavior: "smooth", block: "center" });
            }
            return;
        }

        // All validations passed!
        const selectedYear = document.querySelector('input[name="year"]:checked').value;
        const selectedGender = document.querySelector('input[name="gender"]:checked').value;

        successDetails.innerHTML = `
            <strong>Student:</strong> ${fullNameInput.value.trim()}<br>
            <strong>Email:</strong> ${emailInput.value.trim()}<br>
            <strong>Mobile:</strong> +91 ${mobileInput.value.trim()}<br>
            <strong>Course:</strong> ${courseSelect.value} (${selectedYear})<br>
            <strong>Gender:</strong> ${selectedGender}
        `;

        successBanner.classList.remove("hidden");
        successBanner.scrollIntoView({ behavior: "smooth", block: "start" });

        // Submit to PHP backend for secure database insert (Practical 9)
        setTimeout(() => {
            form.submit();
        }, 1200);
    });
});
