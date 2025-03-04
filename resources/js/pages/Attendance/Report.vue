<template>
  <Head title="Registros de Asistencias" />

  <!-- Navbar (encabezado tipo navbar) -->
  <nav class="w-full bg-white dark:bg-gray-800 px-5 py-1 shadow mb-6">
    <div class="max-w-7xl mx-auto flex items-center justify-between">
      <h1 class="text-2xl font-semibold text-black dark:text-white">Registros de Asistencias</h1>
      <div class="flex-shrink-0">
        <img
          src="https://upload.wikimedia.org/wikipedia/commons/4/4e/Logo_Instituto_Nacional_Electoral.svg"
          alt="Logo INE"
          class="h-16 w-auto block dark:hidden object-contain"
        />
        <img
          src="https://facture.com.mx/wp-content/uploads/2022/03/logoINE-blanco.png"
          alt="Logo INE"
          class="h-16 w-auto hidden dark:block object-contain"
        />
      </div>
    </div>
  </nav>

  <main class="max-w-7xl mx-auto">
    <!-- Botón de sincronizar datos -->
    <div class="flex justify-end mb-4">
      <button
        @click="syncData"
        class="rounded-sm border bg-[#d5007f] text-white px-4 py-1.5 text-sm hover:bg-[#950054] transition-colors flex items-center"
      >
        <span v-if="syncLoading" class="animate-spin mr-2">
          <svg class="h-4 w-4" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4" />
            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z" />
          </svg>
        </span>
        <span v-else>Sincronizar datos</span>
      </button>
    </div>

    <!-- Sección de filtros superiores: Dispositivo y Fechas -->
    <div class="bg-white dark:bg-gray-800 rounded-lg shadow p-4 mb-6">
      <h2 class="text-lg font-medium mb-4 dark:text-white">Seleccionar datos del reporte</h2>
      <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mb-4">
        <div>
          <label for="dispositivo" class="block text-sm font-medium text-gray-700 dark:text-gray-300">Dirección:</label>
          <select
            id="dispositivo"
            v-model="selectedDevice"
            class="mt-1 block w-full pl-3 pr-10 py-2 text-base border dark:border-gray-600 rounded-sm focus:outline-none focus:ring-[#d5007f] focus:border-[#d5007f] dark:bg-gray-700 dark:text-white"
          >
            <option value="">Seleccione la Dirección</option>
            <option v-for="device in devices" :key="device.id" :value="device.id">
              {{ device.description }}
            </option>
          </select>
        </div>
        <div>
          <label for="fecha-inicio" class="block text-sm font-medium text-gray-700 dark:text-gray-300">Fecha inicio:</label>
          <input
            type="date"
            id="fecha-inicio"
            v-model="startDate"
            :min="'2025-01-01'"
            :max="localEndDate"
            class="mt-1 block w-full pl-3 pr-10 py-2 text-base border dark:border-gray-600 rounded-sm focus:outline-none focus:ring-[#d5007f] focus:border-[#d5007f] dark:bg-gray-700 dark:text-white"
          />
        </div>
        <div>
          <label for="fecha-fin" class="block text-sm font-medium text-gray-700 dark:text-gray-300">Fecha fin:</label>
          <input
            type="date"
            id="fecha-fin"
            v-model="endDate"
            :min="startDate"
            :max="maxReportDate"
            class="mt-1 block w-full pl-3 pr-10 py-2 text-base border dark:border-gray-600 rounded-sm focus:outline-none focus:ring-[#d5007f] focus:border-[#d5007f] dark:bg-gray-700 dark:text-white"
          />
        </div>
      </div>
      <div class="flex justify-end mb-4">
        <button
          type="button"
          @click="handleSearch"
          :disabled="loadingDetailedReport"
          class="rounded-sm border bg-[#d5007f] px-5 py-1.5 text-sm text-white hover:bg-[#950054] transition-colors mr-2"
        >
          <span v-if="loadingDetailedReport">
            <svg class="animate-spin -ml-1 mr-2 h-4 w-4 text-white inline-block" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
              <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
              <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
            </svg>
            Cargando...
          </span>
          <span v-else>Buscar</span>
        </button>
      </div>
    </div>

    <!-- Panel de filtros adicionales: Adscripción, filtro de nombre con autocompletar y botón para resetear filtros -->
    <div v-if="selectedDevice && adscriptionOptions.length && showFilterPanel" class="bg-gray-50 dark:bg-gray-800 p-4 rounded-lg mb-6">
      <div class="mb-2 flex justify-between items-center">
        <h3 class="text-sm font-medium text-gray-700 dark:text-gray-300">Filtrar por Área de Adscripción:</h3>
        <button @click="toggleAllAdscriptions" class="text-xs text-[#d5007f] hover:text-[#950054]">
          {{ allAdscriptionsSelected ? 'Deseleccionar todas' : 'Seleccionar todas' }}
        </button>
      </div>
      <div class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 lg:grid-cols-4 gap-2 mb-4">
        <div v-for="adscription in adscriptionOptions" :key="adscription" class="flex items-center">
          <input
            type="checkbox"
            :id="'adscription-' + adscription"
            v-model="selectedAdscriptions"
            :value="adscription"
            class="h-4 w-4 text-[#d5007f] focus:ring-[#d5007f] border dark:border-gray-600 rounded-full"
          />
          <label :for="'adscription-' + adscription" class="ml-2 text-sm text-gray-700 dark:text-gray-300 truncate">
            {{ adscription }}
          </label>
        </div>
      </div>
      <div class="flex items-center space-x-2">
        <div class="relative flex-1">
          <label for="nombre-panel" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
            Buscar por Nombre:
          </label>
          <input
            type="text"
            id="nombre-panel"
            v-model="nameFilter"
            placeholder="Escribe para buscar..."
            @focus="showNameSuggestions = true"
            @blur="hideNameSuggestions"
            class="mt-1 block w-full pl-3 pr-10 py-2 text-base border dark:border-gray-600 rounded-sm focus:outline-none focus:ring-[#d5007f] focus:border-[#d5007f] dark:bg-gray-700 dark:text-white"
          />
          <ul
            v-if="showNameSuggestions && nameSuggestions.length"
            class="absolute z-10 bg-white dark:bg-gray-700 border dark:border-gray-600 w-full mt-1 max-h-48 overflow-auto"
          >
            <li
              v-for="(suggestion, index) in nameSuggestions"
              :key="index"
              @mousedown="selectNameSuggestion(suggestion)"
              class="px-3 py-1 hover:bg-gray-100 dark:hover:bg-gray-600 cursor-pointer"
            >
              {{ suggestion }}
            </li>
          </ul>
        </div>
        <!-- Botón de resetear filtros en el panel adicional -->
        <button
          @click="clearFilters"
          class="rounded-sm border bg-[#d5007f] px-3 py-1.5 text-sm text-white hover:bg-[#950054] transition-colors"
        >
          Resetear filtros
        </button>
      </div>
    </div>

    <!-- Botón para mostrar/ocultar filtros adicionales -->
    <div v-if="selectedDevice && adscriptionOptions.length" class="flex justify-end mb-4">
      <button
        @click="showFilterPanel = !showFilterPanel"
        class="text-sm text-[#d5007f] hover:text-[#950054] flex items-center"
      >
        {{ showFilterPanel ? 'Ocultar filtros' : 'Mostrar filtros' }}
        <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 ml-1" fill="none" viewBox="0 0 24 24" stroke="currentColor">
          <path v-if="showFilterPanel" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 15l7-7 7 7" />
          <path v-else stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
        </svg>
      </button>
    </div>

    <!-- Lista de empleados -->
    <div v-if="selectedDevice" class="bg-white dark:bg-gray-800 rounded-lg shadow overflow-hidden mb-6">
      <div class="p-4 sm:p-6">
        <div class="flex justify-between items-center mb-4">
          <h2 class="text-lg font-medium dark:text-white">Empleados de la Dirección</h2>
        </div>
        <div v-if="loadingEmployees" class="text-center py-4 dark:text-gray-300">
          <p>Cargando empleados...</p>
        </div>
        <div v-else-if="employeeError" class="bg-red-100 dark:bg-red-800 border border-red-400 dark:border-red-600 text-red-700 dark:text-red-200 px-4 py-3 rounded mb-4">
          {{ employeeError }}
        </div>
        <div v-else-if="employees.length === 0" class="text-center py-4 dark:text-gray-300">
          <p>No se encontraron empleados asociados a esta dirección.</p>
        </div>
        <div v-else-if="filteredEmployees.length === 0" class="text-center py-4 dark:text-gray-300">
          <p>No hay empleados que coincidan con los filtros seleccionados.</p>
          <button @click="clearFilters" class="mt-2 text-sm text-[#d5007f] hover:text-[#950054]">
            Restablecer filtros
          </button>
        </div>
        <div v-else class="overflow-x-auto">
          <table class="min-w-full divide-y divide-gray-200">
            <thead class="bg-gray-50 dark:bg-gray-700 sticky top-0">
              <tr>
                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-200 uppercase tracking-wider">Nombre</th>
                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-200 uppercase tracking-wider">Área de Adscripción</th>
                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-200 uppercase tracking-wider">Entrada</th>
                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-200 uppercase tracking-wider">Salida</th>
                <th v-if="detailedReport" class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-200 uppercase tracking-wider">% Asistencia</th>
                <th v-if="detailedReport" class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-200 uppercase tracking-wider">Días Completos</th>
                <th v-if="detailedReport" class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-200 uppercase tracking-wider">Días Laborados</th>
              </tr>
            </thead>
            <tbody class="bg-white dark:bg-gray-800 divide-y divide-gray-200">
              <tr
                v-for="(employee, index) in filteredEmployees"
                :key="employee.id"
                @click="showEmployeeDetails(employee)"
                class="cursor-pointer hover:bg-gray-50 dark:hover:bg-gray-700"
              >
                <td class="px-6 py-4">{{ employee.fullname }}</td>
                <td class="px-6 py-4">{{ employee.adscription }}</td>
                <td class="px-6 py-4 whitespace-nowrap">{{ formatTimeDisplay(employee.entry_time) }}</td>
                <td class="px-6 py-4 whitespace-nowrap">{{ formatTimeDisplay(employee.exit_time) }}</td>
                <td v-if="detailedReport" class="px-6 py-4 whitespace-nowrap">
                  <span
                    :class="{
                      'px-2 py-1 text-xs rounded': true,
                      'bg-green-100 text-green-800 dark:bg-green-700 dark:text-green-300': employee.stats && employee.stats.attendance_rate >= 90,
                      'bg-yellow-100 text-yellow-800 dark:bg-yellow-700 dark:text-yellow-300': employee.stats && employee.stats.attendance_rate >= 70 && employee.stats.attendance_rate < 90,
                      'bg-red-100 text-red-800 dark:bg-red-700 dark:text-red-300': employee.stats && employee.stats.attendance_rate < 70,
                      'bg-gray-100 text-gray-800 dark:bg-gray-600 dark:text-gray-300': !employee.stats
                    }"
                  >
                    {{ getAttendancePercentage(employee) }}
                  </span>
                </td>
                <td v-if="detailedReport" class="px-6 py-4 whitespace-nowrap">
                  {{ employee.stats ? employee.stats.complete_days : '-' }}
                </td>
                <td v-if="detailedReport" class="px-6 py-4 whitespace-nowrap">
                  {{ employee.stats ? employee.stats.working_days : '-' }}
                </td>
              </tr>
            </tbody>
          </table>
          <p class="text-sm mt-2 text-gray-500 dark:text-gray-300">
            Mostrando {{ filteredEmployees.length }} de {{ employees.length }} empleados
          </p>
        </div>
      </div>
    </div>

    <!-- Modal de Detalle del Empleado -->
    <div v-if="showModal" class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50">
      <div class="bg-white dark:bg-gray-800 rounded-lg shadow-xl w-full md:w-[90%] max-w-4xl max-h-[90vh] flex flex-col">
        <!-- Cabecera fija del modal -->
        <div class="p-4 border-b border-gray-200 dark:border-gray-700 sticky top-0 bg-white dark:bg-gray-800 z-10">
          <div class="flex justify-between items-center">
            <h3 class="text-xl font-semibold text-gray-900 dark:text-white">Detalle del Empleado</h3>
            <button @click="closeModal" class="text-gray-400 hover:text-gray-600">
              <svg class="h-6 w-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
              </svg>
            </button>
          </div>
        </div>
        <!-- Cuerpo del modal -->
        <div class="p-4 overflow-y-auto space-y-4">
          <!-- Información General -->
          <div class="bg-gray-50 dark:bg-gray-700 p-4 rounded">
            <h4 class="text-lg font-medium dark:text-white">Información General</h4>
            <p class="text-sm text-gray-500 dark:text-gray-300">
              Nombre: <span class="font-medium dark:text-white">{{ selectedEmployee.fullname }}</span>
            </p>
            <p class="text-sm text-gray-500 dark:text-gray-300">
              Área de Adscripción: <span class="font-medium dark:text-white">{{ selectedEmployee.adscription }}</span>
            </p>
            <p class="text-sm text-gray-500 dark:text-gray-300">
              Entrada Habitual: <span class="font-medium dark:text-white">{{ formatTimeDisplay(selectedEmployee.entry_time) }}</span>
            </p>
            <p class="text-sm text-gray-500 dark:text-gray-300">
              Salida Habitual: <span class="font-medium dark:text-white">{{ formatTimeDisplay(selectedEmployee.exit_time) }}</span>
            </p>
          </div>
          <!-- Estadísticas del Periodo -->
          <div v-if="selectedEmployee.stats" class="bg-gray-50 dark:bg-gray-700 p-4 rounded">
            <h4 class="text-lg font-medium dark:text-white">Estadísticas del Periodo</h4>
            <p class="text-sm text-gray-500 dark:text-gray-300">
              Porcentaje de Asistencia:
              <span
                :class="{
                  'px-2 py-1 text-xs rounded inline-block mt-1': true,
                  'bg-green-100 text-green-800 dark:bg-green-700 dark:text-green-300': selectedEmployee.stats.attendance_rate >= 90,
                  'bg-yellow-100 text-yellow-800 dark:bg-yellow-700 dark:text-yellow-300': selectedEmployee.stats.attendance_rate >= 70 && selectedEmployee.stats.attendance_rate < 90,
                  'bg-red-100 text-red-800 dark:bg-red-700 dark:text-red-300': selectedEmployee.stats.attendance_rate < 70
                }"
              >
                {{ selectedEmployee.stats.attendance_rate }}%
              </span>
            </p>
            <p class="text-sm text-gray-500 dark:text-gray-300">
              Días Completos: <span class="font-medium dark:text-white">{{ selectedEmployee.stats.complete_days }} días</span>
            </p>
            <p class="text-sm text-gray-500 dark:text-gray-300">
              Días Laborables: <span class="font-medium dark:text-white">{{ selectedEmployee.stats.working_days }} días</span>
            </p>
            <p class="text-sm text-gray-500 dark:text-gray-300">
              Total Días: <span class="font-medium dark:text-white">{{ selectedEmployee.stats.total_days }} días</span>
            </p>
          </div>
          <!-- Filtro por Estado -->
          <div>
            <label for="modal-state-filter" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
              Filtrar detalle por Estado:
            </label>
            <select
              id="modal-state-filter"
              v-model="modalStateFilter"
              class="block w-full pl-3 pr-10 py-2 text-base border dark:border-gray-600 rounded focus:outline-none focus:ring-[#d5007f] focus:border-[#d5007f] dark:bg-gray-700 dark:text-white"
            >
              <option v-for="option in attendanceStatusOptions" :key="option" :value="option">
                {{ option === 'all' ? 'Todos' : option }}
              </option>
            </select>
          </div>
          <!-- Detalle Diario -->
          <div class="max-h-64 overflow-y-auto">
            <h4 class="text-lg font-medium dark:text-white mb-2">Detalle Diario</h4>
            <table class="min-w-full divide-y divide-gray-200">
              <thead class="bg-gray-50 dark:bg-gray-700 sticky top-0">
                <tr>
                  <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 dark:text-gray-200 uppercase">Fecha</th>
                  <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 dark:text-gray-200 uppercase">Primer Registro</th>
                  <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 dark:text-gray-200 uppercase">Último Registro</th>
                  <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 dark:text-gray-200 uppercase">Horas Trabajadas</th>
                  <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 dark:text-gray-200 uppercase">Estado</th>
                  <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 dark:text-gray-200 uppercase">Razón</th>
                </tr>
              </thead>
              <tbody class="bg-white dark:bg-gray-800 divide-y divide-gray-200">
                <tr v-for="(day, index) in filteredAttendanceDays" :key="index">
                  <td class="px-4 py-2 whitespace-nowrap">{{ formatDate(day.date) }}</td>
                  <td class="px-4 py-2 whitespace-nowrap">{{ day.first_check || '-' }}</td>
                  <td class="px-4 py-2 whitespace-nowrap">{{ day.last_check || '-' }}</td>
                  <td class="px-4 py-2 whitespace-nowrap">{{ formatWorkingHours(day.working_hours) }}</td>
                  <td class="px-4 py-2 whitespace-nowrap">
                    <span :class="['px-2 py-1 text-xs rounded', getStatusClass(day.status)]">
                      {{ day.status }}
                    </span>
                  </td>
                  <td class="px-4 py-2 whitespace-nowrap">{{ day.status_reason || '-' }}</td>
                </tr>
              </tbody>
            </table>
          </div>
        </div>
        <!-- Botón de cierre -->
        <div class="p-4 border-t border-gray-200 dark:border-gray-700 flex justify-end">
          <button
            @click="closeModal"
            class="px-4 py-2 border dark:border-gray-600 rounded text-sm text-gray-700 dark:text-gray-300 hover:bg-gray-50 dark:hover:bg-gray-700"
          >
            Cerrar
          </button>
        </div>
      </div>
    </div>

    <!-- Spinner de carga -->
    <div v-if="loadingDetailedReport" class="bg-white dark:bg-gray-800 rounded-lg shadow p-6">
      <div class="text-center py-8">
        <svg
          class="animate-spin h-10 w-10 text-[#d5007f] mx-auto"
          xmlns="http://www.w3.org/2000/svg"
          fill="none"
          viewBox="0 0 24 24"
        >
          <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
          <path
            class="opacity-75"
            fill="currentColor"
            d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"
          ></path>
        </svg>
        <p class="mt-4 text-gray-600 dark:text-gray-300">Cargando datos de asistencia...</p>
      </div>
    </div>
  </main>
