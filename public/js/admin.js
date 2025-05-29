document.querySelectorAll('.assign-shift-btn').forEach(button => {
    button.addEventListener('click', () => {
        const driverId = button.dataset.driverId;
        openShiftModal(driverId);
    });
});

function openShiftModal(driverId) {
    const modal = document.getElementById("shiftModal");
    modal.classList.remove("hidden");
    modal.dataset.driverId = driverId;

    const existingShift = window.appData.shifts.find(s => s.driver_id === parseInt(driverId) && 
        new Date(s.shift_start).toDateString() === new Date().toDateString());
    if (existingShift) {
        document.getElementById("shiftStart").value = existingShift.shift_start.slice(0, 16);
        document.getElementById("shiftEnd").value = existingShift.shift_end.slice(0, 16);
        document.getElementById("carId").value = existingShift.car_id || "";
        document.getElementById("offDutyToggle").checked = false;
    } else {
        document.getElementById("shiftStart").value = "";
        document.getElementById("shiftEnd").value = "";
        document.getElementById("carId").value = "";
        document.getElementById("offDutyToggle").checked = false;
    }
    document.getElementById("shiftStart").disabled = false;
    document.getElementById("shiftEnd").disabled = false;
    document.getElementById("carId").disabled = false;
    toggleOffDuty();
}

function closeShiftModal() {
    document.getElementById("shiftModal").classList.add("hidden");
}

function toggleOffDuty() {
    const isOffDuty = document.getElementById("offDutyToggle").checked;
    const startInput = document.getElementById("shiftStart");
    const endInput = document.getElementById("shiftEnd");
    const carInput = document.getElementById("carId");
    if (isOffDuty) {
        startInput.value = "";
        endInput.value = "";
        carInput.value = "";
        startInput.disabled = true;
        endInput.disabled = true;
        carInput.disabled = true;
    } else {
        startInput.disabled = false;
        endInput.disabled = false;
        carInput.disabled = false;
    }
}

function updateShiftButton(driverId, hasShift) {
    const button = document.querySelector(`.assign-shift-btn[data-driver-id="${driverId}"]`);
    if (button) {
        button.textContent = hasShift ? 'Edit Shift' : 'Assign Shift';
        button.dataset.hasShift = hasShift;
        
        // Update button styling
        if (hasShift) {
            button.classList.remove('bg-blue-500', 'hover:bg-blue-600');
            button.classList.add('bg-purple-500', 'hover:bg-purple-600');
        } else {
            button.classList.remove('bg-purple-500', 'hover:bg-purple-600');
            button.classList.add('bg-blue-500', 'hover:bg-blue-600');
        }
    }
}

// Initialize button states on page load
function initializeButtonStates() {
    document.querySelectorAll('.assign-shift-btn[data-driver-id]').forEach(button => {
        const driverId = button.dataset.driverId;
        const hasShift = button.dataset.hasShift === 'true';
        
        if (hasShift) {
            updateShiftButton(driverId, true);
        }
    });
}

