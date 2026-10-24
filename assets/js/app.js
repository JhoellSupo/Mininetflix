/**
 * MiniNetflix - Frontend Logic & Video Player Engine
 */

document.addEventListener('DOMContentLoaded', () => {
    initNavbarScroll();
    initCarousels();
    initMovieModals();
    initFavoriteButtons();
    initSearch();
    initCategoryFilter();
});

// 1. Efecto Navbar al hacer Scroll
function initNavbarScroll() {
    const navbar = document.querySelector('.navbar');
    if (!navbar) return;

    window.addEventListener('scroll', () => {
        if (window.scrollY > 40) {
            navbar.classList.add('scrolled');
        } else {
            navbar.classList.remove('scrolled');
        }
    });
}

// 2. Control de Desplazamiento de Carruseles
function initCarousels() {
    document.querySelectorAll('.carousel-wrapper').forEach(wrapper => {
        const grid = wrapper.querySelector('.movie-row-grid');
        const prevBtn = wrapper.querySelector('.carousel-btn.prev');
        const nextBtn = wrapper.querySelector('.carousel-btn.next');

        if (!grid) return;

        const scrollAmount = () => grid.clientWidth * 0.75;

        if (prevBtn) {
            prevBtn.addEventListener('click', () => {
                grid.scrollBy({ left: -scrollAmount(), behavior: 'smooth' });
            });
        }

        if (nextBtn) {
            nextBtn.addEventListener('click', () => {
                grid.scrollBy({ left: scrollAmount(), behavior: 'smooth' });
            });
        }
    });
}

// 3. Modal de Detalles y Reproductor HTML5 con Presigned URLs
let historyThrottleTimer = null;

function initMovieModals() {
    const modal = document.getElementById('movieModal');
    if (!modal) return;

    const closeBtn = modal.querySelector('.modal-close-btn');
    const player = document.getElementById('modalVideoPlayer');

    // Cerrar modal
    const closeModal = () => {
        modal.classList.remove('is-open');
        document.body.style.overflow = '';
        if (player) {
            player.pause();
            player.removeAttribute('src'); // Detener streaming
            player.load();
        }
    };

    if (closeBtn) {
        closeBtn.addEventListener('click', closeModal);
    }

    modal.addEventListener('click', (e) => {
        if (e.target === modal) {
            closeModal();
        }
    });

    document.addEventListener('keydown', (e) => {
        if (e.key === 'Escape' && modal.classList.contains('is-open')) {
            closeModal();
        }
    });

    // Delegación de eventos para abrir modal desde cualquier tarjeta de película
    document.addEventListener('click', (e) => {
        const trigger = e.target.closest('[data-modal-movie]');
        if (!trigger) return;

        // Si se hizo click en el botón de favorito, no abrir modal
        if (e.target.closest('.btn-toggle-favorite')) {
            return;
        }

        const data = trigger.dataset;
        openMovieModal(data);
    });

    // Eventos del Reproductor de Video para Historial de Reproducciones
    if (player) {
        player.addEventListener('timeupdate', () => {
            const movieId = player.dataset.movieId;
            if (!movieId) return;

            // Throttle de reporte a cada 5 segundos
            if (!historyThrottleTimer) {
                historyThrottleTimer = setTimeout(() => {
                    reportProgress(movieId, Math.floor(player.currentTime), Math.floor(player.duration || 0), false);
                    historyThrottleTimer = null;
                }, 5000);
            }
        });

        player.addEventListener('ended', () => {
            const movieId = player.dataset.movieId;
            if (!movieId) return;
            reportProgress(movieId, Math.floor(player.duration || 0), Math.floor(player.duration || 0), true);
            showToast('¡Has completado este trailer!');
        });
    }
}