</template>

<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import { ref, onMounted, computed, watch } from 'vue';
import axios from 'axios';

// Función para obtener la fecha local en formato YYYY-MM-DD
const getLocalDateString = () => {
  const today = new Date();
  const yyyy = today.getFullYear();
  const mm = String(today.getMonth() + 1).padStart(2, '0');
  const dd = String(today.getDate()).padStart(2, '0');
  return `${yyyy}-${mm}-${dd}`;
};

// Estados generales
const devices = ref([]);
const selectedDevice = ref('');
const loading = ref(true);
const loadingEmployees = ref(false);
const loadingDetailedReport = ref(false);
const error = ref(null);
const employeeError = ref(null);
const employees = ref([]);
const detailedReport = ref(null);

// Estados de filtros
const adscriptionOptions = ref([]);
const selectedAdscriptions = ref([]);
const nameFilter = ref('');
const showNameSuggestions = ref(false);
const showFilterPanel = ref(false);

// Para establecer el máximo de fecha permitido en el reporte (se actualizará al sincronizar)
const maxReportDate = ref(getLocalDateString());

// Usamos la función para obtener la fecha local como valor por defecto de endDate
const startDate = ref('2025-01-01');
const endDate = ref(getLocalDateString());

// Estado para el modal
const showModal = ref(false);
const selectedEmployee = ref(null);
const modalStateFilter = ref("all");
const attendanceStatusOptions = ["all", "Asistencia", "Descanso", "Asueto", "Permiso", "Falta"];
const filteredAttendanceDays = computed(() => {
  if (!selectedEmployee.value || !selectedEmployee.value.attendanceDays) return [];
  if (modalStateFilter.value === "all") return selectedEmployee.value.attendanceDays;
  return selectedEmployee.value.attendanceDays.filter(day => day.status === modalStateFilter.value);
});

