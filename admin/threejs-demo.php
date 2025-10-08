<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Three.js Warehouse Demo - VentDepot</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
</head>
<body class="bg-gray-50">
    <nav class="bg-white shadow-lg">
        <div class="max-w-7xl mx-auto px-4">
            <div class="flex justify-between items-center py-4">
                <div class="flex items-center space-x-4">
                    <a href="../index.php" class="text-2xl font-bold text-blue-600">VentDepot</a>
                    <span class="text-gray-400">|</span>
                    <a href="dashboard.php" class="text-lg font-semibold text-red-600 hover:text-red-700">Admin Panel</a>
                    <span class="text-gray-400">|</span>
                    <span class="text-lg text-gray-600">Three.js Demo</span>
                </div>
                <div class="flex items-center space-x-4">
                    <a href="inventory-threejs.php" class="bg-blue-600 text-white px-4 py-2 rounded-md hover:bg-blue-700">
                        <i class="fas fa-cube mr-2"></i>Open 3D Warehouse
                    </a>
                </div>
            </div>
        </div>
    </nav>

    <div class="max-w-7xl mx-auto px-4 py-8">
        <!-- Header -->
        <div class="text-center mb-12">
            <h1 class="text-4xl font-bold text-gray-900 mb-4">Three.js 3D Warehouse Visualization</h1>
            <p class="text-xl text-gray-600 max-w-3xl mx-auto">
                Experience immersive 3D warehouse management with realistic lighting, shadows, and interactive bin selection. 
                Navigate through zones, racks, and bins in a fully interactive 3D environment.
            </p>
        </div>

        <!-- Features Grid -->
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-8 mb-12">
            <div class="bg-white rounded-lg shadow-lg p-6">
                <div class="text-center">
                    <div class="p-3 rounded-full bg-blue-100 w-16 h-16 flex items-center justify-center mx-auto mb-4">
                        <i class="fas fa-cube text-blue-600 text-2xl"></i>
                    </div>
                    <h3 class="text-xl font-semibold text-gray-900 mb-2">Realistic 3D Environment</h3>
                    <p class="text-gray-600">
                        Fully rendered 3D warehouse with realistic lighting, shadows, and materials. Navigate through 
                        your warehouse as if you were walking through it in person.
                    </p>
                </div>
            </div>

            <div class="bg-white rounded-lg shadow-lg p-6">
                <div class="text-center">
                    <div class="p-3 rounded-full bg-green-100 w-16 h-16 flex items-center justify-center mx-auto mb-4">
                        <i class="fas fa-mouse-pointer text-green-600 text-2xl"></i>
                    </div>
                    <h3 class="text-xl font-semibold text-gray-900 mb-2">Interactive Bin Selection</h3>
                    <p class="text-gray-600">
                        Click on any bin to view detailed information including occupancy status, current products, 
                        and utilization percentages. Move products between bins with ease.
                    </p>
                </div>
            </div>

            <div class="bg-white rounded-lg shadow-lg p-6">
                <div class="text-center">
                    <div class="p-3 rounded-full bg-purple-100 w-16 h-16 flex items-center justify-center mx-auto mb-4">
                        <i class="fas fa-eye text-purple-600 text-2xl"></i>
                    </div>
                    <h3 class="text-xl font-semibold text-gray-900 mb-2">Multiple View Modes</h3>
                    <p class="text-gray-600">
                        Switch between Overview, Zone, and Rack view modes. Each mode provides optimized camera 
                        positioning for different management tasks.
                    </p>
                </div>
            </div>

            <div class="bg-white rounded-lg shadow-lg p-6">
                <div class="text-center">
                    <div class="p-3 rounded-full bg-yellow-100 w-16 h-16 flex items-center justify-center mx-auto mb-4">
                        <i class="fas fa-palette text-yellow-600 text-2xl"></i>
                    </div>
                    <h3 class="text-xl font-semibold text-gray-900 mb-2">Color-Coded Status</h3>
                    <p class="text-gray-600">
                        Bins are color-coded by occupancy status - empty (gray), partial (yellow), full (green), 
                        blocked (red), and reserved (purple) for instant visual feedback.
                    </p>
                </div>
            </div>

            <div class="bg-white rounded-lg shadow-lg p-6">
                <div class="text-center">
                    <div class="p-3 rounded-full bg-red-100 w-16 h-16 flex items-center justify-center mx-auto mb-4">
                        <i class="fas fa-sliders-h text-red-600 text-2xl"></i>
                    </div>
                    <h3 class="text-xl font-semibold text-gray-900 mb-2">Advanced Controls</h3>
                    <p class="text-gray-600">
                        Filter visibility by bin status, toggle labels on/off, and reset camera position. 
                        Full orbit controls with smooth damping for intuitive navigation.
                    </p>
                </div>
            </div>

            <div class="bg-white rounded-lg shadow-lg p-6">
                <div class="text-center">
                    <div class="p-3 rounded-full bg-indigo-100 w-16 h-16 flex items-center justify-center mx-auto mb-4">
                        <i class="fas fa-chart-bar text-indigo-600 text-2xl"></i>
                    </div>
                    <h3 class="text-xl font-semibold text-gray-900 mb-2">Real-Time Statistics</h3>
                    <p class="text-gray-600">
                        View live statistics including total bins, occupied bins, utilization percentages, 
                        and zone counts integrated directly with your warehouse data.
                    </p>
                </div>
            </div>
        </div>

        <!-- Technical Specifications -->
        <div class="bg-white rounded-lg shadow-lg p-8 mb-12">
            <h2 class="text-2xl font-bold text-gray-900 mb-6 text-center">Technical Specifications</h2>
            <div class="grid grid-cols-1 md:grid-cols-2 gap-8">
                <div>
                    <h3 class="text-lg font-semibold text-gray-900 mb-4">3D Rendering Features</h3>
                    <ul class="space-y-2">
                        <li class="flex items-center text-gray-600">
                            <i class="fas fa-check text-green-600 mr-2"></i>
                            WebGL-powered Three.js rendering
                        </li>
                        <li class="flex items-center text-gray-600">
                            <i class="fas fa-check text-green-600 mr-2"></i>
                            Real-time shadows and lighting
                        </li>
                        <li class="flex items-center text-gray-600">
                            <i class="fas fa-check text-green-600 mr-2"></i>
                            Anti-aliasing for smooth edges
                        </li>
                        <li class="flex items-center text-gray-600">
                            <i class="fas fa-check text-green-600 mr-2"></i>
                            Perspective camera with orbit controls
                        </li>
                        <li class="flex items-center text-gray-600">
                            <i class="fas fa-check text-green-600 mr-2"></i>
                            Raycasting for object interaction
                        </li>
                    </ul>
                </div>
                <div>
                    <h3 class="text-lg font-semibold text-gray-900 mb-4">Integration Features</h3>
                    <ul class="space-y-2">
                        <li class="flex items-center text-gray-600">
                            <i class="fas fa-check text-green-600 mr-2"></i>
                            Direct database integration
                        </li>
                        <li class="flex items-center text-gray-600">
                            <i class="fas fa-check text-green-600 mr-2"></i>
                            Real-time inventory data
                        </li>
                        <li class="flex items-center text-gray-600">
                            <i class="fas fa-check text-green-600 mr-2"></i>
                            Product movement functionality
                        </li>
                        <li class="flex items-center text-gray-600">
                            <i class="fas fa-check text-green-600 mr-2"></i>
                            CSRF-protected forms
                        </li>
                        <li class="flex items-center text-gray-600">
                            <i class="fas fa-check text-green-600 mr-2"></i>
                            Responsive design compatibility
                        </li>
                    </ul>
                </div>
            </div>
        </div>

        <!-- Comparison Table -->
        <div class="bg-white rounded-lg shadow-lg p-8 mb-12">
            <h2 class="text-2xl font-bold text-gray-900 mb-6 text-center">Visualization Comparison</h2>
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Feature</th>
                            <th class="px-6 py-3 text-center text-xs font-medium text-gray-500 uppercase tracking-wider">2D View</th>
                            <th class="px-6 py-3 text-center text-xs font-medium text-gray-500 uppercase tracking-wider">Three.js 3D</th>
                        </tr>
                    </thead>
                    <tbody class="bg-white divide-y divide-gray-200">
                        <tr>
                            <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900">Immersion Level</td>
                            <td class="px-6 py-4 whitespace-nowrap text-center text-sm text-gray-500">Basic</td>
                            <td class="px-6 py-4 whitespace-nowrap text-center text-sm text-green-600">High</td>
                        </tr>
                        <tr class="bg-gray-50">
                            <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900">Navigation</td>
                            <td class="px-6 py-4 whitespace-nowrap text-center text-sm text-gray-500">Grid-based</td>
                            <td class="px-6 py-4 whitespace-nowrap text-center text-sm text-green-600">Free-form 3D</td>
                        </tr>
                        <tr>
                            <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900">Visual Depth</td>
                            <td class="px-6 py-4 whitespace-nowrap text-center text-sm text-gray-500">Flat</td>
                            <td class="px-6 py-4 whitespace-nowrap text-center text-sm text-green-600">Realistic</td>
                        </tr>
                        <tr class="bg-gray-50">
                            <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900">Performance</td>
                            <td class="px-6 py-4 whitespace-nowrap text-center text-sm text-green-600">Fast</td>
                            <td class="px-6 py-4 whitespace-nowrap text-center text-sm text-yellow-600">Good</td>
                        </tr>
                        <tr>
                            <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900">User Experience</td>
                            <td class="px-6 py-4 whitespace-nowrap text-center text-sm text-gray-500">Functional</td>
                            <td class="px-6 py-4 whitespace-nowrap text-center text-sm text-green-600">Engaging</td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Call to Action -->
        <div class="text-center">
            <div class="bg-gradient-to-r from-blue-600 to-purple-600 rounded-lg p-8 text-white">
                <h2 class="text-3xl font-bold mb-4">Ready to Experience 3D Warehouse Management?</h2>
                <p class="text-xl mb-6 opacity-90">
                    Step into the future of inventory visualization with our Three.js-powered 3D warehouse system.
                </p>
                <div class="space-x-4">
                    <a href="inventory-threejs.php" class="bg-white text-blue-600 px-8 py-3 rounded-lg font-semibold hover:bg-gray-100 transition-colors">
                        <i class="fas fa-cube mr-2"></i>Launch 3D Warehouse
                    </a>
                    <a href="inventory-visual.php" class="bg-transparent border-2 border-white text-white px-8 py-3 rounded-lg font-semibold hover:bg-white hover:text-blue-600 transition-colors">
                        <i class="fas fa-th mr-2"></i>Compare with 2D View
                    </a>
                </div>
            </div>
        </div>
    </div>
</body>
</html>