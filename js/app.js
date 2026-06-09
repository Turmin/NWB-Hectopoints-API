(function () {
    'use strict';

    var initial = window.HM_INITIAL || {};
    var DEFAULT_ROADS = [
        'A1', 'A2', 'A4', 'A6', 'A7', 'A8', 'A9', 'A10', 'A12', 'A13',
        'A15', 'A16', 'A20', 'A27', 'A28', 'A50', 'A58', 'A67', 'A73',
        'N11', 'N18', 'N33', 'N35', 'N57', 'N59', 'N65', 'N201', 'N206'
    ];
    var state = {
        activeItem: null,
        results: [],
        bboxTimer: null,
        roads: DEFAULT_ROADS.map(function (road) {
            return {
                road: road,
                hectopunten: null
            };
        })
    };

    var els = {
        form: document.getElementById('searchForm'),
        road: document.getElementById('roadInput'),
        hm: document.getElementById('hmInput'),
        side: document.getElementById('sideInput'),
        status: document.getElementById('statusLine'),
        quickRoads: document.getElementById('quickRoads'),
        results: document.getElementById('resultsList'),
        map: document.getElementById('map'),
        empty: document.getElementById('emptyState'),
        detail: document.getElementById('detailCard'),
        title: document.getElementById('detailTitle'),
        source: document.getElementById('detailSource'),
        place: document.getElementById('detailPlace'),
        municipality: document.getElementById('detailMunicipality'),
        manager: document.getElementById('detailManager'),
        street: document.getElementById('detailStreet'),
        wvk: document.getElementById('detailWvk'),
        coords: document.getElementById('detailCoords'),
        osmLink: document.getElementById('osmLink'),
        routeLink: document.getElementById('routeLink'),
        share: document.getElementById('shareButton'),
        locate: document.getElementById('locateButton')
    };

    var map = null;
    var selectedMarker = null;
    var bboxLayer = null;

    function setStatus(message, isError) {
        els.status.textContent = message;
        els.status.classList.toggle('is-error', Boolean(isError));
        els.status.classList.toggle('is-visible', Boolean(message));
    }

    function text(value, fallback) {
        if (value === null || value === undefined || value === '') {
            return fallback || '-';
        }
        return String(value);
    }

    function itemTitle(item) {
        var side = item.side ? ' ' + item.side : '';
        var letter = item.letter ? item.letter : '';
        return item.road + side + ' ' + item.hectometer + letter;
    }

    function apiUrl(path, params) {
        var query = new URLSearchParams();
        Object.keys(params || {}).forEach(function (key) {
            if (params[key] !== null && params[key] !== undefined && params[key] !== '') {
                query.set(key, params[key]);
            }
        });
        return path + (query.toString() ? '?' + query.toString() : '');
    }

    function fetchJson(url) {
        return fetch(url, {
            headers: {
                Accept: 'application/json'
            }
        }).then(function (response) {
            return response.text().then(function (text) {
                if (!text.trim()) {
                    throw new Error('Lege API-response: controleer PHP error log en databaseconfig.');
                }

                var payload;
                try {
                    payload = JSON.parse(text);
                } catch (error) {
                    throw new Error('Ongeldige API-response: ' + text.slice(0, 160));
                }

                if (!response.ok || payload.success === false) {
                    throw new Error(payload.error || 'Onbekende fout.');
                }
                return payload;
            });
        });
    }

    function initMap() {
        if (!window.L) {
            setStatus('De kaartbibliotheek kon niet laden.', true);
            return;
        }

        map = L.map(els.map, {
            zoomControl: false
        }).setView([52.15, 5.35], 8);

        L.control.zoom({
            position: 'bottomright'
        }).addTo(map);

        L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
            maxZoom: 19,
            attribution: '&copy; OpenStreetMap contributors'
        }).addTo(map);

        bboxLayer = L.layerGroup().addTo(map);
        window.setTimeout(function () {
            map.invalidateSize();
            loadBboxMarkers();
        }, 150);

        map.on('moveend', function () {
            window.clearTimeout(state.bboxTimer);
            state.bboxTimer = window.setTimeout(loadBboxMarkers, 240);
        });
    }

    function markerPopup(item) {
        var meta = [item.place, item.municipality].filter(Boolean).join(', ');
        return '<p class="popup-title">' + escapeHtml(itemTitle(item)) + '</p>' +
            '<p class="popup-meta">' + escapeHtml(meta || item.road_manager || 'PDOK NWB') + '</p>';
    }

    function escapeHtml(value) {
        return String(value).replace(/[&<>"']/g, function (char) {
            return {
                '&': '&amp;',
                '<': '&lt;',
                '>': '&gt;',
                '"': '&quot;',
                "'": '&#039;'
            }[char];
        });
    }

    function selectItem(item, pushUrl) {
        if (!item || item.latitude === null || item.longitude === null) {
            return;
        }

        state.activeItem = item;
        renderDetail(item);

        if (map) {
            var latLng = [item.latitude, item.longitude];
            map.invalidateSize();
            if (!selectedMarker) {
                selectedMarker = L.circleMarker(latLng, {
                    radius: 8,
                    color: '#125b38',
                    weight: 2,
                    fillColor: '#d5961f',
                    fillOpacity: 0.95
                }).addTo(map);
            } else {
                selectedMarker.setLatLng(latLng);
            }
            selectedMarker.bindPopup(markerPopup(item)).openPopup();
            map.setView(latLng, Math.max(map.getZoom(), 16), {
                animate: true
            });
        }

        if (pushUrl && item.permalink) {
            history.pushState({
                item: item
            }, '', item.permalink);
        }

        renderResults(state.results, item.id);
    }

    function renderRoadButtons() {
        if (!els.quickRoads) {
            return;
        }

        els.quickRoads.innerHTML = '';
        state.roads.forEach(function (entry) {
            var button = document.createElement('button');
            button.type = 'button';
            button.className = 'road-chip' + (entry.road === els.road.value.trim().toUpperCase() ? ' is-active' : '');
            button.textContent = entry.road;
            if (entry.hectopunten !== null && entry.hectopunten !== undefined) {
                button.title = entry.hectopunten + ' hectopunten';
            }
            button.addEventListener('click', function () {
                els.road.value = entry.road;
                renderRoadButtons();
                search(false);
            });
            els.quickRoads.appendChild(button);
        });
    }

    function loadRoads() {
        renderRoadButtons();
        fetchJson(apiUrl('/api/roads.php', {
            limit: 80
        })).then(function (payload) {
            if (!payload.results || !payload.results.length) {
                return;
            }
            state.roads = payload.results;
            renderRoadButtons();
        }).catch(function () {
            renderRoadButtons();
        });
    }

    function renderDetail(item) {
        els.empty.classList.add('is-hidden');
        els.detail.classList.remove('is-hidden');
        els.title.textContent = itemTitle(item);
        els.source.textContent = item.source || 'PDOK NWB';
        els.place.textContent = text(item.place);
        els.municipality.textContent = text(item.municipality);
        els.manager.textContent = text(item.road_manager);
        els.street.textContent = text(item.street);
        els.wvk.textContent = text(item.wvk_id);
        els.coords.textContent = item.latitude !== null && item.longitude !== null
            ? item.latitude.toFixed(5) + ', ' + item.longitude.toFixed(5)
            : '-';

        var osm = 'https://www.openstreetmap.org/?mlat=' + encodeURIComponent(item.latitude) +
            '&mlon=' + encodeURIComponent(item.longitude) +
            '#map=18/' + encodeURIComponent(item.latitude) + '/' + encodeURIComponent(item.longitude);
        els.osmLink.href = osm;
        els.routeLink.href = item.permalink || '#';
    }

    function renderResults(results, activeId) {
        els.results.innerHTML = '';
        if (!results.length) {
            return;
        }

        results.forEach(function (item) {
            var button = document.createElement('button');
            button.type = 'button';
            button.className = 'result-button' + (item.id === activeId ? ' is-active' : '');
            button.innerHTML = '<span class="result-title"><span>' + escapeHtml(itemTitle(item)) + '</span><span>' +
                escapeHtml(item.side_label || '') + '</span></span><span class="result-meta">' +
                escapeHtml([item.place, item.municipality, item.road_manager].filter(Boolean).join(' / ') || 'PDOK NWB') +
                '</span>';
            button.addEventListener('click', function () {
                selectItem(item, true);
            });
            els.results.appendChild(button);
        });
    }

    function search(pushUrl) {
        var params = {
            weg: els.road.value.trim(),
            hectometer: els.hm.value.trim(),
            zijde: els.side.value,
            limit: 30
        };

        if (!params.weg && !params.hectometer) {
            setStatus('Vul minimaal een weg of hectometer in.', true);
            return;
        }

        setStatus('Zoeken...');
        fetchJson(apiUrl('/api/search.php', params))
            .then(function (payload) {
                state.results = payload.results || [];
                renderResults(state.results, state.activeItem && state.activeItem.id);

                if (!state.results.length) {
                    setStatus('Geen paaltjes gevonden.', true);
                    return;
                }

                setStatus(state.results.length === 1 ? '1 paaltje gevonden.' : state.results.length + ' paaltjes gevonden.');
                selectItem(state.results[0], pushUrl);
            })
            .catch(function (error) {
                setStatus(error.message, true);
            });
    }

    function loadInitialRoute() {
        if (!initial.road || !initial.hm) {
            setStatus('');
            return;
        }

        setStatus('Paaltje laden...');
        fetchJson(apiUrl('/api/hectopunt.php', {
            weg: initial.road,
            zijde: initial.side,
            hm: initial.hm,
            letter: initial.letter
        })).then(function (payload) {
            var item = payload.result;
            state.results = [item];
            renderResults(state.results, item.id);
            selectItem(item, false);
            setStatus('Paaltje geladen.');
        }).catch(function (error) {
            setStatus(error.message, true);
        });
    }

    function loadBboxMarkers() {
        if (!map || map.getZoom() < 13) {
            if (bboxLayer) {
                bboxLayer.clearLayers();
            }
            return;
        }

        var bounds = map.getBounds();
        var bbox = [
            bounds.getWest().toFixed(6),
            bounds.getSouth().toFixed(6),
            bounds.getEast().toFixed(6),
            bounds.getNorth().toFixed(6)
        ].join(',');

        fetchJson(apiUrl('/api/hectopunten-bbox.php', {
            bbox: bbox,
            limit: 350
        })).then(function (payload) {
            if (!bboxLayer) {
                return;
            }
            bboxLayer.clearLayers();
            (payload.features || []).forEach(function (feature) {
                var coords = feature.geometry && feature.geometry.coordinates;
                if (!coords || coords.length < 2) {
                    return;
                }
                var props = feature.properties || {};
                var title = [props.road, props.side, props.hectometer].filter(Boolean).join(' ');
                L.circleMarker([coords[1], coords[0]], {
                    radius: 4,
                    color: '#125b38',
                    weight: 1,
                    fillColor: '#d5961f',
                    fillOpacity: 0.88
                }).bindPopup('<p class="popup-title">' + escapeHtml(title) + '</p>')
                    .addTo(bboxLayer);
            });
        }).catch(function () {
            if (bboxLayer) {
                bboxLayer.clearLayers();
            }
        });
    }

    function locateNearest() {
        if (!navigator.geolocation) {
            setStatus('Locatie is niet beschikbaar.', true);
            return;
        }

        setStatus('Locatie ophalen...');
        navigator.geolocation.getCurrentPosition(function (position) {
            fetchJson(apiUrl('/api/nearest-hectopunt.php', {
                lat: position.coords.latitude,
                lon: position.coords.longitude
            })).then(function (payload) {
                var item = payload.result;
                state.results = [item];
                renderResults(state.results, item.id);
                selectItem(item, true);
                setStatus(item.distance_meters + ' meter van je locatie.');
            }).catch(function (error) {
                setStatus(error.message, true);
            });
        }, function () {
            setStatus('Locatie kon niet worden opgehaald.', true);
        }, {
            enableHighAccuracy: true,
            timeout: 10000,
            maximumAge: 30000
        });
    }

    function shareActive() {
        if (!state.activeItem) {
            return;
        }

        var url = new URL(state.activeItem.permalink, window.location.origin).toString();
        var title = itemTitle(state.activeItem);
        if (navigator.share) {
            navigator.share({
                title: title,
                text: title,
                url: url
            }).catch(function () {});
            return;
        }

        if (!navigator.clipboard || !navigator.clipboard.writeText) {
            window.prompt('Permalink', url);
            return;
        }

        navigator.clipboard.writeText(url).then(function () {
            setStatus('Link gekopieerd.');
        }).catch(function () {
            window.prompt('Permalink', url);
        });
    }

    els.form.addEventListener('submit', function (event) {
        event.preventDefault();
        search(true);
    });

    els.road.addEventListener('input', renderRoadButtons);
    els.share.addEventListener('click', shareActive);
    els.locate.addEventListener('click', locateNearest);

    window.addEventListener('resize', function () {
        if (map) {
            map.invalidateSize();
        }
    });

    window.addEventListener('load', function () {
        if (map) {
            map.invalidateSize();
        }
    });

    window.addEventListener('popstate', function () {
        window.location.reload();
    });

    initMap();
    loadRoads();
    loadInitialRoute();
}());
