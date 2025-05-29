<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>Transport Portal - Sign In</title>
  <!-- Tailwind CSS CDN -->
  <link href="https://unpkg.com/tailwindcss@2.2.19/dist/tailwind.min.css" rel="stylesheet" />
</head>
<body class="bg-gray-100">
  <div class="min-h-screen flex items-center justify-center">
    <div class="bg-white p-8 rounded shadow-md w-full max-w-md">
      <h1 class="text-3xl font-bold text-center mb-6">Sign In</h1>
      <form action="{{ url('/sign-in') }}" method="POST">
        @csrf
        <div class="mb-4">
          <label for="email" class="block font-medium text-gray-700">Email</label>
          <input type="email" id="email" name="email" required
                 class="w-full border border-gray-300 p-2 rounded" />
        </div>
        <div class="mb-4">
          <label for="password" class="block font-medium text-gray-700">Password</label>
          <input type="password" id="password" name="password" required
                 class="w-full border border-gray-300 p-2 rounded" />
        </div>
        <button type="submit"
                class="w-full bg-blue-500 hover:bg-blue-600 text-white font-bold py-2 px-4 rounded">
          Sign In
        </button>
      </form>
      @if ($errors->any())
        <div class="mt-4 text-red-600 text-center">
          {{ $errors->first() }}
        </div>
      @endif
    </div>
  </div>
</body>
</html>
