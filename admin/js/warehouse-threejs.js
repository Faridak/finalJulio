function threejsWarehouse() {
    return {
        selectedLocation: '',
        warehouseData: { zones: [] },
        warehouseStats: { totalBins: 0, occupiedBins: 0, utilization: 0, totalZones: 0 },
        loading: false,
        showMoveProductModal: false,
        selectedBin: { bin_id: '', bin_address: '' },
        selectedBinInfo: null,
        showInventoryPanel: false,
        inventoryItems: [],
        showRackCardPanel: false,
        selectedRackInfo: null,
        rackInventoryItems: [],
        viewMode: 'overview',
        showEmpty: true,
        showOccupied: true,
        showLabels: true,
        
        // Three.js objects
        scene: null,
        camera: null,
        renderer: null,
        controls: null,
        binMeshes: new Map(),
        bins: [], // Array to store all bin objects
        labelSprites: new Map(),
        warehouseGroup: null,
        
        init() {
            console.log('Initializing threejsWarehouse...');
            // Check if Three.js is available
            if (typeof THREE === 'undefined') {
                console.warn('THREE.js not available, waiting...');
                // Try again after a delay
                setTimeout(() => this.init(), 500);
                return;
            }
            
            // Check if OrbitControls is available
            if (typeof THREE.OrbitControls === 'undefined') {
                console.warn('OrbitControls not available, waiting...');
                setTimeout(() => this.init(), 500);
                return;
            }
            
            try {
                this.initThreeJS();
                this.animate();
            } catch (error) {
                console.error('Error initializing Three.js:', error);
                // Fallback to 2D mode
                this.create2DFallback();
            }
        },

        initThreeJS() {
            const container = document.getElementById('three-container');
            
            if (!container) {
                console.error('Three.js container not found');
                return;
            }
            
            // Check again if THREE is available
            if (typeof THREE === 'undefined') {
                throw new Error('THREE.js is not available');
            }
            
            // Scene
            this.scene = new THREE.Scene();
            this.scene.background = new THREE.Color(0xf0f4f8);
            
            // Camera
            this.camera = new THREE.PerspectiveCamera(
                75, 
                container.clientWidth / container.clientHeight, 
                0.1, 
                1000
            );
            this.camera.position.set(50, 30, 50);
            
            // Renderer
            this.renderer = new THREE.WebGLRenderer({ antialias: true });
            this.renderer.setSize(container.clientWidth, container.clientHeight);
            this.renderer.shadowMap.enabled = false;
            // this.renderer.shadowMap.type = THREE.PCFSoftShadowMap; // Disabled to fix proxy error
            container.appendChild(this.renderer.domElement);
            
            // Controls
            this.controls = new THREE.OrbitControls(this.camera, this.renderer.domElement);
            this.controls.enableDamping = true;
            this.controls.dampingFactor = 0.1;
            
            // Lighting
            this.setupLighting();
            
            // Raycaster for clicking
            this.setupRaycaster();
            
            // Handle window resize
            window.addEventListener('resize', () => this.onWindowResize());
        },

        setupLighting() {
            // Ambient light
            const ambientLight = new THREE.AmbientLight(0x404040, 0.6);
            this.scene.add(ambientLight);
            
            // Directional light (main)
            const directionalLight = new THREE.DirectionalLight(0xffffff, 0.8);
            directionalLight.position.set(50, 100, 50);
            directionalLight.castShadow = true;
            directionalLight.shadow.mapSize.width = 2048;
            directionalLight.shadow.mapSize.height = 2048;
            directionalLight.shadow.camera.near = 0.5;
            directionalLight.shadow.camera.far = 200;
            directionalLight.shadow.camera.left = -100;
            directionalLight.shadow.camera.right = 100;
            directionalLight.shadow.camera.top = 100;
            directionalLight.shadow.camera.bottom = -100;
            this.scene.add(directionalLight);
            
            // Additional directional light from other side
            const directionalLight2 = new THREE.DirectionalLight(0xffffff, 0.4);
            directionalLight2.position.set(-50, 50, -50);
            this.scene.add(directionalLight2);
        },

        setupRaycaster() {
            const raycaster = new THREE.Raycaster();
            const mouse = new THREE.Vector2();
            
            this.renderer.domElement.addEventListener('click', (event) => {
                const rect = this.renderer.domElement.getBoundingClientRect();
                mouse.x = ((event.clientX - rect.left) / rect.width) * 2 - 1;
                mouse.y = -((event.clientY - rect.top) / rect.height) * 2 + 1;
                
                raycaster.setFromCamera(mouse, this.camera);
                
                // Check intersection with clickable objects (rack icons)
                if (this.clickableObjects && this.clickableObjects.length > 0) {
                    const iconIntersects = raycaster.intersectObjects(this.clickableObjects);
                    if (iconIntersects.length > 0) {
                        const clickedIcon = iconIntersects[0].object;
                        if (clickedIcon.userData.type === 'rack_info_icon') {
                            this.showRackCard(clickedIcon.userData.rack);
                            return;
                        }
                    }
                }
                
                // Check intersection with bins
                const intersectObjects = [];
                this.bins.forEach(binGroup => {
                    if (binGroup.userData && binGroup.userData.mesh) {
                        intersectObjects.push(binGroup.userData.mesh);
                    }
                });
                
                const intersects = raycaster.intersectObjects(intersectObjects);
                
                if (intersects.length > 0) {
                    const clickedMesh = intersects[0].object;
                    const binGroup = this.bins.find(bin => 
                        bin.userData && bin.userData.mesh === clickedMesh
                    );
                    
                    if (binGroup) {
                        this.selectBin(binGroup.userData);
                    }
                }
            });
        },

        async loadWarehouseData() {
            if (!this.selectedLocation) {
                this.clearScene();
                return;
            }

            this.loading = true;
            try {
                const response = await fetch(`../enhanced-inventory-dashboard.php?action=ajax&type=warehouse_structure&location_id=${this.selectedLocation}`);
                const data = await response.json();
                
                if (data.success) {
                    this.processWarehouseData(data.data);
                    this.create3DWarehouse();
                    this.updateStats();
                }
            } catch (error) {
                console.error('Failed to load warehouse data:', error);
            } finally {
                this.loading = false;
            }
        },

        processWarehouseData(rawData) {
            const zones = new Map();
            
            rawData.forEach(item => {
                if (!zones.has(item.zone_id)) {
                    zones.set(item.zone_id, {
                        zone_id: item.zone_id,
                        zone_code: item.zone_code,
                        zone_name: item.zone_name,
                        zone_type: item.zone_type,
                        racks: new Map(),
                        total_bins: 0,
                        utilized_bins: 0
                    });
                }
                
                const zone = zones.get(item.zone_id);
                
                if (!zone.racks.has(item.rack_id)) {
                    zone.racks.set(item.rack_id, {
                        rack_id: item.rack_id,
                        rack_code: item.rack_code,
                        rack_name: item.rack_name,
                        rack_type: item.rack_type,
                        levels: parseInt(item.levels) || 4,
                        positions: parseInt(item.positions) || 6,
                        bins: new Map()
                    });
                }
                
                const rack = zone.racks.get(item.rack_id);
                
                if (item.bin_id) {
                    const binKey = `${item.rack_id}-${item.level_number}-${item.position_number}`;
                    rack.bins.set(binKey, {
                        bin_id: item.bin_id,
                        bin_code: item.bin_code,
                        bin_address: item.bin_address,
                        level_number: item.level_number,
                        position_number: item.position_number,
                        occupancy_status: item.occupancy_status,
                        current_quantity: item.current_quantity,
                        utilization_percentage: item.utilization_percentage,
                        product_name: item.product_name,
                        product_sku: item.product_sku
                    });
                    
                    zone.total_bins++;
                    if (item.occupancy_status !== 'empty') zone.utilized_bins++;
                }
            });
            
            this.warehouseData.zones = Array.from(zones.values()).map(zone => {
                zone.racks = Array.from(zone.racks.values());
                zone.utilization = zone.total_bins > 0 ? Math.round((zone.utilized_bins / zone.total_bins) * 100) : 0;
                return zone;
            });
        },

        create3DWarehouse() {
            this.clearScene();
            
            this.warehouseGroup = new THREE.Group();
            this.scene.add(this.warehouseGroup);
            
            // If no data loaded, create default 10-rack structure
            if (!this.warehouseData.zones || this.warehouseData.zones.length === 0) {
                this.createDefaultWarehouse();
            } else {
                this.createFromData();
            }
            
            // Add floor
            this.createFloor();
        },
        
        createDefaultWarehouse() {
            // Create a single zone with 10 racks
            const zoneGroup = new THREE.Group();
            
            // Zone platform for 10 racks
            const platformGeometry = new THREE.BoxGeometry(80, 0.5, 40);
            const platformMaterial = new THREE.MeshLambertMaterial({ 
                color: 0x3B82F6,
                transparent: true,
                opacity: 0.3
            });
            const platform = new THREE.Mesh(platformGeometry, platformMaterial);
            platform.position.y = -0.25;
            platform.receiveShadow = true;
            zoneGroup.add(platform);
            
            // Zone label
            this.createZoneLabel({ zone_code: 'A', zone_name: 'Main Warehouse' }, zoneGroup);
            
            // Create 10 racks in 2 rows of 5
            for (let rackIndex = 0; rackIndex < 10; rackIndex++) {
                const rackData = {
                    rack_id: rackIndex + 1,
                    rack_code: 'R' + String(rackIndex + 1).padStart(2, '0'),
                    rack_name: `Rack ${rackIndex + 1}`,
                    levels: 5,
                    positions: 10,
                    bins: new Map()
                };
                
                const rackGroup = this.createRack(rackData, rackIndex);
                
                // Position racks in 2 rows of 5
                const racksPerRow = 5;
                const rackSpacing = 15;
                const rowSpacing = 25;
                
                const row = Math.floor(rackIndex / racksPerRow);
                const col = rackIndex % racksPerRow;
                
                const rackX = (col - (racksPerRow - 1) / 2) * rackSpacing;
                const rackZ = (row - 0.5) * rowSpacing;
                
                rackGroup.position.set(rackX, 0, rackZ);
                zoneGroup.add(rackGroup);
            }
            
            this.warehouseGroup.add(zoneGroup);
            
            // Update stats for default warehouse
            this.warehouseStats = {
                totalBins: 10 * 5 * 10, // 10 racks × 5 shelves × 10 bins
                occupiedBins: 0,
                utilization: 0,
                totalZones: 1,
                totalRacks: 10
            };
        },
        
        createFromData() {
            let zoneSpacing = 40; // Increased for larger racks
            let zoneIndex = 0;
            
            this.warehouseData.zones.forEach(zone => {
                const zoneGroup = this.createZone(zone);
                
                // Position zones in a grid with more space
                const zonesPerRow = 2; // Fewer zones per row for larger racks
                const zoneX = (zoneIndex % zonesPerRow) * zoneSpacing - ((zonesPerRow - 1) * zoneSpacing / 2);
                const zoneZ = Math.floor(zoneIndex / zonesPerRow) * zoneSpacing;
                
                zoneGroup.position.set(zoneX, 0, zoneZ);
                this.warehouseGroup.add(zoneGroup);
                
                zoneIndex++;
            });
        },

        createZone(zone) {
            const zoneGroup = new THREE.Group();
            
            // Calculate zone size based on number of racks
            const rackCount = zone.racks ? zone.racks.length : 0;
            const racksPerRow = Math.min(5, rackCount); // Max 5 racks per row
            const rows = Math.ceil(rackCount / racksPerRow);
            
            const zoneWidth = racksPerRow * 15 + 10; // 15 units per rack + padding
            const zoneDepth = rows * 25 + 10; // 25 units per row + padding
            
            // Zone base platform - dynamic size
            const platformGeometry = new THREE.BoxGeometry(zoneWidth, 0.5, zoneDepth);
            const platformMaterial = new THREE.MeshLambertMaterial({ 
                color: this.getZoneColor(zone.zone_code),
                transparent: true,
                opacity: 0.3
            });
            const platform = new THREE.Mesh(platformGeometry, platformMaterial);
            platform.position.y = -0.25;
            platform.receiveShadow = true;
            zoneGroup.add(platform);
            
            // Zone label
            if (this.showLabels) {
                this.createZoneLabel(zone, zoneGroup);
            }
            
            // Create racks within zone
            let rackIndex = 0;
            zone.racks.forEach(rack => {
                const rackGroup = this.createRack(rack, rackIndex);
                
                // Position racks in rows
                const row = Math.floor(rackIndex / racksPerRow);
                const col = rackIndex % racksPerRow;
                
                const rackX = (col - (racksPerRow - 1) / 2) * 15;
                const rackZ = (row - (rows - 1) / 2) * 25;
                
                rackGroup.position.set(rackX, 0, rackZ);
                zoneGroup.add(rackGroup);
                
                rackIndex++;
            });
            
            return zoneGroup;
        },

        createRack(rack, rackIndex = 0) {
            const rackGroup = new THREE.Group();
            rackGroup.userData = { type: 'rack', rack };
            
            // Configure default structure: 5 shelves × 10 bins per rack
            const defaultLevels = 5;
            const defaultPositions = 10;
            const actualLevels = rack.levels || defaultLevels;
            const actualPositions = rack.positions || defaultPositions;
            
            // Create realistic IKEA-style rack structure
            const rackWidth = 8;
            const rackHeight = 6;
            const rackDepth = 1.5;
            
            // Create rack frame with realistic structure
            this.createRackFrame(rackGroup, rackWidth, rackHeight, rackDepth);
            
            // Create shelves and bins for each level
            for (let level = 0; level < actualLevels; level++) {
                const levelY = (level + 1) * (rackHeight / (actualLevels + 1));
                
                // Create shelf platform
                this.createShelf(rackGroup, rackWidth, rackDepth, levelY);
                
                for (let position = 0; position < actualPositions; position++) {
                    const binX = -rackWidth/2 + (position + 0.5) * (rackWidth / actualPositions);
                    
                    // Create realistic bin with dividers
                    const bin = this.createRealisticBin(binX, levelY, 0, level, position);
                    
                    bin.userData = {
                        type: 'bin',
                        rack: rack,
                        level: level + 1,
                        position: position + 1,
                        binId: `${rack.rack_code}-L${level + 1}-P${String(position + 1).padStart(2, '0')}`,
                        status: 'empty',
                        items: [] // Will be populated with real inventory data
                    };
                    
                    rackGroup.add(bin);
                    this.bins.push(bin);
                }
            }
            
            // Add rack label
            this.createRackLabel(rack, rackGroup, rackHeight + 1);
            
            return rackGroup;
        },

        createRackFrame(rackGroup, width, height, depth) {
            // Create a more geometric and visual rack structure
            const rackMaterial = new THREE.MeshLambertMaterial({ color: 0x2563EB }); // Blue color
            
            // Main rack body (geometric box)
            const mainBodyGeometry = new THREE.BoxGeometry(width, height * 0.1, depth);
            const mainBody = new THREE.Mesh(mainBodyGeometry, rackMaterial);
            mainBody.position.set(0, height * 0.05, 0);
            mainBody.castShadow = true;
            rackGroup.add(mainBody);
            
            // Vertical posts (4 corners) - more prominent
            const postGeometry = new THREE.BoxGeometry(0.2, height, 0.2);
            const postMaterial = new THREE.MeshLambertMaterial({ color: 0x1E40AF }); // Darker blue
            
            const positions = [
                [-width/2 + 0.1, height/2, -depth/2 + 0.1],
                [width/2 - 0.1, height/2, -depth/2 + 0.1],
                [-width/2 + 0.1, height/2, depth/2 - 0.1],
                [width/2 - 0.1, height/2, depth/2 - 0.1]
            ];
            
            positions.forEach(pos => {
                const post = new THREE.Mesh(postGeometry, postMaterial);
                post.position.set(...pos);
                post.castShadow = true;
                rackGroup.add(post);
            });
            
            // Add rack info icon (clickable)
            this.createRackInfoIcon(rackGroup, width, height, depth);
        },
        
        createRackInfoIcon(rackGroup, width, height, depth) {
            // Create a clickable info icon above the rack
            const iconGeometry = new THREE.SphereGeometry(0.3, 16, 16);
            const iconMaterial = new THREE.MeshLambertMaterial({ 
                color: 0xFEF3C7, // Light yellow
                emissive: 0x555500
            });
            
            const icon = new THREE.Mesh(iconGeometry, iconMaterial);
            icon.position.set(0, height + 0.5, 0);
            icon.castShadow = true;
            
            // Make it interactive
            icon.userData = {
                type: 'rack_info_icon',
                isClickable: true,
                rack: rackGroup.userData.rack
            };
            
            // Add pulsing animation
            icon.scale.set(1, 1, 1);
            const originalScale = icon.scale.clone();
            
            // Store animation data
            if (!this.animatedObjects) this.animatedObjects = [];
            this.animatedObjects.push({
                object: icon,
                originalScale: originalScale,
                animationType: 'pulse'
            });
            
            rackGroup.add(icon);
            
            // Add to clickable objects
            if (!this.clickableObjects) this.clickableObjects = [];
            this.clickableObjects.push(icon);
        },
        
        createShelf(rackGroup, width, depth, y) {
            // Enhanced shelf with better geometry
            const shelfGeometry = new THREE.BoxGeometry(width - 0.2, 0.1, depth - 0.1);
            const shelfMaterial = new THREE.MeshLambertMaterial({ 
                color: 0x8B7355,
                transparent: false,
                opacity: 1.0
            });
            
            const shelf = new THREE.Mesh(shelfGeometry, shelfMaterial);
            shelf.position.set(0, y, 0);
            shelf.castShadow = true;
            shelf.receiveShadow = true;
            rackGroup.add(shelf);
            
            // Add shelf edge with geometric pattern
            const edgeGeometry = new THREE.BoxGeometry(width, 0.15, 0.1);
            const edgeMaterial = new THREE.MeshLambertMaterial({ color: 0x6B46C1 }); // Purple accent
            
            const frontEdge = new THREE.Mesh(edgeGeometry, edgeMaterial);
            frontEdge.position.set(0, y + 0.1, depth/2 - 0.05);
            frontEdge.castShadow = true;
            rackGroup.add(frontEdge);
            
            // Add shelf support indicators
            const supportGeometry = new THREE.CylinderGeometry(0.05, 0.05, 0.2);
            const supportMaterial = new THREE.MeshLambertMaterial({ color: 0x374151 });
            
            for (let i = 0; i < 3; i++) {
                const support = new THREE.Mesh(supportGeometry, supportMaterial);
                support.position.set((i - 1) * (width / 3), y - 0.1, 0);
                rackGroup.add(support);
            }
        },
        
        createRealisticBin(x, y, z, level, position) {
            const binGroup = new THREE.Group();
            
            // Enhanced bin container with geometric design
            const binGeometry = new THREE.BoxGeometry(0.7, 0.8, 1.2);
            const binMaterial = new THREE.MeshLambertMaterial({ 
                color: 0xE5E7EB,
                transparent: true,
                opacity: 0.8
            });
            
            const bin = new THREE.Mesh(binGeometry, binMaterial);
            bin.position.set(x, y + 0.4, z);
            bin.castShadow = true;
            bin.receiveShadow = true;
            binGroup.add(bin);
            
            // Add geometric indicators for bin status
            const indicatorGeometry = new THREE.CylinderGeometry(0.1, 0.1, 0.05);
            const indicatorMaterial = new THREE.MeshLambertMaterial({ 
                color: 0x10B981, // Green for available
                emissive: 0x003300
            });
            
            const indicator = new THREE.Mesh(indicatorGeometry, indicatorMaterial);
            indicator.position.set(x, y + 0.85, z + 0.5);
            binGroup.add(indicator);
            
            // Bin address label (geometric)
            const labelGeometry = new THREE.PlaneGeometry(0.5, 0.2);
            const labelMaterial = new THREE.MeshBasicMaterial({ 
                color: 0xFFFFFF,
                transparent: true,
                opacity: 0.9,
                side: THREE.DoubleSide
            });
            
            const label = new THREE.Mesh(labelGeometry, labelMaterial);
            label.position.set(x, y + 0.4, z + 0.61);
            binGroup.add(label);
            
            // Store reference for interaction
            binGroup.userData = {
                type: 'bin',
                level: level + 1,
                position: position + 1,
                mesh: bin,
                indicator: indicator
            };
            
            return binGroup;
        },

        createZoneLabel(zone, zoneGroup) {
            const canvas = document.createElement('canvas');
            const context = canvas.getContext('2d');
            canvas.width = 512;
            canvas.height = 128;
            
            context.fillStyle = '#ffffff';
            context.fillRect(0, 0, canvas.width, canvas.height);
            context.fillStyle = '#000000';
            context.font = '48px Arial';
            context.textAlign = 'center';
            context.fillText(`Zone ${zone.zone_code}`, canvas.width / 2, 80);
            
            const texture = new THREE.CanvasTexture(canvas);
            const spriteMaterial = new THREE.SpriteMaterial({ map: texture });
            const sprite = new THREE.Sprite(spriteMaterial);
            sprite.scale.set(8, 2, 1);
            sprite.position.set(0, 15, 0);
            
            zoneGroup.add(sprite);
        },

        createFloor() {
            // Larger floor for 10-rack warehouse
            const floorGeometry = new THREE.PlaneGeometry(400, 300);
            const floorMaterial = new THREE.MeshLambertMaterial({ 
                color: 0xf0f0f0,
                transparent: true,
                opacity: 0.5
            });
            const floor = new THREE.Mesh(floorGeometry, floorMaterial);
            floor.rotation.x = -Math.PI / 2;
            floor.position.y = -1;
            floor.receiveShadow = true;
            
            // Add floor grid for better spatial reference
            const gridHelper = new THREE.GridHelper(400, 40, 0xcccccc, 0xeeeeee);
            gridHelper.position.y = -0.9;
            gridHelper.material.opacity = 0.3;
            gridHelper.material.transparent = true;
            
            this.scene.add(floor);
            this.scene.add(gridHelper);
        },

        getZoneColor(zoneCode) {
            const colors = {
                'A': 0x3B82F6, // Blue
                'B': 0x10B981, // Green
                'C': 0xF59E0B, // Orange
                'D': 0xEF4444, // Red
                'E': 0x8B5CF6  // Purple
            };
            return colors[zoneCode] || 0x6B7280;
        },

        getBinColor(status) {
            const colors = {
                'empty': 0xE5E7EB,     // Gray
                'partial': 0xFEF3C7,   // Yellow
                'full': 0xDCFCE7,      // Green
                'blocked': 0xFEE2E2,   // Red
                'reserved': 0xEDE9FE   // Purple
            };
            return colors[status] || 0xE5E7EB;
        },

        selectBin(binData) {
            this.selectedBinInfo = binData;
            this.loadBinInventory(binData);
            console.log('Selected bin:', binData);
        },
        
        async loadBinInventory(binData) {
            this.loading = true;
            this.inventoryItems = [];
            
            try {
                // Load inventory for specific bin
                const response = await fetch('../api/get-bin-inventory.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                    },
                    body: JSON.stringify({
                        bin_id: binData.binId,
                        bin_address: binData.bin_address || binData.binId,
                        rack_id: binData.rack?.rack_id,
                        level: binData.level,
                        position: binData.position
                    })
                });
                
                const data = await response.json();
                
                if (data.success) {
                    this.inventoryItems = data.items || [];
                    this.showInventoryPanel = true;
                } else {
                    console.error('Failed to load inventory:', data.message);
                    // Show empty inventory panel
                    this.inventoryItems = [];
                    this.showInventoryPanel = true;
                }
            } catch (error) {
                console.error('Error loading inventory:', error);
                // Show mock data for demonstration
                this.loadMockInventoryData(binData);
                this.showInventoryPanel = true;
            } finally {
                this.loading = false;
            }
        },
        
        loadMockInventoryData(binData) {
            // Mock data for demonstration
            const mockItems = [
                {
                    id: 1,
                    name: "iPhone 14 Pro",
                    sku: "APL-IP14P-256-GB",
                    quantity: 5,
                    image_url: "https://via.placeholder.com/100x100?text=iPhone",
                    price: 999.99,
                    category: "Electronics"
                },
                {
                    id: 2,
                    name: "Samsung Galaxy S23",
                    sku: "SAM-GS23-128-BL",
                    quantity: 3,
                    image_url: "https://via.placeholder.com/100x100?text=Galaxy",
                    price: 799.99,
                    category: "Electronics"
                },
                {
                    id: 3,
                    name: "Wireless Headphones",
                    sku: "SONY-WH1000XM4",
                    quantity: 12,
                    image_url: "https://via.placeholder.com/100x100?text=Headphones",
                    price: 299.99,
                    category: "Audio"
                }
            ];
            
            // Randomly assign some items to bins for demo
            this.inventoryItems = Math.random() > 0.3 ? 
                mockItems.slice(0, Math.floor(Math.random() * 3) + 1) : [];
        },
        
        showRackCard(rackData) {
            // Set rack data for card display
            this.selectedRackInfo = {
                rack_code: rackData.rack_code || 'Unknown',
                rack_name: rackData.rack_name || 'Unknown Rack',
                levels: rackData.levels || 5,
                positions: rackData.positions || 10,
                total_bins: (rackData.levels || 5) * (rackData.positions || 10),
                rack_id: rackData.rack_id || rackData.id
            };
            
            // Load rack inventory
            this.loadRackInventory(this.selectedRackInfo);
            this.showRackCardPanel = true;
            
            console.log('Showing rack card for:', rackData);
        },
        
        async loadRackInventory(rackInfo) {
            this.loading = true;
            this.rackInventoryItems = [];
            
            try {
                const response = await fetch('../api/get-rack-inventory.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                    },
                    body: JSON.stringify({
                        rack_id: rackInfo.rack_id,
                        rack_code: rackInfo.rack_code
                    })
                });
                
                const data = await response.json();
                
                if (data.success) {
                    this.rackInventoryItems = data.data || {};
                } else {
                    console.error('Failed to load rack inventory:', data.message);
                    this.loadMockRackData(rackInfo);
                }
            } catch (error) {
                console.error('Error loading rack inventory:', error);
                this.loadMockRackData(rackInfo);
            } finally {
                this.loading = false;
            }
        },
        
        loadMockRackData(rackInfo) {
            // Mock data grouped by shelf for demonstration
            const mockData = {
                shelves: [
                    {
                        level: 1,
                        items: [
                            { name: 'Laptop Dell XPS', sku: 'DELL-XPS-001', quantity: 8, bin: 'L1-P01' },
                            { name: 'Monitor Samsung', sku: 'SAM-MON-024', quantity: 12, bin: 'L1-P02' }
                        ]
                    },
                    {
                        level: 2,
                        items: [
                            { name: 'iPhone 14 Pro', sku: 'APL-IP14P-256', quantity: 15, bin: 'L2-P01' },
                            { name: 'AirPods Pro', sku: 'APL-AIRP-PRO', quantity: 25, bin: 'L2-P03' }
                        ]
                    }
                ]
            };
            
            this.rackInventoryItems = mockData;
        },
        
        closeRackCard() {
            this.showRackCardPanel = false;
            this.selectedRackInfo = null;
            this.rackInventoryItems = [];
        },

        selectBinForMovement() {
            if (this.selectedBinInfo) {
                this.selectedBin = {
                    bin_id: this.selectedBinInfo.bin_id || '',
                    bin_address: this.selectedBinInfo.bin_address || ''
                };
                this.showMoveProductModal = true;
            }
        },

        setViewMode(mode) {
            this.viewMode = mode;
            
            // Animate camera to different positions based on view mode (adjusted for larger warehouse)
            const targetPosition = new THREE.Vector3();
            const targetLookAt = new THREE.Vector3(0, 0, 0);
            
            switch (mode) {
                case 'overview':
                    targetPosition.set(100, 80, 100);
                    break;
                case 'zone':
                    targetPosition.set(60, 40, 60);
                    break;
                case 'rack':
                    targetPosition.set(25, 20, 25);
                    break;
            }
            
            this.animateCamera(targetPosition, targetLookAt);
        },

        animateCamera(targetPosition, targetLookAt) {
            // Simple animation - in production, use TWEEN.js or similar
            const startPosition = this.camera.position.clone();
            const duration = 1000; // ms
            const startTime = Date.now();
            
            const animate = () => {
                const elapsed = Date.now() - startTime;
                const progress = Math.min(elapsed / duration, 1);
                const eased = this.easeInOutCubic(progress);
                
                this.camera.position.lerpVectors(startPosition, targetPosition, eased);
                this.controls.target.lerp(targetLookAt, eased);
                this.controls.update();
                
                if (progress < 1) {
                    requestAnimationFrame(animate);
                }
            };
            
            animate();
        },

        easeInOutCubic(t) {
            return t < 0.5 ? 4 * t * t * t : (t - 1) * (2 * t - 2) * (2 * t - 2) + 1;
        },

        updateVisibility() {
            this.binMeshes.forEach((mesh, key) => {
                const binData = mesh.userData;
                const isEmpty = !binData.occupancy_status || binData.occupancy_status === 'empty';
                
                if (isEmpty && !this.showEmpty) {
                    mesh.visible = false;
                } else if (!isEmpty && !this.showOccupied) {
                    mesh.visible = false;
                } else {
                    mesh.visible = true;
                }
            });
        },

        resetView() {
            // Adjusted for larger warehouse
            this.camera.position.set(100, 60, 100);
            this.controls.target.set(0, 0, 0);
            this.controls.update();
            this.viewMode = 'overview';
        },
        
        createRackLabel(rack, rackGroup, height) {
            const canvas = document.createElement('canvas');
            const context = canvas.getContext('2d');
            canvas.width = 256;
            canvas.height = 64;
            
            context.fillStyle = '#ffffff';
            context.fillRect(0, 0, canvas.width, canvas.height);
            context.fillStyle = '#000000';
            context.font = '24px Arial';
            context.textAlign = 'center';
            context.fillText(rack.rack_code || 'Rack', canvas.width / 2, 40);
            
            const texture = new THREE.CanvasTexture(canvas);
            const spriteMaterial = new THREE.SpriteMaterial({ map: texture });
            const sprite = new THREE.Sprite(spriteMaterial);
            sprite.scale.set(4, 1, 1);
            sprite.position.set(0, height, 0);
            
            rackGroup.add(sprite);
        },

        updateStats() {
            let totalBins = 0;
            let occupiedBins = 0;
            
            this.warehouseData.zones.forEach(zone => {
                totalBins += zone.total_bins;
                occupiedBins += zone.utilized_bins;
            });
            
            this.warehouseStats = {
                totalBins,
                occupiedBins,
                utilization: totalBins > 0 ? Math.round((occupiedBins / totalBins) * 100) : 0,
                totalZones: this.warehouseData.zones.length
            };
        },

        clearScene() {
            if (this.warehouseGroup) {
                this.scene.remove(this.warehouseGroup);
                this.warehouseGroup = null;
            }
            
            this.binMeshes.clear();
            this.labelSprites.clear();
            this.selectedBinInfo = null;
        },

        animate() {
            requestAnimationFrame(() => this.animate());
            
            // Update animations
            this.updateAnimations();
            
            if (this.controls) {
                this.controls.update();
            }
            
            if (this.renderer && this.scene && this.camera) {
                this.renderer.render(this.scene, this.camera);
            }
        },
        
        updateAnimations() {
            if (this.animatedObjects) {
                const time = Date.now() * 0.003; // Slow animation
                
                this.animatedObjects.forEach(animData => {
                    if (animData.animationType === 'pulse') {
                        const scale = 1 + Math.sin(time) * 0.2;
                        animData.object.scale.set(scale, scale, scale);
                    }
                });
            }
        },
        
        closeInventoryPanel() {
            this.showInventoryPanel = false;
            this.selectedBinInfo = null;
            this.inventoryItems = [];
        },

        create2DFallback() {
            console.log('Creating 2D fallback visualization');
            const container = document.getElementById('three-container');
            if (!container) return;
            
            container.innerHTML = `
                <div class="flex items-center justify-center h-full bg-gradient-to-br from-blue-50 to-indigo-100">
                    <div class="text-center p-8">
                        <div class="mb-6">
                            <i class="fas fa-warehouse text-6xl text-blue-500 mb-4"></i>
                        </div>
                        <h3 class="text-xl font-bold text-gray-800 mb-2">3D Warehouse Visualization</h3>
                        <p class="text-gray-600 mb-4">3D rendering is not available. Using 2D fallback mode.</p>
                        <div class="grid grid-cols-2 gap-4 text-sm">
                            <div class="bg-white p-3 rounded-lg shadow">
                                <div class="font-semibold text-blue-600">📦 Total Bins</div>
                                <div class="text-2xl font-bold">${this.warehouseStats.totalBins || 500}</div>
                            </div>
                            <div class="bg-white p-3 rounded-lg shadow">
                                <div class="font-semibold text-green-600">🏗️ Total Racks</div>
                                <div class="text-2xl font-bold">${this.warehouseStats.totalRacks || 10}</div>
                            </div>
                        </div>
                        <div class="mt-4">
                            <a href="inventory-visual.php" class="bg-blue-600 text-white px-4 py-2 rounded-md hover:bg-blue-700">
                                <i class="fas fa-th mr-2"></i>Switch to 2D View
                            </a>
                        </div>
                    </div>
                </div>
            `;
        },

        onWindowResize() {
            const container = document.getElementById('three-container');
            
            this.camera.aspect = container.clientWidth / container.clientHeight;
            this.camera.updateProjectionMatrix();
            
            this.renderer.setSize(container.clientWidth, container.clientHeight);
        }
    };
}

// Store the actual implementation for fallback loading
window.threejsWarehouseActual = threejsWarehouse;

// Debug log
console.log('warehouse-threejs.js loaded, threejsWarehouse function available:', typeof threejsWarehouse === 'function');