function assignShift() {
    const modal = document.getElementById("shiftModal");
    const driverId = modal.dataset.driverId;
    const isOffDuty = document.getElementById("offDutyToggle").checked;
    const startTime = isOffDuty ? null : (document.getElementById("shiftStart").value || null);
    const endTime = isOffDuty ? null : (document.getElementById("shiftEnd").value || null);
    const carId = isOffDuty ? null : (document.getElementById("carId").value || null);

    if (!isOffDuty && (!startTime || !endTime || !carId)) {
        alert("Please fill in all shift details or mark as Off-Duty.");
        return;
    }

    const data = {
        driver_id: driverId,
        shift_start: startTime,
        shift_end: endTime,
        car_id: carId,
        off_duty: isOffDuty
    };

    fetch('/admin/assign-shift', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
        },
        body: JSON.stringify(data)
    })
    .then(response => response.json())
    .then(result => {
        if (result.success) {
            const button = document.querySelector(`.assign-shift-btn[data-driver-id="${driverId}"]`);
            if (!isOffDuty) {
                // Update button to Edit Shift
                button.textContent = 'Edit Shift';
                button.classList.remove('bg-blue-500', 'hover:bg-blue-600');
                button.classList.add('bg-purple-500', 'hover:bg-purple-600');
                button.dataset.hasShift = 'true';
                button.dataset.shiftEnd = endTime;

                // Update window.appData
                const shiftData = {
                    id: result.shift_id,
                    driver_id: parseInt(driverId),
                    car_id: parseInt(carId),
                    shift_start: startTime,
                    shift_end: endTime,
                    is_active: true
                };
                const existingIndex = window.appData.shifts.findIndex(s => s.driver_id === parseInt(driverId) && 
                    new Date(s.shift_start).toDateString() === new Date().toDateString());
                if (existingIndex >= 0) {
                    window.appData.shifts[existingIndex] = shiftData;
                } else {
                    window.appData.shifts.push(shiftData);
                }
            } else {
                // Update button to Assign Shift
                button.textContent = 'Assign Shift';
                button.classList.remove('bg-purple-500', 'hover:bg-purple-600');
                button.classList.add('bg-blue-500', 'hover:bg-blue-600');
                button.dataset.hasShift = 'false';
                button.dataset.shiftEnd = '';

                // Update window.appData
                window.appData.shifts = window.appData.shifts.filter(s => s.driver_id !== parseInt(driverId) || 
                    new Date(s.shift_start).toDateString() !== new Date().toDateString());
            }
            closeShiftModal();
        } else {
            alert('Failed to assign shift: ' + result.error);
        }
    })
    .catch(error => {
        console.error('Error:', error);
        alert('An error occurred while assigning the shift');
    });
}

function updateDriverStatus(driverId, status) {
    const statusCell = document.querySelector(`tr[data-driver-id="${driverId}"] .status-cell`);
    if (statusCell) {
        statusCell.textContent = status.replace('_', ' ').split(' ').map(word => word.charAt(0).toUpperCase() + word.slice(1)).join(' ');
        statusCell.className = 'status-cell border border-gray-300 px-4 py-2 ' + 
            (status === 'available' ? 'text-green-600' : status === 'on_trip' ? 'text-blue-600' : 'text-gray-600');
        console.log(`Updated status for Driver ${driverId} to ${statusCell.textContent}`);
    } else {
        console.warn(`Status cell not found for Driver ${driverId}`);
    }
}

function updateCarDropdown(availableCars) {
    const carSelect = document.getElementById("carId");
    carSelect.innerHTML = '<option value="">Select a Car</option>';
    availableCars.forEach(car => {
        const option = document.createElement("option");
        option.value = car.id;
        option.textContent = `${car.brand} ${car.model} (${car.license_plate})`;
        carSelect.appendChild(option);
    });
    console.log('Updated car dropdown with:', availableCars);
}

function trackDriver(driverId) {
    alert(`Tracking location for Driver ${driverId}. (This will open a map popup in production.)`);
}

// Add this function to check shift status periodically
function checkShiftStatus() {
    fetch('/shift-states', {
        headers: {
            'Accept': 'application/json'
        }
    })
    .then(response => response.json())
    .then(shifts => {
        const now = new Date();
        console.log('Fetched current shift states:', shifts);
        
        // First, reset all buttons to "Assign Shift" state
        document.querySelectorAll('.assign-shift-btn[data-driver-id]').forEach(button => {
            const driverId = button.dataset.driverId;
            // Find if driver has any active shifts
            const driverShifts = shifts.filter(shift => shift.driver_id == driverId);
            
            if (driverShifts.length > 0) {
                // Driver has active shifts
                const hasActiveShift = driverShifts.some(shift => 
                    new Date(shift.shift_end) > now && shift.has_shift_today
                );
                
                updateShiftButton(driverId, hasActiveShift);
                
                // Update the shift end data attribute
                const latestShift = driverShifts.sort((a, b) => 
                    new Date(b.shift_end) - new Date(a.shift_end)
                )[0];
                
                if (latestShift) {
                    button.dataset.shiftEnd = latestShift.shift_end;
                }
            } else {
                // Driver has no active shifts
                updateShiftButton(driverId, false);
                button.dataset.shiftEnd = '';
            }
        });
        console.log('Button states updated based on server data');
    })
    .catch(error => console.error('Error fetching shift states:', error));
}

