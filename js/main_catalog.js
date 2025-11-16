// ==========================================================
// LÓGICA CENTRAL PARA CARGAR EL CATÁLOGO DINÁMICAMENTE
// ==========================================================

// Asegúrate de que este ID coincida con el que pusiste en car.html
const catalogContainer = document.getElementById('vehicle-catalog-container');
const loadingMessage = document.getElementById('loading-message');

/**
 * Función para llamar al Back-End y renderizar el catálogo.
 */
async function loadVehicles() {
    
    // RUTA CRÍTICA: Desde car.html a backend-scripts/get_vehicles.php
    const apiUrl = 'backend-scripts/get_vehicles.php'; 
    
    if (!catalogContainer) {
        // Si no estamos en car.html, salimos para evitar errores.
        return; 
    }

    try {
        const response = await fetch(apiUrl);
        
        // Si la respuesta no es 200 OK, lanzamos un error que el catch atrapará
        if (!response.ok) {
            throw new Error(`HTTP error! status: ${response.status}`);
        }

        const data = await response.json();

        // 1. Limpiar el mensaje de carga
        if (loadingMessage) {
            loadingMessage.remove();
        }

        if (data.success && data.data.length > 0) {
            
            let htmlContent = '';

            // 2. Iterar sobre los vehículos y construir el HTML
            data.data.forEach(vehicle => {
                
                // Mapear disponibilidad para dar la clase correcta (success, danger, etc.)
                const availabilityClass = vehicle.availability_status === 'available' ? 'text-success' : 'text-danger';
                
                // Formatear el precio a USD
                const formattedPrice = new Intl.NumberFormat('en-US', {
                    style: 'currency',
                    currency: 'USD',
                    minimumFractionDigits: 0,
                    maximumFractionDigits: 0
                }).format(vehicle.price);

                // Construir el HTML para un solo vehículo
                htmlContent += `
                    <div class="col-lg-4 col-md-6 mb-2">
                        <div class="rent-item mb-4">
                            <div class="position-relative">
                                <img class="img-fluid mb-4" src="${vehicle.primary_image}" alt="${vehicle.brand} ${vehicle.model}">  
                            </div>
                            <h4 class="text-uppercase mb-4">${vehicle.brand} ${vehicle.model}</h4>
                            <h3 class="text-primary mb-3">${formattedPrice}</h3>
                            <div class="d-flex justify-content-center mb-4">
                                
                                <div class="px-2">
                                    <i class="fa fa-car text-primary mr-1"></i>
                                    <span>${vehicle.year}</span>
                                </div>
                                
                                <div class="px-2 border-left border-right d-flex align-items-center">
                                    <i class="fa fa-paint-brush text-primary mr-2"></i>
                                    <span>${vehicle.color}</span>
                                </div>
                                
                                <div class="px-2">
                                    <i class="fa fa-check-circle text-primary mr-1"></i>
                                    <span class="font-weight-bold ${availabilityClass}">${vehicle.availability}</span>
                                </div>
                                
                            </div>
                            <a class="btn btn-primary px-3" href="detail.html?vehicle_id=${vehicle.vehicle_id}">See details</a>
                        </div>
                    </div>
                `;
            });
            
            // 3. Insertar el HTML en el contenedor
            catalogContainer.innerHTML = htmlContent;

        } else {
            catalogContainer.innerHTML = '<p class="col-12 text-center text-danger">No vehicles found at this time.</p>';
        }

    } catch (error) {
        console.error('Error fetching data:', error);
        // Si el PHP falló o la ruta fue incorrecta, mostramos este error
        if (catalogContainer) {
            catalogContainer.innerHTML = '<p class="col-12 text-center text-danger">Error retrieving data from server. Check the PHP script and the browser\'s Network tab.</p>';
        }
    }
}

// Llamar a la función cuando la página esté lista
// Solo llamamos a loadVehicles si el contenedor del catálogo existe
if (catalogContainer) {
    document.addEventListener('DOMContentLoaded', loadVehicles);
}