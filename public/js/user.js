const baseUrl = window.location.origin;

let map;
let modalMap;
let directionsService;
let directionsRenderer;
let pickupMarker;
let destinationMarker;
let currentLocationType;
let currentModalLocation;
let pickupAutocomplete;
let destinationAutocomplete;
let modalAutocomplete;
let driverPositionWatch;
let currentRoutePath;
let isTripActive = false;

// Ride request functionality
const rideOptions = document.getElementById('ride_options');
const directRidesContainer = document.getElementById('direct_rides');
const rideSharesContainer = document.getElementById('ride_shares');
const noRidesMessage = document.getElementById('no_rides');
let selectedRide = null;

function loadGoogleMapsScript(callback) {
    const apiKey = window.appData.googleMapsApiKey;
    if (!apiKey) {
        console.error('Google Maps API key not found');
        alert('Google Maps API key is missing. Please contact support.');
        return;
    }

    const script = document.createElement('script');
    script.src = `https://maps.googleapis.com/maps/api/js?key=${apiKey}&libraries=places,directions,geometry&callback=initMap`;
    script.async = true;
    script.defer = true;
    document.head.appendChild(script);
}

function initMap() {
    const defaultLocation = { lat: -6.7924, lng: 39.2083 };

    map = new google.maps.Map(document.getElementById('map'), {
        center: defaultLocation,
        zoom: 14,
        mapTypeControl: false,
        streetViewControl: false,
    });

    directionsService = new google.maps.DirectionsService();
    directionsRenderer = new google.maps.DirectionsRenderer({
        map: map,
        suppressMarkers: true,
        polylineOptions: { strokeColor: '#FFD700', strokeWeight: 5 },
    });

    modalMap = new google.maps.Map(document.getElementById('map-modal-map'), {
        center: defaultLocation,
        zoom: 14,
        mapTypeControl: false,
        streetViewControl: false,
    });

    if (navigator.geolocation) {
        navigator.geolocation.getCurrentPosition(
            (position) => {
                const userLocation = {
                    lat: position.coords.latitude,
                    lng: position.coords.longitude,
                };
                map.setCenter(userLocation);
                modalMap.setCenter(userLocation);
                updatePickupLocation(userLocation);
                console.log('Geolocation success:', userLocation);
            },
            (error) => {
                console.error('Geolocation error:', error.message);
                alert('Unable to get your location. Using default location (Dar es Salaam).');
            },
            {
                enableHighAccuracy: true,
                timeout: 5000,
                maximumAge: 0,
            }
        );
    } else {
        console.error('Geolocation not supported by browser');
        alert('Geolocation is not supported by your browser. Using default location.');
    }

    setupLocationInputs();

    modalMap.addListener('center_changed', () => {
        const center = modalMap.getCenter();
        currentModalLocation = { lat: center.lat(), lng: center.lng() };
        reverseGeocode(currentModalLocation, 'map-modal-search-input');
    });
}

function setupLocationInputs() {
    const pickupInput = document.getElementById('pickup_point');
    pickupAutocomplete = new google.maps.places.Autocomplete(pickupInput, {
        types: ['geocode'],
        componentRestrictions: { country: 'tz' },
    });
    pickupAutocomplete.addListener('place_changed', () => {
        const place = pickupAutocomplete.getPlace();
        if (place.geometry) {
            const location = {
                lat: place.geometry.location.lat(),
                lng: place.geometry.location.lng(),
            };
            updatePickupLocation(location);
            map.setCenter(location);
            updateRoute();
        }
    });

    const destinationInput = document.getElementById('destination');
    destinationAutocomplete = new google.maps.places.Autocomplete(destinationInput, {
        types: ['geocode'],
        componentRestrictions: { country: 'tz' },
    });
    destinationAutocomplete.addListener('place_changed', () => {
        const place = destinationAutocomplete.getPlace();
        if (place.geometry) {
            const location = {
                lat: place.geometry.location.lat(),
                lng: place.geometry.location.lng(),
            };
            updateDestinationLocation(location);
            map.setCenter(location);
            updateRoute();
        }
    });

    const modalSearchInput = document.getElementById('map-modal-search-input');
    modalAutocomplete = new google.maps.places.Autocomplete(modalSearchInput, {
        types: ['geocode'],
        componentRestrictions: { country: 'tz' },
    });
    modalAutocomplete.addListener('place_changed', () => {
        const place = modalAutocomplete.getPlace();
        if (place.geometry) {
            const location = {
                lat: place.geometry.location.lat(),
                lng: place.geometry.location.lng(),
            };
            modalMap.setCenter(location);
            currentModalLocation = location;
        }
    });
}