// Poll driver statuses every 60 seconds
function fetchDriverStatuses() {
    fetch('/driver-statuses', {
        headers: {
            'Accept': 'application/json'
        }
    })
    .then(response => response.json())
    .then(drivers => {
        drivers.forEach(driver => {
            updateDriverStatus(driver.id, driver.status);
        });
        console.log('Driver statuses updated:', drivers);
    })
    .catch(error => console.error('Error fetching driver statuses:', error));
}

// Initial state setup
initializeButtonStates();
fetchDriverStatuses(); // Initial fetch
checkShiftStatus(); // Initial check

// Set interval for periodic updates
setInterval(fetchDriverStatuses, 30000); // Every 30 seconds
setInterval(checkShiftStatus, 30000); // Check every 30 seconds

// Driver Shift Modal Handling
const driverShiftModal = document.getElementById("driverShiftModal");
const closeDriverModal = document.getElementById("closeDriverModal");
const currentShiftTabModal = document.getElementById("currentShiftTabModal");
const pastShiftTabModal = document.getElementById("pastShiftTabModal");
const shiftLogTabModal = document.getElementById("shiftLogTabModal");

document.querySelectorAll('.driver-name').forEach(link => {
    link.addEventListener('click', (e) => {
        e.preventDefault();
        const driverId = parseInt(link.getAttribute('data-driver-id'));
        const driver = window.appData.drivers.find(d => d.id === driverId);
        document.getElementById('modalDriverName').textContent = `${driver.name}'s Shift Details`;
        driverShiftModal.dataset.driverId = driverId;
        driverShiftModal.classList.remove('hidden');
        showModalTab('current', driverId);
    });
});

closeDriverModal.addEventListener('click', () => {
    driverShiftModal.classList.add('hidden');
    driverShiftModal.dataset.driverId = '';
});

currentShiftTabModal.addEventListener('click', () => {
    const driverId = parseInt(driverShiftModal.dataset.driverId);
    if (driverId) showModalTab('current', driverId);
});

pastShiftTabModal.addEventListener('click', () => {
    const driverId = parseInt(driverShiftModal.dataset.driverId);
    if (driverId) showModalTab('past', driverId);
});

shiftLogTabModal.addEventListener('click', () => {
    const driverId = parseInt(driverShiftModal.dataset.driverId);
    if (driverId) showModalTab('logs', driverId);
});

function showModalTab(tab, driverId) {
    const currentShiftModal = document.getElementById('currentShiftModal');
    const pastShiftsModal = document.getElementById('pastShiftsModal');
    const shiftLogsModal = document.getElementById('shiftLogsModal');
    const currentShiftButton = document.getElementById('currentShiftTabModal');
    const pastShiftButton = document.getElementById('pastShiftTabModal');
    const shiftLogsButton = document.getElementById('shiftLogTabModal');

    // Hide all and reset buttons
    currentShiftModal.classList.add('hidden');
    pastShiftsModal.classList.add('hidden');
    shiftLogsModal.classList.add('hidden');
    
    currentShiftButton.classList.remove('text-blue-600', 'border-blue-500');
    currentShiftButton.classList.add('text-gray-600', 'border-transparent');
    
    pastShiftButton.classList.remove('text-blue-600', 'border-blue-500');
    pastShiftButton.classList.add('text-gray-600', 'border-transparent');
    
    shiftLogsButton.classList.remove('text-blue-600', 'border-blue-500');
    shiftLogsButton.classList.add('text-gray-600', 'border-transparent');

    if (tab === 'current') {
        currentShiftModal.classList.remove('hidden');
        currentShiftButton.classList.add('text-blue-600', 'border-blue-500');
        currentShiftButton.classList.remove('text-gray-600', 'border-transparent');
        renderCurrentShiftTripsModal(driverId);
    } else if (tab === 'past') {
        pastShiftsModal.classList.remove('hidden');
        pastShiftButton.classList.add('text-blue-600', 'border-blue-500');
        pastShiftButton.classList.remove('text-gray-600', 'border-transparent');
        renderPastShiftsModal(driverId);
    } else if (tab === 'logs') {
        shiftLogsModal.classList.remove('hidden');
        shiftLogsButton.classList.add('text-blue-600', 'border-blue-500');
        shiftLogsButton.classList.remove('text-gray-600', 'border-transparent');
        renderShiftLogsModal(driverId);
    }
}