function openMovieModal(data) {
    const modal = document.getElementById('movieModal');
    if (!modal) return;

    const player = document.getElementById('modalVideoPlayer');
    const titleEl = document.getElementById('modalMovieTitle');
    const descEl = document.getElementById('modalMovieDesc');
    const categoryEl = document.getElementById('modalMovieCategory');
    const yearEl = document.getElementById('modalMovieYear');
    const durationEl = document.getElementById('modalMovieDuration');
    const ratingEl = document.getElementById('modalMovieRating');
    const favBtn = document.getElementById('modalFavBtn');
    const fullWatchBtn = document.getElementById('modalFullWatchBtn');

    if (titleEl) titleEl.textContent = data.title || '';
    if (descEl) descEl.textContent = data.description || '';
    if (categoryEl) categoryEl.textContent = data.category || '';
    if (yearEl) yearEl.textContent = data.year || '';
    if (durationEl) durationEl.textContent = data.duration || '';
    if (ratingEl) ratingEl.textContent = data.rating ? `★ ${data.rating}` : '';

    if (fullWatchBtn && data.id) {
        fullWatchBtn.href = `${window.BASE_URL || ''}/watch.php?id=${data.id}`;
    }

    if (favBtn) {
        favBtn.dataset.movieId = data.id;
        const isFav = data.isFavorite === '1' || data.isFavorite === 'true';
        updateFavoriteButtonVisual(favBtn, isFav);
    }

    if (player && data.trailerUrl) {
        player.dataset.movieId = data.id;
        player.src = data.trailerUrl;
        player.load();
        player.play().catch(() => {
            // Autoplay bloqueado por el navegador
        });
    }

    modal.classList.add('is-open');
    document.body.style.overflow = 'hidden';
}

// 4. Reporte Asíncrono de Historial de Reproducciones
function reportProgress(movieId, progressSeconds, totalSeconds, completed) {
    if (!movieId) return;
    const url = `${window.BASE_URL || ''}/api/history.php`;

    fetch(url, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({
            movie_id: movieId,
            progress_seconds: progressSeconds,
            total_seconds: totalSeconds,
            completed: completed ? 1 : 0
        })
    }).catch(err => console.error('Error reportando historial:', err));
}

// 5. Botones de Favorito (Mi Lista)
function initFavoriteButtons() {
    document.addEventListener('click', (e) => {
        const btn = e.target.closest('.btn-toggle-favorite');
        if (!btn) return;
        e.stopPropagation();

        const movieId = btn.dataset.movieId;
        if (!movieId) return;

        toggleFavorite(movieId, btn);
    });
}

function toggleFavorite(movieId, buttonEl) {
    const url = `${window.BASE_URL || ''}/api/favorite.php`;

    fetch(url, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ movie_id: movieId })
    })
    .then(res => res.json())
    .then(data => {
        if (data.success) {
            updateFavoriteButtonVisual(buttonEl, data.is_favorite);
            // Si el botón está en el modal o en tarjetas, sincronizar todos los botones con ese movieId
            document.querySelectorAll(`.btn-toggle-favorite[data-movie-id="${movieId}"]`).forEach(b => {
                updateFavoriteButtonVisual(b, data.is_favorite);
            });
            showToast(data.message);
        } else {
            showToast(data.error || 'No se pudo actualizar favoritos.', 'error');
        }
    })
    .catch(err => {
        showToast('Error de conexión al servidor.', 'error');
    });
}

function updateFavoriteButtonVisual(btn, isFavorite) {
    if (!btn) return;
    const icon = btn.querySelector('i, span.fav-icon');
    const label = btn.querySelector('.fav-label');

    if (isFavorite) {
        btn.classList.add('is-active');
        if (icon) icon.innerHTML = '&#10004;'; // checkmark
        if (label) label.textContent = 'En Mi Lista';
        btn.setAttribute('title', 'Eliminar de Mi Lista');
    } else {
        btn.classList.remove('is-active');
        if (icon) icon.innerHTML = '+';
        if (label) label.textContent = 'Mi Lista';
        btn.setAttribute('title', 'Agregar a Mi Lista');
    }
}

// 6. Búsqueda y Filtro en Vivo (Ultra Rápida: 0 ms en catálogo)
const searchResultsCache = new Map();

function normalizeSearchText(str) {
    return (str || '')
        .toLowerCase()
        .normalize('NFD')
        .replace(/[\u0300-\u036f]/g, '');
}