function openMapModal(locationType) {
    currentLocationType = locationType;
    const modal = document.getElementById('map-modal');
    modal.style.display = 'flex';
    document.getElementById('map-modal-title').textContent = 
        locationType === 'pickup' ? 'Select Pickup Location' : 'Select Destination';

    google.maps.event.trigger(modalMap, 'resize');

    if (locationType === 'pickup') {
        const pickupLat = document.getElementById('pickup_lat').value;
        const pickupLng = document.getElementById('pickup_lng').value;
        if (pickupLat && pickupLng) {
            const location = { lat: parseFloat(pickupLat), lng: parseFloat(pickupLng) };
            modalMap.setCenter(location);
            document.getElementById('map-modal-search-input').value = document.getElementById('pickup_point').value;
        } else if (navigator.geolocation) {
            navigator.geolocation.getCurrentPosition(
                (position) => {
                    const location = {
                        lat: position.coords.latitude,
                        lng: position.coords.longitude,
                    };
                    modalMap.setCenter(location);
                },
                () => {
                    modalMap.setCenter({ lat: -6.7924, lng: 39.2083 });
                }
            );
        }
    } else {
        const destLat = document.getElementById('destination_lat').value;
        const destLng = document.getElementById('destination_lng').value;
        if (destLat && destLng) {
            const location = { lat: parseFloat(destLat), lng: parseFloat(destLng) };
            modalMap.setCenter(location);
            document.getElementById('map-modal-search-input').value = document.getElementById('destination').value;
        }
    }
}

function closeMapModal() {
    document.getElementById('map-modal').style.display = 'none';
}

function confirmLocation() {
    if (!currentModalLocation) return;

    console.log("Confirming location:", currentModalLocation);

    const selectedAddress = document.getElementById('map-modal-search-input').value;
    console.log("Selected address:", selectedAddress);

    if (currentLocationType === 'pickup') {
        document.getElementById('pickup_lat').value = currentModalLocation.lat;
        document.getElementById('pickup_lng').value = currentModalLocation.lng;
        document.getElementById('pickup_point').value = selectedAddress;
        updatePickupLocation(currentModalLocation);
    } else {
        document.getElementById('destination_lat').value = currentModalLocation.lat;
        document.getElementById('destination_lng').value = currentModalLocation.lng;
        document.getElementById('destination').value = selectedAddress;
        updateDestinationLocation(currentModalLocation);
    }

    closeMapModal();
    updateRoute();
}

function updatePickupLocation(location) {
    document.getElementById('pickup_lat').value = location.lat;
    document.getElementById('pickup_lng').value = location.lng;
    reverseGeocode(location, 'pickup_point');

    if (pickupMarker) {
        pickupMarker.setMap(null);
    }
    pickupMarker = new google.maps.Marker({
        position: location,
        map: map,
        icon: {
            url: 'http://maps.google.com/mapfiles/ms/icons/green-dot.png',
            scaledSize: new google.maps.Size(32, 32),
        },
        title: 'Pickup Location',
    });
}