function formatDate(dateStr) {
    if (!dateStr) return 'N/A';
    const date = new Date(dateStr);
    const weekday = date.toLocaleString('en-US', { weekday: 'long' });
    const month = date.toLocaleString('en-US', { month: 'long' });
    const day = date.getDate();
    const year = date.getFullYear();
    return `${weekday}, ${month} ${day} ${year}`;
}

function formatTime(dateString) {
    if (!dateString) return '[-]';
    // Always use the server's timezone (EAT/UTC+3)
    const eatDateString = dateString.includes('Z') || dateString.includes('+') ? dateString : `${dateString}+03:00`;
    return new Intl.DateTimeFormat('en-US', {
        hour: '2-digit',
        minute: '2-digit',
        timeZone: 'Africa/Dar_es_Salaam'
    }).format(new Date(eatDateString));
}

// Helper function to convert any date string to EAT timezone
function convertToEAT(dateString) {
    if (!dateString) return null;
    const eatDateString = dateString.includes('Z') || dateString.includes('+') ? dateString : `${dateString}+03:00`;
    return new Date(eatDateString);
}

function getStatusClass(status) {
    const normalizedStatus = (status || '').toString().toLowerCase().trim();
    if (normalizedStatus === 'ongoing') return 'bg-green-100 text-green-600 border-green-400';
    if (normalizedStatus === 'completed') return 'bg-blue-100 text-blue-600 border-blue-400';
    if (normalizedStatus === 'cancelled') return 'bg-red-100 text-red-600 border-red-400';
    if (normalizedStatus === 'assigned') return 'bg-yellow-100 text-yellow-600 border-yellow-400';
    return 'bg-gray-500 text-white';
}