// Computada para sugerencias de autocompletar
const nameSuggestions = computed(() => {
  const filter = nameFilter.value.trim().toLowerCase();
  if (!filter) return [];
  const suggestions = employees.value.map(emp => emp.fullname).filter(name => name.toLowerCase().includes(filter));
  return Array.from(new Set(suggestions));
});

// Función para seleccionar sugerencia (usando mousedown)
const selectNameSuggestion = (suggestion: string) => {
  nameFilter.value = suggestion;
  showNameSuggestions.value = false;
};

// Cargar dispositivos
onMounted(async () => {
  try {
    const response = await axios.get('/api/zteko');
    devices.value = response.data;
    loading.value = false;
  } catch (err) {
    error.value = 'Error al cargar los dispositivos: ' + (err.response?.data?.message || err.message);
    loading.value = false;
  }
});

// Vigilar cambios en el dispositivo
watch(selectedDevice, async (newValue, oldValue) => {
  if (newValue && newValue !== oldValue) {
    selectedAdscriptions.value = [];
    adscriptionOptions.value = [];
    nameFilter.value = '';
    await loadEmployeesByDevice(newValue);
  } else if (!newValue) {
    employees.value = [];
    adscriptionOptions.value = [];
    selectedAdscriptions.value = [];
    nameFilter.value = '';
  }
});