function updateDestinationLocation(location) {
    document.getElementById('destination_lat').value = location.lat;
    document.getElementById('destination_lng').value = location.lng;
    reverseGeocode(location, 'destination');

    if (destinationMarker) {
        destinationMarker.setMap(null);
    }
    destinationMarker = new google.maps.Marker({
        position: location,
        map: map,
        icon: {
            url: 'http://maps.google.com/mapfiles/ms/icons/red-dot.png',
            scaledSize: new google.maps.Size(32, 32),
        },
        title: 'Destination',
    });
}

function reverseGeocode(location, inputId) {
    const geocoder = new google.maps.Geocoder();
    geocoder.geocode({ location: location }, (results, status) => {
        if (status === 'OK' && results[0]) {
            document.getElementById(inputId).value = results[0].formatted_address;
        }
    });
}

function updateRoute() {
    const pickupLat = document.getElementById('pickup_lat').value;
    const pickupLng = document.getElementById('pickup_lng').value;
    const destLat = document.getElementById('destination_lat').value;
    const destLng = document.getElementById('destination_lng').value;

    if (pickupLat && pickupLng && destLat && destLng) {
        const pickup = new google.maps.LatLng(parseFloat(pickupLat), parseFloat(pickupLng));
        const destination = new google.maps.LatLng(parseFloat(destLat), parseFloat(destLng));

        directionsService.route(
            {
                origin: pickup,
                destination: destination,
                travelMode: google.maps.TravelMode.DRIVING,
                drivingOptions: {
                    departureTime: new Date(),
                    trafficModel: 'bestguess',
                },
            },
            (response, status) => {
                if (status === 'OK') {
                    directionsRenderer.setDirections(response);
                    map.fitBounds(response.routes[0].bounds);
                    currentRoutePath = response.routes[0].overview_path;
                } else {
                    console.error('Directions request failed:', status);
                }
            }
        );
    }
}

/* function startDriverTracking() {
    if (!isTripActive) {
        console.log('Trip not active, skipping driver tracking');
        return;
    }

    if (driverPositionWatch) {
        navigator.geolocation.clearWatch(driverPositionWatch);
    }

    if (navigator.geolocation) {
        driverPositionWatch = navigator.geolocation.watchPosition(
            (position) => {
                const driverLocation = {
                    lat: position.coords.latitude,
                    lng: position.coords.longitude,
                };
                checkDriverDeviation(driverLocation);
            },
            (error) => {
                console.error('Geolocation error:', error);
            },
            {
                enableHighAccuracy: true,
                timeout: 5000,
                maximumAge: 0,
            }
        );
    }
} */

function checkDriverDeviation(driverLocation) {
    if (!currentRoutePath) return;

    let minDistance = Infinity;
    currentRoutePath.forEach((point) => {
        const distance = google.maps.geometry.spherical.computeDistanceBetween(
            new google.maps.LatLng(driverLocation.lat, driverLocation.lng),
            point
        );
        minDistance = Math.min(minDistance, distance);
    });

    if (minDistance > 100) {
        console.log('Driver off-route, recalculating...');
        const destinationLat = document.getElementById('destination_lat').value;
        const destinationLng = document.getElementById('destination_lng').value;
        if (destinationLat && destinationLng) {
            const destination = new google.maps.LatLng(parseFloat(destinationLat), parseFloat(destinationLng));
            directionsService.route(
                {
                    origin: driverLocation,
                    destination: destination,
                    travelMode: google.maps.TravelMode.DRIVING,
                    drivingOptions: {
                        departureTime: new Date(),
                        trafficModel: 'bestguess',
                    },
                },
                (response, status) => {
                    if (status === 'OK') {
                        directionsRenderer.setDirections(response);
                        map.fitBounds(response.routes[0].bounds);
                        currentRoutePath = response.routes[0].overview_path;
                        console.log('Route updated due to deviation');
                    } else {
                        console.error('Rerouting failed:', status);
                    }
                }
            );
        }
    }
}

