// Define base URL dynamically
const baseUrl = window.location.origin;

// Safely access window.appData with fallbacks
const allTrips = (window.appData && window.appData.allTrips) || [];
const allShifts = (window.appData && window.appData.allShifts) || [];
const currentShiftId = (window.appData && window.appData.currentShiftId) || null;
const assignedRequests = (window.appData && window.appData.assignedRequests) || [];

console.log('All Trips:', allTrips);
console.log('All Shifts:', allShifts);
console.log('Current Shift ID:', currentShiftId);
console.log('Assigned Requests:', assignedRequests);

function showTab(tab) {
    const currentShiftTab = document.getElementById("currentShift");
    const pastShiftTab = document.getElementById("pastShifts");
    const currentShiftButton = document.getElementById("currentShiftTab");
    const pastShiftButton = document.getElementById("pastShiftTab");

    if (tab === "current") {
        currentShiftTab.classList.remove("hidden");
        pastShiftTab.classList.add("hidden");
        currentShiftButton.classList.add("text-blue-600", "border-blue-500");
        pastShiftButton.classList.remove("text-blue-600", "border-blue-500");
        pastShiftButton.classList.add("text-gray-600", "border-transparent");
        renderCurrentShiftTrips();
    } else {
        pastShiftTab.classList.remove("hidden");
        currentShiftTab.classList.add("hidden");
        pastShiftButton.classList.add("text-blue-600", "border-blue-500");
        currentShiftButton.classList.remove("text-blue-600", "border-blue-500");
        currentShiftButton.classList.add("text-gray-600", "border-transparent");
        renderPastShifts();
    }
}

function formatDate(dateStr) {
    if (!dateStr) return "N/A";
    const date = new Date(dateStr);
    const options = { weekday: "long", year: "numeric", month: "long", day: "numeric" };
    return date.toLocaleDateString("en-US", options);
}

function formatTime(dateString) {
    if (!dateString) return "[-]";
    const eatDateString = dateString.includes('Z') || dateString.includes('+') ? dateString : `${dateString}+03:00`;
    return new Intl.DateTimeFormat('en-US', {
        hour: '2-digit',
        minute: '2-digit',
        timeZone: 'Africa/Dar_es_Salaam'
    }).format(new Date(eatDateString));
}

function getStatusClass(status) {
    const normalizedStatus = (status || '').toString().toLowerCase().trim();
    if (normalizedStatus === "ongoing") return "bg-green-100 text-green-600 border-green-400";
    if (normalizedStatus === "completed") return "bg-blue-100 text-blue-600 border-blue-400";
    if (normalizedStatus === "cancelled") return "bg-red-100 text-red-600 border-red-400";
    if (normalizedStatus === "assigned") return "bg-yellow-100 text-yellow-600 border-yellow-400";
    return "bg-gray-500 text-white";
}