// Cargar empleados asociados al dispositivo
const loadEmployeesByDevice = async (deviceId: string) => {
  loadingEmployees.value = true;
  employees.value = [];
  employeeError.value = null;
  try {
    const response = await axios.get('/api/employees/by-device', { params: { device_id: deviceId } });
    employees.value = response.data.employees || [];
    const adscriptions = new Set();
    employees.value.forEach(employee => {
      if (employee.adscription) adscriptions.add(employee.adscription);
    });
    adscriptionOptions.value = Array.from(adscriptions).sort();
    selectedAdscriptions.value = [...adscriptionOptions.value];
    loadingEmployees.value = false;
  } catch (err) {
    employeeError.value = 'Error al cargar los empleados: ' + (err.response?.data?.message || err.message);
    loadingEmployees.value = false;
  }
};

// Filtrar empleados según área y nombre
const filteredEmployees = computed(() => {
  if (!employees.value.length) return [];
  let filtered = employees.value;
  if (selectedAdscriptions.value.length > 0) {
    filtered = filtered.filter(employee => selectedAdscriptions.value.includes(employee.adscription));
  }
  if (nameFilter.value.trim() !== '') {
    const lowerNameFilter = nameFilter.value.toLowerCase();
    filtered = filtered.filter(employee => employee.fullname.toLowerCase().includes(lowerNameFilter));
  }
  return filtered;
});

