document.addEventListener("DOMContentLoaded", function() {
    const loginForm = document.getElementById("loginForm");
    const registerForm = document.getElementById("registerForm");

    if (loginForm) {
        loginForm.addEventListener("submit", handleLogin);
    }

    if (registerForm) {
        registerForm.addEventListener("submit", handleRegister);
    }

    checkAuth();
});

function checkAuth() {
    const token = localStorage.getItem("tcg_token");
    const currentPage = window.location.pathname;

    if (token && (currentPage.includes("login.php") || currentPage.includes("register.php"))) {
        window.location.href = "/index.php";
    }

    if (!token && currentPage.includes("index.php")) {
        window.location.href = "/login.php";
    }
}

async function handleLogin(e) {
    e.preventDefault();

    const email = document.getElementById("email").value;
    const password = document.getElementById("password").value;

    try {
        const result = await api.login(email, password);
        window.location.href = "/index.php";
    } catch (error) {
        showError(error.message);
    }
}

async function handleRegister(e) {
    e.preventDefault();

    const username = document.getElementById("username").value;
    const email = document.getElementById("email").value;
    const password = document.getElementById("password").value;
    const confirmPassword = document.getElementById("confirmPassword").value;

    if (password !== confirmPassword) {
        showError("Passwords do not match");
        return;
    }

    if (password.length < 8) {
        showError("Password must be at least 8 characters");
        return;
    }

    try {
        const result = await api.register(username, email, password);
        window.location.href = "/index.php";
    } catch (error) {
        showError(error.message);
    }
}

function showError(message) {
    let errorDiv = document.querySelector(".alert-danger");

    if (!errorDiv) {
        errorDiv = document.createElement("div");
        errorDiv.className = "alert alert-danger";
        document.querySelector(".auth-box").insertBefore(errorDiv, document.querySelector(".auth-box").firstChild.nextSibling);
    }

    errorDiv.textContent = message;
}

function clearError() {
    const errorDiv = document.querySelector(".alert-danger");
    if (errorDiv) {
        errorDiv.remove();
    }
}
