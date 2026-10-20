// Practical 6: Fetch API, Search, Filter, Sort and Pagination

let allEvents = [];
let currentPage = 1;
const itemsPerPage = 6;

const searchInput = document.getElementById("searchInput");
const categoryFilter = document.getElementById("categoryFilter");
const sortSelect = document.getElementById("sortSelect");
const eventsGrid = document.getElementById("eventsGrid");
const paginationBar = document.getElementById("paginationBar");
const loadingState = document.getElementById("loadingState");
const errorState = document.getElementById("errorState");
const emptyState = document.getElementById("emptyState");

// Fetch events from external JSON file
function loadEvents() {
    loadingState.style.display = "block";
    errorState.style.display = "none";
    emptyState.style.display = "none";

    fetch("api/events.php")
        .then(response => {
            if (!response.ok) throw new Error("API error: " + response.status);
            return response.json();
        })
        .then(res => {
            const data = (res && res.data) ? res.data : res;
            allEvents = data;
            localStorage.setItem("cached_events", JSON.stringify(data));
            loadingState.style.display = "none";
            render();
        })
        .catch(() => {
            // Fallback to static events.json if PHP server unavailable
            return fetch("events.json")
                .then(r => r.json())
                .then(data => {
                    allEvents = data;
                    localStorage.setItem("cached_events", JSON.stringify(data));
                    loadingState.style.display = "none";
                    render();
                });
        })
        .catch(err => {
            console.error("Fetch failed:", err);
            // Fallback to cache if available
            const cached = localStorage.getItem("cached_events");
            if (cached) {
                allEvents = JSON.parse(cached);
                loadingState.style.display = "none";
                render();
            } else {
                loadingState.style.display = "none";
                errorState.style.display = "block";
                errorState.textContent = "Failed to load events. Please try again later.";
            }
        });
}

// Filter, Sort and Paginate Events
function getProcessedEvents() {
    let result = [...allEvents];

    // 1. Search filter
    const query = searchInput.value.trim().toLowerCase();
    if (query) {
        result = result.filter(item => 
            item.title.toLowerCase().includes(query) ||
            item.description.toLowerCase().includes(query) ||
            item.location.toLowerCase().includes(query)
        );
    }

    // 2. Category filter
    const category = categoryFilter.value;
    if (category !== "All") {
        result = result.filter(item => item.category === category);
    }

    // 3. Sorting
    const sortVal = sortSelect.value;
    if (sortVal === "date-asc") {
        result.sort((a, b) => new Date(a.date) - new Date(b.date));
    } else if (sortVal === "date-desc") {
        result.sort((a, b) => new Date(b.date) - new Date(a.date));
    } else if (sortVal === "title-asc") {
        result.sort((a, b) => a.title.localeCompare(b.title));
    }

    return result;
}

// Render cards and pagination
function render() {
    const filtered = getProcessedEvents();
    const totalPages = Math.ceil(filtered.length / itemsPerPage);

    if (currentPage > totalPages) {
        currentPage = 1;
    }

    if (filtered.length === 0) {
        eventsGrid.innerHTML = "";
        emptyState.style.display = "block";
        paginationBar.innerHTML = "";
        return;
    } else {
        emptyState.style.display = "none";
    }

    // Pagination slice
    const startIndex = (currentPage - 1) * itemsPerPage;
    const paginatedItems = filtered.slice(startIndex, startIndex + itemsPerPage);

    // Render Event Cards
    eventsGrid.innerHTML = paginatedItems.map(item => `
        <div class="event-card">
            <div>
                <span class="event-tag">${item.category}</span>
                <h3>${item.title}</h3>
                <div class="event-meta">📅 <strong>Date:</strong> ${item.date}</div>
                <div class="event-meta">📍 <strong>Venue:</strong> ${item.location}</div>
                <div class="event-meta">👥 <strong>Seats:</strong> ${item.seats}</div>
                <p class="event-desc">${item.description}</p>
            </div>
        </div>
    `).join("");

    // Render Pagination Controls
    let pagHtml = `<button ${currentPage === 1 ? 'disabled' : ''} onclick="changePage(${currentPage - 1})">Prev</button>`;
    for (let i = 1; i <= totalPages; i++) {
        pagHtml += `<button class="${i === currentPage ? 'active' : ''}" onclick="changePage(${i})">${i}</button>`;
    }
    pagHtml += `<button ${currentPage === totalPages ? 'disabled' : ''} onclick="changePage(${currentPage + 1})">Next</button>`;

    paginationBar.innerHTML = pagHtml;
}

function changePage(page) {
    currentPage = page;
    render();
}

// Event Listeners for search, filter, and sort
searchInput.addEventListener("input", () => {
    currentPage = 1;
    render();
});

categoryFilter.addEventListener("change", () => {
    currentPage = 1;
    render();
});

sortSelect.addEventListener("change", () => {
    render();
});

// Initial Load
document.addEventListener("DOMContentLoaded", loadEvents);