function renderCurrentShiftTripsModal(driverId) {
    const currentShiftTripsList = document.getElementById('currentShiftTripsListModal');
    currentShiftTripsList.innerHTML = '';

    // Get the current shift, if any
    const currentShift = window.appData.shifts.find(s => 
        s.driver_id === driverId && 
        convertToEAT(s.shift_end) >= new Date()
    );

    // Get trips for today, including completed ones
    const currentTrips = window.appData.trips.filter(trip => {
        // Get the trip date
        const tripDate = new Date(trip.started_at).toDateString();
        const today = new Date().toDateString();
        
        // Include if the trip is from today AND belongs to this driver
        return trip.driver_id === driverId && tripDate === today;
    });

    // Get current day requests, including cancelled ones
    const currentRequests = window.appData.assignedRequests.filter(req => {
        // Get the request date
        const requestDate = new Date(req.assigned_at).toDateString();
        const today = new Date().toDateString();
        
        // Include if the request is from today AND belongs to this driver
        return req.driver_id === driverId && requestDate === today;
    });

    console.log('Current Shift Data:', { driverId, currentShift, currentTrips, currentRequests });

    if (!currentShift && currentTrips.length === 0 && currentRequests.length === 0) {
        currentShiftTripsList.innerHTML = '<li class="text-gray-600">No current shift or trips/requests.</li>';
        return;
    }

    if (currentShift) {
        const shiftInfo = document.createElement('li');
        shiftInfo.className = 'bg-gray-50 p-3 rounded-md mb-2';
        shiftInfo.innerHTML = `
            <p><strong>Shift Start:</strong> ${formatTime(currentShift.shift_start)}</p>
            <p><strong>Shift End:</strong> ${formatTime(currentShift.shift_end)}</p>
            <p><strong>Car:</strong> ${currentShift.car ? `${currentShift.car.brand} ${currentShift.car.model} (${currentShift.car.license_plate})` : 'N/A'}</p>
        `;
        currentShiftTripsList.appendChild(shiftInfo);
    }

    const items = [
        ...currentTrips.map(trip => ({ type: 'trip', data: trip })),
        ...currentRequests.map(req => ({ type: 'request', data: req }))
    ].sort((a, b) => {
        const aTime = a.type === 'trip' ? new Date(a.data.started_at) : new Date(a.data.assigned_at);
        const bTime = b.type === 'trip' ? new Date(b.data.started_at) : new Date(b.data.assigned_at);
        return bTime - aTime;
    });

    items.forEach(item => {
        const { type, data } = item;
        const tripItem = document.createElement('li');
        tripItem.className = 'bg-gray-100 p-3 rounded-md shadow-sm border border-gray-200';
        const pickupLocation = data.pickup_location || (data.car_request ? data.car_request.pickup_location : 'N/A');
        const dropoffLocation = data.dropoff_location || (data.car_request ? data.car_request.dropoff_location : 'N/A');
        const status = type === 'trip' ? data.status : data.status;

        tripItem.innerHTML = `
            <p class="flex justify-between">
                <span class="font-semibold text-blue-600"><strong class="text-black">${type === 'trip' ? 'Trip' : 'Request'} ID:</strong> ${data.id}</span>
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
        currentShiftTripsList.appendChild(tripItem);
    });
}

function renderPastShiftsModal(driverId) {
    const pastShiftsList = document.getElementById('pastShiftsListModal');
    pastShiftsList.innerHTML = '';

    const allShifts = window.appData.shifts.filter(s => 
        s.driver_id === driverId && 
        convertToEAT(s.shift_end) < new Date()
    );

    // Get trips NOT from today
    const pastTrips = window.appData.trips.filter(trip => {
        // Get the trip date
        const tripDate = new Date(trip.started_at).toDateString();
        const today = new Date().toDateString();
        
        // Include if the trip is NOT from today AND belongs to this driver
        return trip.driver_id === driverId && tripDate !== today;
    });

    // Get requests NOT from today
    const pastRequests = window.appData.assignedRequests.filter(req => {
        // Get the request date
        const requestDate = new Date(req.assigned_at).toDateString();
        const today = new Date().toDateString();
        
        // Include if the request is NOT from today AND belongs to this driver
        return req.driver_id === driverId && requestDate !== today;
    });

    console.log('Past Shifts Data:', { driverId, allShifts, pastTrips, pastRequests });

    if (allShifts.length === 0 && pastTrips.length === 0 && pastRequests.length === 0) {
        pastShiftsList.innerHTML = '<li class="text-gray-600">No past shifts, trips, or requests found.</li>';
        return;
    }

    const shifts = {};
    allShifts.forEach(shift => {
        const date = new Date(shift.shift_start).toDateString();
        shifts[date] = {
            items: [],
            startTime: formatTime(shift.shift_start),
            endTime: formatTime(shift.shift_end),
            carPlate: shift.car ? shift.car.license_plate : 'N/A',
            carBrand: shift.car ? shift.car.brand : 'N/A',
            carModel: shift.car ? shift.car.model : 'N/A',
            shiftDate: new Date(shift.shift_start)
        };
    });

    pastTrips.forEach(trip => {
        const shift = window.appData.shifts.find(s => s.id === trip.shift_id);
        const date = shift ? new Date(shift.shift_start).toDateString() : new Date(trip.started_at).toDateString();
        if (!shifts[date]) {
            shifts[date] = { 
                items: [], 
                startTime: 'N/A', 
                endTime: 'N/A', 
                carPlate: 'N/A', 
                carBrand: 'N/A', 
                carModel: 'N/A',
                shiftDate: shift ? new Date(shift.shift_start) : new Date(trip.started_at)
            };
        }
        shifts[date].items.push({ type: 'trip', data: trip });
    });

    pastRequests.forEach(req => {
        const shift = window.appData.shifts.find(s => 
            s.driver_id === req.driver_id && 
            new Date(s.shift_start).toDateString() === new Date(req.assigned_at).toDateString()
        );
        const date = shift ? new Date(shift.shift_start).toDateString() : new Date(req.assigned_at).toDateString();
        if (!shifts[date]) {
            shifts[date] = { 
                items: [], 
                startTime: 'N/A', 
                endTime: 'N/A', 
                carPlate: 'N/A', 
                carBrand: 'N/A', 
                carModel: 'N/A',
                shiftDate: shift ? new Date(shift.shift_start) : new Date(req.assigned_at)
            };
        }
        shifts[date].items.push({ type: 'request', data: req });
    });

    const sortedShifts = Object.entries(shifts).sort((a, b) => b[1].shiftDate - a[1].shiftDate);

    sortedShifts.forEach(([date, shiftData]) => {
        shiftData.items.sort((a, b) => {
            const aTime = a.type === 'trip' ? new Date(a.data.started_at) : new Date(a.data.assigned_at);
            const bTime = b.type === 'trip' ? new Date(b.data.started_at) : new Date(b.data.assigned_at);
            return bTime - aTime;
        });

        const shiftItem = document.createElement('li');
        shiftItem.className = 'border border-gray-300 rounded-lg p-4 cursor-pointer bg-gray-50 hover:bg-gray-100 transition-all flex justify-between items-center shadow-md';
        shiftItem.innerHTML = `
            <span class="text-blue-600 font-semibold">${formatDate(date)}</span>
            <i class="fas fa-chevron-down text-gray-500 transition-transform"></i>
        `;

        const shiftDetails = document.createElement('div');
        shiftDetails.className = 'hidden shift-details bg-white p-4 mt-2 rounded-lg shadow-md border border-gray-200';
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
                    return `
                        <li class="bg-gray-100 p-3 rounded-md shadow-sm border border-gray-200">
                            <p class="flex justify-between">
                                <span class="font-semibold text-blue-600"><strong class="text-black">${type === 'trip' ? 'Trip' : 'Request'} ID:</strong> ${data.id}</span>
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

        shiftItem.addEventListener('click', (e) => {
            e.stopPropagation();
            document.querySelectorAll('#pastShiftsListModal .shift-details').forEach(div => {
                if (div !== shiftDetails) div.classList.add('hidden');
            });
            shiftDetails.classList.toggle('hidden');
            shiftItem.querySelector('i').classList.toggle('rotate-180');
        });

        pastShiftsList.appendChild(shiftItem);
        pastShiftsList.appendChild(shiftDetails);
    });
}