function initSearch() {
    const searchInput = document.querySelector('.search-input');
    if (!searchInput) return;

    let debounceTimer;

    searchInput.addEventListener('input', (e) => {
        const query = e.target.value.trim();
        const normQuery = normalizeSearchText(query);

        // 1. Filtrado instantáneo en vivo (0 ms) si hay tarjetas de películas en la página
        const catalogCards = document.querySelectorAll('.catalog-container .movie-card, .movies-grid .movie-card');
        if (catalogCards.length > 0) {
            let matchCount = 0;
            const sections = document.querySelectorAll('.category-row');

            catalogCards.forEach(card => {
                const title = normalizeSearchText(card.dataset.title || '');
                const desc = normalizeSearchText(card.dataset.description || '');
                const cat = normalizeSearchText(card.dataset.category || '');

                const isMatch = (normQuery.length === 0) || 
                                title.includes(normQuery) || 
                                cat.includes(normQuery) || 
                                desc.includes(normQuery);

                if (isMatch) {
                    card.style.display = '';
                    card.classList.remove('is-search-hidden');
                    matchCount++;
                } else {
                    card.style.display = 'none';
                    card.classList.add('is-search-hidden');
                }
            });

            // Ocultar secciones/carruseles que no tengan películas coincidentes
            sections.forEach(sec => {
                const visibleInSec = sec.querySelectorAll('.movie-card:not(.is-search-hidden)').length;
                if (normQuery.length > 0 && visibleInSec === 0) {
                    sec.style.display = 'none';
                } else {
                    sec.style.display = '';
                }
            });

            // Actualizar etiqueta del contador si existe
            const countLabel = document.querySelector('.catalog-visible-count');
            if (countLabel) {
                if (normQuery.length > 0) {
                    countLabel.textContent = `(${matchCount} encontradas para "${query}")`;
                } else {
                    countLabel.textContent = `(${catalogCards.length} títulos disponibles)`;
                }
            }

            // Mensaje de sin resultados amigable
            let emptyState = document.getElementById('catalog-empty-search');
            const grid = document.querySelector('.catalog-container .movies-grid');
            if (normQuery.length > 0 && matchCount === 0) {
                if (!emptyState && grid) {
                    emptyState = document.createElement('div');
                    emptyState.id = 'catalog-empty-search';
                    emptyState.style.cssText = 'text-align: center; padding: 60px 20px; width: 100%; grid-column: 1 / -1; color: var(--text-muted);';
                    emptyState.innerHTML = `
                        <div style="font-size: 2.5rem; margin-bottom: 10px;">&#128269;</div>
                        <h3 style="color: white; font-size: 1.3rem; margin-bottom: 6px;">No se encontraron películas</h3>
                        <p style="font-size: 0.9rem;">No hay títulos que coincidan con "<strong>${escapeHtml(query)}</strong>"</p>
                    `;
                    grid.appendChild(emptyState);
                }
                if (emptyState) emptyState.style.display = 'block';
            } else if (emptyState) {
                emptyState.style.display = 'none';
            }

            closeSearchResults();
            return;
        }

        // 2. Si estamos en otra página (ej. watch o panel), buscar vía API ultra rápida en caché
        clearTimeout(debounceTimer);
        debounceTimer = setTimeout(() => {
            if (normQuery.length >= 2) {
                performLiveSearch(query);
            } else {
                closeSearchResults();
            }
        }, 80);
    });

    // Tecla Escape limpia la búsqueda al instante
    searchInput.addEventListener('keydown', (e) => {
        if (e.key === 'Escape') {
            searchInput.value = '';
            searchInput.dispatchEvent(new Event('input'));
            searchInput.blur();
        }
    });
}

function performLiveSearch(query) {
    const cacheKey = query.trim().toLowerCase();
    if (searchResultsCache.has(cacheKey)) {
        renderSearchResults(searchResultsCache.get(cacheKey), query);
        return;
    }

    const url = `${window.BASE_URL || ''}/api/search.php?q=${encodeURIComponent(query)}`;

    fetch(url)
        .then(res => res.json())
        .then(data => {
            if (data.success) {
                searchResultsCache.set(cacheKey, data.results);
                renderSearchResults(data.results, query);
            }
        })
        .catch(err => console.error('Error buscando películas:', err));
}