const toggleAllAdscriptions = () => {
  if (selectedAdscriptions.value.length === adscriptionOptions.value.length) {
    selectedAdscriptions.value = [];
  } else {
    selectedAdscriptions.value = [...adscriptionOptions.value];
  }
};

const allAdscriptionsSelected = computed(() => {
  return selectedAdscriptions.value.length === adscriptionOptions.value.length && adscriptionOptions.value.length > 0;
});

// Formatear hora
const formatTimeDisplay = (timeString: string) => {
  if (!timeString) return '-';
  if (timeString.match(/^\d{1,2}:\d{2}(:\d{2})?$/)) return timeString;
  let [time, period] = timeString.split(/\s+/);
  let [hours, minutes, seconds] = time.split(':').map(Number);
  if (period && period.toLowerCase().includes('p') && hours < 12) hours += 12;
  else if (period && period.toLowerCase().includes('a') && hours === 12) hours = 0;
  return `${String(hours).padStart(2, '0')}:${String(minutes).padStart(2, '0')}${seconds ? `:${String(seconds).padStart(2, '0')}` : ''}`;
};

// Cargar reporte detallado usando endDate (valor local) directamente
const loadDetailedReport = async () => {
  if (!selectedDevice.value) {
    error.value = 'Por favor, seleccione una Dirección';
    return;
  }
  loadingDetailedReport.value = true;
  error.value = null;
  try {
    const response = await axios.get('/api/reports/attendance-detailed-report', {
      params: {
        device_id: selectedDevice.value,
        start_date: startDate.value,
        end_date: endDate.value
      }
    });
    detailedReport.value = response.data;
    processEmployeesWithDetailedReport();
    loadingDetailedReport.value = false;
  } catch (err) {
    error.value = 'Error al cargar el reporte detallado: ' + (err.response?.data?.message || err.message);
    loadingDetailedReport.value = false;
  }
};

