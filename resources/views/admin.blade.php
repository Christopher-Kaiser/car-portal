<!DOCTYPE html>
<html lang="en">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>Admin Dashboard - Transport Portal</title>
        <link href="https://unpkg.com/tailwindcss@2.2.19/dist/tailwind.min.css" rel="stylesheet" />
        <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css" rel="stylesheet" />
        <meta name="csrf-token" content="{{ csrf_token() }}">
    </head>
    <body class="bg-gray-100">
        <div class="max-w-4xl mx-auto p-6 bg-white shadow-lg rounded-lg my-8">
            <h1 class="text-3xl font-bold text-center mb-6">Admin Dashboard</h1>
            
            <!-- Manage Drivers & Shifts -->
            <div class="mb-8">
                <h2 class="text-2xl font-semibold mb-4 text-gray-700">Manage Drivers & Shifts</h2>
                <table class="w-full border-collapse border border-gray-300">
                    <thead>
                        <tr class="bg-gray-100">
                            <th class="border border-gray-300 px-4 py-2 text-left">Driver Name</th>
                            <th class="border border-gray-300 px-4 py-2 text-left">Status</th>
                            <th class="border border-gray-300 px-4 py-2 text-left">Shift</th>
                            <th class="border border-gray-300 px-4 py-2 text-left">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($drivers as $driver)
                            <tr class="border border-gray-300" data-driver-id="{{ $driver['id'] }}">
                                <td class="border border-gray-300 px-4 py-2">
                                    <a href="#" class="text-blue-600 hover:underline driver-name" data-driver-id="{{ $driver['id'] }}">{{ $driver['name'] }}</a>
                                </td>
                                <td class="status-cell border border-gray-300 px-4 py-2 {{ $driver['status'] === 'available' ? 'text-green-600' : ($driver['status'] === 'on_trip' ? 'text-blue-600' : 'text-gray-600') }}">
                                    {{ ucfirst(str_replace('_', ' ', $driver['status'])) }}
                                </td>
                                <td class="border border-gray-300 px-4 py-2">
                                    <button 
                                        class="assign-shift-btn bg-blue-500 text-white px-3 py-1 rounded hover:bg-blue-600 min-w-[100px] w-32 transition-colors duration-200"
                                        data-driver-id="{{ $driver['id'] }}"
                                        data-has-shift="{{ $driver['has_shift_today'] ? 'true' : 'false' }}"
                                        data-shift-end="{{ $driver['has_shift_today'] ? $driver['shift']['shift_end'] : '' }}"
                                    >
                                        {{ $driver['has_shift_today'] ? 'Edit Shift' : 'Assign Shift' }}
                                    </button>
                                </td>
                                <td class="border border-gray-300 px-4 py-2">
                                    <button class="bg-green-500 text-white px-3 py-1 rounded hover:bg-green-600" onclick="trackDriver({{ $driver['id'] }})">
                                        Track Location
                                    </button>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="4" class="border border-gray-300 px-4 py-2 text-center text-gray-600">No drivers found.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        
            <!-- Pending Issues Section -->
            <div class="mb-8">
                <h2 class="text-2xl font-semibold mb-4 text-gray-700">Pending Issues</h2>
                <div id="pendingIssuesList" class="space-y-4">
                    <!-- Pending issues will be populated here by JavaScript -->
                </div>
            </div>
        </div>

        <!-- Shift Assignment Modal -->
        <div id="shiftModal" class="hidden fixed inset-0 bg-gray-800 bg-opacity-50 flex items-center justify-center">
            <div class="bg-white p-6 rounded shadow-lg w-full max-w-md">
                <h3 class="text-xl font-semibold mb-4">Assign Shift</h3>
                <label class="block mb-2 font-medium text-gray-700">Start Time</label>
                <input type="datetime-local" id="shiftStart" class="w-full border border-gray-300 p-2 rounded mb-4">
                <label class="block mb-2 font-medium text-gray-700">End Time</label>
                <input type="datetime-local" id="shiftEnd" class="w-full border border-gray-300 p-2 rounded mb-4">
                <label class="block mb-2 font-medium text-gray-700">Car</label>
                <select id="carId" class="w-full border border-gray-300 p-2 rounded mb-4">
                    <option value="">Select a Car</option>
                    @foreach ($cars as $car)
                        <option value="{{ $car['id'] }}">{{ $car['brand'] }} {{ $car['model'] }} ({{ $car['license_plate'] }})</option>
                    @endforeach
                </select>
                <div class="mb-4">
                    <input type="checkbox" id="offDutyToggle" class="mr-2" onchange="toggleOffDuty()">
                    <label for="offDutyToggle" class="font-medium text-gray-700">Mark as Off-Duty</label>
                </div>
                <div class="flex justify-end">
                    <button class="bg-gray-500 text-white px-4 py-2 rounded mr-2" onclick="closeShiftModal()">Cancel</button>
                    <button class="bg-blue-500 text-white px-4 py-2 rounded" onclick="assignShift()">Save</button>
                </div>
            </div>
        </div>

        <!-- Trip Assignment Modal -->
        <div id="tripAssignmentModal" class="hidden fixed inset-0 bg-gray-800 bg-opacity-50 flex items-center justify-center">
            <div class="bg-white p-6 rounded shadow-lg w-full max-w-md">
                <div class="flex justify-between items-center mb-4">
                    <h3 class="text-xl font-semibold">Assign Driver to Request</h3>
                    <button onclick="closeTripAssignmentModal()" class="text-gray-600 hover:text-gray-800">
                        <i class="fas fa-times"></i>
                    </button>
                </div>
                <div id="requestDetails" class="mb-4 p-4 bg-gray-50 rounded">
                    <!-- Request details will be populated here -->
                </div>
                <form id="tripAssignmentForm" class="space-y-4">
                    <input type="hidden" id="requestId" name="requestId">
                    <div>
                        <label for="driverSelect" class="block font-medium text-gray-700">Select Driver:</label>
                        <select id="driverSelect" name="driver_id" class="w-full p-2 border rounded">
                            <option value="">Select a Driver</option>
                        </select>
                    </div>
                    <button type="submit" class="w-full bg-blue-500 hover:bg-blue-600 text-white font-bold py-2 px-4 rounded">
                        Assign Driver
                    </button>
                </form>
            </div>
        </div>

        <!-- Driver Shift Details Modal -->
        <div id="driverShiftModal" class="hidden fixed inset-0 bg-gray-800 bg-opacity-50 flex items-center justify-center" data-driver-id="">
            <div class="bg-white p-6 rounded shadow-lg w-full max-w-3xl flex flex-col" style="max-height: 80vh;">
                <div class="flex justify-between items-center mb-4">
                    <h2 id="modalDriverName" class="text-xl font-semibold"></h2>
                    <button id="closeDriverModal" class="text-gray-600 hover:text-gray-800 text-2xl font-bold p-2" aria-label="Close modal">
                        <i class="fas fa-times"></i>
                    </button>
                </div>
                <div class="flex border-b border-gray-300">
                    <button id="currentShiftTabModal" class="flex-1 p-2 text-center text-gray-600 border-b-2 border-transparent focus:outline-none">Current Shift</button>
                    <button id="pastShiftTabModal" class="flex-1 p-2 text-center text-gray-600 border-b-2 border-transparent focus:outline-none">Past Shifts</button>
                    <button id="shiftLogTabModal" class="flex-1 p-2 text-center text-gray-600 border-b-2 border-transparent focus:outline-none">Shift Logs</button>
                </div>
                <div class="mt-2 flex-1 overflow-y-auto">
                    <div id="currentShiftModal" class="tab-content">
                        <div class="bg-white p-4 rounded shadow">
                            <h3 class="text-xl font-semibold mb-4">Current Shift Trips</h3>
                            <ul id="currentShiftTripsListModal" class="space-y-4"></ul>
                        </div>
                    </div>
                    <div id="pastShiftsModal" class="tab-content hidden">
                        <div class="bg-white p-4 rounded shadow">
                            <h3 class="text-xl font-semibold mb-4">Past Shifts</h3>
                            <ul id="pastShiftsListModal" class="space-y-4"></ul>
                        </div>
                    </div>
                    <div id="shiftLogsModal" class="tab-content hidden">
                        <div class="bg-white p-4 rounded shadow">
                            <h3 class="text-xl font-semibold mb-4">Shift Logs</h3>
                            <ul id="shiftLogsListModal" class="space-y-4"></ul>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <script type="text/javascript">
            window.appData = {
                drivers: @json($drivers),
                shifts: @json($shifts),
                trips: @json($trips),
                assignedRequests: @json($assignedRequests),
                cars: @json($cars)
            };
        </script>
        <script src="{{ asset('js/admin.js') }}"></script>
    </body>
</html>