function renderSearchResults(results, query) {
    let container = document.getElementById('liveSearchResults');
    if (!container) {
        container = document.createElement('div');
        container.id = 'liveSearchResults';
        container.className = 'live-search-overlay';
        document.body.appendChild(container);
    }

    if (results.length === 0) {
        container.innerHTML = `
            <div class="search-results-modal">
                <div class="search-results-header">
                    <h3>Resultados para "${escapeHtml(query)}"</h3>
                    <button class="icon-btn" onclick="closeSearchResults()">&times;</button>
                </div>
                <div class="search-empty">
                    <p>No se encontraron películas ni trailers con ese término.</p>
                </div>
            </div>
        `;
    } else {
        const cardsHtml = results.map(m => `
            <div class="movie-card" data-modal-movie
                 data-id="${m.id}"
                 data-title="${escapeHtml(m.title)}"
                 data-description="${escapeHtml(m.description)}"
                 data-category="${escapeHtml(m.category_name)}"
                 data-year="${m.release_year}"
                 data-duration="${m.duration_formatted}"
                 data-rating="${m.rating}"
                 data-trailer-url="${m.trailer_url}"
                 data-is-favorite="${m.is_favorite}">
                <div class="card-poster-wrapper">
                    <img class="card-poster" src="${m.cover_url}" alt="${escapeHtml(m.title)}" loading="lazy">
                    <div class="card-overlay-actions">
                        <div class="action-buttons">
                            <span class="icon-btn btn-play-circle">&#9658;</span>
                            <button class="icon-btn btn-toggle-favorite ${m.is_favorite ? 'is-active' : ''}" data-movie-id="${m.id}">
                                <span class="fav-icon">${m.is_favorite ? '&#10004;' : '+'}</span>
                            </button>
                        </div>
                    </div>
                </div>
                <div class="card-info">
                    <h4 class="card-title">${escapeHtml(m.title)}</h4>
                    <div class="card-meta-line">
                        <span class="card-rating">★ ${m.rating}</span>
                        <span>${m.release_year}</span>
                    </div>
                </div>
            </div>
        `).join('');

        container.innerHTML = `
            <div class="search-results-modal">
                <div class="search-results-header">
                    <h3>Resultados para "${escapeHtml(query)}" (${results.length})</h3>
                    <button class="icon-btn" onclick="closeSearchResults()">&times;</button>
                </div>
                <div class="movies-grid">${cardsHtml}</div>
            </div>
        `;
    }

    container.classList.add('is-visible');
}

function closeSearchResults() {
    const container = document.getElementById('liveSearchResults');
    if (container) {
        container.classList.remove('is-visible');
    }
}

// 7. Sistema de Notificaciones Toast
function showToast(message, type = 'info') {
    let toastContainer = document.getElementById('toastContainer');
    if (!toastContainer) {
        toastContainer = document.createElement('div');
        toastContainer.id = 'toastContainer';
        toastContainer.style.cssText = 'position:fixed;bottom:24px;right:24px;z-index:9999;display:flex;flex-direction:column;gap:10px;';
        document.body.appendChild(toastContainer);
    }

    const toast = document.createElement('div');
    const bgColor = type === 'error' ? 'rgba(229, 9, 20, 0.95)' : 'rgba(24, 25, 31, 0.95)';
    const borderColor = type === 'error' ? '#ff3b44' : 'rgba(255, 255, 255, 0.2)';

    toast.style.cssText = `
        background: ${bgColor};
        color: white;
        padding: 12px 20px;
        border-radius: 8px;
        border: 1px solid ${borderColor};
        box-shadow: 0 10px 25px rgba(0, 0, 0, 0.6);
        font-size: 0.9rem;
        font-weight: 500;
        display: flex;
        align-items: center;
        gap: 10px;
        transform: translateY(20px);
        opacity: 0;
        transition: all 0.3s ease;
    `;
    toast.textContent = message;

    toastContainer.appendChild(toast);
    setTimeout(() => {
        toast.style.transform = 'translateY(0)';
        toast.style.opacity = '1';
    }, 10);

    setTimeout(() => {
        toast.style.transform = 'translateY(20px)';
        toast.style.opacity = '0';
        setTimeout(() => toast.remove(), 300);
    }, 3200);
}