// Pending Issues Handling
function checkPendingIssues() {
    const pendingIssuesList = document.getElementById('pendingIssuesList');
    pendingIssuesList.innerHTML = '';

    // Debug log the unassigned requests
    console.log('All assigned requests:', window.appData.assignedRequests);

    const unassignedRequests = window.appData.assignedRequests.filter(request => {
        // Use the EAT timezone for date calculations
        const requestTime = convertToEAT(request.requested_at);
        const currentTime = new Date();
        const minutesDiff = (currentTime - requestTime) / (1000 * 60);
        return !request.driver_id && request.status === 'Pending' && minutesDiff >= 2;
    });

    console.log('Filtered unassigned requests:', unassignedRequests);

    if (unassignedRequests.length === 0) {
        pendingIssuesList.innerHTML = '<p class="text-center text-gray-600">No pending issues at the moment.</p>';
        return;
    }

    unassignedRequests.forEach(request => {
        // Use the EAT timezone for date calculations
        const requestTime = convertToEAT(request.requested_at);
        const currentTime = new Date();
        const minutesDiff = Math.floor((currentTime - requestTime) / (1000 * 60));

        // Get correct pickup and destination fields - check request structure
        const pickupLocation = request.pickup_location || 
                              (request.car_request ? request.car_request.pickup_location : 'N/A');
        const dropoffLocation = request.dropoff_location || 
                               (request.car_request ? request.car_request.dropoff_location : 'N/A');

        const issueItem = document.createElement('div');
        issueItem.className = 'border border-gray-300 rounded-lg p-4 hover:bg-gray-50 cursor-pointer';
        issueItem.innerHTML = `
            <div class="flex justify-between items-start">
                <div>
                    <h3 class="font-semibold text-red-600">Unassigned Request #${request.id}</h3>
                    <p class="text-sm text-gray-600">Waiting for ${minutesDiff} minutes</p>
                </div>
                <button onclick="openTripAssignmentModal(${request.id})" class="bg-blue-500 text-white px-3 py-1 rounded hover:bg-blue-600">
                    Assign Driver
                </button>
            </div>
            <div class="mt-2 text-sm">
                <p><strong>Pick-up:</strong> ${pickupLocation}</p>
                <p><strong>Destination:</strong> ${dropoffLocation}</p>
                <p><strong>Requested at:</strong> ${formatTime(request.requested_at)}</p>
            </div>
        `;
        pendingIssuesList.appendChild(issueItem);
    });
}