function renderCurrentShiftTrips() {
    const currentShiftTripsList = document.getElementById("currentShiftTripsList");
    if (!currentShiftTripsList) {
        console.error('currentShiftTripsList not found');
        return;
    }
    currentShiftTripsList.innerHTML = "";

    const currentTrips = allTrips.filter(trip => 
        trip.shift_id === currentShiftId && 
        new Date(trip.started_at).toDateString() === new Date().toDateString()
    );

    const currentRequests = assignedRequests.filter(req => 
        !allTrips.some(trip => trip.request_id === req.id) &&
        new Date(req.assigned_at).toDateString() === new Date().toDateString()
    );

    if (currentTrips.length === 0 && currentRequests.length === 0) {
        currentShiftTripsList.innerHTML = "<li>No trips or requests for the current shift.</li>";
        return;
    }

    const items = [
        ...currentTrips.map(trip => ({ type: 'trip', data: trip })),
        ...currentRequests.map(req => ({ type: 'request', data: req }))
    ].sort((a, b) => {
        const aTime = a.type === 'trip' ? new Date(a.data.started_at) : new Date(a.data.assigned_at);
        const bTime = b.type === 'trip' ? new Date(b.data.started_at) : new Date(b.data.assigned_at);
        return bTime - aTime; // Newest first
    });

    items.forEach(item => {
        const { type, data } = item;
        const tripItem = document.createElement("li");
        tripItem.className = "bg-gray-100 p-3 rounded-md shadow-sm border border-gray-200";
        const pickupLocation = data.pickup_location || (data.car_request ? data.car_request.pickup_location : 'N/A');
        const dropoffLocation = data.dropoff_location || (data.car_request ? data.car_request.dropoff_location : 'N/A');
        const status = type === 'trip' ? data.status : data.status;
        const id = type === 'trip' ? data.id : data.id;

        tripItem.innerHTML = `
            <p class="flex justify-between">
                <span class="font-semibold text-blue-600"><strong class="text-black">${type === 'trip' ? 'Trip' : 'Request'} ID:</strong> ${id}</span>
                <span class="text-xs font-semibold px-2 py-1 rounded-md border ${getStatusClass(status)}">${status.toUpperCase()}</span>
            </p>
            <p class="flex justify-between mt-1 text-sm">
                <span><strong>Pick-up:</strong> ${pickupLocation}</span>
                <span><strong>Drop-off:</strong> ${dropoffLocation}</span>
            </p>
            <p class="text-xs mt-1 text-gray-600">
                ${type === 'trip' 
                    ? `<strong>Started at:</strong> ${formatTime(data.started_at)} - <strong>Ended at:</strong> ${formatTime(data.ended_at)}`
                    : `<strong>Assigned at:</strong> ${formatTime(data.assigned_at)}`}
            </p>
        `;

        if (status.toLowerCase() === "assigned" || status.toLowerCase() === "ongoing") {
            const buttonContainer = document.createElement("div");
            buttonContainer.className = "flex justify-center mt-3 gap-2";
            const startEndButton = document.createElement("button");
            startEndButton.className = status.toLowerCase() === "assigned"
                ? "w-full bg-green-500 hover:bg-green-600 text-white font-semibold py-2 px-4 rounded text-sm"
                : "w-full bg-blue-500 hover:bg-blue-600 text-white font-semibold py-2 px-4 rounded text-sm";
            startEndButton.textContent = status.toLowerCase() === "assigned" ? "Start Trip" : "End Trip";
            const cancelButton = document.createElement("button");
            cancelButton.className = "w-full bg-red-500 hover:bg-red-600 text-white font-semibold py-2 px-4 rounded text-sm";
            cancelButton.textContent = "Cancel Trip";
            let isTripStarted = status.toLowerCase() === "ongoing";

            startEndButton.addEventListener("click", async () => {
                if (!isTripStarted) {
                    try {
                        const response = await fetch(`${baseUrl}/trips/start/${id}`, {
                            method: 'POST',
                            headers: {
                                'Content-Type': 'application/json',
                                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
                            }
                        });
                        if (!response.ok) throw new Error(`HTTP error! Status: ${response.status}`);
                        const result = await response.json();
                        console.log('Start Trip Response:', response.status, result);
                        if (result.success) {
                            const newTrip = result.trip;
                            allTrips.push(newTrip);

                            // Update the request in assignedRequests to remove it since it’s now a trip
                            const requestIndex = assignedRequests.findIndex(r => r.id === newTrip.request_id);
                            if (requestIndex !== -1) assignedRequests.splice(requestIndex, 1);

                            // Update UI with new Trip ID and details
                            tripItem.querySelector('span.font-semibold.text-blue-600').innerHTML = 
                                `<strong class="text-black">Trip ID:</strong> ${newTrip.id}`;
                            startEndButton.textContent = "End Trip";
                            startEndButton.classList.replace("bg-green-500", "bg-blue-500");
                            startEndButton.classList.replace("hover:bg-green-600", "hover:bg-blue-600");
                            tripItem.querySelector('span.border').className = 
                                `text-xs font-semibold px-2 py-1 rounded-md border ${getStatusClass(newTrip.status)}`;
                            tripItem.querySelector('span.border').textContent = newTrip.status.toUpperCase();
                            tripItem.querySelector('p.text-gray-600').innerHTML = `
                                <strong>Started at:</strong> ${formatTime(newTrip.started_at)} - 
                                <strong>Ended at:</strong> ${formatTime(newTrip.ended_at)}
                            `;
                            const pickup = newTrip.car_request ? newTrip.car_request.pickup_location : pickupLocation;
                            const dropoff = newTrip.car_request ? newTrip.car_request.dropoff_location : dropoffLocation;
                            tripItem.querySelector('p.flex.justify-between.mt-1.text-sm').innerHTML = `
                                <span><strong>Pick-up:</strong> ${pickup}</span>
                                <span><strong>Drop-off:</strong> ${dropoff}</span>
                            `;
                            item.type = 'trip';
                            item.data = newTrip;
                            isTripStarted = true;
                        } else {
                            alert('Failed to start trip: ' + (result.error || 'Unknown error'));
                        }
                    } catch (error) {
                        console.error('Error starting trip:', error);
                        alert('An error occurred while starting the trip.');
                    }
                } else {
                    try {
                        const response = await fetch(`${baseUrl}/trips/${id}/end`, {
                            method: 'POST',
                            headers: {
                                'Content-Type': 'application/json',
                                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
                            }
                        });
                        if (!response.ok) throw new Error(`HTTP error! Status: ${response.status}`);
                        const result = await response.json();
                        console.log('End Trip Response:', response.status, result);
                        if (result.success) {
                            const updatedTrip = result.trip;
                            const tripIndex = allTrips.findIndex(t => t.id === updatedTrip.id);
                            if (tripIndex !== -1) allTrips[tripIndex] = updatedTrip;
                            tripItem.querySelector('span.border').className = 
                                `text-xs font-semibold px-2 py-1 rounded-md border ${getStatusClass(updatedTrip.status)}`;
                            tripItem.querySelector('span.border').textContent = updatedTrip.status.toUpperCase();
                            tripItem.querySelector('p.text-gray-600').innerHTML = `
                                <strong>Started at:</strong> ${formatTime(updatedTrip.started_at)} - 
                                <strong>Ended at:</strong> ${formatTime(updatedTrip.ended_at)}
                            `;
                            buttonContainer.remove();
                        } else {
                            alert('Failed to end trip: ' + (result.error || 'Unknown error'));
                        }
                    } catch (error) {
                        console.error('Error ending trip:', error);
                        alert('An error occurred while ending the trip.');
                    }
                }
            });

            cancelButton.addEventListener("click", async () => {
                try {
                    const url = isTripStarted ? `${baseUrl}/trips/${id}/cancel` : `${baseUrl}/trips/cancel-request/${id}`;
                    const response = await fetch(url, {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
                        }
                    });
                    if (!response.ok) throw new Error(`HTTP error! Status: ${response.status}`);
                    const result = await response.json();
                    console.log('Cancel Response:', response.status, result);
                    if (result.success) {
                        if (isTripStarted) {
                            const updatedTrip = result.trip;
                            const tripIndex = allTrips.findIndex(t => t.id === updatedTrip.id);
                            if (tripIndex !== -1) allTrips[tripIndex] = updatedTrip;
                            tripItem.querySelector('span.border').className = 
                                `text-xs font-semibold px-2 py-1 rounded-md border ${getStatusClass(updatedTrip.status)}`;
                            tripItem.querySelector('span.border').textContent = updatedTrip.status.toUpperCase();
                            tripItem.querySelector('p.text-gray-600').innerHTML = `
                                <strong>Started at:</strong> ${formatTime(updatedTrip.started_at)} - 
                                <strong>Ended at:</strong> ${formatTime(updatedTrip.ended_at || new Date())}
                            `;
                            buttonContainer.remove();
                        } else {
                            const cancelledRequest = result.request;
                            const requestIndex = assignedRequests.findIndex(r => r.id === cancelledRequest.id);
                            if (requestIndex !== -1) assignedRequests[requestIndex] = cancelledRequest; // Update instead of remove
                            tripItem.querySelector('span.border').className = 
                                `text-xs font-semibold px-2 py-1 rounded-md border ${getStatusClass(cancelledRequest.status)}`;
                            tripItem.querySelector('span.border').textContent = cancelledRequest.status.toUpperCase();
                            buttonContainer.remove();
                        }
                    } else {
                        alert('Failed to cancel: ' + (result.error || 'Unknown error'));
                    }
                } catch (error) {
                    console.error('Error cancelling:', error);
                    alert('An error occurred while cancelling.');
                }
            });

            buttonContainer.appendChild(startEndButton);
            buttonContainer.appendChild(cancelButton);
            tripItem.appendChild(buttonContainer);
        }
        currentShiftTripsList.appendChild(tripItem);
    });
}

