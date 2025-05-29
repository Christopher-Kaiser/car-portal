<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>User Dashboard - Transport Portal</title>
  <!-- Tailwind CSS CDN -->
  <link href="https://unpkg.com/tailwindcss@2.2.19/dist/tailwind.min.css" rel="stylesheet" />
  <!-- Font Awesome for icons -->
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" />
  <meta name="csrf-token" content="{{ csrf_token() }}">
  <style>
    html, body {
      margin: 0;
      padding: 0;
      height: 100%;
      overflow: hidden;
    }
    #map {
      position: fixed;
      top: 0;
      left: 0;
      width: 100vw;
      height: 100vh;
      z-index: 1;
    }
    .form-panel {
      position: fixed;
      bottom: 0;
      left: 50%;
      transform: translateX(-50%);
      max-width: 600px;
      width: 90%;
      background-color: rgba(255, 255, 255, 0.95);
      z-index: 10;
      padding: 1.5rem;
      border-top-left-radius: 1rem;
      border-top-right-radius: 1rem;
      box-shadow: 0 -4px 6px rgba(0, 0, 0, 0.1);
      max-height: 70vh;
      overflow-y: auto;
    }
    .location-input {
      position: relative;
    }
    .location-input i {
      position: absolute;
      left: 12px;
      top: 50%;
      transform: translateY(-50%);
      color: #4B5563;
      z-index: 15;
    }
    .location-input input {
      padding-left: 40px;
      padding-right: 40px;
      border: 1px solid #e5e7eb;
      border-radius: 0.5rem;
      height: 48px;
      font-size: 1rem;
    }
    .map-button {
      position: absolute;
      right: 8px;
      top: 50%;
      transform: translateY(-50%);
      background-color: #f3f4f6;
      border: 1px solid #e5e7eb;
      border-radius: 4px;
      width: 32px;
      height: 32px;
      text-align: center;
      color: #4B5563;
      font-size: 16px;
      cursor: pointer;
      z-index: 15;
      transition: all 0.2s;
      padding: 0;
      line-height: 32px;
      font-family: 'Font Awesome 6 Free';
      font-weight: 900;
    }
    .map-button:hover {
      background-color: #e5e7eb;
      color: #3B82F6;
    }
    .connector-line {
      position: absolute;
      left: 20px;
      top: 56px;
      bottom: 56px;
      width: 2px;
      background-color: #d1d5db;
      z-index: 12;
    }
    .map-modal {
      position: fixed;
      top: 0;
      left: 0;
      width: 100%;
      height: 100%;
      background-color: #fff;
      z-index: 9999;
      display: none;
      flex-direction: column;
    }
    .map-modal-header {
      display: flex;
      align-items: center;
      padding: 1rem;
      background-color: #fff;
      border-bottom: 1px solid #e5e7eb;
      box-shadow: 0 1px 2px 0 rgba(0, 0, 0, 0.05);
    }
    .map-modal-title {
      flex-grow: 1;
      font-weight: 600;
      font-size: 1.125rem;
      text-align: center;
    }
    .map-modal-close {
      font-size: 1.5rem;
      cursor: pointer;
    }
    .map-modal-content {
      flex-grow: 1;
      position: relative;
    }
    #map-modal-map {
      width: 100%;
      height: 100%;
    }
    .map-modal-footer {
      padding: 1rem;
      background-color: #fff;
      border-top: 1px solid #e5e7eb;
      display: flex;
      justify-content: center;
    }
    .map-modal-search {
      position: absolute;
      top: 1rem;
      left: 50%;
      transform: translateX(-50%);
      width: 90%;
      max-width: 800px;
      z-index: 9999;
      padding: 0.75rem;
      background-color: white;
      border-radius: 0.5rem;
      box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1);
    }
    .map-modal-search input {
      width: 100%;
      padding: 0.75rem 1rem 0.75rem 2.5rem;
      border: 1px solid #e5e7eb;
      border-radius: 0.375rem;
      font-size: 1rem;
    }
    .map-modal-search .relative {
      position: relative;
    }
    .map-modal-search i {
      position: absolute;
      left: 0.75rem;
      top: 50%;
      transform: translateY(-50%);
      color: #4B5563;
      z-index: 10;
    }
    .select-location-btn {
      background-color: #3B82F6;
      color: white;
      font-weight: 600;
      padding: 0.75rem 1.5rem;
      border-radius: 0.375rem;
      cursor: pointer;
      transition: background-color 0.2s;
    }
    .select-location-btn:hover {
      background-color: #2563EB;
    }
    .pac-container {
      z-index: 10000 !important;
    }
    .form-panel h2 {
      font-size: 1.5rem;
      font-weight: 600;
      color: #1F2937;
      margin-bottom: 1rem;
      text-align: center;
    }
    .form-panel button[type="submit"] {
      height: 48px;
      font-size: 1rem;
      border-radius: 0.5rem;
    }
    @media (max-width: 640px) {
      .form-panel {
        max-width: 400px;
        padding: 1rem;
      }
      .form-panel h2 {
        font-size: 1.25rem;
      }
      .location-input input {
        font-size: 0.875rem;
        height: 44px;
      }
      .form-panel button[type="submit"] {
        font-size: 0.875rem;
        height: 44px;
      }
      .connector-line {
        left: 18px;
      }
    }
  </style>
