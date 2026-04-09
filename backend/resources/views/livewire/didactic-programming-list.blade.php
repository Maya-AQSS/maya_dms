<div x-data="{
    hierarchy: {{ $hierarchyJson }},
    allDocuments: {{ $documentsJson }},
    
    // Filtros activos
    typeId: '',
    studyId: '',
    moduleId: '',
    
    // Estado del acordeón móvil
    isMobileOpen: false,

    // Getters reactivos
    get filteredStudies() {
        if (!this.typeId) return [];
        const type = this.hierarchy.find(t => t.id === this.typeId);
        return type ? type.studies : [];
    },

    get filteredModules() {
        if (!this.studyId) return [];
        const study = this.filteredStudies.find(s => s.id === this.studyId);
        return study ? study.course_modules : [];
    },

    get filteredDocuments() {
        return this.allDocuments.filter(doc => {
            const matchesType = !this.typeId || this.hierarchy.find(t => t.id === this.typeId)?.studies.some(s => s.id === doc.study_id);
            const matchesStudy = !this.studyId || doc.study_id === this.studyId;
            const matchesModule = !this.moduleId || doc.course_module_id === this.moduleId;
            return matchesType && matchesStudy && matchesModule;
        });
    },

    get activeFiltersCount() {
        let count = 0;
        if (this.typeId) count++;
        if (this.studyId) count++;
        if (this.moduleId) count++;
        return count;
    },

    clearFilters() {
        this.typeId = '';
        this.studyId = '';
        this.moduleId = '';
    },

    // Manejo de cambios en cascada
    onTypeChange() {
        this.studyId = '';
        this.moduleId = '';
    },

    onStudyChange() {
        this.moduleId = '';
    },

    // Benchmark de rendimiento
    async runPerformanceBenchmark() {
        console.log('--- Iniciando Benchmark de Rendimiento ---');
        const originalDocs = [...this.allDocuments];
        
        // 1. Generación de 5.000 items
        const mockDocs = [];
        const types = this.hierarchy;
        if (types.length === 0) {
            console.error('No hay datos de jerarquía para el benchmark');
            return;
        }

        for (let i = 0; i < 5000; i++) {
            const type = types[Math.floor(Math.random() * types.length)];
            const study = type.studies[Math.floor(Math.random() * type.studies.length)] || { id: null };
            const module = study.course_modules ? study.course_modules[Math.floor(Math.random() * study.course_modules.length)] : { id: null };
            
            mockDocs.push({
                id: `mock-${i}`,
                title: `Doc Ficticio ${i}`,
                status: ['published', 'draft', 'in_review'][Math.floor(Math.random() * 3)],
                study_id: study.id,
                course_module_id: module ? module.id : null,
                study_name: study.name || 'N/A',
                module_name: module ? module.name : 'N/A',
                updated_at: 'Recién'
            });
        }

        this.allDocuments = mockDocs;
        console.log(`Dataset de ${this.allDocuments.length} documentos cargado.`);

        // 2. Medición de tiempo (100 iteraciones para media)
        const iterations = 100;
        const start = performance.now();
        
        for (let j = 0; j < iterations; j++) {
            // Forzamos el acceso al getter filtrado
            const test = this.filteredDocuments;
        }
        
        const end = performance.now();
        const totalDuration = end - start;
        const avgDuration = totalDuration / iterations;

        console.log(`Resultado: ${avgDuration.toFixed(4)}ms por operación (media de ${iterations} runs)`);
        
        // 3. Verificación del requisito
        const resultMessage = avgDuration < 16 
            ? `✅ PASS: ${avgDuration.toFixed(2)}ms (Menor que 16ms)` 
            : `❌ FAIL: ${avgDuration.toFixed(2)}ms (Mayor que 16ms)`;
        
        console.log(resultMessage);
        
        // Restaurar estado
        this.allDocuments = originalDocs;
        
        return {
            avgTimeMs: avgDuration,
            items: mockDocs.length,
            success: avgDuration < 16
        };
    }
}" class="w-full max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8" x-cloak>
    
    <!-- HEADER & FILTERS -->
    <div class="mb-8">
        <div class="flex flex-col md:flex-row md:items-center justify-between gap-4 mb-6">
            <h1 class="text-2xl font-bold text-gray-900 dark:text-white">Programaciones Didácticas</h1>
            
            <!-- Vista Escritorio: Filtros Horizontales -->
            <div class="hidden md:flex items-end gap-3">
                <div class="flex flex-col gap-1">
                    <label for="type-desktop" class="text-xs font-semibold text-gray-500 uppercase tracking-wider">Tipo de Estudio</label>
                    <select id="type-desktop" x-model="typeId" @change="onTypeChange()" 
                        class="block w-48 pl-3 pr-10 py-2 text-sm border-gray-300 dark:border-gray-700 focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 rounded-md dark:bg-[#161615]">
                        <option value="">Todos los tipos</option>
                        <template x-for="type in hierarchy" :key="type.id">
                            <option :value="type.id" x-text="type.name"></option>
                        </template>
                    </select>
                </div>

                <div class="flex flex-col gap-1">
                    <label for="study-desktop" class="text-xs font-semibold text-gray-500 uppercase tracking-wider">Estudio</label>
                    <select id="study-desktop" x-model="studyId" @change="onStudyChange()"
                        :disabled="!typeId" :aria-disabled="!typeId"
                        class="block w-48 pl-3 pr-10 py-2 text-sm border-gray-300 dark:border-gray-700 focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 rounded-md dark:bg-[#161615] disabled:bg-gray-100 disabled:cursor-not-allowed dark:disabled:bg-gray-800">
                        <option value="">Todos los estudios</option>
                        <template x-for="study in filteredStudies" :key="study.id">
                            <option :value="study.id" x-text="study.name"></option>
                        </template>
                    </select>
                </div>

                <div class="flex flex-col gap-1">
                    <label for="module-desktop" class="text-xs font-semibold text-gray-500 uppercase tracking-wider">Módulo</label>
                    <select id="module-desktop" x-model="moduleId"
                        :disabled="!studyId" :aria-disabled="!studyId"
                        class="block w-48 pl-3 pr-10 py-2 text-sm border-gray-300 dark:border-gray-700 focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 rounded-md dark:bg-[#161615] disabled:bg-gray-100 disabled:cursor-not-allowed dark:disabled:bg-gray-800">
                        <option value="">Todos los módulos</option>
                        <template x-for="module in filteredModules" :key="module.id">
                            <option :value="module.id" x-text="module.name"></option>
                        </template>
                    </select>
                </div>

                <button @click="clearFilters()" :disabled="!typeId" 
                    class="px-4 py-2 text-sm font-medium text-gray-700 bg-white border border-gray-300 rounded-md hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500 disabled:opacity-50 disabled:cursor-not-allowed dark:bg-gray-800 dark:text-gray-200 dark:border-gray-600">
                    Limpiar filtros
                </button>
            </div>

            <!-- Vista Móvil: Botón Acordeón -->
            <div class="md:hidden">
                <button @click="isMobileOpen = !isMobileOpen" 
                    class="w-full flex items-center justify-between px-4 py-3 bg-white dark:bg-[#161615] border border-gray-200 dark:border-gray-800 rounded-lg shadow-sm"
                    :aria-expanded="isMobileOpen">
                    <span class="flex items-center gap-2 font-medium">
                        Filtros
                        <template x-if="activeFiltersCount > 0">
                            <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium bg-indigo-100 text-indigo-800" x-text="activeFiltersCount"></span>
                        </template>
                    </span>
                    <svg class="w-5 h-5 transition-transform duration-200" :class="isMobileOpen ? 'rotate-180' : ''" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"></path>
                    </svg>
                </button>
            </div>
        </div>

        <!-- Panel Acordeón Móvil -->
        <div x-show="isMobileOpen" x-transition:enter="transition ease-out duration-200" x-transition:enter-start="opacity-0 -translate-y-4" x-transition:enter-end="opacity-100 translate-y-0"
            class="md:hidden mb-6 p-4 bg-gray-50 dark:bg-[#111110] border border-gray-200 dark:border-gray-800 rounded-lg space-y-4" role="region">
            
            <div class="space-y-1">
                <label for="type-mobile" class="text-xs font-semibold text-gray-500 uppercase tracking-wider">Tipo de Estudio</label>
                <select id="type-mobile" x-model="typeId" @change="onTypeChange()" 
                    class="block w-full py-2 px-3 border border-gray-300 dark:border-gray-700 rounded-md bg-white dark:bg-[#161615]">
                    <option value="">Todos los tipos</option>
                    <template x-for="type in hierarchy" :key="type.id">
                        <option :value="type.id" x-text="type.name"></option>
                    </template>
                </select>
            </div>

            <div class="space-y-1">
                <label for="study-mobile" class="text-xs font-semibold text-gray-500 uppercase tracking-wider">Estudio</label>
                <select id="study-mobile" x-model="studyId" @change="onStudyChange()" :disabled="!typeId"
                    class="block w-full py-2 px-3 border border-gray-300 dark:border-gray-700 rounded-md bg-white dark:bg-[#161615] disabled:bg-gray-100 dark:disabled:bg-gray-800">
                    <option value="">Todos los estudios</option>
                    <template x-for="study in filteredStudies" :key="study.id">
                        <option :value="study.id" x-text="study.name"></option>
                    </template>
                </select>
            </div>

            <div class="space-y-1">
                <label for="module-mobile" class="text-xs font-semibold text-gray-500 uppercase tracking-wider">Módulo</label>
                <select id="module-mobile" x-model="moduleId" :disabled="!studyId"
                    class="block w-full py-2 px-3 border border-gray-300 dark:border-gray-700 rounded-md bg-white dark:bg-[#161615] disabled:bg-gray-100 dark:disabled:bg-gray-800">
                    <option value="">Todos los módulos</option>
                    <template x-for="module in filteredModules" :key="module.id">
                        <option :value="module.id" x-text="module.name"></option>
                    </template>
                </select>
            </div>

            <button @click="clearFilters()" :disabled="!typeId"
                class="w-full py-2 text-sm font-medium text-red-600 bg-red-50 border border-red-200 rounded-md hover:bg-red-100 disabled:opacity-50">
                Limpiar filtros
            </button>
        </div>
    </div>

    <!-- DOCUMENT LIST -->
    <div class="bg-white dark:bg-[#161615] shadow-sm rounded-xl overflow-hidden border border-gray-200 dark:border-gray-800">
        <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-800">
            <thead class="bg-gray-50 dark:bg-[#111110]">
                <tr>
                    <th class="px-6 py-3 text-left text-xs font-semibold text-gray-500 uppercase tracking-wider">Título</th>
                    <th class="px-6 py-3 text-left text-xs font-semibold text-gray-500 uppercase tracking-wider">Estudio / Módulo</th>
                    <th class="px-6 py-3 text-left text-xs font-semibold text-gray-500 uppercase tracking-wider">Estado</th>
                    <th class="px-6 py-3 text-left text-xs font-semibold text-gray-500 uppercase tracking-wider">Última mod.</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-200 dark:divide-gray-800">
                <template x-for="doc in filteredDocuments" :key="doc.id">
                    <tr class="hover:bg-gray-50 dark:hover:bg-gray-900 transition-colors">
                        <td class="px-6 py-4 whitespace-nowrap">
                            <div class="text-sm font-medium text-gray-900 dark:text-gray-100" x-text="doc.title"></div>
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap">
                            <div class="text-xs text-gray-500 dark:text-gray-400" x-text="doc.study_name"></div>
                            <div class="text-xs font-medium text-gray-400" x-text="doc.module_name"></div>
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap">
                            <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full"
                                :class="{
                                    'bg-green-100 text-green-800': doc.status === 'published',
                                    'bg-yellow-100 text-yellow-800': doc.status === 'draft',
                                    'bg-blue-100 text-blue-800': doc.status === 'in_review'
                                }"
                                x-text="doc.status">
                            </span>
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500 dark:text-gray-400" x-text="doc.updated_at"></td>
                    </tr>
                </template>
                <template x-if="filteredDocuments.length === 0">
                    <tr>
                        <td colspan="4" class="px-6 py-12 text-center text-gray-500 dark:text-gray-400">
                            No se encontraron programaciones con los filtros seleccionados.
                        </td>
                    </tr>
                </template>
            </tbody>
        </table>
    </div>
</div>