const processEmployeesWithDetailedReport = () => {
  if (!detailedReport.value || !employees.value.length) return;
  const attendanceMap = new Map();
  detailedReport.value.attendance.forEach(item => attendanceMap.set(item.uid, item));
  employees.value = employees.value.map(employee => {
    const attendanceData = attendanceMap.get(employee.checker_uid);
    return attendanceData
      ? { ...employee, stats: attendanceData.statistics, attendanceDays: attendanceData.days }
      : employee;
  });
};

const handleSearch = async () => {
  if (!selectedDevice.value) {
    error.value = 'Por favor, seleccione una Dirección';
    return;
  }
  await loadDetailedReport();
};

const formatDate = (dateString: string) => {
  if (!dateString) return '-';
  const d = new Date(dateString.replace(/-/g, '/'));
  return d.toLocaleDateString('es-ES', { day: '2-digit', month: '2-digit', year: 'numeric' });
};

const formatTime = (dateTimeString: string) => {
  if (!dateTimeString) return '-';
  const d = new Date(dateTimeString);
  return d.toLocaleTimeString('es-ES', { hour: '2-digit', minute: '2-digit', second: '2-digit', hour12: false });
};

const getAttendancePercentage = (employee: any) => {
  if (!employee.stats) return '-';
  return `${employee.stats.attendance_rate}%`;
};