</head>
<body class="bg-gray-100">
  <!-- Full-Screen Map -->
  <div id="map"></div>

  <!-- Ride Request Form Panel -->
  <div class="form-panel">
    <h2>Request a Ride</h2>
    <form id="carRequestForm" action="/car/request" method="POST" class="space-y-4">
      @csrf
      <div class="relative">
        <div class="connector-line"></div>
        
        <div class="location-input mb-3 relative">
          <i class="fa-solid fa-map-pin text-green-600"></i>
          <input type="text" id="pickup_point" name="pickup_point" class="w-full" placeholder="Enter pickup location">
          <button type="button" id="pickup_map_btn" class="map-button" aria-label="Open map for pickup location">
            <i class="fa-solid fa-map-location-dot"></i>
          </button>
          <input type="hidden" id="pickup_lat" name="pickup_lat">
          <input type="hidden" id="pickup_lng" name="pickup_lng">
        </div>
        
        <div class="location-input relative">
          <i class="fa-solid fa-map-marker-alt text-red-600"></i>
          <input type="text" id="destination" name="destination" class="w-full" placeholder="Enter destination">
          <button type="button" id="destination_map_btn" class="map-button" aria-label="Open map for destination">
            <i class="fa-solid fa-map-location-dot"></i>
          </button>
          <input type="hidden" id="destination_lat" name="destination_lat">
          <input type="hidden" id="destination_lng" name="destination_lng">
        </div>
      </div>
      
      <div class="flex items-center space-x-4">
        <div class="flex-1">
          <label for="passenger_count" class="block text-sm font-medium text-gray-700">Passengers</label>
          <input type="number" id="passenger_count" name="passenger_count" min="1" max="4" value="1" 
            class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500">
        </div>
      </div>

      <div id="ride_options" class="hidden space-y-4">
        <div class="bg-blue-50 p-4 rounded-lg">
          <h3 class="text-lg font-medium text-blue-900 mb-2">Available Ride Options</h3>
          
          <div id="direct_rides" class="space-y-3 mb-4">
            <h4 class="font-medium text-blue-800">Direct Rides</h4>
            <!-- Direct rides will be populated here -->
          </div>

          <div id="ride_shares" class="space-y-3">
            <h4 class="font-medium text-blue-800">Ride Shares</h4>
            <!-- Ride shares will be populated here -->
          </div>

          <div id="no_rides" class="hidden text-center py-4">
            <p class="text-gray-600">No rides available at the moment. Please try again later.</p>
          </div>
        </div>
      </div>

      <div class="flex justify-between items-center">
        <div class="text-sm text-gray-600">
          <span id="estimated_fare">Estimated fare: $0.00</span>
        </div>
        <button type="submit" class="bg-blue-600 text-white px-6 py-2 rounded-lg hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-offset-2">
          Find Rides
        </button>
      </div>
    </form>
  </div>

  <!-- Ride Options Modal -->
  <div id="ride-options-modal" class="fixed inset-0 bg-black bg-opacity-50 hidden flex items-center justify-center z-50">
    <div class="bg-white rounded-lg p-6 max-w-lg w-full mx-4">
      <div class="flex justify-between items-center mb-4">
        <h3 class="text-lg font-medium text-gray-900">Ride Details</h3>
        <button onclick="closeRideOptionsModal()" class="text-gray-400 hover:text-gray-500">
          <i class="fa-solid fa-times"></i>
        </button>
      </div>
      
      <div id="ride-details" class="space-y-4">
        <!-- Ride details will be populated here -->
      </div>

      <div class="mt-6 flex justify-end space-x-3">
        <button onclick="closeRideOptionsModal()" class="px-4 py-2 border border-gray-300 rounded-md text-gray-700 hover:bg-gray-50">
          Cancel
        </button>
        <button onclick="requestRide()" class="px-4 py-2 bg-blue-600 text-white rounded-md hover:bg-blue-700">
          Request Ride
        </button>
      </div>
    </div>
  </div>

  <!-- Full Page Map Modal -->
  <div id="map-modal" class="map-modal">
    <div class="map-modal-header">
      <div class="map-modal-close" id="map-modal-close">
        <i class="fa-solid fa-arrow-left"></i>
      </div>
      <div class="map-modal-title" id="map-modal-title">Select Location</div>
      <div style="width: 24px;"></div>
    </div>
    <div class="map-modal-content">
      <div id="map-modal-map"></div>
      <div class="map-modal-search">
        <div class="relative">
          <i class="fa-solid fa-search"></i>
          <input type="text" id="map-modal-search-input" placeholder="Search for a location">
        </div>
      </div>
    </div>
    <div class="map-modal-footer">
      <button class="select-location-btn" id="select-location-btn">Confirm Location</button>
    </div>
  </div>

  <!-- Inject app data -->
  <script type="text/javascript">
    window.appData = {
        googleMapsApiKey: '{{ env('GOOGLE_MAPS_API_KEY') }}'
    };
  </script>

  <!-- Google Maps JS (loaded dynamically in user.js) -->
  <script src="/js/user.js" type="text/javascript"></script>
</body>
</html>
