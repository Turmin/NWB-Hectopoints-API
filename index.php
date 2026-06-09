<?php

declare(strict_types=1);

require_once __DIR__ . '/lib/Nwb.php';

$initial = [
    'road' => isset($_GET['weg']) ? nwbNormalizeRoad((string)$_GET['weg']) : '',
    'side' => isset($_GET['zijde']) ? nwbNormalizeSide((string)$_GET['zijde']) : '',
    'hm' => isset($_GET['hm']) ? (string)$_GET['hm'] : '',
    'letter' => isset($_GET['letter']) ? strtoupper(trim((string)$_GET['letter'])) : '',
];

$assetVersion = (string)max(
    @filemtime(__DIR__ . '/css/app.css') ?: 1,
    @filemtime(__DIR__ . '/js/app.js') ?: 1
);
?>
<!doctype html>
<html lang="nl">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="theme-color" content="#f6f3ea">
    <meta name="description" content="Zoek hectometerpaaltjes op Nederlandse wegen en bekijk locatie, wegbeheerder, gemeente en kaart.">
    <title>Hectometerpaaltjes</title>
    <link rel="manifest" href="/manifest.json">
    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" integrity="sha256-p4NxAoJBhIINfQX9/J5Gx2AT3NQB/5S6R5J5p9U0uCk=" crossorigin="">
    <link rel="stylesheet" href="/css/app.css?v=<?= h($assetVersion) ?>">
</head>
<body>
    <div class="app-shell">
        <header class="topbar">
            <a class="brand" href="/" aria-label="Hectometerpaaltjes home">
                <span class="brand-mark" aria-hidden="true">H</span>
                <span>Hectometerpaaltjes</span>
            </a>
            <button class="ghost-button" id="locateButton" type="button">Dichtbij</button>
        </header>

        <main class="workspace">
            <section class="search-panel" aria-label="Zoeken">
                <form class="search-form" id="searchForm" autocomplete="off">
                    <label>
                        <span>Weg</span>
                        <input id="roadInput" name="weg" type="text" inputmode="text" placeholder="A4" value="<?= h($initial['road']) ?>">
                    </label>

                    <label>
                        <span>Hectometer</span>
                        <input id="hmInput" name="hectometer" type="text" inputmode="decimal" placeholder="8" value="<?= h($initial['hm']) ?>">
                    </label>

                    <label>
                        <span>Zijde</span>
                        <select id="sideInput" name="zijde">
                            <option value="">Alle</option>
                            <option value="R" <?= $initial['side'] === 'R' ? 'selected' : '' ?>>Rechts</option>
                            <option value="L" <?= $initial['side'] === 'L' ? 'selected' : '' ?>>Links</option>
                        </select>
                    </label>

                    <button class="primary-button" type="submit">Zoeken</button>
                </form>

                <div class="status-line" id="statusLine" role="status">Klaar</div>
                <div class="results-list" id="resultsList" aria-label="Resultaten"></div>
            </section>

            <section class="map-panel" aria-label="Kaart">
                <div id="map" class="map"></div>
            </section>

            <aside class="detail-panel" id="detailPanel" aria-label="Details">
                <div class="empty-state" id="emptyState">
                    <strong>Zoek op weg en hectometer</strong>
                    <span>Bijvoorbeeld A4, 8, rechts.</span>
                </div>

                <article class="detail-card is-hidden" id="detailCard">
                    <div class="detail-heading">
                        <div>
                            <p class="eyebrow" id="detailSource">PDOK NWB</p>
                            <h1 id="detailTitle">-</h1>
                        </div>
                        <button class="ghost-button" id="shareButton" type="button">Delen</button>
                    </div>

                    <dl class="facts-grid">
                        <div>
                            <dt>Plaats</dt>
                            <dd id="detailPlace">-</dd>
                        </div>
                        <div>
                            <dt>Gemeente</dt>
                            <dd id="detailMunicipality">-</dd>
                        </div>
                        <div>
                            <dt>Wegbeheerder</dt>
                            <dd id="detailManager">-</dd>
                        </div>
                        <div>
                            <dt>Straat</dt>
                            <dd id="detailStreet">-</dd>
                        </div>
                        <div>
                            <dt>Wegvak</dt>
                            <dd id="detailWvk">-</dd>
                        </div>
                        <div>
                            <dt>Coordinaten</dt>
                            <dd id="detailCoords">-</dd>
                        </div>
                    </dl>

                    <div class="detail-actions">
                        <a class="secondary-button" id="osmLink" href="#" target="_blank" rel="noopener">OpenStreetMap</a>
                        <a class="secondary-button" id="routeLink" href="#">Permalink</a>
                    </div>
                </article>
            </aside>
        </main>
    </div>

    <script>
        window.HM_INITIAL = <?= json_encode($initial, JSON_UNESCAPED_SLASHES) ?>;
    </script>
    <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js" integrity="sha256-20nQCchB9co0qIjJZRGuk2/Z9VM+kNiyxNV1lvTlZBo=" crossorigin=""></script>
    <script src="/js/app.js?v=<?= h($assetVersion) ?>"></script>
</body>
</html>