const showEmployeeDetails = (employee: any) => {
  selectedEmployee.value = employee;
  modalStateFilter.value = "all";
  showModal.value = true;
};

const closeModal = () => {
  showModal.value = false;
  selectedEmployee.value = null;
};

const getStatusClass = (status: string) => {
  switch (status) {
    case 'Asistencia': return 'bg-green-100 text-green-800 dark:bg-green-700 dark:text-green-300';
    case 'Descanso': return 'bg-blue-100 text-blue-800 dark:bg-blue-700 dark:text-blue-300';
    case 'Asueto': return 'bg-purple-100 text-purple-800 dark:bg-purple-700 dark:text-purple-300';
    case 'Permiso': return 'bg-yellow-100 text-yellow-800 dark:bg-yellow-700 dark:text-yellow-300';
    default: return 'bg-red-100 text-red-800 dark:bg-red-700 dark:text-red-300';
  }
};

const formatWorkingHours = (hours: number) => {
  if (hours === undefined || hours === null) return '-';
  return `${hours.toFixed(2)} hrs`;
};

const clearFilters = () => {
  selectedAdscriptions.value = [...adscriptionOptions.value];
  nameFilter.value = '';
};

const syncLoading = ref(false);
const syncData = async () => {
  if (!selectedDevice.value) {
    alert("Por favor, seleccione una Dirección");
    return;
  }
  syncLoading.value = true;
  try {
    const response = await axios.get(`/api/attendances/sync/${selectedDevice.value}`);
    let lastDate;
    if (response.data.last_added_record && response.data.last_added_record.date) {
      lastDate = response.data.last_added_record.date;
    } else if (response.data.last_record_before_sync && response.data.last_record_before_sync.date) {
      lastDate = response.data.last_record_before_sync.date;
    }
    if (lastDate) {
      const d = new Date(lastDate.replace(" ", "T"));
      const yyyy = d.getFullYear();
      const mm = String(d.getMonth() + 1).padStart(2, '0');
      const dd = String(d.getDate()).padStart(2, '0');
      const formattedLastDate = `${yyyy}-${mm}-${dd}`;
      maxReportDate.value = formattedLastDate;
      alert(`Sincronización completada: ${response.data.message}. Último registro: ${formattedLastDate}`);
    } else {
      alert(`Sincronización completada: ${response.data.message}`);
    }
  } catch (err) {
    alert("Error en la sincronización: " + (err.response?.data?.message || err.message));
  }
  syncLoading.value = false;
};

const hideNameSuggestions = () => {
  setTimeout(() => { showNameSuggestions.value = false; }, 100);
};
</script>