// Trip Assignment Modal Functions
function openTripAssignmentModal(requestId) {
    const modal = document.getElementById('tripAssignmentModal');
    const request = window.appData.assignedRequests.find(r => r.id === requestId);
    
    if (!request) {
        console.error('Request not found:', requestId);
        return;
    }

    // Get correct pickup and destination fields
    const pickupLocation = request.pickup_location || 
                         (request.car_request ? request.car_request.pickup_location : 'N/A');
    const dropoffLocation = request.dropoff_location || 
                          (request.car_request ? request.car_request.dropoff_location : 'N/A');

    // Populate request details
    document.getElementById('requestDetails').innerHTML = `
        <p><strong>Request ID:</strong> ${request.id}</p>
        <p><strong>Pick-up Point:</strong> ${pickupLocation}</p>
        <p><strong>Destination:</strong> ${dropoffLocation}</p>
        <p><strong>Requested at:</strong> ${formatTime(request.requested_at)}</p>
    `;

    // Populate driver select
    const driverSelect = document.getElementById('driverSelect');
    driverSelect.innerHTML = '<option value="">Select a Driver</option>';
    
    // Log all available drivers for debugging
    console.log('All drivers:', window.appData.drivers);
    
    // Find all available drivers
    const availableDrivers = window.appData.drivers.filter(driver => {
        return driver.status === 'available';
    });
    
    console.log('Filtered available drivers:', availableDrivers);

    // Add all available drivers to среды

    availableDrivers.forEach(driver => {
        const option = document.createElement('option');
        option.value = driver.id;
        option.textContent = driver.name;
        driverSelect.appendChild(option);
    });

    console.log('Driver dropdown populated with', availableDrivers.length, 'drivers');

    document.getElementById('requestId').value = requestId;
    modal.classList.remove('hidden');
}

function closeTripAssignmentModal() {
    document.getElementById('tripAssignmentModal').classList.add('hidden');
}

// Handle trip assignment form submission
document.getElementById('tripAssignmentForm').addEventListener('submit', function(e) {
    e.preventDefault();
    const requestId = document.getElementById('requestId').value;
    const driverId = document.getElementById('driverSelect').value;

    console.log('Submitting assignment form with:', { requestId, driverId });

    if (!driverId) {
        alert('Please select a driver');
        return;
    }

    // Get CSRF token
    const token = document.querySelector('meta[name="csrf-token"]').content;
    console.log('CSRF Token:', token ? 'Found' : 'Not found');

    // Prepare data object matching server expectations
    const data = {
        car_request_id: parseInt(requestId),
        driver_id: parseInt(driverId)
    };

    console.log('Sending data to server:', data);

    fetch('/admin/assign-driver', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': token,
            'Accept': 'application/json'
        },
        body: JSON.stringify(data)
    })
    .then(response => {
        if (!response.ok) {
            throw new Error('Network response was not ok: ' + response.status);
        }
        return response.json();
    })
    .then(result => {
        console.log('Server response:', result);
        
        if (result.success) {
            // Update the request in window.appData
            const requestIndex = window.appData.assignedRequests.findIndex(r => r.id === parseInt(requestId));
            if (requestIndex !== -1) {
                window.appData.assignedRequests[requestIndex].driver_id = parseInt(driverId);
                window.appData.assignedRequests[requestIndex].status = 'Assigned';
                window.appData.assignedRequests[requestIndex].assigned_at = new Date().toISOString();
                console.log('Updated request in appData:', window.appData.assignedRequests[requestIndex]);
            } else {
                console.warn('Request not found in appData:', requestId);
            }
            
            // Refresh pending issues
            checkPendingIssues();
            
            // Close modal
            closeTripAssignmentModal();
            
            // Show success message
            alert('Driver assigned successfully!');
        } else {
            console.error('Assignment failed:', result.error);
            alert('Failed to assign driver: ' + (result.error || 'Unknown error'));
        }
    })
    .catch(error => {
        console.error('Error assigning driver:', error);
        alert('An error occurred while assigning the driver: ' + error.message);
    });
});