function handleFormSubmission() {
    const form = document.getElementById('carRequestForm');
    form.addEventListener('submit', function(e) {
        e.preventDefault();

        const pickupPoint = document.getElementById('pickup_point').value;
        const destination = document.getElementById('destination').value;
        const requestDate = document.getElementById('request_date').value;

        if (!pickupPoint || !destination || !requestDate) {
            alert('Please fill in all required fields');
            return;
        }

        const formData = new FormData(this);
        fetch('/car/request', {
            method: 'POST',
            body: formData,
            headers: {
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
            },
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                console.log('Car request successful:', data);
                isTripActive = true;
                startDriverTracking();
                alert('Ride requested successfully!');
            } else {
                console.error('Car request failed:', data.error);
                alert('Failed to request ride: ' + data.error);
            }
        })
        .catch(error => {
            console.error('Form submission error:', error);
            alert('An error occurred while requesting the ride.');
        });
    });
}

document.addEventListener('DOMContentLoaded', () => {
    console.log('DOM loaded for user dashboard');

    loadGoogleMapsScript();

    document.getElementById('pickup_map_btn').addEventListener('click', () => openMapModal('pickup'));
    document.getElementById('destination_map_btn').addEventListener('click', () => openMapModal('destination'));
    document.getElementById('map-modal-close').addEventListener('click', closeMapModal);
    document.getElementById('select-location-btn').addEventListener('click', confirmLocation);

    handleFormSubmission();
});

// Ride request functionality
document.getElementById('carRequestForm').addEventListener('submit', function(e) {
    e.preventDefault();
    findRideOptions();
});

function findRideOptions() {
    const pickupLat = document.getElementById('pickup_lat').value;
    const pickupLng = document.getElementById('pickup_lng').value;
    const destLat = document.getElementById('destination_lat').value;
    const destLng = document.getElementById('destination_lng').value;
    const passengerCount = document.getElementById('passenger_count').value;

    if (!pickupLat || !pickupLng || !destLat || !destLng) {
        return;
    }

    fetch('/ride-share/find-ride-options', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
        },
        body: JSON.stringify({
            pickup_lat: pickupLat,
            pickup_lng: pickupLng,
            destination_lat: destLat,
            destination_lng: destLng,
            pickup_location: document.getElementById('pickup_point').value,
            destination: document.getElementById('destination').value,
            passenger_count: passengerCount
        })
    })
    .then(response => response.json())
    .then(data => {
        directRidesContainer.innerHTML = '';
        rideSharesContainer.innerHTML = '';
        
        if (!data.has_options) {
            noRidesMessage.classList.remove('hidden');
            return;
        }

        noRidesMessage.classList.add('hidden');
        
        // Populate direct rides
        if (data.direct_rides.length > 0) {
            data.direct_rides.forEach(ride => {
                const rideElement = createRideElement(ride);
                directRidesContainer.appendChild(rideElement);
            });
        } else {
            directRidesContainer.innerHTML = '<p class="text-gray-600">No direct rides available</p>';
        }

        // Populate ride shares
        if (data.ride_shares.length > 0) {
            data.ride_shares.forEach(ride => {
                const rideElement = createRideElement(ride);
                rideSharesContainer.appendChild(rideElement);
            });
        } else {
            rideSharesContainer.innerHTML = '<p class="text-gray-600">No ride shares available</p>';
        }

        rideOptions.classList.remove('hidden');
    })
    .catch(error => {
        console.error('Error finding ride options:', error);
    });
}