function escapeHtml(text) {
    if (!text) return '';
    return String(text)
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;')
        .replace(/'/g, '&#039;');
}

// 7. Filtrado Instantáneo por Categoría (Sin recarga de página)
function initCategoryFilter() {
    const pills = document.querySelectorAll('.category-filter-pill');
    if (!pills.length) return;

    pills.forEach(pill => {
        pill.addEventListener('click', (e) => {
            const grid = document.querySelector('.movies-grid');
            if (!grid) {
                // Si no está en vista cuadrícula, permitir navegación normal
                return;
            }

            e.preventDefault();
            const catId = parseInt(pill.dataset.filterCategory, 10) || 0;

            // Actualizar estado visual de las píldoras
            pills.forEach(p => {
                p.classList.remove('active');
                p.style.background = '';
                p.style.color = '';
                p.style.borderColor = '';
            });
            pill.classList.add('active');
            pill.style.background = 'var(--primary-red)';
            pill.style.color = '#ffffff';
            pill.style.borderColor = 'var(--primary-red)';

            // Filtrar tarjetas en el DOM al instante
            const cards = grid.querySelectorAll('.movie-card');
            let visibleCount = 0;

            cards.forEach(card => {
                const cardCatId = parseInt(card.dataset.categoryId, 10);
                if (catId === 0 || cardCatId === catId) {
                    card.style.display = '';
                    card.classList.remove('is-filtered-out');
                    visibleCount++;
                } else {
                    card.style.display = 'none';
                    card.classList.add('is-filtered-out');
                }
            });

            // Actualizar contadores visibles
            const toolbarBadge = document.querySelector('.catalog-toolbar .tech-badge');
            if (toolbarBadge) {
                toolbarBadge.textContent = `${visibleCount} películas`;
            }
            const headerCount = document.querySelector('.catalog-visible-count');
            if (headerCount) {
                headerCount.textContent = `(${visibleCount} títulos disponibles)`;
            }

            // Mensaje de categoría vacía si aplica
            let emptyMsg = document.getElementById('filterEmptyState');
            if (visibleCount === 0) {
                if (!emptyMsg) {
                    emptyMsg = document.createElement('div');
                    emptyMsg.id = 'filterEmptyState';
                    emptyMsg.style.cssText = 'grid-column: 1 / -1; text-align: center; padding: 50px 20px; background: var(--bg-card); border-radius: var(--radius-lg); border: 1px solid var(--border-subtle);';
                    emptyMsg.innerHTML = '<div style="font-size: 2.2rem; margin-bottom: 10px;">&#127916;</div><h4 style="font-size: 1.2rem; margin-bottom: 6px;">No hay películas en esta categoría</h4><p style="color: var(--text-secondary); font-size: 0.9rem;">Prueba seleccionando otra categoría o todas las películas.</p>';
                    grid.appendChild(emptyMsg);
                }
                emptyMsg.style.display = 'block';
            } else if (emptyMsg) {
                emptyMsg.style.display = 'none';
            }

            // Actualizar URL sin recargar
            const url = new URL(window.location.href);
            if (catId === 0) {
                url.searchParams.delete('category');
            } else {
                url.searchParams.set('category', catId);
            }
            window.history.pushState({ category: catId }, '', url);
        });
    });

    // Soporte para navegar hacia atrás/adelante en el historial del navegador
    window.addEventListener('popstate', (e) => {
        const url = new URL(window.location.href);
        const catId = parseInt(url.searchParams.get('category'), 10) || 0;
        const targetPill = document.querySelector(`.category-filter-pill[data-filter-category="${catId}"]`);
        if (targetPill) {
            targetPill.click();
        }
    });
}