function renderPastShifts() {
    const pastShiftsList = document.getElementById("pastShiftsList");
    if (!pastShiftsList) {
        console.error('pastShiftsList not found');
        return;
    }
    pastShiftsList.innerHTML = "";

    const pastTrips = allTrips.filter(trip => 
        trip.shift_id !== currentShiftId || 
        new Date(trip.started_at).toDateString() !== new Date().toDateString()
    );

    const pastRequests = assignedRequests.filter(req => 
        !allTrips.some(trip => trip.request_id === req.id) &&
        new Date(req.assigned_at).toDateString() !== new Date().toDateString()
    );

    if (pastTrips.length === 0 && pastRequests.length === 0) {
        pastShiftsList.innerHTML = "<li>No past trips or requests found.</li>";
        return;
    }

    const shifts = {};
    pastTrips.forEach(trip => {
        const shift = allShifts.find(s => s.id === trip.shift_id);
        if (!shift) return;
        const date = new Date(shift.shift_start).toDateString();
        if (!shifts[date]) {
            shifts[date] = {
                items: [],
                startTime: shift ? formatTime(shift.shift_start) : "N/A",
                endTime: shift ? formatTime(shift.shift_end) : "N/A",
                carPlate: shift && shift.car ? shift.car.license_plate : "N/A",
                carBrand: shift && shift.car ? shift.car.brand : "N/A",
                carModel: shift && shift.car ? shift.car.model : "N/A"
            };
        }
        shifts[date].items.push({ type: 'trip', data: trip });
    });

    pastRequests.forEach(req => {
        const shift = allShifts.find(s => 
            s.driver_id === req.driver_id && 
            new Date(s.shift_start).toDateString() === new Date(req.assigned_at).toDateString()
        );
        if (!shift) return;
        const date = new Date(shift.shift_start).toDateString();
        if (!shifts[date]) {
            shifts[date] = {
                items: [],
                startTime: shift ? formatTime(shift.shift_start) : "N/A",
                endTime: shift ? formatTime(shift.shift_end) : "N/A",
                carPlate: shift && shift.car ? shift.car.license_plate : "N/A",
                carBrand: shift && shift.car ? shift.car.brand : "N/A",
                carModel: shift && shift.car ? shift.car.model : "N/A"
            };
        }
        shifts[date].items.push({ type: 'request', data: req });
    });

    Object.entries(shifts).forEach(([date, shiftData]) => {
        shiftData.items.sort((a, b) => {
            const aTime = a.type === 'trip' ? new Date(a.data.started_at) : new Date(a.data.assigned_at);
            const bTime = b.type === 'trip' ? new Date(b.data.started_at) : new Date(b.data.assigned_at);
            return bTime - aTime; // Newest first
        });

        const shiftItem = document.createElement("li");
        shiftItem.className = "border border-gray-300 rounded-lg p-4 cursor-pointer bg-gray-50 hover:bg-gray-100 transition-all flex justify-between items-center shadow-md";
        shiftItem.innerHTML = `
            <span class="text-blue-600 font-semibold">${formatDate(date)}</span>
            <i class="fas fa-chevron-down text-gray-500 transition-transform"></i>
        `;

        const shiftDetails = document.createElement("div");
        shiftDetails.className = "hidden shift-details bg-white p-4 mt-2 rounded-lg shadow-md border border-gray-200";
        
        shiftDetails.innerHTML = `
            <p class="mb-2">
                <strong>Shift Start:</strong> ${shiftData.startTime} | 
                <strong>Shift End:</strong> ${shiftData.endTime}
            </p>
            <p class="mb-2">
                <strong>Car Plate No.:</strong> ${shiftData.carPlate} | 
                <strong>Brand:</strong> ${shiftData.carBrand} | 
                <strong>Model:</strong> ${shiftData.carModel}
            </p>
            <h4 class="mt-3 font-semibold text-lg">Trips and Requests:</h4>
            <ul class="mt-2 space-y-4">
                ${shiftData.items.map(item => {
                    const { type, data } = item;
                    const pickupLocation = data.pickup_location || (data.car_request ? data.car_request.pickup_location : 'N/A');
                    const dropoffLocation = data.dropoff_location || (data.car_request ? data.car_request.dropoff_location : 'N/A');
                    const status = type === 'trip' ? data.status : data.status;
                    const id = type === 'trip' ? data.id : data.id;
                    return `
                        <li class="bg-gray-100 p-3 rounded-md shadow-sm border border-gray-200">
                            <p class="flex justify-between">
                                <span class="font-semibold text-blue-600"><strong class="text-black">${type === 'trip' ? 'Trip' : 'Request'} ID:</strong> ${id}</span>
                                <span class="text-xs font-semibold px-2 py-1 rounded-md border ${getStatusClass(status)}">${status.toUpperCase()}</span>
                            </p>
                            <p class="flex justify-between mt-1 text-sm">
                                <span><strong>Pick-up:</strong> ${pickupLocation}</span>
                                <span><strong>Drop-off:</strong> ${dropoffLocation}</span>
                            </p>
                            <p class="text-xs mt-1 text-gray-600">
                                ${type === 'trip' 
                                    ? `<strong>Started at:</strong> ${formatTime(data.started_at)} - <strong>Ended at:</strong> ${formatTime(data.ended_at)}`
                                    : `<strong>Assigned at:</strong> ${formatTime(data.assigned_at)}`}
                            </p>
                        </li>
                    `;
                }).join('')}
            </ul>
        `;

        shiftItem.addEventListener("click", () => {
            document.querySelectorAll("#pastShiftsList .shift-details").forEach(div => {
                if (div !== shiftDetails) div.classList.add("hidden");
            });
            shiftDetails.classList.toggle("hidden");
            shiftItem.querySelector("i").classList.toggle("rotate-180");
        });

        pastShiftsList.appendChild(shiftItem);
        pastShiftsList.appendChild(shiftDetails);
    });
}