function createRideElement(ride) {
    const div = document.createElement('div');
    div.className = 'bg-white p-4 rounded-lg border border-gray-200 hover:border-blue-500 cursor-pointer';
    
    let details = '';
    if (ride.type === 'direct') {
        details = `
            <div class="flex justify-between items-start">
                <div>
                    <h4 class="font-medium text-gray-900">${ride.name}</h4>
                    <p class="text-sm text-gray-600">${formatDuration(ride.estimated_arrival)} away</p>
                </div>
                <div class="text-right">
                    <p class="text-sm text-gray-600">${formatDistance(ride.distance)} away</p>
                </div>
            </div>
        `;
    } else {
        details = `
            <div class="flex justify-between items-start">
                <div>
                    <h4 class="font-medium text-gray-900">${ride.driver_name}</h4>
                    <p class="text-sm text-gray-600">${formatDuration(ride.detour_duration)} detour</p>
                </div>
                <div class="text-right">
                    <p class="text-sm text-gray-600">${ride.available_seats} seats available</p>
                    <p class="text-sm text-gray-600">${formatDistance(ride.detour_distance)} detour</p>
                </div>
            </div>
        `;
    }
    
    div.innerHTML = details;
    div.addEventListener('click', () => showRideDetails(ride));
    return div;
}

function showRideDetails(ride) {
    selectedRide = ride;
    const modal = document.getElementById('ride-options-modal');
    const details = document.getElementById('ride-details');
    
    let detailsHtml = '';
    if (ride.type === 'direct') {
        detailsHtml = `
            <div class="space-y-4">
                <div class="flex justify-between">
                    <span class="text-gray-600">Driver:</span>
                    <span class="font-medium">${ride.name}</span>
                </div>
                <div class="flex justify-between">
                    <span class="text-gray-600">Estimated Arrival:</span>
                    <span>${formatDuration(ride.estimated_arrival)}</span>
                </div>
                <div class="flex justify-between">
                    <span class="text-gray-600">Distance:</span>
                    <span>${formatDistance(ride.distance)}</span>
                </div>
            </div>
        `;
    } else {
        detailsHtml = `
            <div class="space-y-4">
                <div class="flex justify-between">
                    <span class="text-gray-600">Driver:</span>
                    <span class="font-medium">${ride.driver_name}</span>
                </div>
                <div class="flex justify-between">
                    <span class="text-gray-600">Detour:</span>
                    <span>${formatDuration(ride.detour_duration)} (${formatDistance(ride.detour_distance)})</span>
                </div>
                <div class="flex justify-between">
                    <span class="text-gray-600">Available Seats:</span>
                    <span>${ride.available_seats}</span>
                </div>
                <div class="flex justify-between">
                    <span class="text-gray-600">Estimated Arrival:</span>
                    <span>${formatTime(ride.estimated_arrival)}</span>
                </div>
            </div>
        `;
    }
    
    details.innerHTML = detailsHtml;
    modal.classList.remove('hidden');
}

function closeRideOptionsModal() {
    document.getElementById('ride-options-modal').classList.add('hidden');
    selectedRide = null;
}

function requestRide() {
    if (!selectedRide) return;

    const formData = {
        pickup_lat: document.getElementById('pickup_lat').value,
        pickup_lng: document.getElementById('pickup_lng').value,
        destination_lat: document.getElementById('destination_lat').value,
        destination_lng: document.getElementById('destination_lng').value,
        pickup_location: document.getElementById('pickup_point').value,
        destination: document.getElementById('destination').value,
        passenger_count: document.getElementById('passenger_count').value
    };

    if (selectedRide.type === 'direct') {
        formData.driver_id = selectedRide.id;
    } else {
        formData.trip_id = selectedRide.id;
    }

    fetch('/ride-share/request', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
        },
        body: JSON.stringify(formData)
    })
    .then(response => response.json())
    .then(data => {
        closeRideOptionsModal();
        // Show success message or handle the response
        alert('Ride request sent successfully!');
    })
    .catch(error => {
        console.error('Error requesting ride:', error);
        alert('Error requesting ride. Please try again.');
    });
}

function formatDuration(seconds) {
    const minutes = Math.round(seconds / 60);
    return `${minutes} min`;
}

function formatDistance(meters) {
    const kilometers = (meters / 1000).toFixed(1);
    return `${kilometers} km`;
}

function formatTime(timestamp) {
    return new Date(timestamp).toLocaleTimeString();
}
