<?php
/**
 * MiniNetflix - Footer y Modal Global de Película
 */
?>
    <!-- Modal Global de Reproducción de Trailer y Detalles -->
    <div id="movieModal" class="modal-backdrop" role="dialog" aria-modal="true" aria-labelledby="modalMovieTitle">
        <div class="modal-card">
            <button class="modal-close-btn" aria-label="Cerrar modal">&times;</button>
            
            <div class="modal-video-container">
                <video id="modalVideoPlayer" controls playsinline preload="metadata" class="modal-video-player">
                    Tu navegador no soporta el elemento de video HTML5.
                </video>
            </div>

            <div class="modal-body">
                <div class="modal-meta-row">
                    <span id="modalMovieRating" class="meta-rating" style="color: var(--accent-gold); font-weight: 700;"></span>
                    <span id="modalMovieYear" class="meta-year"></span>
                    <span id="modalMovieDuration" class="meta-duration"></span>
                    <span class="badge-res">4K Ultra HD</span>
                </div>

                <h2 id="modalMovieTitle" class="modal-title">Título de la Película</h2>
                <p id="modalMovieDesc" class="modal-description">Descripción de la película...</p>

                <div style="display: flex; gap: 14px; margin-bottom: 24px; flex-wrap: wrap;">
                    <a id="modalFullWatchBtn" href="#" class="btn btn-primary">
                        <span>&#9658;</span> Ver en Modo Cine
                    </a>
                    <button id="modalFavBtn" class="btn btn-secondary btn-toggle-favorite" data-movie-id="">
                        <span class="fav-icon">+</span>
                        <span class="fav-label">Mi Lista</span>
                    </button>
                </div>

                <div class="modal-details-grid">
                    <div>
                        <div class="detail-item">
                            <span class="detail-label">Categoría: </span>
                            <span id="modalMovieCategory" class="detail-value">Acción</span>
                        </div>
                        <div class="detail-item">
                            <span class="detail-label">Calidad: </span>
                            <span class="detail-value" style="color: #6ee7b7;">Full HD / 4K Ultra HD</span>
                        </div>
                    </div>
                    <div>
                        <div class="detail-item">
                            <span class="detail-label">Audio: </span>
                            <span class="detail-value">Español 5.1 / Inglés Original</span>
                        </div>
                        <div class="detail-item">
                            <span class="detail-label">Subtítulos: </span>
                            <span class="detail-value">Español, Inglés</span>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Pie de Página -->
    <footer class="footer">
        <div class="footer-content" style="justify-content: center; text-align: center;">
            <p style="color: var(--text-muted); font-size: 0.88rem; margin: 0;">
                &copy; <?= date('Y') ?> <?= APP_NAME ?>. Todos los derechos reservados.
            </p>
        </div>
    </footer>

    <!-- Scripts -->
    <script src="<?= BASE_URL ?>/assets/js/app.js?v=<?= file_exists(__DIR__ . '/../assets/js/app.js') ? filemtime(__DIR__ . '/../assets/js/app.js') : time() ?>"></script>
</body>
</html>