// Check for pending issues every minute
setInterval(checkPendingIssues, 60000);
// Initial check
checkPendingIssues();

// Initialize button states when the page loads
document.addEventListener('DOMContentLoaded', function() {
    initializeButtonStates();
});

function renderShiftLogsModal(driverId) {
    const shiftLogsList = document.getElementById('shiftLogsListModal');
    shiftLogsList.innerHTML = '<li class="text-center text-gray-600">Loading shift logs...</li>';

    fetch(`/driver-shift-logs/${driverId}`, {
        headers: {
            'Accept': 'application/json'
        }
    })
    .then(response => response.json())
    .then(logs => {
        console.log('Shift Logs Data:', logs);
        
        if (logs.length === 0) {
            shiftLogsList.innerHTML = '<li class="text-center text-gray-600">No shift logs found for this driver.</li>';
            return;
        }
        
        shiftLogsList.innerHTML = '';
        
        // Group logs by month/year for better organization
        const groupedLogs = {};
        logs.forEach(log => {
            const date = new Date(log.shift_date);
            const monthYear = `${date.toLocaleString('default', { month: 'long' })} ${date.getFullYear()}`;
            
            if (!groupedLogs[monthYear]) {
                groupedLogs[monthYear] = [];
            }
            
            // Add formatted date with day of week
            const weekday = date.toLocaleString('en-US', { weekday: 'long' });
            const month = date.toLocaleString('en-US', { month: 'long' });
            const day = date.getDate();
            const year = date.getFullYear();
            log.display_date = `${weekday}, ${month} ${day} ${year}`;
            
            groupedLogs[monthYear].push(log);
        });
        
        // Create a section for each month
        Object.keys(groupedLogs).forEach(monthYear => {
            const monthHeader = document.createElement('li');
            monthHeader.className = 'font-semibold text-lg text-blue-600 mt-4 mb-2';
            monthHeader.textContent = monthYear;
            shiftLogsList.appendChild(monthHeader);
            
            // Create a table for this month's logs
            const table = document.createElement('table');
            table.className = 'w-full border-collapse';
            table.innerHTML = `
                <thead>
                    <tr class="bg-gray-100">
                        <th class="border px-4 py-2 text-left">Date</th>
                        <th class="border px-4 py-2 text-left">Status</th>
                    </tr>
                </thead>
                <tbody>
                </tbody>
            `;
            
            const tbody = table.querySelector('tbody');
            
            // Add rows for each log entry
            groupedLogs[monthYear].forEach(log => {
                const row = document.createElement('tr');
                row.className = 'border hover:bg-gray-50';
                
                const dateCell = document.createElement('td');
                dateCell.className = 'border px-4 py-2';
                dateCell.textContent = log.display_date;
                
                const statusCell = document.createElement('td');
                statusCell.className = 'border px-4 py-2';
                
                if (log.on_duty) {
                    statusCell.innerHTML = '<span class="px-2 py-1 bg-green-100 text-green-800 rounded-full text-xs font-semibold">On Duty</span>';
                } else {
                    statusCell.innerHTML = '<span class="px-2 py-1 bg-red-100 text-red-800 rounded-full text-xs font-semibold">Off Duty</span>';
                }
                
                row.appendChild(dateCell);
                row.appendChild(statusCell);
                tbody.appendChild(row);
            });
            
            const tableContainer = document.createElement('li');
            tableContainer.className = 'mb-4 shadow-sm';
            tableContainer.appendChild(table);
            shiftLogsList.appendChild(tableContainer);
        });
    })
    .catch(error => {
        console.error('Error fetching shift logs:', error);
        shiftLogsList.innerHTML = '<li class="text-center text-red-600">Error loading shift logs. Please try again.</li>';
    });
}