document.addEventListener('DOMContentLoaded', () => {
    console.log('DOM loaded');
    showTab("current");

    let trackingEnabled = false;
    const toggleButton = document.getElementById("trackingToggleButton");
    if (toggleButton) {
        toggleButton.addEventListener("click", function () {
            console.log('Tracking toggle clicked, enabled:', trackingEnabled);
            trackingEnabled = !trackingEnabled;
            const trackingText = document.getElementById("trackingToggleText");
            if (trackingEnabled) {
                updateLocationStatus("tracking");
                trackingText.textContent = "Disable Tracking";
            } else {
                updateLocationStatus("disabled");
                trackingText.textContent = "Enable Tracking";
            }
        });
    } else {
        console.error('trackingToggleButton not found');
    }
});

function updateLocationStatus(status) {
    console.log('Update location status:', status);
    const statusBox = document.getElementById("locationStatus");
    if (!statusBox) {
        console.error('locationStatus not found');
        return;
    }
    const statusText = statusBox.querySelector("span");
    const statusIcon = statusBox.querySelector("i");

    statusBox.classList.remove("mb-6", "bg-green-500", "bg-red-500", "bg-gray-500");
    statusBox.classList.add("mb-4");

    switch (status) {
        case "tracking":
            statusText.textContent = "Live location is being shared.";
            statusBox.classList.add("bg-green-500");
            statusIcon.className = "fas fa-broadcast-tower mr-2";
            break;
        case "disrupted":
            statusText.textContent = "Live location sharing is disrupted.";
            statusBox.classList.add("bg-red-500");
            statusIcon.className = "fas fa-exclamation-triangle mr-2";
            break;
        case "disabled":
        default:
            statusText.textContent = "Live location disabled.";
            statusBox.classList.add("bg-gray-500");
            statusIcon.className = "fas fa-ban mr-2";
            break;
    }
}