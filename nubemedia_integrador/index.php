<?php
declare(strict_types=1);
require __DIR__ . '/config/database.php';

function h(?string $value): string
{
    return htmlspecialchars((string)$value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

$search = trim((string)($_GET['q'] ?? ''));
$category = trim((string)($_GET['category'] ?? ''));
$allowedCategories = ['Video', 'Música', 'Documento'];
if (!in_array($category, $allowedCategories, true)) {
    $category = '';
}

try {
    $categories = $pdo->query("SELECT DISTINCT category FROM public.media_resources WHERE is_active = TRUE ORDER BY category")->fetchAll(PDO::FETCH_COLUMN);

    $sql = "SELECT id, title, description, category, provider, external_url, created_at
            FROM public.media_resources
            WHERE is_active = TRUE";
    $params = [];

    if ($search !== '') {
        $sql .= " AND (title ILIKE :title_search OR description ILIKE :description_search OR provider ILIKE :provider_search)";
        $needle = '%' . $search . '%';
        $params[':title_search'] = $needle;
        $params[':description_search'] = $needle;
        $params[':provider_search'] = $needle;
    }
    if ($category !== '') {
        $sql .= " AND category = :category";
        $params[':category'] = $category;
    }
    $sql .= " ORDER BY is_featured DESC, title ASC";

    $stmt = $pdo->prepare($sql);
    foreach ($params as $key => $value) {
        $stmt->bindValue($key, $value, PDO::PARAM_STR);
    }
    $stmt->execute();
    $resources = $stmt->fetchAll();
    $totalResources = (int)$pdo->query("SELECT COUNT(*) FROM public.media_resources WHERE is_active = TRUE")->fetchColumn();
} catch (PDOException $e) {
    error_log('NubeMedia: error consultando el catálogo: ' . $e->getMessage());
    http_response_code(500);
    exit('No fue posible consultar el catálogo. Revisa los registros del servicio.');
}

$providerIcons = [
    'YouTube' => '▶',
    'Spotify' => '♫',
    'Google Drive' => '▣',
    'Blender Studio' => '✦',
];
$providerColors = [
    'YouTube' => 'youtube',
    'Spotify' => 'spotify',
    'Google Drive' => 'drive',
    'Blender Studio' => 'blender',
];
?>
<!doctype html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="description" content="NubeMedia: catálogo de videos, música y documentos compartidos mediante plataformas digitales en la nube.">
    <meta name="theme-color" content="#111827">
    <title>NubeMedia | Biblioteca digital</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@400;500;600;700;800&family=Manrope:wght@600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="assets/styles.css">
</head>
<body>
    <div class="page-shell">
        <header class="site-header">
            <a class="brand" href="index.php" aria-label="NubeMedia, inicio">
                <span class="brand-mark"><span></span><span></span><span></span></span>
                <span>Nube<span class="brand-accent">Media</span></span>
            </a>
            <nav class="header-nav" aria-label="Navegación principal">
                <a href="#catalogo">Explorar</a>
                <a href="#acerca">Acerca de</a>
            </nav>
            <a class="header-pill" href="#catalogo"><span class="status-dot"></span> Biblioteca en línea</a>
        </header>

        <main>
            <section class="hero">
                <div class="hero-copy">
                    <p class="eyebrow"><span class="eyebrow-line"></span> TU CONTENIDO, EN UN SOLO LUGAR</p>
                    <h1>Descubre algo<br><span>que te inspire.</span></h1>
                    <p class="hero-description">Una biblioteca digital para encontrar videos, música y documentos alojados en tus plataformas favoritas.</p>
                    <a class="primary-button" href="#catalogo">Explorar biblioteca <span aria-hidden="true">↗</span></a>
                </div>
                <div class="hero-art" aria-hidden="true">
                    <div class="orbit orbit-one"></div><div class="orbit orbit-two"></div>
                    <div class="glass-card card-back"><span class="mini-wave">♫</span><span class="mini-label">AUDIO</span><span class="mini-bars"><i></i><i></i><i></i><i></i><i></i><i></i><i></i></span></div>
                    <div class="glass-card card-front"><span class="play-shape">▶</span><span class="hero-card-label">PLAY SOMETHING NEW</span><span class="hero-card-caption">Ideas that move you.</span></div>
                    <div class="floating-dot dot-a"></div><div class="floating-dot dot-b"></div><div class="floating-star">✳</div>
                </div>
                <div class="hero-bottom"><span>VIDEO · MÚSICA · DOCUMENTOS</span><span>RECURSOS CONECTADOS A LA NUBE <b>↓</b></span></div>
            </section>

            <section class="catalog-section" id="catalogo">
                <div class="section-heading">
                    <div><p class="eyebrow dark-eyebrow">BIBLIOTECA DIGITAL</p><h2>Explora el catálogo</h2><p class="section-subtitle">Todos los recursos se consultan desde nuestra base de datos en la nube.</p></div>
                    <div class="resource-counter"><strong><?= $totalResources ?></strong><span>recursos<br>disponibles</span></div>
                </div>

                <form class="filters" method="get" action="index.php" id="filter-form">
                    <label class="search-box"><span aria-hidden="true">⌕</span><input type="search" name="q" value="<?= h($search) ?>" placeholder="Buscar por título, descripción o plataforma..." aria-label="Buscar recursos"></label>
                    <label class="category-select"><span class="sr-only">Categoría</span><select name="category" aria-label="Filtrar por categoría"><option value="">Todas las categorías</option><?php foreach ($categories as $item): ?><option value="<?= h((string)$item) ?>" <?= $category === $item ? 'selected' : '' ?>><?= h((string)$item) ?></option><?php endforeach; ?></select></label>
                    <button class="filter-button" type="submit">Buscar</button>
                    <?php if ($search !== '' || $category !== ''): ?><a class="clear-filter" href="index.php">Limpiar</a><?php endif; ?>
                </form>

                <?php if ($resources): ?>
                    <div class="resource-grid">
                        <?php foreach ($resources as $resource):
                            $provider = (string)$resource['provider'];
                            $providerClass = $providerColors[$provider] ?? 'default';
                            $providerIcon = $providerIcons[$provider] ?? '↗';
                            $resourceCategory = (string)$resource['category'];
                        ?>
                        <article class="resource-card">
                            <div class="card-visual visual-<?= h(strtolower($resourceCategory)) ?>">
                                <div class="visual-top"><span class="category-chip"><?= h(strtoupper($resourceCategory)) ?></span><span class="visual-index">NM / <?= str_pad((string)$resource['id'], 2, '0', STR_PAD_LEFT) ?></span></div>
                                <div class="visual-symbol"><?php if ($resourceCategory === 'Video'): ?>▶<?php elseif ($resourceCategory === 'Música'): ?>♫<?php else: ?>▤<?php endif; ?></div>
                                <span class="visual-bottom">NUBEMEDIA COLLECTION</span>
                            </div>
                            <div class="card-content">
                                <div class="provider-row"><span class="provider-icon <?= h($providerClass) ?>"><?= h($providerIcon) ?></span><span><?= h($provider) ?></span><span class="external-mark">↗</span></div>
                                <h3><?= h((string)$resource['title']) ?></h3>
                                <p><?= h((string)$resource['description']) ?></p>
                                <a class="resource-link" href="<?= h((string)$resource['external_url']) ?>" target="_blank" rel="noopener noreferrer">Abrir recurso <span aria-hidden="true">↗</span></a>
                            </div>
                        </article>
                        <?php endforeach; ?>
                    </div>
                <?php else: ?>
                    <div class="empty-state"><span>⌕</span><h3>No encontramos recursos</h3><p>Prueba con otra palabra o selecciona una categoría diferente.</p><a href="index.php">Ver todo el catálogo</a></div>
                <?php endif; ?>
            </section>

            <section class="platform-band" aria-label="Plataformas enlazadas">
                <p>RECURSOS CONECTADOS DESDE</p>
                <div><span class="platform-youtube">▶ YouTube</span><span class="platform-spotify">♫ Spotify</span><span class="platform-drive">▣ Google Drive</span><span class="platform-blender">✦ Blender Studio</span></div>
            </section>
        </main>

        <footer class="site-footer" id="acerca">
            <a class="brand footer-brand" href="index.php"><span class="brand-mark"><span></span><span></span><span></span></span><span>Nube<span class="brand-accent">Media</span></span></a>
            <p>Una biblioteca digital dinámica conectada a servicios externos en la nube.</p>
            <span class="footer-note">PROYECTO INTEGRADOR · CONCEPTUALIZACIÓN DE SERVICIOS EN LA NUBE</span>
        </footer>
    </div>
    <script>
        // Enviar el filtro de categoría al cambiarlo; funciona sin bibliotecas externas.
        document.querySelector('.category-select select')?.addEventListener('change', () => {
            document.getElementById('filter-form')?.requestSubmit();
        });
    </script>
</body>
</html>
