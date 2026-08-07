/**
 * Leaflet.js Geolocation & Real-time Tracking Engine (Cameroun 🇨🇲)
 * Application AgriConnect
 */

const AgriMap = {
    // Coordonnées par défaut du Cameroun (Douala / Yaoundé / Bafoussam)
    defaultCoords: { lat: 4.0511, lng: 9.7679 }, // Douala

    // Icônes personnalisées Leaflet
    icons: {
        ferme: L.icon({
            iconUrl: 'https://raw.githubusercontent.com/pointhi/leaflet-color-markers/master/img/marker-icon-green.png',
            shadowUrl: 'https://cdnjs.cloudflare.com/ajax/libs/leaflet/1.7.1/images/marker-shadow.png',
            iconSize: [25, 41],
            iconAnchor: [12, 41],
            popupAnchor: [1, -34],
            shadowSize: [41, 41]
        }),
        destination: L.icon({
            iconUrl: 'https://raw.githubusercontent.com/pointhi/leaflet-color-markers/master/img/marker-icon-red.png',
            shadowUrl: 'https://cdnjs.cloudflare.com/ajax/libs/leaflet/1.7.1/images/marker-shadow.png',
            iconSize: [25, 41],
            iconAnchor: [12, 41],
            popupAnchor: [1, -34],
            shadowSize: [41, 41]
        }),
        transporteur: L.icon({
            iconUrl: 'https://raw.githubusercontent.com/pointhi/leaflet-color-markers/master/img/marker-icon-gold.png',
            shadowUrl: 'https://cdnjs.cloudflare.com/ajax/libs/leaflet/1.7.1/images/marker-shadow.png',
            iconSize: [25, 41],
            iconAnchor: [12, 41],
            popupAnchor: [1, -34],
            shadowSize: [41, 41]
        })
    },

    /**
     * Géolocalisation automatique en temps réel via l'API Geolocation HTML5 du smartphone
     */
    detecterPositionEnTempsReel: function(latInputId, lngInputId, mapObj, markerObj) {
        if (!navigator.geolocation) {
            alert("La géolocalisation n'est pas supportée par votre navigateur.");
            return;
        }

        const btn = event?.currentTarget;
        if (btn) btn.innerHTML = '<i class="fa-solid fa-spinner fa-spin"></i> Détection GPS en cours...';

        navigator.geolocation.getCurrentPosition(
            (position) => {
                const lat = position.coords.latitude;
                const lng = position.coords.longitude;

                const latInput = document.getElementById(latInputId);
                const lngInput = document.getElementById(lngInputId);

                if (latInput) latInput.value = lat.toFixed(6);
                if (lngInput) lngInput.value = lng.toFixed(6);

                if (mapObj && markerObj) {
                    markerObj.setLatLng([lat, lng]);
                    mapObj.setView([lat, lng], 14);
                }

                if (btn) btn.innerHTML = '<i class="fa-solid fa-crosshairs"></i> Position GPS capturée !';
            },
            (error) => {
                alert("Impossible d'obtenir votre position GPS actuelle. Assurez-vous d'avoir autorisé l'accès à la géolocalisation.");
                if (btn) btn.innerHTML = '<i class="fa-solid fa-crosshairs"></i> Utiliser Ma Position GPS';
            },
            { enableHighAccuracy: true, timeout: 10000, maximumAge: 0 }
        );
    },

    /**
     * Initialise une carte de sélection de coordonnées (Formulaires)
     */
    initPicker: function(mapId, latInputId, lngInputId, initialLat = 4.0511, initialLng = 9.7679) {
        const container = document.getElementById(mapId);
        if (!container) return;

        const latInput = document.getElementById(latInputId);
        const lngInput = document.getElementById(lngInputId);

        const currentLat = parseFloat(latInput?.value) || initialLat;
        const currentLng = parseFloat(lngInput?.value) || initialLng;

        const map = L.map(mapId).setView([currentLat, currentLng], 12);

        L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
            maxZoom: 19,
            attribution: '© OpenStreetMap | AgriConnect Cameroun 🇨🇲'
        }).addTo(map);

        let marker = L.marker([currentLat, currentLng], { draggable: true, icon: this.icons.ferme }).addTo(map);

        function updateInputs(lat, lng) {
            if (latInput) latInput.value = lat.toFixed(6);
            if (lngInput) lngInput.value = lng.toFixed(6);
        }

        marker.on('dragend', function(e) {
            const pos = marker.getLatLng();
            updateInputs(pos.lat, pos.lng);
        });

        map.on('click', function(e) {
            marker.setLatLng(e.latlng);
            updateInputs(e.latlng.lat, e.latlng.lng);
        });

        // Bouton automatique "Ma position GPS"
        const btnGeo = document.getElementById('btnMaPositionGPS');
        if (btnGeo) {
            btnGeo.addEventListener('click', function() {
                AgriMap.detecterPositionEnTempsReel(latInputId, lngInputId, map, marker);
            });
        }
    },

    /**
     * Initialise la carte de suivi GPS en temps réel
     */
    initLiveTracking: function(mapId, orderId, isTransporter = false, startLat, startLng, endLat, endLng) {
        const container = document.getElementById(mapId);
        if (!container) return;

        const map = L.map(mapId).setView([startLat || 4.0511, startLng || 9.7679], 11);

        L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
            maxZoom: 19,
            attribution: '© OpenStreetMap | AgriConnect Live GPS 🇨🇲'
        }).addTo(map);

        // Point de retrait (Ferme)
        if (startLat && startLng) {
            L.marker([startLat, startLng], { icon: this.icons.ferme })
                .addTo(map)
                .bindPopup("<b>Point de Retrait</b><br>Exploitation Agricole");
        }

        // Point de livraison (Acheteur)
        if (endLat && endLng) {
            L.marker([endLat, endLng], { icon: this.icons.destination })
                .addTo(map)
                .bindPopup("<b>Point de Livraison</b><br>Adresse de l'acheteur");
        }

        let transporterMarker = null;
        let routeLine = null;

        // Fonction d'émission GPS (Côté Transporteur)
        if (isTransporter && navigator.geolocation) {
            navigator.geolocation.watchPosition(
                (position) => {
                    const lat = position.coords.latitude;
                    const lng = position.coords.longitude;

                    // Envoi au serveur
                    fetch('api/update_gps.php', {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                        body: `commande_id=${orderId}&latitude=${lat}&longitude=${lng}`
                    })
                    .then(res => res.json())
                    .then(data => {
                        console.log('Position GPS transmise:', data);
                    })
                    .catch(err => console.error('Erreur d-envoi GPS:', err));
                },
                (err) => console.warn('Erreur Geolocation HTML5:', err.message),
                { enableHighAccuracy: true, timeout: 10000, maximumAge: 3000 }
            );
        }

        // Polling pour rafraîchir la position du transporteur sur la carte
        function fetchLivePosition() {
            fetch(`api/get_gps.php?commande_id=${orderId}`)
                .then(res => res.json())
                .then(data => {
                    if (data.success && data.latitude && data.longitude) {
                        const tLat = parseFloat(data.latitude);
                        const tLng = parseFloat(data.longitude);

                        if (!transporterMarker) {
                            transporterMarker = L.marker([tLat, tLng], { icon: AgriMap.icons.transporteur })
                                .addTo(map)
                                .bindPopup(`<b>Transporteur</b><br>Dernière maj: ${data.derniere_maj || 'En direct'}`);
                        } else {
                            transporterMarker.setLatLng([tLat, tLng]);
                        }

                        // Tracer l'itinéraire visuel
                        const points = [];
                        if (startLat && startLng) points.push([startLat, startLng]);
                        points.push([tLat, tLng]);
                        if (endLat && endLng) points.push([endLat, endLng]);

                        if (routeLine) {
                            routeLine.setLatLngs(points);
                        } else {
                            routeLine = L.polyline(points, { color: '#059669', weight: 4, dashArray: '8, 8' }).addTo(map);
                        }

                        const statusElem = document.getElementById('tracking-live-status');
                        if (statusElem) {
                            statusElem.innerHTML = `<span class="status-badge status-en_cours_livraison"><i class="fa-solid fa-satellite-dish"></i> GPS Actif - Position mise à jour</span>`;
                        }
                    }
                })
                .catch(err => console.error('Erreur chargement GPS:', err));
        }

        fetchLivePosition();
        setInterval(fetchLivePosition, 4000);
    }
};
