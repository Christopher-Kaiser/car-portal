<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>Driver Dashboard - Transport Portal</title>
    <link href="https://unpkg.com/tailwindcss@2.2.19/dist/tailwind.min.css" rel="stylesheet" />
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css" rel="stylesheet" />
</head>
<body class="bg-gray-100">
    <div class="max-w-3xl mx-auto p-6 bg-white shadow-lg rounded-lg my-8">
        <h1 class="text-3xl font-bold text-center mb-6">Driver Dashboard</h1>

        <!-- Location Tracking Status -->
        <div id="locationStatus" class="mx-auto w-80 p-3 flex items-center justify-center text-white font-semibold rounded mb-4 text-center bg-gray-500">
            <i class="fas fa-ban mr-2"></i>
            <span>Live location disabled.</span>
        </div>
        
        <!-- Tracking Toggle Button -->
        <div class="flex justify-center mb-6">
            <button id="trackingToggleButton" class="h-10 w-[200px] inline-flex items-center justify-center bg-blue-500 hover:bg-blue-600 text-white font-semibold px-4 rounded">
                <i class="fas fa-power-off mr-2"></i>
                <span id="trackingToggleText" class="whitespace-nowrap">Enable Tracking</span>
            </button>
        </div>

        <!-- Shift & Assigned Car Information -->
        <div class="bg-blue-100 p-6 rounded-lg shadow mb-6 border border-blue-200">
            <h2 class="text-2xl font-semibold text-blue-600 mb-4">Shift Details</h2>
            <div class="grid grid-rows-4 grid-cols-2 gap-x-60" style="grid-template-rows: auto auto auto auto;">
                <div class="font-bold mb-6">Shift Start Time: <span class="font-normal">{{ $shift ? $shift->shift_start->format('h:i A') : 'Not scheduled' }}</span></div>
                <div class="font-bold mb-6">Shift End Time: <span class="font-normal">{{ $shift ? $shift->shift_end->format('h:i A') : 'Not scheduled' }}</span></div>
                <div class="col-span-2 text-xl font-semibold mb-4">Assigned Car:</div>
                <div class="mb-2"><strong>Car Plate No.:</strong> {{ $car ? $car->license_plate : 'Not assigned' }}</div>
                <div class="mb-2"><strong>Brand:</strong> {{ $car ? $car->brand : 'N/A' }}</div>
                <div class="mb-2"><strong>Status:</strong> {{ $car ? $car->status : 'N/A' }}</div>
                <div class="mb-2"><strong>Model:</strong> {{ $car ? $car->model : 'N/A' }}</div>
            </div>
        </div>

        <!-- Driver's Trips Section -->
        <div class="bg-green-200 p-4 rounded-lg shadow-lg mb-6">
            <h2 class="text-2xl font-semibold text-gray-700 mb-4">Your Trips</h2>
            <div class="flex border-b border-gray-300">
                <button id="pastShiftTab" class="flex-1 p-2 text-center text-gray-600 border-b-2 border-transparent focus:outline-none" onclick="showTab('past')">Past Shifts</button>
                <button id="currentShiftTab" class="flex-1 p-2 text-center text-gray-600 border-b-2 border-transparent focus:outline-none" onclick="showTab('current')">Current Shift</button>
            </div>
            <div class="mt-2">
                <div id="currentShift" class="tab-content">
                    <div class="bg-white p-4 rounded shadow">
                        <h3 class="text-xl font-semibold mb-4">Current Shift Trips</h3>
                        <ul id="currentShiftTripsList" class="space-y-4"></ul>
                    </div>
                </div>
                <div id="pastShifts" class="tab-content hidden">
                    <div class="bg-white p-4 rounded shadow">
                        <h3 class="text-xl font-semibold mb-4">Past Shifts</h3>
                        <ul id="pastShiftsList" class="space-y-4"></ul>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Inject PHP data into JavaScript -->
    <script type="text/javascript">
        window.appData = {
            allTrips: @json($trips),
            allShifts: @json($shifts),
            currentShiftId: @json($shift ? $shift->id : null),
            assignedRequests: @json($assignedRequests)
        };
        console.log('App Data:', window.appData);
    </script>

    <!-- Include external JavaScript file -->
    <script src="/js/driver.js" type="text/javascript"></script>
</body>
</html>